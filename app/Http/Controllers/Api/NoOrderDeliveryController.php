<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DraftOrderItem;
use App\Models\OutboxEvent;
use App\Models\Product;
use App\Models\SubChangeRequest;
use App\Models\User;
use App\Models\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NoOrderDeliveryController extends Controller
{
    public function options(Request $request)
    {
        $types = SubChangeRequest::query()
            ->whereNotNull('subscription_type_id')
            ->whereIn('status', ['approved', 'active'])
            ->select('subscription_type_id')
            ->distinct()
            ->get()
            ->map(function ($row) {
                $name = DB::table('subscription_types')
                    ->where('id', $row->subscription_type_id)
                    ->value('name');

                return [
                    'id' => (int) $row->subscription_type_id,
                    'name' => $name ?: ('Type #' . $row->subscription_type_id),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'subscription_types' => $types,
            'reasons' => [
                [
                    'key' => 'delivery_exec_unavailable',
                    'label' => 'Delivery Exec Unavailable',
                    'customer_reason' => 'delivery executive unavailable',
                ],
                [
                    'key' => 'vendor_not_supplies',
                    'label' => 'Vendor Not Supplies',
                    'customer_reason' => 'vendor not supplying',
                ],
                [
                    'key' => 'item_not_available',
                    'label' => 'Item Not Available',
                    'customer_reason' => 'item not available',
                ],
                [
                    'key' => 'transportation_issue',
                    'label' => 'Transportation Issue',
                    'customer_reason' => 'transportation issue',
                ],
            ],
            'transportation_impacts' => [
                ['key' => 'full_zone', 'label' => 'Full Zone'],
                ['key' => 'selected_items', 'label' => 'Selected Items'],
            ],
        ]);
    }

    public function products(Request $request)
    {
        $validated = $request->validate([
            'subscription_type_id' => 'required|integer',
        ]);

        $items = DraftOrderItem::query()
            ->with(['product', 'variant'])
            ->where('status', DraftOrderItem::STATUS_ACTIVE)
            ->whereHas('draftOrder', function ($q) use ($validated) {
                $q->whereHas('changeRequest', function ($cr) use ($validated) {
                    $cr->where('subscription_type_id', $validated['subscription_type_id'])
                        ->whereIn('status', ['approved', 'active']);
                });
            })
            ->whereNotNull('variant_id')
            ->get()
            ->map(function ($item) {
                $variantTitle = optional($item->variant)->title;

                return [
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'name' => $variantTitle,
                ];
            })
            ->unique('variant_id')
            ->values();

        return response()->json([
            'success' => true,
            'products' => $items,
        ]);
    }

    public function vendors(Request $request)
    {
        $validated = $request->validate([
            'subscription_type_id' => 'required|integer',
        ]);

        $vendorIds = DraftOrderItem::query()
            ->whereNotNull('vendor_id')
            ->where('status', DraftOrderItem::STATUS_ACTIVE)
            ->whereHas('draftOrder', function ($q) use ($validated) {
                $q->whereHas('changeRequest', function ($cr) use ($validated) {
                    $cr->where('subscription_type_id', $validated['subscription_type_id'])
                        ->whereIn('status', ['approved', 'active']);
                });
            })
            ->distinct()
            ->pluck('vendor_id');

        $vendors = User::query()
            ->whereIn('id', $vendorIds)
            ->select('id', 'first_name', 'last_name', 'display_name', 'phone')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->display_name
                        ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
                        ?: ($user->phone ?? 'Vendor #' . $user->id),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'vendors' => $vendors,
        ]);
    }

    public function vendorProducts(Request $request)
    {
        $validated = $request->validate([
            'subscription_type_id' => 'required|integer',
            'vendor_id' => 'required|integer',
        ]);

        $items = DraftOrderItem::query()
            ->with(['product', 'variant'])
            ->where('vendor_id', $validated['vendor_id'])
            ->where('status', DraftOrderItem::STATUS_ACTIVE)
            ->whereHas('draftOrder', function ($q) use ($validated) {
                $q->whereHas('changeRequest', function ($cr) use ($validated) {
                    $cr->where('subscription_type_id', $validated['subscription_type_id'])
                        ->whereIn('status', ['approved', 'active']);
                });
            })
            ->whereNotNull('variant_id')
            ->get()
            ->map(function ($item) {
                $variantTitle = optional($item->variant)->title;

                return [
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'name' => $variantTitle,
                ];
            })
            ->unique('variant_id')
            ->values();

        return response()->json([
            'success' => true,
            'products' => $items,
        ]);
    }

    public function deliveryExecs(Request $request)
    {
        $validated = $request->validate([
            'subscription_type_id' => 'required|integer',
            'zone_id' => 'nullable|integer',
        ]);

        $execs = UserService::query()
            ->with('user')
            ->where('role_name', 'workman')
            ->where('service_handle', 'delivery-boy')
            ->where('subscription_type_id', $validated['subscription_type_id'])
            ->where('status', 'approved')
            ->where('is_active', true)
            ->when($request->filled('zone_id'), function ($q) use ($validated) {
                $q->where('zone_id', $validated['zone_id']);
            })
            ->get()
            ->map(function ($service) {
                $user = $service->user;

                return [
                    'id' => $user?->id,
                    'name' => $user?->display_name
                        ?: trim(($user?->first_name ?? '') . ' ' . ($user?->last_name ?? ''))
                        ?: ($user?->phone ?? 'Delivery Exec #' . $service->user_id),
                    'zone_id' => $service->zone_id,
                ];
            })
            ->filter(fn($row) => !empty($row['id']))
            ->values();

        return response()->json([
            'success' => true,
            'delivery_execs' => $execs,
        ]);
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'zone_id' => 'required|integer',
            'subscription_type_id' => 'required|integer',

            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'days_count' => 'required|integer|min:1',

            'reason_key' => 'required|string',
            'reason' => 'required|string|max:255',

            'scope_label' => 'required|string|max:500',

            'template_key' => 'nullable|string',

            'vendor_id' => 'nullable|integer',
            'delivery_exec_id' => 'nullable|integer',
            'impact_level' => 'nullable|string',

            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer',

            'variant_ids' => 'nullable|array',
            'variant_ids.*' => 'integer',
        ]);

        OutboxEvent::create([
            'event_type' => 'zone.no_delivery.notify',
            'aggregate_type' => 'zone',
            'aggregate_id' => $validated['zone_id'],
            'status' => 'pending',
            'payload' => [
                'zone_id' => $validated['zone_id'],
                'subscription_type_id' => $validated['subscription_type_id'],

                // keep old key for existing handlers
                'delivery_date' => $validated['from_date'],

                'from_date' => $validated['from_date'],
                'to_date' => $validated['to_date'],
                'days_count' => $validated['days_count'],

                'reason_key' => $validated['reason_key'],
                'reason' => $validated['reason'],

                'scope_label' => $validated['scope_label'],

                // one Interakt template
                'template_key' => $validated['template_key'] ?? 'no_milk_2',

                'vendor_id' => $request->input('vendor_id'),
                'delivery_exec_id' => $request->input('delivery_exec_id'),
                'impact_level' => $request->input('impact_level'),

                'product_ids' => $request->input('product_ids', []),
                'variant_ids' => $request->input('variant_ids', []),
            ],
            'scheduled_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification queued successfully',
        ]);
    }
}
