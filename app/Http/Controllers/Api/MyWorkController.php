<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryTask;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\SubscriptionType; // if you have model, else we use DB table

class MyWorkController extends Controller
{
    // GET /api/my-work?status=today|pending|completed
    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status', 'today');

        $tasks = DeliveryTask::query()
            ->where('delivery_exec_id', $user->id)
            ->where(function ($q) use ($status) {
                if ($status === 'completed') {
                    $q->where('status', 'completed');
                } elseif ($status === 'pending') {
                    $q->whereIn('status', ['pending']);
                } else {
                    // today
                    $q->whereIn('status', ['today', 'in_progress']);
                }
            })
            ->orderBy('start_date')
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'delivery_task' => $t->delivery_task,
                'status' => $t->status,
                'zone_id' => $t->zone_id,
                'start_date' => optional($t->start_date)->toDateString(),
                'end_date' => optional($t->end_date)->toDateString(),
            ]);

        return response()->json(['data' => $tasks]);
    }

    // POST /api/my-work/{id}/start
    public function start(Request $request, $id)
    {
        $task = DeliveryTask::where('id', $id)
            ->where('delivery_exec_id', $request->user()->id)
            ->firstOrFail();

        if ($task->status === 'completed') {
            return response()->json(['message' => 'Already completed'], 422);
        }

        $task->status = 'in_progress';
        $task->start_date = Carbon::today();
        $task->save();

        return response()->json(['message' => 'Task started']);
    }

    // POST /api/my-work/{id}/complete
    public function complete(Request $request, $id)
    {
        $task = DeliveryTask::where('id', $id)
            ->where('delivery_exec_id', $request->user()->id)
            ->firstOrFail();

        $task->status = 'completed';
        $task->end_date = Carbon::today();
        $task->save();

        return response()->json(['message' => 'Task completed']);
    }

    // GET /api/my-work/summary
    public function summary(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $zoneId = $user->zone_id;
        if (!$zoneId) {
            return response()->json(['message' => 'No zone assigned for this user'], 422);
        }

        $today = Carbon::today()->toDateString();

        // ✅ Count active draft_order_items for today, grouped by subscription_type_id
        $rows = DB::table('draft_order_items as doi')
            ->join('draft_orders as do', 'do.id', '=', 'doi.draft_order_id')
            ->join('sub_change_requests as scr', 'scr.id', '=', 'do.change_request_id')
            ->where('scr.zone_id', $zoneId)
            ->where('scr.status', 'approved') // only approved subs
            ->where(function ($q) { // active items
                $q->whereNull('doi.status')->orWhere('doi.status', 'active');
            })
            ->whereDate('doi.start_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('doi.end_date')->orWhereDate('doi.end_date', '>=', $today);
            })
            ->groupBy('scr.subscription_type_id')
            ->selectRaw('scr.subscription_type_id, COUNT(*) as today_count')
            ->get();

        // ✅ Pull subscription type names (table: subscription_types)
        $typeNames = DB::table('subscription_types')
            ->select('id', 'name')
            ->get()
            ->keyBy('id');

        $types = $rows->map(function ($r) use ($typeNames) {
            $name = $typeNames[$r->subscription_type_id]->name ?? ('Type ' . $r->subscription_type_id);

            return [
                'subscription_type_id' => (int) $r->subscription_type_id,
                'title' => $name,
                'today' => (int) $r->today_count,
                'pending' => 0,
                'completed' => 0,
            ];
        })->values();

        return response()->json([
            'zone_id' => (int) $zoneId,
            'date' => $today,
            'types' => $types,
        ]);
    }

    // GET /api/my-work/orders?type=milk&status=today
    // GET /api/my-work/orders?type=milk&status=today
    public function orders(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $zoneId = $user->zone_id;
        if (!$zoneId) {
            return response()->json(['message' => 'No zone assigned'], 422);
        }

        $type = strtolower($request->query('type', 'milk'));
        $today = now()->toDateString();

        // find subscription_type_id (Milk)
        $subType = DB::table('subscription_types')
            ->whereRaw('LOWER(name) LIKE ?', ["%{$type}%"])
            ->first();

        if (!$subType) {
            return response()->json(['data' => []]);
        }

        $items = DB::table('draft_order_items as doi')
            ->join('draft_orders as do', 'do.id', '=', 'doi.draft_order_id')
            ->join('sub_change_requests as scr', 'scr.id', '=', 'do.change_request_id')
            ->join('users as u', 'u.id', '=', 'scr.for_user_id')
            ->where('scr.zone_id', $zoneId)
            ->where('scr.subscription_type_id', $subType->id)
            ->where('scr.status', 'approved')
            ->where(function ($q) {
                $q->whereNull('doi.status')->orWhere('doi.status', 'active');
            })
            ->whereDate('doi.start_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('doi.end_date')->orWhereDate('doi.end_date', '>=', $today);
            })
            ->orderBy('u.name')
            ->select([
                'doi.id',
                'u.name as customer_name',
                'u.phone as customer_phone',
                'doi.qty',
                'doi.unit',
                'doi.product_id',
                'doi.variant_id',
            ])
            ->get();

        return response()->json([
            'zone_id' => $zoneId,
            'date' => $today,
            'data' => $items,
        ]);
    }
    public function allProducts()
    {
        $products = DB::table('products as p')
            ->select(
                'p.product_id as id',
                'p.title'
            )
            ->orderBy('p.title')
            ->get();

        $variants = DB::table('variants')
            ->select(
                'variant_id as id',
                'product_id',
                'title',
                'sku',
                'price'
            )
            ->orderBy('position')
            ->get()
            ->groupBy('product_id');

        $data = $products->map(function ($p) use ($variants) {
            return [
                'id' => $p->id,
                'title' => $p->title,
                'variants' => $variants[$p->id] ?? [],
            ];
        });

        return response()->json([
            'data' => $data->values(),
        ]);
    }
}
