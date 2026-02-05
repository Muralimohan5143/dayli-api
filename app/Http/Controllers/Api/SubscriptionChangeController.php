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
                Rule::requiredIf(
                    fn() =>
                    collect(request('items'))->contains(fn($i) => ($i['action'] ?? null) === 'pause')
                ),
            ],

            'items.*.price'            => 'nullable|numeric',
            'items.*.mrp'              => 'nullable|numeric',
            'items.*.cost_price'       => 'nullable|numeric',
            'items.*.discount_percent' => 'nullable|numeric',
            'items.*.discount_amount'  => 'nullable|numeric',

            'items.*.action'           => 'required|in:pause,cancel',
        ]);

        try {
            return DB::transaction(function () use ($validated) {

                foreach ($validated['items'] as $item) {

                    /** @var DraftOrderItem $original */
                    $original = DraftOrderItem::findOrFail((int) $item['original_item_id']);

                    // ✅ 1) Find latest row in this subscription chain (parent = latest row)
                    $current = DraftOrderItem::query()
                        ->where('draft_order_id', $original->draft_order_id)
                        ->where('variant_id', $original->variant_id)
                        ->where(function ($q) use ($original) {
                            // vendor_id NULL-safe
                            if (is_null($original->vendor_id)) {
                                $q->whereNull('vendor_id');
                            } else {
                                $q->where('vendor_id', $original->vendor_id);
                            }
                        })
                        ->orderByDesc('id')
                        ->firstOrFail();

                    // ✅ 2) Close previous row end_date = (new start_date - 1 day)
                    $newStart = \Carbon\Carbon::parse($item['start_date'])->startOfDay();
                    $prevEnd  = $newStart->copy()->subDay()->toDateString();

                    $current->update([
                        'end_date' => $prevEnd,
                    ]);

                    // ✅ 3) Create new row - sequential chain: original_item_id = current.id
                    DraftOrderItem::create([
                        'draft_order_id'   => $current->draft_order_id,
                        'original_item_id' => $current->id,                 // ✅ IMPORTANT CHANGE
                        'change_action'    => $item['action'],

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
                            'source'           => 'pause_cancel',
                        ],
                    ]);
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
}
