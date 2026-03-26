<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class GenerateOrdersFromDraftOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public int $draftOrderId
    ) {}

    public function handle(): void
    {
        $draftOrder = DB::table('draft_orders')
            ->where('id', $this->draftOrderId)
            ->first();

        if (!$draftOrder) {
            return;
        }

        $doiRows = DB::table('draft_order_items')
            ->where('draft_order_id', $this->draftOrderId)
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        if ($doiRows->isEmpty()) {
            return;
        }

        $ordersByDate = [];

        foreach ($doiRows as $doi) {
            if ((float)($doi->qty ?? 0) <= 0) {
                continue;
            }

            if (($doi->status ?? null) === 'paused') {
                continue;
            }

            if (empty($doi->start_date)) {
                continue;
            }

            $start = Carbon::parse($doi->start_date);
            $end = !empty($doi->end_date)
                ? Carbon::parse($doi->end_date)
                : $start->copy();

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $dateKey = $date->toDateString();

                $ordersByDate[$dateKey][] = [
                    'product_id' => (int)$doi->product_id,
                    'variant_id' => (int)$doi->variant_id,
                    'qty' => (float)$doi->qty,
                    'unit_price' => (float)($doi->price_snapshot ?? 0),
                    'title' => $this->resolveItemTitle(
                        (int)$doi->product_id,
                        (int)$doi->variant_id
                    ),
                ];
            }
        }

        foreach ($ordersByDate as $date => $items) {
            DB::transaction(function () use ($draftOrder, $date, $items) {
                $order = $this->ensureOrder(
                    customerId: (int)$draftOrder->customer_id,
                    zoneId: $draftOrder->zone_id ? (int)$draftOrder->zone_id : null,
                    draftOrderId: (int)$draftOrder->id,
                    status: 'pending',
                    date: $date
                );

                $subtotal = 0.0;

                foreach ($items as $item) {
                    $created = $this->ensureOrderItem(
                        orderId: (int)$order['id'],
                        productId: (int)$item['product_id'],
                        variantId: (int)$item['variant_id'],
                        title: (string)$item['title'],
                        qty: (float)$item['qty'],
                        unitPrice: (float)$item['unit_price'],
                        actualsDate: $date
                    );

                    $subtotal += round(((float)$item['qty']) * ((float)$item['unit_price']), 2);
                }

                DB::table('orders')
                    ->where('id', $order['id'])
                    ->update([
                        'item_count' => count($items),
                        'subtotal' => $subtotal,
                        'current_subtotal' => $subtotal,
                        'current_total' => $subtotal,
                        'total' => $subtotal,
                        'updated_at' => now(),
                    ]);
            });
        }
    }

    private function ensureOrder(
        int $customerId,
        ?int $zoneId,
        int $draftOrderId,
        string $status,
        string $date
    ): array {
        $number = "sub:{$draftOrderId}:{$date}";

        $existing = DB::table('orders')->where('number', $number)->first();
        if ($existing) {
            DB::table('orders')
                ->where('id', $existing->id)
                ->update([
                    'delivery_date' => $date,
                    'updated_at' => now(),
                ]);

            return ['id' => (int)$existing->id, 'created' => false];
        }

        $id = DB::table('orders')->insertGetId([
            'order_type' => 'subscription',
            'customer_id' => $customerId,
            'zone_id' => $zoneId,
            'draft_order_id' => $draftOrderId,
            'number' => $number,
            'delivery_date' => $date,
            'status' => $status,
            'confirmed' => 0,
            'closed' => 0,
            'requires_shipping' => 1,
            'taxes_included' => 0,
            'tax_exempt' => 0,
            'test' => 0,
            'unpaid' => 0,
            'subtotal' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => (int)$id, 'created' => true];
    }

    private function ensureOrderItem(
        int $orderId,
        int $productId,
        int $variantId,
        string $title,
        float $qty,
        float $unitPrice,
        string $actualsDate
    ): bool {
        $existing = DB::table('order_items')
            ->where('order_id', $orderId)
            ->where('variant_id', $variantId)
            ->first();

        $lineTotal = round($unitPrice * $qty, 2);

        if ($existing) {
            DB::table('order_items')
                ->where('id', $existing->id)
                ->update([
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'actuals_date' => $actualsDate,
                    'updated_at' => now(),
                ]);

            return false;
        }

        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'title' => $title,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'actuals_date' => $actualsDate,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    private function resolveItemTitle(int $productId, int $variantId): string
    {
        $row = DB::table('variants')
            ->join('products', 'products.product_id', '=', 'variants.product_id')
            ->where('variants.variant_id', $variantId)
            ->select(
                'products.title as product_title',
                'variants.title as variant_title'
            )
            ->first();

        if (!$row) {
            return 'Subscription Item';
        }

        $productTitle = trim((string)($row->product_title ?? ''));
        $variantTitle = trim((string)($row->variant_title ?? ''));

        if ($variantTitle !== '' && strtolower($variantTitle) !== 'default title') {
            return $productTitle . ' - ' . $variantTitle;
        }

        return $productTitle !== '' ? $productTitle : 'Subscription Item';
    }
}
