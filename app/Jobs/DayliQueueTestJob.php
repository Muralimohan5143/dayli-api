<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DayliQueueTestJob implements ShouldQueue
{
    use Queueable;

    /**
     * Number of retries.
     */
    public int $tries = 3;

    /**
     * Timeout in seconds.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('DAYLI REDIS QUEUE IS WORKING', [
            'processed_at' => now()->toDateTimeString(),
        ]);
    }
}
