<?php

namespace App\Ops\Contracts;

use App\Models\OutboxEvent;

interface EventHandler
{
    /**
     * Must be idempotent.
     * Throw exception on failure -> engine will retry/backoff.
     */
    public function handle(OutboxEvent $event): array;
}
