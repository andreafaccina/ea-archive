<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[Signature('archive:copy-ea-storage {disk : Configured private destination disk} {--apply : Copy files and update database references}')]
#[Description('Preview or copy EA sources and artifacts without deleting original files')]
class CopyEaStorage extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $target = $this->argument('disk');
        if (! config('filesystems.disks.'.$target) || config('filesystems.disks.'.$target.'.visibility') === 'public') {
            $this->error('Choose a configured private disk.');

            return self::FAILURE;
        }
        try {
            foreach (['experts' => 'source', 'builds' => 'artifact'] as $table => $prefix) {
                DB::table($table)->whereNotNull($prefix.'_path')->where($prefix.'_disk', '!=', $target)
                    ->orderBy('id')->chunkById(100, function ($records) use ($table, $prefix, $target): void {
                        foreach ($records as $record) {
                            $from = $record->{$prefix.'_disk'};
                            $path = $record->{$prefix.'_path'};
                            $this->line($table.' #'.$record->id.': '.$from.' -> '.$target);
                            if (! $this->option('apply')) {
                                continue;
                            }
                            $source = Storage::disk($from);
                            $destination = Storage::disk($target);
                            $hash = $this->digest($from, $path);
                            if ($destination->exists($path)) {
                                if (! hash_equals($hash, $this->digest($target, $path))) {
                                    throw new \RuntimeException('Destination conflict: original file retained.');
                                }
                            } else {
                                $stream = $source->readStream($path);
                                try {
                                    if (! $destination->put($path, $stream)) {
                                        throw new \RuntimeException('Copy failed.');
                                    }
                                } finally {
                                    if (is_resource($stream)) {
                                        fclose($stream);
                                    }
                                }
                                if (! hash_equals($hash, $this->digest($target, $path))) {
                                    throw new \RuntimeException('Copy checksum mismatch: original retained.');
                                }
                            }
                            DB::table($table)->where('id', $record->id)->where($prefix.'_disk', $from)->where($prefix.'_path', $path)
                                ->update([$prefix.'_disk' => $target, $prefix.'_sha256' => $hash, 'updated_at' => now()]);
                        }
                    });
            }
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Copy interrupted. Check private logs; original files have not been deleted.');

            return self::FAILURE;
        }
        $this->info($this->option('apply') ? 'Copy completed. Original files retained.' : 'Preview only. Use --apply to copy.');

        return self::SUCCESS;
    }

    private function digest(string $disk, string $path): string
    {
        $stream = Storage::disk($disk)->readStream($path);
        if (! is_resource($stream)) {
            throw new \RuntimeException('File unavailable.');
        }
        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }
}
