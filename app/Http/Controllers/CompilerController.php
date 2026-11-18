<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompilerController extends Controller
{
    public function claim(): JsonResponse
    {
        $result = DB::transaction(function (): ?array {
            DB::table('builds')->where('status', 'compiling')->where('lease_expires_at', '<=', now())
                ->where('attempts', '>=', config('archive.max_attempts'))
                ->update(['status' => 'failed', 'error' => 'compilation.failed', 'updated_at' => now()]);
            for ($i = 0; $i < 50; $i++) {
                $build = DB::table('builds')->where(function ($query) {
                    $query->where('status', 'queued')->orWhere(function ($query) {
                        $query->where('status', 'compiling')->where(function ($lease) {
                            $lease->whereNull('lease_expires_at')->orWhere('lease_expires_at', '<=', now());
                        });
                    });
                })->orderBy('id')->lockForUpdate()->first();
                if (! $build) {
                    return null;
                }
                $expert = DB::table('experts')->where('id', $build->expert_id)->where('status', 'published')->first();
                $account = DB::table('broker_accounts')->where('id', $build->broker_account_id)->where('user_id', $build->user_id)->where('status', 'verified')->first();
                if (! $expert || ! $expert->source_path || ! $account || $build->attempts >= config('archive.max_attempts')) {
                    DB::table('builds')->where('id', $build->id)->update(['status' => 'failed', 'error' => 'compilation.failed', 'updated_at' => now()]);

                    continue;
                }
                $token = bin2hex(random_bytes(32));
                $expires = now()->addSeconds(config('archive.lease_seconds'));
                DB::table('builds')->where('id', $build->id)->update([
                    'status' => 'compiling', 'error' => null, 'attempts' => $build->attempts + 1,
                    'lease_hash' => hash('sha256', $token), 'lease_expires_at' => $expires,
                    'source_disk' => $expert->source_disk, 'source_path' => $expert->source_path,
                    'source_sha256' => $expert->source_sha256, 'updated_at' => now(),
                ]);

                return ['id' => $build->id, 'token' => $token, 'expires_at' => $expires->toIso8601String(),
                    'login' => $account->login, 'server' => $account->server, 'source_sha256' => $expert->source_sha256,
                    'protocol' => 1];
            }

            return null;
        });

        return response()->json(['build' => $result]);
    }

    public function source(Request $request, int $id): StreamedResponse
    {
        $build = DB::table('builds')->where('id', $id)->firstOrFail();
        $this->checkLease($request, $build);
        $this->checkEligibility($build);
        $disk = Storage::disk($build->source_disk);
        abort_unless($disk->exists($build->source_path), 404);

        return $disk->download($build->source_path, 'expert.mq5', ['Cache-Control' => 'no-store', 'Content-Type' => 'application/octet-stream']);
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        $request->validate(['artifact' => 'required|file|max:16384', 'sha256' => ['required', 'regex:/^[a-f0-9]{64}$/']]);
        abort_unless(strtolower($request->file('artifact')->getClientOriginalExtension()) === 'ex5', 422);
        $hash = hash_file('sha256', $request->file('artifact')->getRealPath());
        abort_unless(hash_equals($hash, $request->string('sha256')->toString()), 422);
        $path = null;
        $diskName = config('archive.files_disk');
        try {
            return DB::transaction(function () use ($request, $id, $hash, $diskName, &$path): JsonResponse {
                $build = DB::table('builds')->where('id', $id)->lockForUpdate()->firstOrFail();
                $this->checkToken($request, $build);
                if ($build->status === 'ready') {
                    abort_unless(hash_equals($build->artifact_sha256 ?? '', $hash), 409);

                    return response()->json(['status' => 'ready']);
                }
                $this->checkLease($request, $build);
                $this->checkEligibility($build);
                $path = Storage::disk($diskName)->putFileAs('artifacts', $request->file('artifact'), Str::uuid().'.ex5');
                abort_unless(is_string($path), 503, 'Artifact storage unavailable.');
                DB::table('builds')->where('id', $id)->update(['status' => 'ready', 'artifact_disk' => $diskName,
                    'artifact_path' => $path, 'artifact_sha256' => $hash, 'error' => null, 'updated_at' => now()]);

                return response()->json(['status' => 'ready']);
            });
        } catch (\Throwable $exception) {
            if (is_string($path)) {
                Storage::disk($diskName)->delete($path);
            }
            throw $exception;
        }
    }

    public function fail(Request $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id): JsonResponse {
            $build = DB::table('builds')->where('id', $id)->lockForUpdate()->firstOrFail();
            $this->checkLease($request, $build);
            DB::table('builds')->where('id', $id)->update(['status' => 'failed', 'error' => 'compilation.failed', 'updated_at' => now()]);

            return response()->json(['status' => 'failed']);
        });
    }

    private function checkToken(Request $request, object $build): void
    {
        abort_unless($build->lease_hash && hash_equals($build->lease_hash, hash('sha256', $request->header('X-Build-Token', ''))), 403);
    }

    private function checkLease(Request $request, object $build): void
    {
        $this->checkToken($request, $build);
        abort_unless($build->status === 'compiling' && $build->lease_expires_at && Carbon::parse($build->lease_expires_at)->isFuture(), 409);
    }

    private function checkEligibility(object $build): void
    {
        abort_unless(DB::table('experts')->where('id', $build->expert_id)->where('status', 'published')->exists(), 409);
        abort_unless(DB::table('broker_accounts')->where('id', $build->broker_account_id)->where('user_id', $build->user_id)->where('status', 'verified')->exists(), 409);
    }
}
