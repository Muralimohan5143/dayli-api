<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        if ($userId <= 0) return response()->json(['message' => 'user_id required'], 422);

        $u = DB::table('users')
            ->select('id', DB::raw('COALESCE(display_name, name, "") as name'), 'phone', 'address', 'nagar', 'pincode')
            ->where('id', $userId)
            ->first();

        if (!$u) return response()->json(['message' => 'User not found'], 404);

        // ✅ Only invoices that actually have due > 0
        $invoices = DB::table('invoices')
            ->whereNull('deleted_at')
            ->where('user_id', $userId)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->where('grand_total', '>', 0)
            ->orderBy('invoice_date', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($i) {
                $invoiceNo = $i->invoice_number ?: ($i->number ?: ('INV-' . str_pad((string)$i->id, 6, '0', STR_PAD_LEFT)));

                $grand = (float) ($i->grand_total ?? 0);
                $due   = (float) ($i->Unpaid_dues ?? 0);

                // ✅ your rule: only unpaid_dues matters, but old data can be 0
                // so treat due as grand_total if invoice is unpaid/partial and unpaid_dues is 0
                if ($due <= 0) $due = $grand;

                return [
                    'id' => (int) $i->id,
                    'user_id' => $i->user_id ? (int)$i->user_id : null,
                    'invoice_no' => (string) $invoiceNo,
                    'invoice_date' => (string) ($i->invoice_date ?? ''),
                    'order_start_date' => (string) ($i->order_start_date ?? ''),
                    'order_end_date' => (string) ($i->order_end_date ?? ''),
                    'grand_total' => $grand,
                    'due_amount' => max($due, 0),
                    'payment_status' => (string) ($i->payment_status ?? 'unpaid'),
                ];
            });


        $dueTotal = (float) $invoices->sum('due_amount');

        $address = trim((string)($u->address ?? ''));
        if (($u->nagar ?? '') !== '') $address = trim($address . ' ' . $u->nagar);
        if (($u->pincode ?? '') !== '') $address = trim($address . ' ' . $u->pincode);

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
                'order_id'      => $inv->order_id,
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
                    'order_id'      => (int) ($inv->order_id ?? 0),
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
    public function storeInwardPaymentAllocations(Request $request)
    {
        $userId = (int) $request->input('user_id');
        $amount = (float) $request->input('amount');
        $date   = (string) $request->input('payment_date'); // YYYY-MM-DD
        $method = (string) $request->input('method', 'cash');
        $note   = (string) $request->input('note', '');

        $allocs = $request->input('allocations');

        if ($userId <= 0) return response()->json(['message' => 'user_id required'], 422);
        if ($amount <= 0) return response()->json(['message' => 'amount must be > 0'], 422);
        if ($date === '') return response()->json(['message' => 'payment_date required'], 422);
        if (!is_array($allocs) || count($allocs) === 0) {
            return response()->json(['message' => 'allocations required'], 422);
        }

        return DB::transaction(function () use ($userId, $amount, $date, $method, $note, $allocs) {

            $remaining = $amount;
            $results = [];

            foreach ($allocs as $row) {
                if ($remaining <= 0) break;

                $invoiceId = (int) ($row['invoice_id'] ?? 0);
                $wantPay   = (float) ($row['amount'] ?? 0);

                if ($invoiceId <= 0 || $wantPay <= 0) continue;

                // lock invoice
                $inv = DB::table('invoices')
                    ->where('id', $invoiceId)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if (!$inv) continue;

                // safety: must belong to same user
                if ((int)($inv->user_id ?? 0) !== $userId) continue;

                $grand = (float) ($inv->grand_total ?? 0);

                // if grand_total 0 => mark paid and skip
                if ($grand <= 0) {
                    DB::table('invoices')->where('id', $invoiceId)->update([
                        'Unpaid_dues'    => 0,
                        'payment_status' => 'paid',
                        'updated_at'     => now(),
                    ]);
                    continue;
                }

                // due calc (same rule you already used)
                $due = (float) ($inv->Unpaid_dues ?? 0);
                if ($due <= 0) $due = $grand;
                if ($due <= 0) continue;

                // cannot exceed remaining + cannot exceed due
                $payNow = min($wantPay, $remaining, $due);
                if ($payNow <= 0) continue;

                $newDue = max($due - $payNow, 0);

                // derive status
                if ($newDue <= 0.0001) {
                    $status = 'paid';
                } elseif ($newDue < $grand) {
                    $status = 'partial';
                } else {
                    $status = 'unpaid';
                }

                // create inward payment row (only on submit)
                $pid = DB::table('inward_payments')->insertGetId([
                    'order_id'      => (int) ($inv->order_id ?? 0),
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

                // update invoice
                DB::table('invoices')->where('id', $invoiceId)->update([
                    'Unpaid_dues'    => $newDue,
                    'payment_status' => $status,
                    'updated_at'     => now(),
                ]);

                $results[] = [
                    'payment_id' => (int) $pid,
                    'invoice_id' => $invoiceId,
                    'paid'       => (float) $payNow,
                    'old_due'    => (float) $due,
                    'new_due'    => (float) $newDue,
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
                'allocations' => $results,
            ]);
        });
    }
}
