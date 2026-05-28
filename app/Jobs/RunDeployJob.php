<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class RunDeployJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    public function handle(): void
    {
        $script = base_path('scripts/deploy.sh');
        if (! is_file($script)) {
            Log::error('[deploy] Script not found: '.$script);
            return;
        }

        Log::info('[deploy] Starting...');
        $result = Process::run('SITES="haichau danguyhoakhanh hoacuong vptu tgdvdn" bash '.escapeshellarg($script));
        Log::info('[deploy] Output: '.($result->output() ?: '(empty)'));
        if ($result->failed()) {
            Log::error('[deploy] Failed: '.$result->errorOutput());
        } else {
            Log::info('[deploy] Completed successfully.');
        }
    }
}
