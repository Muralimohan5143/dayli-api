<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * Record a customer payment and apply explicit invoice allocations.
     *
     * This is reusable from:
     * - Controllers
     * - UPI verification handlers
     * - Batch jobs
     */
    public function recordAndAllocate(
        int $userId,
        float $amount,
        string $date,
        string $method,
        array $allocations,
        ?string $note = null,
        ?int $createdBy = null,
        ?string $referenceNo = null,
        string $source = 'invoice_allocation'
    ): array {
        return DB::transaction(function () use (
            $userId,
            $amount,
            $date,
            $method,
            $allocations,
            $note,
            $createdBy,
            $referenceNo,
            $source
        ) {
            $paymentId = DB::table('payments')->insertGetId([
                'party_type'   => 'customer',
                'party_id'     => $userId,
                'direction'    => 'in',
                'received_at'  => $date . ' 00:00:00',
                'method'       => $method,
                'reference_no' => $referenceNo,
                'amount'       => $amount,
                'status'       => 'posted',
                'created_by'   => $createdBy,

                'meta' => json_encode([
                    'source' => $source,
                ]),

                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $remaining = $amount;
            $allocatedTotal = 0.0;
            $results = [];

            foreach ($allocations as $row) {
                if ($remaining <= 0) {
                    break;
                }

                $invoiceId = (int) ($row['invoice_id'] ?? 0);
                $wantPay = (float) ($row['amount'] ?? 0);

                if ($invoiceId <= 0 || $wantPay <= 0) {
                    continue;
                }

                $invoice = DB::table('invoices')
                    ->where('id', $invoiceId)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if (!$invoice) {
                    continue;
                }

                if ((int) ($invoice->user_id ?? 0) !== $userId) {
                    continue;
                }

                $grand = (float) ($invoice->grand_total ?? 0);
                $due = (float) ($invoice->Unpaid_dues ?? 0);

                /*
                 * Historical imported invoices may store the real balance
                 * in meta.sheet_closing_dues.
                 */
                $meta = json_decode($invoice->meta ?? '{}', true);

                if (
                    !empty($meta['historical_import']) &&
                    ($invoice->payment_status ?? '') !== 'paid'
                ) {
                    $due = max(
                        0,
                        (float) ($meta['sheet_closing_dues'] ?? $due)
                    );
                }

                if ($grand <= 0 || $due <= 0) {
                    continue;
                }

                $payNow = min(
                    $wantPay,
                    $remaining,
                    $due
                );

                if ($payNow <= 0) {
                    continue;
                }

                $newDue = max(
                    $due - $payNow,
                    0
                );

                if ($newDue <= 0.0001) {
                    $status = 'paid';
                } elseif ($newDue < $grand) {
                    $status = 'partial';
                } else {
                    $status = 'unpaid';
                }

                DB::table('payment_allocations')->insert([
                    'payment_id'          => $paymentId,
                    'inward_payment_id'   => null,
                    'invoice_id'          => $invoiceId,
                    'allocatable_type'    => 'invoice',
                    'allocatable_id'      => $invoiceId,
                    'amount_applied'      => $payNow,
                    'allocated_amount'    => $payNow,
                    'is_final_allocation' => $newDue <= 0.0001 ? 1 : 0,
                    'note'                => $note,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);

                $invoiceUpdate = [
                    'Unpaid_dues'    => $newDue,
                    'payment_status' => $status,
                    'updated_at'     => now(),
                ];

                if ($status === 'paid') {
                    $invoiceUpdate['status'] = 'paid';
                }

                DB::table('invoices')
                    ->where('id', $invoiceId)
                    ->update($invoiceUpdate);

                /*
                 * Historical carry-forward behaviour already used by
                 * the existing controller.
                 */
                if ($status === 'paid') {
                    DB::table('invoices')
                        ->where('user_id', $userId)
                        ->whereNull('deleted_at')
                        ->where(
                            'invoice_date',
                            '<',
                            $invoice->invoice_date
                        )
                        ->whereIn(
                            'payment_status',
                            ['unpaid', 'partial']
                        )
                        ->update([
                            'Unpaid_dues'    => 0,
                            'payment_status' => 'paid',
                            'status'         => 'paid',
                            'updated_at'     => now(),
                        ]);
                }

                $allocatedTotal += $payNow;
                $remaining -= $payNow;

                $results[] = [
                    'payment_id'     => $paymentId,
                    'invoice_id'     => $invoiceId,
                    'paid'           => $payNow,
                    'old_due'        => $due,
                    'new_due'        => $newDue,
                    'payment_status' => $status,
                ];
            }

            $creditAmount = max(
                $remaining,
                0
            );

            /*
             * Notification remains event-driven.
             */
            DB::table('outbox_events')->insert([
                'event_type' => 'payment.received',

                'aggregate_type' => 'payment',
                'aggregate_id' => $paymentId,

                'idempotency_key' =>
                'payment.received:' . $paymentId,

                'scheduled_at' => now(),
                'status' => 'pending',
                'priority' => 5,
                'attempts' => 0,
                'max_attempts' => 10,

                'payload' => json_encode([
                    'user_id' => $userId,
                    'payment_id' => $paymentId,
                    'amount' => round($amount, 2),
                    'payment_date' => $date,
                    'method' => $method,
                    'allocated_amount' =>
                    round($allocatedTotal, 2),
                    'credit_amount' =>
                    round($creditAmount, 2),
                    'allocations' => $results,
                ]),

                'notify_on' => 'failure',

                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'ok' => true,
                'user_id' => $userId,
                'payment_id' => $paymentId,
                'received_amount' => round($amount, 2),
                'allocated_amount' =>
                round($allocatedTotal, 2),
                'credit_amount' =>
                round($creditAmount, 2),
                'allocations' => $results,
            ];
        });
    }
}
