<?php

namespace App\Console\Commands;

use App\Jobs\ProcessOutboxEventJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OpsDispatchDueEvents extends Command
{
    protected $signature = 'ops:dispatch-due {--batch=50} {--lock-ttl=10}';
    protected $description = 'Claims due outbox events and dispatches processing jobs';

    public function handle(): int
    {
        $batch = (int) $this->option('batch');
        $lockTtlMinutes = (int) $this->option('lock-ttl');

        $now = now();
        $workerId = gethostname() . ':' . getmypid();

        // Claim inside transaction with SKIP LOCKED
        $claimedIds = DB::transaction(function () use ($batch, $lockTtlMinutes, $now, $workerId) {
            // MySQL 8 supports SKIP LOCKED.
            $ids = DB::select(
                "
                SELECT id
                FROM outbox_events
                WHERE status IN ('pending','retrying')
                  AND scheduled_at <= ?
                  AND (locked_at IS NULL OR locked_at < DATE_SUB(?, INTERVAL ? MINUTE))
                ORDER BY priority ASC, scheduled_at ASC, id ASC
                LIMIT {$batch}
                FOR UPDATE SKIP LOCKED
                ",
                [$now, $now, $lockTtlMinutes]
            );

            $ids = array_map(fn($r) => (int)$r->id, $ids);
            if (empty($ids)) return [];

            DB::table('outbox_events')
                ->whereIn('id', $ids)
                ->update([
                    'status'     => 'processing',
                    'locked_at'  => $now,
                    'locked_by'  => $workerId,
                    'started_at' => $now,
                    'attempts'   => DB::raw('attempts + 1'),
                    'updated_at' => $now,
                ]);

            return $ids;
        });

        foreach ($claimedIds as $id) {
            ProcessOutboxEventJob::dispatch($id)->onQueue('ops');
        }

        $this->info("Claimed: " . count($claimedIds));
        return self::SUCCESS;
    }
}
