<?php

// app/Console/Commands/OutboxWorkCommand.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class OutboxWorkCommand extends Command
{
    protected $signature = 'outbox:work {--limit=50}';
    protected $description = 'Process outbox events';

    public function handle(): int
    {
        $limit = (int)$this->option('limit');
        $lockToken = bin2hex(random_bytes(16));

        // 1) lock rows (atomic)
        $ids = DB::transaction(function () use ($limit, $lockToken) {

            $rows = DB::table('outbox_events')
                ->whereIn('status', ['pending', 'retry'])
                ->where(function ($q) {
                    $q->whereNull('available_at')->orWhere('available_at', '<=', now());
                })
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get(['id']);

            $ids = $rows->pluck('id')->all();
            if (!$ids) return [];

            DB::table('outbox_events')
                ->whereIn('id', $ids)
                ->update([
                    'status' => 'processing',
                    'locked_at' => now(),
                    'lock_token' => $lockToken,
                    'updated_at' => now(),
                ]);

            return $ids;
        });

        if (!$ids) {
            $this->info('No outbox events to process.');
            return self::SUCCESS;
        }

        // 2) process locked rows
        $events = DB::table('outbox_events')
            ->whereIn('id', $ids)
            ->where('lock_token', $lockToken)
            ->get();

        foreach ($events as $ev) {
            try {
                $payload = json_decode($ev->payload, true) ?: [];

                $this->dispatchEvent($ev->event_type, $payload, $ev);

                DB::table('outbox_events')->where('id', $ev->id)->update([
                    'status' => 'success',
                    'processed_at' => now(),
                    'last_error' => null,
                    'lock_token' => null,
                    'locked_at' => null,
                    'updated_at' => now(),
                ]);
            } catch (Throwable $e) {
                $this->markFailureOrRetry($ev->id, $ev->attempts, $ev->max_attempts, $e);
            }
        }

        $this->info('Processed outbox batch: ' . count($events));
        return self::SUCCESS;
    }

    protected function dispatchEvent(string $type, array $payload, $ev): void
    {
        // Route to handlers
        switch ($type) {
            case 'vendor_supply_entered':
                $this->handleVendorSupplyEntered($payload, $ev);
                return;

            default:
                // unknown event type = fail fast
                throw new \RuntimeException("Unknown outbox event_type: {$type}");
        }
    }

    protected function handleVendorSupplyEntered(array $payload, $ev): void
    {
        // Required fields
        $vendorId = (int)($payload['vendor_id'] ?? 0);
        $orderId  = (int)($payload['order_id'] ?? 0);
        if (!$vendorId || !$orderId) {
            throw new \InvalidArgumentException('payload missing vendor_id or order_id');
        }

        /**
         * Put your “next-step” logic here.
         * Typical examples:
         * - Build/update vendor inward summaries for (zone,date)
         * - Enqueue reconciliation for that date
         * - Sync to external system
         *
         * IMPORTANT: make this idempotent.
         */
        // Example: mark some derived table, or enqueue reco_queue...
        // DB::table('reco_queue')->updateOrInsert([...]);
    }

    protected function markFailureOrRetry(int $id, int $attempts, int $maxAttempts, Throwable $e): void
    {
        $attempts2 = $attempts + 1;

        if ($attempts2 >= $maxAttempts) {
            DB::table('outbox_events')->where('id', $id)->update([
                'status' => 'failed',
                'attempts' => $attempts2,
                'last_error' => $this->trimError($e),
                'processed_at' => now(),
                'lock_token' => null,
                'locked_at' => null,
                'updated_at' => now(),
            ]);
            return;
        }

        // exponential backoff: 1m, 2m, 4m, ... capped to 60m
        $delayMinutes = min(60, (int)pow(2, max(0, $attempts2 - 1)));
        DB::table('outbox_events')->where('id', $id)->update([
            'status' => 'retry',
            'attempts' => $attempts2,
            'last_error' => $this->trimError($e),
            'available_at' => now()->addMinutes($delayMinutes),
            'lock_token' => null,
            'locked_at' => null,
            'updated_at' => now(),
        ]);
    }

    protected function trimError(Throwable $e): string
    {
        $msg = get_class($e) . ': ' . $e->getMessage();
        return mb_substr($msg, 0, 4000);
    }
}
