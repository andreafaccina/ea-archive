<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Compatibility handler: old queued jobs leave builds for the external worker. */
class CompileExpert implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $buildId) {}

    public function handle(): void {}
}
