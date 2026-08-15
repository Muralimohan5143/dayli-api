<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminBillingController extends Controller
{
    public function search(Request $request)
    {
        $q = trim((string) $request->query('query', ''));
        if ($q === '') return response()->json([]);

        // 1) Search directly in users
        $users = DB::table('users')
            ->select('id', DB::raw('COALESCE(display_name, name, "") as name'), 'phone', 'address', 'nagar', 'pincode')
            ->whereNull('deleted_at')
            ->where(function ($w) use ($q) {
                $w->where('phone', 'like', "%{$q}%")
                    ->orWhere('display_name', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%");
            })
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get();

        // 2) Search in invoices meta->billing_phone and map to users
        // JSON_EXTRACT(meta,'$.billing_phone') returns string with quotes, use JSON_UNQUOTE
        $invoiceUserIds = DB::table('invoices')
            ->whereNull('deleted_at')
            ->whereIn('payment_status', ['unpaid', 'partial', 'paid']) // any
            ->where(function ($w) use ($q) {
                $w->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta,'$.billing_phone')) LIKE ?", ["%{$q}%"])
                    ->orWhere('invoice_number', 'like', "%{$q}%")
                    ->orWhere('number', 'like', "%{$q}%");
            })
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique()
            ->values();

        $usersFromInvoices = collect();
        if ($invoiceUserIds->isNotEmpty()) {
            $usersFromInvoices = DB::table('users')
                ->select('id', DB::raw('COALESCE(display_name, name, "") as name'), 'phone', 'address', 'nagar', 'pincode')
                ->whereNull('deleted_at')
                ->whereIn('id', $invoiceUserIds->all())
                ->limit(20)
                ->get();
        }

        // merge + unique by id
        $merged = $users->concat($usersFromInvoices)->unique('id')->take(20)->values();

        $out = $merged->map(function ($u) {
            $address = trim((string)($u->address ?? ''));
            if (($u->nagar ?? '') !== '') $address = trim($address . ' ' . $u->nagar);
            if (($u->pincode ?? '') !== '') $address = trim($address . ' ' . $u->pincode);

            return [
                'id' => (int) $u->id,
                'name' => (string) ($u->name ?? ''),
                'phone' => (string) ($u->phone ?? ''),
                'address' => $address,
            ];
        });

        return response()->json($out);
    }


    public function userUnpaid(Request $request)
    {
        $userId = (int) $request->query('user_id', 0);

        if ($userId <= 0) {
            return response()->json(['message' => 'user_id required'], 422);
        }

        $u = DB::table('users')
            ->select(
                'id',
                DB::raw('COALESCE(display_name, name, "") as name'),
                'phone',
                'address',
                'nagar',
                'pincode'
            )
            ->where('id', $userId)
            ->first();

        if (!$u) {
            return response()->json(['message' => 'User not found'], 404);
        }

        /*
     * IMPORTANT:
     * Historical milk invoices use carry-forward balances.
     * So Jan, Feb, Mar... must NOT all be collected separately.
     *
     * The latest invoice represents the customer's current balance.
     */
        $latestInvoice = DB::table('invoices')
            ->whereNull('deleted_at')
            ->where('user_id', $userId)
            ->where('grand_total', '>', 0)
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->first();

        $invoices = collect();

        if ($latestInvoice) {
            $meta = json_decode($latestInvoice->meta ?? '{}', true);

            $isHistorical = !empty($meta['historical_import']);

            if (($latestInvoice->payment_status ?? '') === 'paid') {

                // Paid invoice must never have a current payable due.
                $due = 0;
            } elseif (
                $isHistorical &&
                array_key_exists('sheet_closing_dues', $meta)
            ) {

                // Historical unpaid/partial invoice:
                // sheet closing dues is the source balance.
                $due = max(
                    0,
                    (float) $meta['sheet_closing_dues']
                );
            } else {

                // Normal generated invoice.
                $due = (float) ($latestInvoice->grand_total ?? 0);

                if (
                    isset($latestInvoice->Unpaid_dues) &&
                    (float) $latestInvoice->Unpaid_dues > 0
                ) {
                    $due = (float) $latestInvoice->Unpaid_dues;
                }
            }

            if ($due > 0) {
                $invoiceNo =
                    $latestInvoice->invoice_number
                    ?: ($latestInvoice->number
                        ?: ('INV-' . str_pad(
                            (string) $latestInvoice->id,
                            6,
                            '0',
                            STR_PAD_LEFT
                        )));

                $invoices->push([
                    'id' => (int) $latestInvoice->id,
                    'user_id' => $latestInvoice->user_id
                        ? (int) $latestInvoice->user_id
                        : null,
                    'invoice_no' => (string) $invoiceNo,
                    'invoice_date' => (string) ($latestInvoice->invoice_date ?? ''),
                    'order_start_date' => (string) ($latestInvoice->order_start_date ?? ''),
                    'order_end_date' => (string) ($latestInvoice->order_end_date ?? ''),
                    'grand_total' => (float) ($latestInvoice->grand_total ?? 0),
                    'due_amount' => round($due, 2),
                    'unpaid_dues' => round($due, 2),
                    'payment_status' => $due > 0
                        ? 'partial'
                        : 'paid',
                ]);
            }
        }

        $dueTotal = (float) $invoices->sum('due_amount');

        $address = trim((string) ($u->address ?? ''));

        if (($u->nagar ?? '') !== '') {
            $address = trim($address . ' ' . $u->nagar);
        }

        if (($u->pincode ?? '') !== '') {
            $address = trim($address . ' ' . $u->pincode);
        }

        return response()->json([
            'customer' => [
                'id' => (int) $u->id,
                'name' => (string) $u->name,
                'phone' => (string) ($u->phone ?? ''),
                'address' => $address,
            ],
            'summary' => [
                'unpaid_count' => $invoices->count(),
                'due_total' => $dueTotal,
            ],
            'invoices' => $invoices->values(),
        ]);
    }


    private function _derivePaymentStatus(float $grand, float $due): string
    {
        if ($grand <= 0) return 'paid';
        if ($due <= 0.0001) return 'paid';
        if ($due < $grand) return 'partial';
        return 'unpaid';
    }
    public function unpaidInvoices(Request $request)
    {
        $month  = trim((string) $request->query('month', ''));   // YYYY-MM
        $search = trim((string) $request->query('search', ''));

        $q = DB::table('invoices as i')
            ->leftJoin('users as u', 'u.id', '=', 'i.user_id')
            ->select([
                'i.id',
                'i.user_id',
                'i.billing_name',
                'i.invoice_date',
                'i.invoice_number',
                'i.number',
                'i.order_start_date',
                'i.order_end_date',
                'i.grand_total',
                'i.Unpaid_dues',
                'i.payment_status',
                DB::raw('COALESCE(u.display_name, u.name, "") as customer_name'),
                DB::raw('COALESCE(u.phone, "") as phone'),
            ])
            ->whereNull('i.deleted_at')
            ->whereIn('i.payment_status', ['unpaid', 'partial'])
            ->where('i.Unpaid_dues', '>=', 0);

        if ($month !== '') {
            $q->whereRaw('DATE_FORMAT(i.invoice_date, "%Y-%m") = ?', [$month]);
        }

        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('i.billing_name', 'like', "%{$search}%")
                    ->orWhere('i.invoice_number', 'like', "%{$search}%")
                    ->orWhere('i.number', 'like', "%{$search}%")
                    ->orWhere('u.phone', 'like', "%{$search}%")
                    ->orWhere('u.name', 'like', "%{$search}%")
                    ->orWhere('u.display_name', 'like', "%{$search}%");
            });
        }

        $rows = $q->orderBy('i.invoice_date', 'desc')->limit(200)->get()->map(function ($r) {
            $invoiceNo = $r->invoice_number ?: ($r->number ?: ('INV-' . str_pad((string)$r->id, 6, '0', STR_PAD_LEFT)));

            return [
                'id' => (int) $r->id,
                'user_id' => $r->user_id ? (int)$r->user_id : null,
                'invoice_no' => (string) $invoiceNo,
                'billing_name' => (string) ($r->billing_name ?? ''),
                'customer_name' => (string) ($r->customer_name ?? ''),
                'phone' => (string) ($r->phone ?? ''),
                'invoice_date' => (string) ($r->invoice_date ?? ''),
                'order_start_date' => (string) ($r->order_start_date ?? ''),
                'order_end_date' => (string) ($r->order_end_date ?? ''),
                'grand_total' => (float) ($r->grand_total ?? 0),
                'due_amount' => (float) ($r->Unpaid_dues ?? 0),
                'payment_status' => (string) ($r->payment_status ?? 'unpaid'),
            ];
        });

        return response()->json(['data' => $rows->values()]);
    }

    public function collectPayment(Request $request)
    {
        $request->validate([
            'invoice_id' => 'required|integer',
            'user_id' => 'required|integer',
            'amount' => 'required|numeric|min:0.01',
            'received_at' => 'required|date',   // YYYY-MM-DD
            'method' => 'required|string',       // cash/upi/...
            'reference_no' => 'nullable|string|max:191',
        ]);

        $invoiceId = (int) $request->input('invoice_id');
        $userId    = (int) $request->input('user_id');
        $amount    = (float) $request->input('amount');
        $received  = $request->input('received_at');
        $method    = $request->input('method');
        $ref       = $request->input('reference_no');

        $inv = DB::table('invoices')->where('id', $invoiceId)->lockForUpdate()->first();
        if (!$inv) return response()->json(['message' => 'Invoice not found'], 404);

        if ((int)($inv->user_id ?? 0) !== $userId) {
            return response()->json(['message' => 'Invoice user mismatch'], 422);
        }

        $due = (float) ($inv->Unpaid_dues ?? 0);
        if ($due <= 0) return response()->json(['message' => 'Invoice already settled'], 422);

        // do not allow overpay (optional)
        if ($amount > $due) $amount = $due;

        return DB::transaction(function () use ($inv, $invoiceId, $userId, $amount, $received, $method, $ref, $request) {
            $invoiceNo = $inv->invoice_number ?: ($inv->number ?: ('INV-' . str_pad((string)$inv->id, 6, '0', STR_PAD_LEFT)));

            // insert payment (no invoice_id column -> store in meta)
            DB::table('payments')->insert([
                'party_type' => 'customer',
                'party_id' => $userId,
                'direction' => 'in',
                'received_at' => $received . ' 00:00:00',
                'method' => $method,
                'reference_no' => $ref,
                'amount' => $amount,
                'status' => 'posted',
                'created_by' => optional($request->user())->id,
                'meta' => json_encode([
                    'invoice_id' => $invoiceId,
                    'invoice_no' => $invoiceNo,
                    'order_id' => (int) ($inv->order_id ?? 0),
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $newDue = max(((float)$inv->Unpaid_dues) - $amount, 0);

            $newPaymentStatus = 'partial';
            if ($newDue <= 0.0001) $newPaymentStatus = 'paid';
            if ($amount <= 0) $newPaymentStatus = (string)($inv->payment_status ?? 'unpaid');

            $update = [
                'Unpaid_dues' => $newDue,
                'payment_status' => $newPaymentStatus,
                'updated_at' => now(),
            ];

            // optionally set invoice.status = paid if fully paid
            if ($newPaymentStatus === 'paid') {
                $update['status'] = 'paid';
            }

            DB::table('invoices')->where('id', $invoiceId)->update($update);

            return response()->json([
                'ok' => true,
                'invoice_id' => $invoiceId,
                'new_due' => $newDue,
                'payment_status' => $newPaymentStatus,
            ]);
        });
    }
    public function storeInwardPayment(Request $request)
    {
        $invoiceId = (int) $request->input('invoice_id');
        $amount    = (float) $request->input('amount');
        $date      = (string) $request->input('payment_date'); // YYYY-MM-DD
        $method    = (string) $request->input('method', 'cash');
        $note      = (string) $request->input('note', '');

        if ($invoiceId <= 0) return response()->json(['message' => 'invoice_id required'], 422);
        if ($amount <= 0) return response()->json(['message' => 'amount must be > 0'], 422);
        if ($date === '') return response()->json(['message' => 'payment_date required'], 422);

        $inv = DB::table('invoices')->where('id', $invoiceId)->first();
        if (!$inv) return response()->json(['message' => 'Invoice not found'], 404);

        $grand = (float) ($inv->grand_total ?? 0);

        // Rule: if grand_total is 0 => treat as paid (nothing to collect)
        if ($grand <= 0) {
            DB::table('invoices')->where('id', $invoiceId)->update([
                'Unpaid_dues'    => 0,
                'payment_status' => 'paid',
                'updated_at'     => now(),
            ]);
            return response()->json([
                'message' => 'Invoice already paid (grand_total = 0)',
                'invoice_id' => $invoiceId,
                'due_amount' => 0,
                'payment_status' => 'paid',
            ]);
        }

        $due = (float) ($inv->Unpaid_dues ?? 0);
        if ($due <= 0 && in_array(($inv->payment_status ?? ''), ['unpaid', 'partial'], true)) {
            $due = $grand; // safety for old data
        }

        // do not allow overpay
        if ($amount > $due) $amount = $due;

        $newDue = max($due - $amount, 0);

        DB::beginTransaction();
        try {
            // insert inward payment
            $pid = DB::table('inward_payments')->insertGetId([
                'order_id'      => null,
                'invoice_id'    => $invoiceId,
                'payment_date'  => $date,
                'amount'        => $amount,
                'due_amount'    => $newDue,
                'currency'      => $inv->currency ?? 'INR',
                'method'        => $method,
                'note'          => $note ?: null,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            // update invoice dues + payment status (derived from grand_total + newDue)
            if ($grand <= 0 || $newDue <= 0) {
                $paymentStatus = 'paid';
            } elseif ($newDue < $grand) {
                $paymentStatus = 'partial';
            } else {
                $paymentStatus = 'unpaid';
            }

            DB::table('invoices')->where('id', $invoiceId)->update([
                'Unpaid_dues'    => $newDue,
                'payment_status' => $paymentStatus,
                'updated_at'     => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Payment recorded',
                'payment_id' => $pid,
                'invoice_id' => $invoiceId,
                'due_amount' => $newDue,
                'payment_status' => $paymentStatus,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Automatic allocation: apply a single payment amount across unpaid/partial invoices (oldest-first)
     * for a given user.
     */
    public function storeInwardPaymentAuto(Request $request)
    {
        $userId  = (int) $request->input('user_id');
        $amount  = (float) $request->input('amount');
        $date    = (string) $request->input('payment_date'); // YYYY-MM-DD
        $method  = (string) $request->input('method', 'cash');
        $note    = (string) $request->input('note', '');

        if ($userId <= 0) return response()->json(['message' => 'user_id required'], 422);
        if ($amount <= 0) return response()->json(['message' => 'amount must be > 0'], 422);
        if ($date === '') return response()->json(['message' => 'payment_date required'], 422);

        return DB::transaction(function () use ($userId, $amount, $date, $method, $note) {

            $remaining = $amount;
            $allocations = [];

            // oldest-first (recommended). If you want latest-first change asc->desc
            $invoices = DB::table('invoices')
                ->whereNull('deleted_at')
                ->where('user_id', $userId)
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->where('grand_total', '>', 0)
                ->orderBy('invoice_date', 'asc')
                ->lockForUpdate()
                ->get();

            foreach ($invoices as $inv) {
                if ($remaining <= 0) break;

                $invoiceId = (int) $inv->id;
                $grand = (float) ($inv->grand_total ?? 0);
                if ($grand <= 0) {
                    // nothing to pay
                    DB::table('invoices')->where('id', $invoiceId)->update([
                        'Unpaid_dues'    => 0,
                        'payment_status' => 'paid',
                        'updated_at'     => now(),
                    ]);
                    continue;
                }

                // IMPORTANT: for "first time unpaid invoices" Unpaid_dues might be 0.
                // then treat due = grand_total.
                $due = (float) ($inv->Unpaid_dues ?? 0);
                if ($due <= 0) $due = $grand;
                if ($due <= 0) continue;

                $payNow = min($remaining, $due);
                if ($payNow <= 0) continue;

                $newDue = max($due - $payNow, 0);

                // status based on remaining dues
                if ($newDue <= 0.0001) {
                    $status = 'paid';
                } elseif ($newDue < $grand) {
                    $status = 'partial';
                } else {
                    $status = 'unpaid';
                }

                // store inward payment allocation row
                $pid = DB::table('inward_payments')->insertGetId([
                    'order_id'      => null,
                    'invoice_id'    => $invoiceId,
                    'payment_date'  => $date,
                    'amount'        => $payNow,
                    'due_amount'    => $newDue,
                    'currency'      => $inv->currency ?? 'INR',
                    'method'        => $method,
                    'note'          => ($note !== '' ? $note : null),
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);

                // ✅ update only unpaid_dues + payment_status
                DB::table('invoices')->where('id', $invoiceId)->update([
                    'Unpaid_dues'    => $newDue,
                    'payment_status' => $status,
                    'updated_at'     => now(),
                ]);

                $allocations[] = [
                    'payment_id' => (int) $pid,
                    'invoice_id' => $invoiceId,
                    'paid' => (float) $payNow,
                    'old_due' => (float) $due,
                    'new_due' => (float) $newDue,
                    'payment_status' => $status,
                ];

                $remaining -= $payNow;
            }

            return response()->json([
                'ok' => true,
                'user_id' => $userId,
                'requested_amount' => (float) $amount,
                'allocated_amount' => (float) ($amount - max($remaining, 0)),
                'remaining_amount' => (float) max($remaining, 0),
                'allocations' => $allocations,
            ]);
        });
    }
    /**
     * Submit payment with explicit allocations from UI.
     * Creates inward_payments rows ONLY here (on Submit).
     *
     * Request:
     * {
     *   "user_id": 10872,
     *   "amount": 3000,
     *   "payment_date": "2025-11-30",
     *   "method": "cash",
     *   "note": "optional",
     *   "allocations": [
     *     {"invoice_id": 11, "amount": 2000},
     *     {"invoice_id": 12, "amount": 1000}
     *   ]
     * }
     */
    public function storeInwardPaymentAllocations(
        Request $request,
        PaymentService $paymentService
    ) {
        $userId = (int) $request->input('user_id');
        $amount = (float) $request->input('amount');
        $date   = (string) $request->input('payment_date');
        $method = (string) $request->input('method', 'cash');
        $note   = (string) $request->input('note', '');

        $allocs = $request->input('allocations');

        if ($userId <= 0) {
            return response()->json([
                'message' => 'user_id required',
            ], 422);
        }

        if ($amount <= 0) {
            return response()->json([
                'message' => 'amount must be > 0',
            ], 422);
        }

        if ($date === '') {
            return response()->json([
                'message' => 'payment_date required',
            ], 422);
        }

        if (!is_array($allocs) || count($allocs) === 0) {
            return response()->json([
                'message' => 'allocations required',
            ], 422);
        }

        $result = $paymentService->recordAndAllocate(
            userId: $userId,
            amount: $amount,
            date: $date,
            method: $method,
            allocations: $allocs,
            note: $note !== '' ? $note : null,
            createdBy: optional($request->user())->id,
            referenceNo: null,
            source: 'invoice_allocation'
        );

        return response()->json($result);
    }
    public function initiateUpiPayment(Request $request)
    {
        $request->validate([
            'invoice_id' => 'required|integer',
        ]);

        $user = $request->user();
        $invoiceId = (int) $request->input('invoice_id');

        $invoice = DB::table('invoices')
            ->where('id', $invoiceId)
            ->whereNull('deleted_at')
            ->first();

        if (!$invoice) {
            return response()->json([
                'ok' => false,
                'message' => 'Invoice not found',
            ], 404);
        }

        if ((int) ($invoice->user_id ?? 0) !== (int) $user->id) {
            return response()->json([
                'ok' => false,
                'message' => 'Invoice does not belong to this user',
            ], 403);
        }

        $meta = json_decode($invoice->meta ?? '{}', true);

        if (!empty($meta['historical_import'])) {
            // Historical Jan-Jun invoice
            if (($invoice->payment_status ?? '') === 'paid') {
                $due = 0.0;
            } else {
                $due = max(
                    0,
                    round(
                        (float) ($meta['sheet_closing_dues'] ?? 0),
                        2
                    )
                );
            }
        } else {
            // July onwards generated invoice
            $due = max(
                0,
                round(
                    (float) ($invoice->Unpaid_dues ?? 0),
                    2
                )
            );
        }

        if ($due <= 0) {
            return response()->json([
                'ok' => false,
                'message' => 'Invoice already settled',
            ], 422);
        }

        $reference = 'DAYLI-' . $invoiceId . '-' . now()->format('YmdHis') . '-' . random_int(1000, 9999);

        $attemptId = DB::table('upi_payment_attempts')->insertGetId([
            'user_id' => $user->id,
            'invoice_id' => $invoiceId,
            'amount' => round($due, 2),
            'payment_reference' => $reference,
            'status' => 'initiated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $vpa = config('services.dayli_upi.vpa');
        $name = config('services.dayli_upi.name', 'Leela');

        $upiUri = 'upi://pay?' . http_build_query([
            'pa' => $vpa,
            'pn' => $name,
            'tr' => $reference,
            'tn' => 'Dayli Invoice ' . ($invoice->invoice_number ?? $invoiceId),
            'am' => number_format($due, 2, '.', ''),
            'cu' => 'INR',
        ]);

        return response()->json([
            'ok' => true,
            'attempt_id' => $attemptId,
            'invoice_id' => $invoiceId,
            'amount' => round($due, 2),
            'payment_reference' => $reference,
            'upi_uri' => $upiUri,
        ]);
    }
    public function submitUpiResult(Request $request)
    {
        $request->validate([
            'attempt_id'   => 'required|integer',
            'result_code'  => 'nullable|integer',
            'upi_response' => 'required|string',
        ]);

        $user = $request->user();

        $attemptId = (int) $request->input('attempt_id');
        $resultCode = $request->input('result_code');
        $rawResponse = trim((string) $request->input('upi_response'));

        $attempt = DB::table('upi_payment_attempts')
            ->where('id', $attemptId)
            ->where('user_id', $user->id)
            ->first();

        if (!$attempt) {
            return response()->json([
                'ok' => false,
                'message' => 'Payment attempt not found',
            ], 404);
        }

        // Already processed / already queued.
        if (
            in_array(
                $attempt->status,
                ['successful', 'failed', 'cancelled'],
                true
            )
        ) {
            return response()->json([
                'ok' => true,
                'status' => $attempt->status,
                'message' => 'Payment attempt already processed',
            ]);
        }

        /*
     * Typical UPI response:
     *
     * txnId=xxx&
     * txnRef=DAYLI-...&
     * Status=SUCCESS&
     * responseCode=00
     */
        parse_str($rawResponse, $parts);

        $upiStatus = strtoupper(
            trim(
                (string) (
                    $parts['Status']
                    ?? $parts['status']
                    ?? ''
                )
            )
        );

        $txnId = trim(
            (string) (
                $parts['txnId']
                ?? $parts['txnid']
                ?? ''
            )
        );

        $txnRef = trim(
            (string) (
                $parts['txnRef']
                ?? $parts['txnref']
                ?? ''
            )
        );

        $responseCode = trim(
            (string) (
                $parts['responseCode']
                ?? $parts['responsecode']
                ?? ''
            )
        );

        /*
     * Do NOT allocate here.
     *
     * Client SUCCESS only means:
     * "please verify this transaction".
     */
        if ($upiStatus !== 'SUCCESS') {
            return response()->json([
                'ok' => false,
                'message' => 'UPI payment is not reported as successful',
                'upi_status' => $upiStatus,
            ], 422);
        }

        /*
     * Returned transaction reference must match
     * the reference generated by Dayli.
     */
        if (
            $txnRef !== '' &&
            $txnRef !== $attempt->payment_reference
        ) {
            return response()->json([
                'ok' => false,
                'message' => 'Payment reference mismatch',
            ], 422);
        }

        return DB::transaction(function () use (
            $attempt,
            $attemptId,
            $rawResponse,
            $txnId,
            $responseCode,
            $resultCode
        ) {
            DB::table('upi_payment_attempts')
                ->where('id', $attemptId)
                ->update([
                    'upi_transaction_id' => ($txnId !== '' && strtolower($txnId) !== 'null')
                        ? $txnId
                        : null,

                    'response_code' =>
                    $responseCode !== ''
                        ? $responseCode
                        : null,

                    // Waiting for server-side verification.
                    'status' => 'pending',

                    'raw_response' => $rawResponse,

                    'updated_at' => now(),
                ]);

            /*
         * Prevent duplicate verification events.
         */
            $idempotencyKey =
                'upi.payment.verify:' . $attemptId;

            $alreadyQueued = DB::table('outbox_events')
                ->where(
                    'idempotency_key',
                    $idempotencyKey
                )
                ->exists();

            if (!$alreadyQueued) {
                DB::table('outbox_events')->insert([
                    'event_type' =>
                    'upi.payment.verify',

                    'aggregate_type' =>
                    'upi_payment_attempt',

                    'aggregate_id' =>
                    $attemptId,

                    'idempotency_key' =>
                    $idempotencyKey,

                    'payload' => json_encode([
                        'attempt_id' =>
                        $attemptId,

                        'user_id' =>
                        (int) $attempt->user_id,

                        'invoice_id' =>
                        (int) $attempt->invoice_id,

                        'amount' =>
                        round(
                            (float) $attempt->amount,
                            2
                        ),

                        'payment_reference' =>
                        $attempt->payment_reference,

                        'upi_transaction_id' => ($txnId !== '' &&
                            strtolower($txnId) !== 'null')
                            ? $txnId
                            : null,

                        'response_code' =>
                        $responseCode,

                        'result_code' =>
                        $resultCode,

                        'raw_response' =>
                        $rawResponse,
                    ]),

                    'status' => 'pending',
                    'priority' => 5,
                    'attempts' => 0,
                    'max_attempts' => 10,
                    'scheduled_at' => now(),
                    'notify_on' => 'failure',

                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return response()->json([
                'ok' => true,
                'status' => 'pending_verification',
                'message' =>
                'Payment submitted for verification',
            ]);
        });
    }
    public function pendingUpiVerifications(Request $request)
    {
        $rows = DB::table('upi_payment_attempts as a')
            ->join('users as u', 'u.id', '=', 'a.user_id')
            ->join('invoices as i', 'i.id', '=', 'a.invoice_id')
            ->select([
                'a.id as attempt_id',
                'a.user_id',
                'a.invoice_id',
                'a.amount',
                'a.payment_reference',
                'a.upi_transaction_id',
                'a.response_code',
                'a.status',
                'a.raw_response',
                'a.created_at',
                'a.updated_at',

                'i.invoice_number',
                'i.invoice_date',
                'i.Unpaid_dues',
                'i.payment_status',

                DB::raw(
                    'COALESCE(u.display_name, u.name, "") as customer_name'
                ),

                'u.phone',
            ])
            ->where('a.status', 'pending')
            ->whereNull('i.deleted_at')
            ->orderBy('a.created_at', 'asc')
            ->get()
            ->map(function ($row) {
                return [
                    'attempt_id' => (int) $row->attempt_id,
                    'user_id' => (int) $row->user_id,
                    'customer_name' => (string) $row->customer_name,
                    'phone' => (string) ($row->phone ?? ''),

                    'invoice_id' => (int) $row->invoice_id,
                    'invoice_number' => (string) ($row->invoice_number ?? ''),
                    'invoice_date' => (string) ($row->invoice_date ?? ''),

                    'amount' => round((float) $row->amount, 2),

                    'payment_reference' =>
                    (string) $row->payment_reference,

                    'upi_transaction_id' =>
                    $row->upi_transaction_id
                        ? (string) $row->upi_transaction_id
                        : null,

                    'response_code' =>
                    $row->response_code !== null
                        ? (string) $row->response_code
                        : null,

                    'status' => (string) $row->status,

                    'invoice_due' =>
                    round((float) ($row->Unpaid_dues ?? 0), 2),

                    'invoice_payment_status' =>
                    (string) ($row->payment_status ?? ''),

                    'submitted_at' =>
                    (string) ($row->updated_at ?? $row->created_at),
                ];
            });

        return response()->json([
            'ok' => true,
            'count' => $rows->count(),
            'data' => $rows->values(),
        ]);
    }

    public function approveUpiPayment(
        Request $request,
        int $attemptId,
        PaymentService $paymentService
    ) {
        $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $reviewer = $request->user();

        return DB::transaction(function () use (
            $attemptId,
            $paymentService,
            $reviewer,
            $request
        ) {
            $attempt = DB::table('upi_payment_attempts')
                ->where('id', $attemptId)
                ->lockForUpdate()
                ->first();

            if (!$attempt) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Payment attempt not found',
                ], 404);
            }

            // Prevent double approval / duplicate allocation.
            if ($attempt->status === 'successful') {
                return response()->json([
                    'ok' => true,
                    'message' => 'Payment already verified',
                    'payment_id' => $attempt->payment_id,
                ]);
            }

            if ($attempt->status !== 'pending') {
                return response()->json([
                    'ok' => false,
                    'message' => 'Only pending payments can be approved',
                ], 422);
            }

            $invoice = DB::table('invoices')
                ->where('id', $attempt->invoice_id)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (!$invoice) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Invoice not found',
                ], 404);
            }

            if ((int) $invoice->user_id !== (int) $attempt->user_id) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Invoice/customer mismatch',
                ], 422);
            }

            /*
         * Zone Manager has manually checked the real PhonePe /
         * bank transaction and approved it.
         *
         * Now use the common PaymentService.
         */
            $result = $paymentService->recordAndAllocate(
                userId: (int) $attempt->user_id,
                amount: (float) $attempt->amount,
                date: now()->toDateString(),
                method: 'upi',
                allocations: [
                    [
                        'invoice_id' => (int) $attempt->invoice_id,
                        'amount' => (float) $attempt->amount,
                    ],
                ],
                note: $request->input('note')
                    ?: 'UPI manually verified by Zone Manager',
                createdBy: $reviewer?->id,
                referenceNo: $attempt->upi_transaction_id
                    ?: $attempt->payment_reference,
                source: 'upi_manual_verification'
            );

            $paymentId = (int) ($result['payment_id'] ?? 0);

            if ($paymentId <= 0) {
                throw new \RuntimeException(
                    'Payment allocation did not create a payment record'
                );
            }

            DB::table('upi_payment_attempts')
                ->where('id', $attemptId)
                ->update([
                    'status' => 'successful',
                    'payment_id' => $paymentId,
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);

            return response()->json([
                'ok' => true,
                'message' => 'Payment verified and allocated successfully',
                'attempt_id' => $attemptId,
                'payment_id' => $paymentId,
                'allocation' => $result,
            ]);
        });
    }

    public function rejectUpiPayment(Request $request, int $attemptId)
    {
        $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($attemptId) {
            $attempt = DB::table('upi_payment_attempts')
                ->where('id', $attemptId)
                ->lockForUpdate()
                ->first();

            if (!$attempt) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Payment attempt not found',
                ], 404);
            }

            if ($attempt->status === 'successful') {
                return response()->json([
                    'ok' => false,
                    'message' => 'Successful payment cannot be rejected',
                ], 422);
            }

            if ($attempt->status !== 'pending') {
                return response()->json([
                    'ok' => false,
                    'message' => 'Only pending payments can be rejected',
                ], 422);
            }

            DB::table('upi_payment_attempts')
                ->where('id', $attemptId)
                ->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);

            return response()->json([
                'ok' => true,
                'message' => 'Payment verification rejected',
                'attempt_id' => $attemptId,
            ]);
        });
    }
}
