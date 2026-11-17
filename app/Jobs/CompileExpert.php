<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

class CompileExpert implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 150;

    public function __construct(public int $buildId) {}

    public function handle(): void
    {
        if (! DB::table('builds')->where('id', $this->buildId)->where('status', 'queued')->update(['status' => 'compiling', 'updated_at' => now()])) {
            return;
        }
        $dir = Storage::disk('local')->path('work/'.bin2hex(random_bytes(16)));
        try {
            $b = DB::table('builds')->find($this->buildId);
            $a = DB::table('broker_accounts')->where('id', $b->broker_account_id)->where('user_id', $b->user_id)->where('status', 'verified')->firstOrFail();
            $e = DB::table('experts')->where('id', $b->expert_id)->where('status', 'published')->firstOrFail();
            $compiler = config('archive.compiler');
            if (! $compiler || ! is_file($compiler)) {
                throw new RuntimeException('Compiler unavailable');
            }
            $source = Storage::disk('local')->get($e->source_path);
            if (! str_contains($source, 'int EA_OnInit()') || ! str_contains($source, 'void EA_OnTick()')) {
                throw new RuntimeException('Invalid source contract');
            }
            $server = json_encode($a->server, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $guard = "\nint OnInit(){if(AccountInfoInteger(ACCOUNT_LOGIN)!=".$a->login.' || AccountInfoString(ACCOUNT_SERVER)!='.$server.")return INIT_FAILED;return EA_OnInit();}\nvoid OnTick(){if(AccountInfoInteger(ACCOUNT_LOGIN)==".$a->login.' && AccountInfoString(ACCOUNT_SERVER)=='.$server.")EA_OnTick();}\n";
            File::ensureDirectoryExists($dir);
            File::put($dir.'/expert.mq5', $source.$guard);
            $p = new Process([$compiler, '/compile:'.$dir.'/expert.mq5', '/log:'.$dir.'/compile.log']);
            $p->setTimeout(120);
            $p->run();
            $log = is_file($dir.'/compile.log') ? file_get_contents($dir.'/compile.log') : '';
            if (str_contains($log, "\0")) {
                $log = mb_convert_encoding($log, 'UTF-8', 'UTF-16LE');
            }
            if (! is_file($dir.'/expert.ex5') || ! preg_match('/\b0 errors\b/i', $log)) {
                throw new RuntimeException('Compilation failed');
            }
            $path = 'artifacts/'.bin2hex(random_bytes(24)).'.ex5';
            Storage::disk('local')->put($path, file_get_contents($dir.'/expert.ex5'));
            DB::table('builds')->where('id', $b->id)->update(['status' => 'ready', 'artifact_path' => $path, 'updated_at' => now()]);
        } catch (\Throwable $e) {
            report($e);
            DB::table('builds')->where('id', $this->buildId)->update(['status' => 'failed', 'error' => 'Compilazione non disponibile. Contatta il supporto.', 'updated_at' => now()]);
        } finally {
            if (is_dir($dir)) {
                File::deleteDirectory($dir);
            }
        }
    }

    public function failed(?\Throwable $e): void
    {
        DB::table('builds')->where('id', $this->buildId)->where('status', 'compiling')->update(['status' => 'failed', 'error' => 'Worker interrotto.', 'updated_at' => now()]);
    }
}
