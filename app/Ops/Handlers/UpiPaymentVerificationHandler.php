<?php

namespace App\Ops\Handlers;

use App\Ops\Contracts\EventHandler;
use Illuminate\Support\Facades\DB;

class UpiPaymentVerificationHandler implements EventHandler
{
    public function handle($event): array
    {
        $payload = is_array($event->payload)
            ? $event->payload
            : json_decode($event->payload, true);

        $attemptId = (int) ($payload['attempt_id'] ?? 0);

        if ($attemptId <= 0) {
            throw new \RuntimeException(
                'Invalid upi.payment.verify payload: attempt_id required'
            );
        }

        $attempt = DB::table('upi_payment_attempts')
            ->where('id', $attemptId)
            ->first();

        if (!$attempt) {
            throw new \RuntimeException(
                "UPI payment attempt {$attemptId} not found"
            );
        }

        // Idempotency protection.
        if ($attempt->status === 'successful') {
            return [
                'ok' => true,
                'already_processed' => true,
                'attempt_id' => $attemptId,
                'payment_id' => $attempt->payment_id,
            ];
        }

        /*
         * IMPORTANT:
         *
         * Do NOT call PaymentService here yet.
         *
         * The response received from the customer's UPI app is
         * client-side information. It is not independent server-side
         * verification of money received by Leela.
         *
         * We need a bank / PSP / payment-provider verification source
         * before automatic allocation is allowed.
         */

        DB::table('upi_payment_attempts')
            ->where('id', $attemptId)
            ->update([
                'status' => 'pending',
                'updated_at' => now(),
            ]);

        return [
            'ok' => true,
            'verified' => false,
            'status' => 'pending_verification',
            'attempt_id' => $attemptId,
        ];
    }
}
