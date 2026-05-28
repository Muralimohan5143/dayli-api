<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\DraftOrderItem;
use Illuminate\Validation\Rule;

class SubscriptionChangeController extends Controller
{
    public function store(Request $request)
    {
        Log::info('SubscriptionChangeController START');
        Log::info('Payload received', $request->all());

        $user = $request->user();
        if (! $user) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $validated = $request->validate([
            'customer_id' => 'nullable|integer|exists:users,id',
            'items' => 'required|array|min:1',

            'items.*.original_item_id' => 'required|integer',
            'items.*.qty'              => 'required|numeric',
            'items.*.unit'             => 'required|string|max:20',

            'items.*.frequency_type'   => 'nullable|string|max:50',

            // ✅ Conditional dates
            'items.*.start_date' => [
                'nullable',
                'date',
                Rule::requiredIf(
                    fn() =>
                    collect(request('items'))->contains(fn($i) => ($i['action'] ?? null) === 'cancel')
                ),
            ],

            'items.*.end_date' => [
                'nullable',
                'date',
            ],

            'items.*.price'            => 'nullable|numeric',
            'items.*.mrp'              => 'nullable|numeric',
            'items.*.cost_price'       => 'nullable|numeric',
            'items.*.discount_percent' => 'nullable|numeric',
            'items.*.discount_amount'  => 'nullable|numeric',

            'items.*.action'           => 'required|in:pause,cancel',
        ]);

        $targetUserId = $this->resolveTargetUserId($request);

        try {
            return DB::transaction(function () use ($validated, $targetUserId, $user) {

                foreach ($validated['items'] as $item) {

                    /** @var DraftOrderItem $original */
                    $original = DraftOrderItem::findOrFail((int) $item['original_item_id']);

                    $belongs = DB::table('draft_order_items as doi')
                        ->join('draft_orders as dor', 'dor.id', '=', 'doi.draft_order_id')
                        ->join('sub_change_requests as scr', 'scr.id', '=', 'dor.change_request_id')
                        ->where('doi.id', $original->id)
                        ->where('scr.for_user_id', $targetUserId)
                        ->exists();

                    if (!$belongs) {
                        abort(403, 'Not allowed');
                    }

                    // ✅ 1) Find latest row in this subscription chain (parent = latest row)
                    $newStart = \Carbon\Carbon::parse($item['start_date'])->startOfDay();

                    $current = DraftOrderItem::query()
                        ->where('draft_order_id', $original->draft_order_id)
                        ->where('variant_id', $original->variant_id)
                        ->where(function ($q) use ($original) {
                            if (is_null($original->vendor_id)) {
                                $q->whereNull('vendor_id');
                            } else {
                                $q->where('vendor_id', $original->vendor_id);
                            }
                        })
                        ->whereDate('start_date', '<=', $newStart->toDateString())
                        ->whereIn('status', ['active', 'paused'])
                        ->orderByDesc('start_date')
                        ->orderByDesc('id')
                        ->firstOrFail();

                    $prevEnd = $newStart->copy()->subDay()->toDateString();

                    // // ✅ 2) Close previous row end_date = (new start_date - 1 day)
                    // $newStart = \Carbon\Carbon::parse($item['start_date'])->startOfDay();
                    // $prevEnd  = $newStart->copy()->subDay()->toDateString();

                    $this->cancelFuturePlannedRows($current, $newStart->toDateString(), (int) $user->id);

                    if (
                        !$current->start_date ||
                        \Carbon\Carbon::parse($current->start_date)->lte(\Carbon\Carbon::parse($prevEnd))
                    ) {
                        $current->update([
                            'end_date' => $prevEnd,
                            'closed_by_action' => $item['action'],
                        ]);
                    } else {
                        $current->update([
                            'status' => 'void',
                            'closed_by_action' => 'superseded_by_new_change',
                        ]);
                    }

                    // ✅ 3) Create new row - sequential chain: original_item_id = current.id
                    $newChangeRow = DraftOrderItem::create([
                        'draft_order_id'      => $current->draft_order_id,
                        'original_item_id'    => $current->id,
                        'supersedes_doi_id'  => $current->id,
                        'created_from_action' => $item['action'],
                        'change_action'      => $item['action'],
                        'product_id'       => $current->product_id,
                        'variant_id'       => $current->variant_id,
                        'vendor_id'        => $current->vendor_id,
                        'qty'              => 0,
                        'unit'             => $item['unit'],
                        'frequency_type'   => null,

                        'price_snapshot'   => $item['price'] ?? $current->price_snapshot,

                        'start_date'       => $item['start_date'],
                        'end_date'         => $item['action'] === 'pause'
                            ? $item['end_date']
                            : null,

                        // ✅ status must match action
                        'status' => $item['action'] === 'pause' ? 'paused' : 'cancelled',

                        'meta' => [
                            'mrp'              => $item['mrp'] ?? 0,
                            'cost_price'       => $item['cost_price'] ?? 0,
                            'discount_amount'  => $item['discount_amount'] ?? 0,
                            'discount_percent' => $item['discount_percent'] ?? 0,
                            'source'           => !empty($validated['customer_id']) ? 'operator_pause_cancel' : 'pause_cancel',
                            'for_user_id'      => $targetUserId,
                            'by_user_id'       => $user->id,
                            'actor_role'       => $user->getRoleNames()->first(),
                        ],
                    ]);
                    // ✅ If pause has end_date, auto create resume row from next day
                    if (
                        ($item['action'] ?? null) === 'pause'
                        && !empty($item['end_date'])
                    ) {
                        $resumeStart = \Carbon\Carbon::parse($item['end_date'])
                            ->addDay()
                            ->toDateString();

                        DraftOrderItem::create([
                            'draft_order_id'      => $current->draft_order_id,
                            'original_item_id'    => $newChangeRow->id,
                            'supersedes_doi_id'  => $newChangeRow->id,
                            'created_from_action' => 'auto_resume_after_pause',
                            'change_action'      => 'resume',
                            'product_id'       => $current->product_id,
                            'variant_id'       => $current->variant_id,
                            'vendor_id'        => $current->vendor_id,

                            'qty'              => $original->qty,
                            'unit'             => $original->unit,
                            'frequency_type'   => $original->frequency_type,

                            'price_snapshot'   => $original->price_snapshot,
                            'start_date'       => $resumeStart,
                            'end_date'         => null,
                            'status'           => 'active',

                            'meta' => [
                                'source'       => !empty($validated['customer_id']) ? 'operator_auto_resume_after_pause' : 'auto_resume_after_pause',
                                'for_user_id'  => $targetUserId,
                                'by_user_id'   => $user->id,
                                'pause_row_id' => $newChangeRow->id,
                            ],
                        ]);
                    }
                }

                return response()->json([
                    'ok'      => true,
                    'message' => 'Subscription updated successfully',
                ], 200);
            });
        } catch (\Throwable $e) {
            Log::error('SubscriptionChangeController ERROR', [
                'msg' => $e->getMessage(),
            ]);

            return response()->json([
                'ok'      => false,
                'message' => 'Server error while recording change',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    private function resolveTargetUserId(Request $request): int
    {
        $authUser = $request->user();
        if (!$authUser) abort(401, 'Unauthenticated');

        $customerId = $request->input('customer_id') ?: $request->query('customer_id');

        if ($customerId) {
            if (!$authUser->hasAnyRole(['admin', 'zone-manager', 'workman-delivery-boy'])) {
                abort(403, 'Not allowed to manage customer subscriptions');
            }

            return (int) $customerId; // for_user_id
        }

        return (int) $authUser->id;
    }

    private function cancelFuturePlannedRows(DraftOrderItem $current, string $newStart, int $newActionByUserId): void
    {
        DraftOrderItem::query()
            ->where('draft_order_id', $current->draft_order_id)
            ->where('product_id', $current->product_id)
            ->where('variant_id', $current->variant_id)
            ->where(function ($q) use ($current) {
                if (is_null($current->vendor_id)) {
                    $q->whereNull('vendor_id');
                } else {
                    $q->where('vendor_id', $current->vendor_id);
                }
            })
            ->whereDate('start_date', '>=', $newStart)
            ->whereIn('status', ['active', 'paused'])
            ->update([
                'status' => 'void',
                'closed_by_action' => 'superseded_by_new_change',
                'updated_at' => now(),
            ]);
    }
}
