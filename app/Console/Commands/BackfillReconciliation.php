<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Ops\Handlers\DailyZoneReconcileHandler;
use App\Models\OutboxEvent;
use Carbon\Carbon;

class BackfillReconciliation extends Command
{
    protected $signature = 'dayli:reconcile-backfill 
        {--from=} 
        {--to=} 
        {--zone=} 
        {--subtype=}';

    protected $description = 'Backfill reconciliation reports for date range';

    public function handle(DailyZoneReconcileHandler $handler): int
    {
        $from = Carbon::parse($this->option('from'));
        $to = Carbon::parse($this->option('to'));

        $zoneId = (int) $this->option('zone');
        $subTypeId = (int) $this->option('subtype');

        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $event = new OutboxEvent();

            $event->event_type = 'zone.daily.reconcile';
            $event->payload = [
                'zone_id' => $zoneId,
                'delivery_date' => $date->toDateString(),
                'subscription_type_id' => $subTypeId,
            ];


            $this->info('EVENT TYPE: ' . $event->event_type);
            $this->info('PAYLOAD: ' . json_encode($event->payload));
            $result = $handler->handle($event);

            $this->info($date->toDateString() . ' => ' . json_encode($result));
        }

        return self::SUCCESS;
    }
}
