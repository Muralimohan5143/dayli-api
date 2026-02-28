<?php

namespace App\Jobs;

use App\Models\OutboxEvent;
use App\Ops\EventHandlerRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessOutboxEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // IMPORTANT: We manage retries in DB table, not queue.
    public int $tries = 1;

    public function __construct(public int $eventId) {}

    public function handle(EventHandlerRegistry $registry): void
    {
        $event = OutboxEvent::query()->find($this->eventId);
        if (!$event) return;

        // If someone already finished it (race), skip.
        if (!in_array($event->status, ['processing'], true)) return;

        try {
            $handler = $registry->resolve($event->event_type);

            $result = $handler->handle($event);

            $event->update([
                'status'      => 'succeeded',
                'finished_at' => now(),
                'result'      => $result,
                'last_error'  => null,
                'locked_at'   => null,
                'locked_by'   => null,
            ]);

            $this->notifyIfNeeded($event, 'succeeded');
        } catch (Throwable $e) {
            $this->markFailedWithRetry($event, $e);
        }
    }

    private function markFailedWithRetry(OutboxEvent $event, Throwable $e): void
    {
        $now = now();
        $attempts = (int) $event->attempts;
        $max = (int) $event->max_attempts;

        $error = substr($e->getMessage() . "\n" . $e->getTraceAsString(), 0, 65000);

        if ($attempts >= $max) {
            $event->update([
                'status'      => 'dead',
                'finished_at' => $now,
                'last_error'  => $error,
                'locked_at'   => null,
                'locked_by'   => null,
            ]);

            $this->notifyIfNeeded($event, 'dead');
            return;
        }

        // Exponential backoff (cap 30 minutes) + jitter (0-30s)
        $baseMinutes = min(30, (int) pow(2, max(0, $attempts - 1)));
        $jitterSeconds = random_int(0, 30);

        $nextRun = $now->copy()->addMinutes($baseMinutes)->addSeconds($jitterSeconds);

        $event->update([
            'status'       => 'retrying',
            'scheduled_at' => $nextRun,
            'finished_at'  => $now,
            'last_error'   => $error,
            'locked_at'    => null,
            'locked_by'    => null,
        ]);

        // Optional: notify after 3 consecutive failures (you can tune)
        if ($attempts >= 3) {
            $this->notifyIfNeeded($event, 'retrying');
        }
    }

    private function notifyIfNeeded(OutboxEvent $event, string $state): void
    {
        // Keep it minimal now. Expand later to Slack/WhatsApp/email.
        if ($event->notify_on === 'none') return;

        if ($event->notify_on === 'always') {
            logger()->info("OPS_NOTIFY: {$state}", ['event_id' => $event->id, 'type' => $event->event_type]);
            return;
        }

        // notify_on=failure
        if (in_array($state, ['retrying', 'dead'], true)) {
            logger()->warning("OPS_NOTIFY: {$state}", ['event_id' => $event->id, 'type' => $event->event_type]);
        }
    }
}
