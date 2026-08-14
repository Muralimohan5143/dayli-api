<?php

namespace App\Ops\Handlers;

use App\Ops\Contracts\EventHandler;
use App\Services\FcmService;
use Illuminate\Support\Facades\Log;

class PaymentReceivedNotificationHandler implements EventHandler
{
    public function handle($event): array
    {
        $payload = is_array($event->payload)
            ? $event->payload
            : json_decode($event->payload, true);

        $userId = (int) ($payload['user_id'] ?? 0);
        $paymentId = (int) ($payload['payment_id'] ?? 0);

        $amount = (float) ($payload['amount'] ?? 0);
        $paymentDate = (string) ($payload['payment_date'] ?? '');
        $method = (string) ($payload['method'] ?? '');

        $allocatedAmount = (float) ($payload['allocated_amount'] ?? 0);
        $creditAmount = (float) ($payload['credit_amount'] ?? 0);

        if ($userId <= 0) {
            throw new \RuntimeException(
                'Invalid payment.received payload: user_id required'
            );
        }

        if ($paymentId <= 0) {
            throw new \RuntimeException(
                'Invalid payment.received payload: payment_id required'
            );
        }

        if ($amount <= 0) {
            throw new \RuntimeException(
                'Invalid payment.received payload: amount must be greater than 0'
            );
        }

        $amountText = number_format($amount, 2);

        $body = "We received your payment of Rs {$amountText}/-. Thank you.";

        $pushPayload = [
            'title' => 'Payment Received',
            'body' => $body,

            'data' => [
                'type' => 'payment_received',

                // Customer can be taken to invoice/payment screen later.
                'screen' => 'sub_invoices',

                'payment_id' => (string) $paymentId,
                'customer_id' => (string) $userId,
                'amount' => (string) $amount,
                'payment_date' => $paymentDate,
                'method' => $method,
                'allocated_amount' => (string) $allocatedAmount,
                'credit_amount' => (string) $creditAmount,
            ],
        ];

        $pushResult = app(FcmService::class)
            ->sendToUser($userId, $pushPayload);

        /*
         * No device token is not a processing failure.
         * Payment is already recorded correctly.
         * We simply record that notification was skipped.
         */
        if (($pushResult['total'] ?? 0) === 0) {
            Log::info('Payment received push skipped - no valid FCM token', [
                'payment_id' => $paymentId,
                'user_id' => $userId,
                'amount' => $amount,
                'payment_date' => $paymentDate,
            ]);

            return [
                'ok' => true,
                'sent' => false,
                'reason' => 'no_valid_fcm_token',
                'payment_id' => $paymentId,
                'user_id' => $userId,
            ];
        }

        if (($pushResult['ok'] ?? 0) > 0) {
            Log::info('Payment received push sent', [
                'payment_id' => $paymentId,
                'user_id' => $userId,
                'amount' => $amount,
                'payment_date' => $paymentDate,
                'method' => $method,
                'push_result' => $pushResult,
            ]);

            return [
                'ok' => true,
                'sent' => true,
                'payment_id' => $paymentId,
                'user_id' => $userId,
                'push_result' => $pushResult,
            ];
        }

        /*
         * FCM existed but delivery failed.
         * Throw exception so the existing Outbox retry mechanism handles it.
         */
        Log::error('Payment received push failed', [
            'payment_id' => $paymentId,
            'user_id' => $userId,
            'amount' => $amount,
            'push_result' => $pushResult,
        ]);

        throw new \RuntimeException(
            'Payment received FCM notification failed'
        );
    }
}
