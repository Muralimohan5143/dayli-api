<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\DraftOrderItem;

class SubscriptionsApiController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

            $tab = $request->query('tab', 'active'); // active | paused | cancel


            if (!in_array($tab, ['active', 'paused', 'cancel'], true)) {
                $tab = 'active';
            }
            // 🔴 TEMPORARY: FORCE DATE FOR TESTING
            $asOf = $request->query('date')
                ? \Carbon\Carbon::parse($request->query('date'))->startOfDay()
                : \Carbon\Carbon::today();

            $asOfDate = $asOf->toDateString();

            $latestIds = DB::table('draft_order_items as doi')
                ->join('draft_orders as dor', 'dor.id', '=', 'doi.draft_order_id')
                ->join('sub_change_requests as scr', 'scr.id', '=', 'dor.change_request_id')
                ->where('scr.for_user_id', $user->id)
                ->where('dor.status', 'active')

                // ✅ start_date can be NULL OR <= asOf
                // ->where(function ($q) use ($asOfDate) {
                //     $q->whereNull('doi.start_date')
                //         ->orWhereDate('doi.start_date', '<=', $asOfDate);
                // })

                // ✅ end_date can be NULL OR >= asOf
                ->where(function ($q) use ($asOfDate) {
                    $q->whereNull('doi.end_date')
                        ->orWhereDate('doi.end_date', '>=', $asOfDate);
                })

                ->selectRaw('MAX(doi.id) as id')
                ->groupBy(
                    'doi.draft_order_id',
                    'doi.variant_id',
                    DB::raw('COALESCE(doi.vendor_id,0)')
                );

            $items = DraftOrderItem::query()
                ->with('product')
                ->whereIn('id', $latestIds)
                ->get();

            $groups = [];

            foreach ($items as $item) {
                $status = $item->status ?? 'active';

                // ✅ Tab filtering
                if ($tab === 'active' && $status !== 'active') continue;
                if ($tab === 'paused' && $status !== 'paused') continue;
                if ($tab === 'cancel' && $status !== 'cancelled') continue;

                $typeName = optional($item->product)->product_type ?? 'Others';

                if (!isset($groups[$typeName])) {
                    $groups[$typeName] = [
                        'type_name'     => $typeName,
                        'product_count' => 0,
                        'total_qty'     => 0.0,
                        'items'         => [],
                    ];
                }

                $groups[$typeName]['product_count']++;
                $groups[$typeName]['total_qty'] += (float) $item->qty;

                $startDate = $item->start_date ? $item->start_date->format('Y-m-d') : null;
                $endDate   = $item->end_date   ? $item->end_date->format('Y-m-d')   : null;

                $groups[$typeName]['items'][] = [
                    'id'           => $item->id,
                    'product_id'   => $item->product_id,
                    'variant_id'   => $item->variant_id,
                    'product_name' => optional($item->product)->title,
                    'qty'          => (float) $item->qty,
                    'unit'         => $item->unit,
                    'vendor_id'    => $item->vendor_id,
                    'image'        => optional($item->product)->img_src,
                    'mrp'          => (float) ($item->price_snapshot ?? 0),

                    'frequency'    => $item->frequency_type,
                    'start_date'   => $startDate,
                    'end_date'     => $endDate,
                    'status'       => $status,
                ];
            }

            return response()->json(['data' => array_values($groups)]);
        } catch (\Throwable $e) {
            Log::error('Subscriptions API Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to fetch subscriptions',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ Helper: Ensure this DraftOrderItem belongs to current customer
     */
    private function assertOwnership(Request $request, DraftOrderItem $item): void
    {
        $user = $request->user();
        if (!$user) abort(401, 'Unauthenticated');

        $ok = DB::table('draft_order_items as doi')
            ->join('draft_orders as dor', 'dor.id', '=', 'doi.draft_order_id')
            ->join('sub_change_requests as scr', 'scr.id', '=', 'dor.change_request_id')
            ->where('doi.id', $item->id)
            ->where('scr.for_user_id', $user->id)
            ->exists();

        if (!$ok) abort(403, 'Not allowed');
    }

    /**
     * ✅ Helper: root id for chain
     */
    private function rootId(DraftOrderItem $item): int
    {
        return (int) ($item->original_item_id ?: $item->id);
    }


    private function effectiveStartDate(?string $startDate = null): string
    {
        // If UI gives date (cancel), use it
        if ($startDate) {
            return \Carbon\Carbon::parse($startDate)->toDateString();
        }

        // If UI doesn't give (pause/resume/restart), start from TOMORROW
        return now()->addDay()->toDateString();
    }

    private function closePreviousRow(DraftOrderItem $old, string $newStartDate): void
    {
        $newStart = \Carbon\Carbon::parse($newStartDate)->startOfDay();
        $prevEnd  = $newStart->copy()->subDay()->toDateString();

        // safety: don't go before old start_date
        if ($old->start_date) {
            $oldStart = \Carbon\Carbon::parse($old->start_date)->toDateString();
            if ($prevEnd < $oldStart) $prevEnd = $oldStart;
        }

        // ✅ ALWAYS update (your rule)
        $old->update(['end_date' => $prevEnd]);
    }


    /**
     * ✅ Create a new row in same chain (pause/cancel/resume)
     */
    private function createChainRow(DraftOrderItem $base, array $overrides): DraftOrderItem
    {
        $rootId = $this->rootId($base);

        // ✅ use meta (your real column)
        $meta = is_array($base->meta) ? $base->meta : (json_decode($base->meta ?? '[]', true) ?: []);
        $meta['snapshot'] = $meta['snapshot'] ?? [
            'qty'       => (float) $base->qty,
            'frequency' => $base->frequency_type,
            'unit'      => $base->unit,
        ];

        $data = [
            'draft_order_id'   => $base->draft_order_id,
            'original_item_id' => $base->id,

            'change_action'    => $base->change_action,
            'status'           => $base->status ?? 'active',

            'product_id'       => $base->product_id,
            'variant_id'       => $base->variant_id,
            'vendor_id'        => $base->vendor_id,

            'qty'              => $base->qty,
            'unit'             => $base->unit,
            'frequency_type'   => $base->frequency_type,

            'start_date'       => $base->start_date,
            'end_date'         => $base->end_date,

            'price_snapshot'   => $base->price_snapshot,
            'meta'             => $meta,
        ];

        // apply overrides
        foreach ($overrides as $k => $v) {
            $data[$k] = $v;
        }

        // ✅ HARD GUARANTEE: status must match change_action
        if (!empty($data['change_action'])) {
            $action = $data['change_action'];
            if ($action === 'pause') {
                $data['status'] = 'paused';
            } elseif ($action === 'cancel') {
                $data['status'] = 'cancelled';
            } elseif (in_array($action, ['resume', 'restart'], true)) {
                $data['status'] = 'active';
            }
        }

        return DraftOrderItem::create($data);
    }


    /**
     * ✅ Pause: creates NEW row (status=paused)
     * UI requirement: Pause model only End Date is mandatory
     */
    public function pause(Request $request, DraftOrderItem $item)
    {
        $this->assertOwnership($request, $item);

        $request->validate([
            'end_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        DB::transaction(function () use ($request, $item, &$new) {

            // ✅ new row starts TOMORROW (because Pause modal doesn't ask start_date)
            $newStart = $this->effectiveStartDate(null);

            // ✅ close old row: end_date = newStart - 1
            $this->closePreviousRow($item, $newStart);

            // ✅ create new paused row
            $new = $this->createChainRow($item, [
                'change_action'  => 'pause',
                'qty'            => 0,
                'frequency_type' => null,
                'start_date'     => $newStart,
                'end_date'       => $request->input('end_date'),
                'status'         => 'paused',
            ]);
        });

        return response()->json(['ok' => true, 'item' => $new]);
    }


    /**
     * ✅ Cancel: creates NEW row (status=cancelled)
     * UI requirement: Cancel model only Start Date is mandatory
     */
    public function cancel(Request $request, DraftOrderItem $item)
    {
        $this->assertOwnership($request, $item);

        $request->validate([
            'start_date' => ['required', 'date'],
        ]);

        DB::transaction(function () use ($request, $item, &$new) {

            // ✅ cancel row starts from given start_date (Cancel modal asks start_date)
            $newStart = $this->effectiveStartDate($request->input('start_date'));

            // ✅ close old row: end_date = newStart - 1
            $this->closePreviousRow($item, $newStart);

            // ✅ create new cancelled row
            $new = $this->createChainRow($item, [
                'change_action'  => 'cancel',
                'qty'            => 0,
                'frequency_type' => null,
                'start_date'     => $newStart,
                'end_date'       => null,
                'status'         => 'cancelled',
            ]);
        });

        return response()->json(['ok' => true, 'item' => $new]);
    }


    /**
     * ✅ Resume: creates NEW row (status=active)
     * Used from Paused tab (More -> Resume) and also from Cancel tab if you want.
     */
    public function resume(Request $request, DraftOrderItem $item)
    {
        $this->assertOwnership($request, $item);
        $request->validate([
            'qty'            => ['nullable', 'numeric', 'min:0'],
            'unit'           => ['nullable', 'string', 'max:20'],
            'frequency_type' => ['nullable', 'string', 'max:50'],
            'start_date'     => ['nullable', 'date'],
            'end_date'       => ['nullable', 'date'],
        ]);

        $meta = is_array($item->meta) ? $item->meta : (json_decode($item->meta ?? '[]', true) ?: []);
        $snap = $meta['snapshot'] ?? null;

        $lastActive = DraftOrderItem::query()
            ->where('draft_order_id', $item->draft_order_id)
            ->where('product_id', $item->product_id)
            ->where('variant_id', $item->variant_id)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();

        // defaults (snapshot -> last active -> fallback)
        $defaultQty  = $snap['qty'] ?? ($lastActive ? (float)$lastActive->qty : 1);
        $defaultFreq = $snap['frequency'] ?? ($lastActive ? $lastActive->frequency_type : 'daily');
        $defaultUnit = $snap['unit'] ?? ($lastActive ? $lastActive->unit : $item->unit);

        // ✅ take from UI if provided, else defaults
        $qtyFromUi  = $request->input('qty');
        $unitFromUi = $request->input('unit');
        $freqFromUi = $request->input('frequency_type');

        // ✅ START DATE: if UI sends it, use it. else tomorrow.
        $startFromUi = $request->input('start_date');
        $newStart = $this->effectiveStartDate($startFromUi);

        // ✅ END DATE: normally null for resume; if UI sends, accept it
        $endFromUi = $request->input('end_date');

        $qty  = ($qtyFromUi !== null) ? (float)$qtyFromUi : (float)$defaultQty;
        $unit = ($unitFromUi !== null && $unitFromUi !== '') ? $unitFromUi : $defaultUnit;
        $freq = ($freqFromUi !== null && $freqFromUi !== '') ? $freqFromUi : $defaultFreq;

        DB::transaction(function () use ($item, $qty, $freq, $unit, $newStart, $endFromUi, &$new) {

            // ✅ close paused row up to (newStart - 1)
            $this->closePreviousRow($item, $newStart);

            // ✅ create new ACTIVE row with UI values
            $new = $this->createChainRow($item, [
                'change_action'  => 'resume',
                'qty'            => $qty,
                'unit'           => $unit,
                'frequency_type' => $freq,
                'start_date'     => $newStart,
                'end_date'       => $endFromUi,   // usually null
                'status'         => 'active',
            ]);
        });

        return response()->json(['ok' => true, 'item' => $new]);
    }

    public function raiseDispute(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $data = $request->validate([
            'subscription_item_id' => 'nullable|integer',
            'order_id'             => 'nullable|integer',
            'order_item_id'        => 'nullable|integer',

            'expected_product_id'  => 'nullable|integer',
            'expected_variant_id'  => 'nullable|integer',
            'expected_qty' => 'required|numeric|min:0',

            'dispute_type'         => 'required|in:wrong_product,quantity_mismatch,not_delivered,quality_issue,other',

            // delivered items (JSON array)
            'delivered_items'      => 'nullable|array',
            'delivered_items.*.p_id' => 'required|integer',
            'delivered_items.*.v_id' => 'nullable|integer',
            'delivered_items.*.qty'  => 'required|numeric|min:0.01',

            'delivered_qty'        => 'nullable|numeric|min:0.01',


            'notes'                => 'nullable|string|max:2000',
            'dispute_date'         => 'nullable|date',
        ]);

        // ✅ basic rule: wrong_product must have delivered_product
        if (($data['dispute_type'] ?? '') === 'wrong_product' && empty($data['delivered_items'])) {
            return response()->json([
                'message' => 'delivered_items is required for wrong_product dispute'
            ], 422);
        }


        $id = DB::table('disputes')->insertGetId([
            'user_id'             => (int) $user->id,

            'subscription_item_id' => $data['subscription_item_id'] ?? null,
            'order_id'            => $data['order_id'] ?? null,
            'order_item_id'       => $data['order_item_id'] ?? null,

            'expected_product_id' => $data['expected_product_id'] ?? null,
            'expected_variant_id' => $data['expected_variant_id'] ?? null,
            'expected_qty'        => (float) $data['expected_qty'],

            'dispute_type'        => $data['dispute_type'],
            'delivered_items'     => isset($data['delivered_items']) ? json_encode($data['delivered_items']) : null,
            'delivered_qty'       => isset($data['delivered_qty']) ? (float) $data['delivered_qty'] : null,


            'notes'               => $data['notes'] ?? null,
            'status'              => 'open',
            'dispute_date'        => $data['dispute_date'] ?? now()->toDateString(),
            'created_by'          => (int) $user->id,

            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        return response()->json([
            'message' => 'Dispute raised',
            'id' => (int) $id,
        ], 201);
    }



    public function restart(Request $request, DraftOrderItem $item)
    {
        $this->assertOwnership($request, $item);

        if (($item->status ?? '') !== 'cancelled') {
            return response()->json([
                'ok' => false,
                'message' => 'Restart allowed only for cancelled items',
            ], 422);
        }

        // ✅ same validation style as resume
        $request->validate([
            'qty'            => ['nullable', 'numeric', 'min:0'],
            'unit'           => ['nullable', 'string', 'max:20'],
            'frequency_type' => ['nullable', 'string', 'max:50'],
            'start_date'     => ['nullable', 'date'],
            'end_date'       => ['nullable', 'date'],
        ]);

        $meta = is_array($item->meta) ? $item->meta : (json_decode($item->meta ?? '[]', true) ?: []);
        $snap = $meta['snapshot'] ?? null;

        $lastActive = DraftOrderItem::query()
            ->where('draft_order_id', $item->draft_order_id)
            ->where('product_id', $item->product_id)
            ->where('variant_id', $item->variant_id)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();

        // defaults (snapshot -> last active -> fallback)
        $defaultQty  = $snap['qty'] ?? ($lastActive ? (float)$lastActive->qty : 1);
        $defaultFreq = $snap['frequency'] ?? ($lastActive ? $lastActive->frequency_type : 'daily');
        $defaultUnit = $snap['unit'] ?? ($lastActive ? $lastActive->unit : $item->unit);

        // ✅ take from UI if provided, else defaults
        $qtyFromUi  = $request->input('qty');
        $unitFromUi = $request->input('unit');
        $freqFromUi = $request->input('frequency_type');

        // ✅ START DATE: if UI sends it, use it. else tomorrow.
        $startFromUi = $request->input('start_date');
        $newStart = $this->effectiveStartDate($startFromUi);

        // ✅ END DATE: normally null for restart; if UI sends, accept it
        $endFromUi = $request->input('end_date');

        $qty  = ($qtyFromUi !== null) ? (float)$qtyFromUi : (float)$defaultQty;
        $unit = ($unitFromUi !== null && $unitFromUi !== '') ? $unitFromUi : $defaultUnit;
        $freq = ($freqFromUi !== null && $freqFromUi !== '') ? $freqFromUi : $defaultFreq;

        DB::transaction(function () use ($item, $qty, $freq, $unit, $newStart, $endFromUi, &$new) {

            // ✅ close cancelled row up to (newStart - 1)
            $this->closePreviousRow($item, $newStart);

            // ✅ create new ACTIVE row with UI values
            $new = $this->createChainRow($item, [
                'change_action'  => 'restart',
                'qty'            => $qty,
                'unit'           => $unit,
                'frequency_type' => $freq,
                'start_date'     => $newStart,
                'end_date'       => $endFromUi, // usually null
                'status'         => 'active',
            ]);
        });

        return response()->json(['ok' => true, 'item' => $new]);
    }
}
