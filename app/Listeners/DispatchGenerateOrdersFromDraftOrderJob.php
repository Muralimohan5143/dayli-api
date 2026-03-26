<?php

namespace App\Listeners;

use App\Events\DraftOrderReadyForOrders;
use App\Jobs\GenerateOrdersFromDraftOrderJob;

class DispatchGenerateOrdersFromDraftOrderJob
{
    public function handle(DraftOrderReadyForOrders $event): void
    {
        GenerateOrdersFromDraftOrderJob::dispatch($event->draftOrderId);
    }
}
