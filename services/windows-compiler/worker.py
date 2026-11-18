"""EA Archive compiler agent. Python standard library only; Windows production host."""

import hashlib
import json
import logging
import os
from pathlib import Path
import re
import signal
import subprocess
import tempfile
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid

STOP = threading.Event()
MAX_SOURCE = 2 * 1024 * 1024
MAX_ARTIFACT = 16 * 1024 * 1024


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def locked_source(raw, login, server):
    if not re.fullmatch(r"[1-9][0-9]{0,17}", str(login)):
        raise ValueError("Invalid account login")
    if not isinstance(server, str) or not server or len(server) > 120 or any(ord(c) < 32 for c in server):
        raise ValueError("Invalid account server")
    source = raw.decode("utf-16") if raw.startswith((b"\xff\xfe", b"\xfe\xff")) else raw.decode("utf-8-sig")
    # This compatibility gate complements mandatory human review; it is not a sandbox.
    code = re.sub(r'"(?:\\.|[^"\\])*"|//[^\n]*|/\*.*?\*/', " ", source, flags=re.S)
    if re.search(r"^\s*#\s*(?!property\b)\w+", code, re.M):
        raise ValueError("Only property preprocessor directives are allowed")
    if re.search(r"\bOn[A-Z]\w*\s*\(", code):
        raise ValueError("Use only EA_OnInit and EA_OnTick entrypoints")
    if not re.search(r"\bint\s+EA_OnInit\s*\(\s*(?:void)?\s*\)", code) or not re.search(r"\bvoid\s+EA_OnTick\s*\(\s*(?:void)?\s*\)", code):
        raise ValueError("Source does not implement the entrypoint contract")
    quoted_server = json.dumps(server, ensure_ascii=False)
    condition = f"AccountInfoInteger(ACCOUNT_LOGIN)=={login} && AccountInfoString(ACCOUNT_SERVER)=={quoted_server}"
    wrapper = f"\nint OnInit(){{if(!({condition}))return INIT_FAILED;return EA_OnInit();}}\nvoid OnTick(){{if({condition})EA_OnTick();}}\n"
    return (source + wrapper).encode("utf-8-sig")


class Worker:
    def __init__(self):
        self.base = os.environ["EA_API_URL"].rstrip("/")
        parsed = urllib.parse.urlsplit(self.base)
        if parsed.scheme != "https" or not parsed.netloc or parsed.username or parsed.password or parsed.query or parsed.fragment:
            raise ValueError("EA_API_URL must be an HTTPS origin, optionally with an application base path")
        self.token = os.environ["COMPILER_API_TOKEN"]
        if len(self.token) < 32:
            raise ValueError("COMPILER_API_TOKEN must contain at least 32 characters")
        self.compiler = Path(os.environ["METAEDITOR_PATH"])
        if not self.compiler.is_file():
            raise ValueError("METAEDITOR_PATH must point to MetaEditor")
        self.work_root = Path(os.environ.get("EA_WORK_DIRECTORY", "C:/EACompiler/work"))
        self.work_root.mkdir(parents=True, exist_ok=True)
        self.http = urllib.request.build_opener(NoRedirect)

    def request(self, path, method="GET", body=None, build_token=None, content_type=None):
        headers = {"Authorization": "Bearer " + self.token, "Accept": "application/json"}
        if build_token:
            headers["X-Build-Token"] = build_token
        if content_type:
            headers["Content-Type"] = content_type
        request = urllib.request.Request(self.base + "/api/v1/compiler/" + path, data=body, headers=headers, method=method)
        with self.http.open(request, timeout=45) as response:
            result = response.read(MAX_SOURCE + 1 if path.endswith("/source") else 1024 * 1024)
        if path.endswith("/source") and len(result) > MAX_SOURCE:
            raise ValueError("Source too large")
        return result

    def complete(self, job, artifact):
        boundary = "eaarchive" + uuid.uuid4().hex
        digest = hashlib.sha256(artifact).hexdigest()
        body = (f'--{boundary}\r\nContent-Disposition: form-data; name="sha256"\r\n\r\n{digest}\r\n'
                f'--{boundary}\r\nContent-Disposition: form-data; name="artifact"; filename="expert.ex5"\r\n'
                'Content-Type: application/octet-stream\r\n\r\n').encode() + artifact + f"\r\n--{boundary}--\r\n".encode()
        for attempt in range(3):
            try:
                self.request(f"builds/{job['id']}/complete", "POST", body, job["token"], "multipart/form-data; boundary=" + boundary)
                return
            except urllib.error.HTTPError as error:
                if error.code < 500 or attempt == 2:
                    raise
            except (urllib.error.URLError, TimeoutError):
                if attempt == 2:
                    raise
            STOP.wait(2)

    def compile(self, job):
        if job.get("protocol") != 1 or not isinstance(job.get("id"), int) or not re.fullmatch(r"[a-f0-9]{64}", job.get("token", "")):
            raise ValueError("Unsupported compiler job")
        raw = self.request(f"builds/{job['id']}/source", build_token=job["token"])
        if job.get("source_sha256") and hashlib.sha256(raw).hexdigest() != job["source_sha256"]:
            raise ValueError("Source checksum mismatch")
        content = locked_source(raw, job["login"], job["server"])
        with tempfile.TemporaryDirectory(prefix="build-", dir=self.work_root) as directory:
            directory = Path(directory)
            source, log, artifact = directory / "expert.mq5", directory / "compile.log", directory / "expert.ex5"
            source.write_bytes(content)
            flags = subprocess.CREATE_NO_WINDOW | subprocess.CREATE_NEW_PROCESS_GROUP
            process = subprocess.Popen([str(self.compiler), "/compile:" + str(source), "/log:" + str(log)],
                                       cwd=directory, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, creationflags=flags)
            try:
                process.wait(timeout=120)
            except subprocess.TimeoutExpired:
                subprocess.run(["taskkill.exe", "/PID", str(process.pid), "/T", "/F"], capture_output=True, timeout=15, creationflags=subprocess.CREATE_NO_WINDOW)
                process.wait(timeout=15)
                raise
            raw_log = log.read_bytes() if log.exists() else b""
            decoded = raw_log.decode("utf-16") if raw_log.startswith((b"\xff\xfe", b"\xfe\xff")) else raw_log.decode("utf-8", errors="replace")
            if not re.search(r"\b0 errors\b", decoded, re.I) or not artifact.is_file() or not 0 < artifact.stat().st_size <= MAX_ARTIFACT:
                raise ValueError("MetaEditor did not produce a valid artifact")
            self.complete(job, artifact.read_bytes())

    def run(self):
        while not STOP.is_set():
            job = None
            try:
                job = json.loads(self.request("claim", "POST", b"{}", content_type="application/json"))["build"]
                if job:
                    self.compile(job)
                    logging.info("Build %s completed", job["id"])
                else:
                    STOP.wait(5)
            except Exception as error:
                # Never log credentials, request URLs, source code, or compiler output.
                logging.error("Compiler operation failed (%s)", type(error).__name__)
                if job and isinstance(job.get("id"), int):
                    try:
                        self.request(f"builds/{job['id']}/fail", "POST", b"{}", job["token"], "application/json")
                    except Exception:
                        logging.warning("Failure acknowledgment unavailable; lease recovery will apply")
                STOP.wait(5)


if __name__ == "__main__":
    if os.name != "nt":
        raise SystemExit("This compiler service requires Windows")
    logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
    signal.signal(signal.SIGINT, lambda *_: STOP.set())
    signal.signal(signal.SIGTERM, lambda *_: STOP.set())
    Worker().run()
