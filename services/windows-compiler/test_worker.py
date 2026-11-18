import unittest
from unittest.mock import Mock, patch
from pathlib import Path
import tempfile
from worker import Worker, locked_source


class SourceContractTest(unittest.TestCase):
    def test_wrapper_locks_init_and_tick_to_both_account_and_server(self):
        source = locked_source(b"#property strict\nint EA_OnInit(){return 0;}\nvoid EA_OnTick(){}", "12345", 'Broker-"Live')
        text = source.decode("utf-8-sig")
        self.assertEqual(text.count("AccountInfoInteger(ACCOUNT_LOGIN)==12345"), 2)
        self.assertEqual(text.count('AccountInfoString(ACCOUNT_SERVER)=="Broker-\\"Live"'), 2)
        self.assertIn("return INIT_FAILED", text)

    def test_rejects_macros_includes_and_custom_events(self):
        base = b"int EA_OnInit(){return 0;}\nvoid EA_OnTick(){}\n"
        for suffix in [b'#define OnInit Custom', b'#include "x.mqh"', b'void OnTimer(){}']:
            with self.subTest(suffix=suffix), self.assertRaises(ValueError):
                locked_source(base + suffix, "12345", "Broker-Live")

    def test_rejects_missing_entrypoint_and_account_injection(self):
        with self.assertRaises(ValueError):
            locked_source(b"void EA_OnTick(){}", "12345", "Broker-Live")
        with self.assertRaises(ValueError):
            locked_source(b"int EA_OnInit(){return 0;} void EA_OnTick(){}", "1;Trade()", "Broker-Live")


class CompileFlowTest(unittest.TestCase):
    def test_compiles_locked_source_returns_artifact_and_cleans_temp_directory(self):
        with tempfile.TemporaryDirectory() as root:
            worker = Worker.__new__(Worker)
            worker.work_root = Path(root)
            worker.compiler = Path(root) / "metaeditor64.exe"
            worker.request = Mock(return_value=b"int EA_OnInit(){return 0;} void EA_OnTick(){}")
            worker.complete = Mock()
            job = {"id": 1, "token": "a" * 64, "protocol": 1, "login": "12345", "server": "Broker-Live", "source_sha256": None}

            def compiler(command, **kwargs):
                directory = Path(kwargs["cwd"])
                self.assertIn("ACCOUNT_LOGIN", (directory / "expert.mq5").read_text(encoding="utf-8-sig"))
                self.assertTrue(command[1].startswith("/compile:"))
                (directory / "compile.log").write_text("0 errors, 0 warnings", encoding="utf-16")
                (directory / "expert.ex5").write_bytes(b"compiled mock result")
                return Mock()

            with patch("worker.subprocess.Popen", side_effect=compiler):
                worker.compile(job)

            worker.complete.assert_called_once_with(job, b"compiled mock result")
            self.assertEqual(list(Path(root).iterdir()), [])

    def test_checksum_mismatch_never_invokes_compiler(self):
        worker = Worker.__new__(Worker)
        worker.request = Mock(return_value=b"tampered source")
        job = {"id": 1, "token": "a" * 64, "protocol": 1, "login": "12345", "server": "Broker-Live", "source_sha256": "0" * 64}

        with patch("worker.subprocess.Popen") as process, self.assertRaises(ValueError):
            worker.compile(job)

        process.assert_not_called()


if __name__ == "__main__":
    unittest.main()
