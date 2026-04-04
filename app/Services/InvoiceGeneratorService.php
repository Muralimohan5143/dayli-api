<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceGeneratorService
{
    public function generateForReport(
        int $zoneId,
        int $subscriptionTypeId,
        string $monthStart,
        string $monthEndExclusive
    ): int {
        $invoiceDate = Carbon::parse($monthStart)->endOfMonth()->toDateString();

        $rows = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('variants as v', 'v.variant_id', '=', 'oi.variant_id')
            ->join('products as p', 'p.product_id', '=', 'v.product_id')
            ->join('subscription_sub_types as sst', 'sst.slug', '=', 'p.product_sub_type')
            ->whereNotNull('oi.actuals_date')
            ->where('o.zone_id', $zoneId)
            ->where('sst.subscription_type_id', $subscriptionTypeId)
            ->where('oi.actuals_date', '>=', $monthStart)
            ->where('oi.actuals_date', '<', $monthEndExclusive)
            ->selectRaw('
                o.customer_id as user_id,
                MIN(o.id) as any_order_id,
                MIN(o.order_type) as any_order_type,
                oi.product_id as product_id,
                oi.variant_id as variant_id,
                oi.title as title,
                SUM(oi.quantity) as monthly_count,
                AVG(oi.unit_price) as unit_price_avg,
                SUM(oi.line_total) as line_total_sum
            ')
            ->groupBy('o.customer_id', 'oi.product_id', 'oi.variant_id', 'oi.title')
            ->orderBy('o.customer_id')
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $byUser = [];

        foreach ($rows as $r) {
            $uid = (int) $r->user_id;

            if (!isset($byUser[$uid])) {
                $byUser[$uid] = [
                    'user_id' => $uid,
                    'order_id' => (int) $r->any_order_id,
                    'order_type' => (string) $r->any_order_type,
                    'items' => [],
                ];
            }

            $byUser[$uid]['items'][] = $r;
        }

        $processed = 0;

        foreach ($byUser as $uid => $payload) {
            $subtotal = 0.0;

            foreach ($payload['items'] as $it) {
                $subtotal += (float) $it->line_total_sum;
            }

            $previousDues = $this->computePreviousDues($uid, $monthStart);
            $grandTotal = round($subtotal + $previousDues, 2);

            $u = DB::table('users')
                ->where('id', $uid)
                ->select('display_name', 'name', 'first_name', 'last_name', 'phone')
                ->first();

            $billingName = $this->pickName($u);

            $invNumber = sprintf(
                'MILK-%s-Z%s-S%s-U%s',
                str_replace('-', '', substr($monthStart, 0, 7)),
                $zoneId,
                $subscriptionTypeId,
                $uid
            );

            $data = [
                'order_id' => $payload['order_id'],
                'order_type' => $payload['order_type'],
                'order_start_date' => $monthStart,
                'order_end_date' => Carbon::parse($monthEndExclusive)->subDay()->toDateString(),
                'user_id' => $uid,
                'billing_name' => $billingName ?: 'Customer',
                'invoice_date' => $invoiceDate,
                'status' => 'issued',
                'payment_status' => 'unpaid',
                'gst_status' => 'unfiled',
                'subtotal' => round($subtotal, 2),
                'Unpaid_dues' => round($previousDues, 2),
                'tax' => 0,
                'tax_total' => 0,
                'discount' => 0,
                'delivery_fee' => 0,
                'total' => round($subtotal, 2),
                'grand_total' => $grandTotal,
                'currency' => 'INR',
                'number' => $invNumber,
                'invoice_number' => $invNumber,
                'meta' => json_encode([
                    'month' => substr($monthStart, 0, 7),
                    'zone_id' => $zoneId,
                    'subscription_type_id' => $subscriptionTypeId,
                    'billing_phone' => $u?->phone,
                ]),
                'updated_at' => now(),
            ];

            DB::table('invoices')->updateOrInsert(
                ['number' => $invNumber],
                array_merge($data, ['created_at' => now()])
            );

            $invoiceId = DB::table('invoices')
                ->where('number', $invNumber)
                ->value('id');

            if ($invoiceId) {
                DB::table('invoice_items')->where('invoice_id', $invoiceId)->delete();

                foreach ($payload['items'] as $it) {
                    DB::table('invoice_items')->insert([
                        'invoice_id' => $invoiceId,
                        'title' => (string) $it->title,
                        'quantity' => (int) round((float) $it->monthly_count),
                        'unit_price' => round((float) $it->unit_price_avg, 2),
                        'line_total' => round((float) $it->line_total_sum, 2),
                        'meta' => json_encode([
                            'product_id' => $it->product_id,
                            'variant_id' => $it->variant_id,
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $processed++;
        }

        return $processed;
    }

    private function computePreviousDues(int $userId, string $monthStart): float
    {
        $invSum = (float) DB::table('invoices')
            ->where('user_id', $userId)
            ->whereNotNull('invoice_date')
            ->where('invoice_date', '<', $monthStart)
            ->sum('grand_total');

        $paySum = (float) DB::table('inward_payments as p')
            ->leftJoin('invoices as i', 'i.id', '=', 'p.invoice_id')
            ->where('i.user_id', $userId)
            ->where(function ($q) use ($monthStart) {
                $q->whereNull('p.payment_date')
                    ->orWhere('p.payment_date', '<', $monthStart);
            })
            ->sum('p.amount');

        return max(0, round($invSum - $paySum, 2));
    }

    private function pickName($u): string
    {
        if (!$u) {
            return '';
        }

        if (!empty($u->display_name)) {
            return (string) $u->display_name;
        }

        if (!empty($u->name)) {
            return (string) $u->name;
        }

        $fn = trim((string) ($u->first_name ?? ''));
        $ln = trim((string) ($u->last_name ?? ''));

        return trim($fn . ' ' . $ln);
    }
}
