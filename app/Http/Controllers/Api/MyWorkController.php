<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryTask;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MyWorkController extends Controller
{
    // =========================
    // Helpers
    // =========================

    private function authUser(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(response()->json(['message' => 'Unauthenticated'], 401));
        }
        return $user;
    }

    private function zoneIdOrFail($user): int
    {
        $zoneId = (int)($user->zone_id ?? 0);
        if (!$zoneId) {
            abort(response()->json(['message' => 'No zone assigned for this user'], 422));
        }
        return $zoneId;
    }

    private function isDeliveryBoy($user): bool
    {
        // your roles are: workman-delivery-boy, workman-delivery-boy-milk, etc.
        if (!method_exists($user, 'hasRole')) return false;

        return $user->hasRole('workman-delivery-boy')
            || (method_exists($user, 'getRoleNames') && $user->getRoleNames()->contains(fn($r) => str_starts_with($r, 'workman-delivery-boy')));
    }

    /**
     * ✅ DELIVERY BOY FILTER:
     * Only show subscriptions where scr.for_user_id has role = customer
     */
    private function applyDeliveryBoyCustomerOnly($query, $user)
    {
        if (!$this->isDeliveryBoy($user)) return $query;

        // whereExists is safer + fast
        return $query->whereExists(function ($q) {
            $q->select(DB::raw(1))
                ->from('model_has_roles as mhr')
                ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                ->where('mhr.model_type', '=', \App\Models\User::class)
                ->where('r.name', '=', 'customer')
                ->whereColumn('mhr.model_id', 'scr.for_user_id');
        });
    }

    private function resolveSubscriptionTypeIdFromType(string $type): ?int
    {
        $type = strtolower(trim($type));
        if ($type === '') return null;

        $row = DB::table('subscription_types')
            ->whereRaw('LOWER(name) LIKE ?', ['%' . $type . '%'])
            ->first();

        $id = (int)($row->id ?? 0);
        return $id > 0 ? $id : null;
    }

    // =========================
    // Delivery Tasks (existing)
    // =========================

    // GET /api/my-work?status=today|pending|completed
    public function index(Request $request)
    {
        $user = $this->authUser($request);
        $status = $request->query('status', 'today');

        $tasks = DeliveryTask::query()
            ->where('delivery_exec_id', $user->id)
            ->where(function ($q) use ($status) {
                if ($status === 'completed') {
                    $q->where('status', 'completed');
                } elseif ($status === 'pending') {
                    $q->whereIn('status', ['pending']);
                } else {
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
        $user = $this->authUser($request);

        $task = DeliveryTask::where('id', $id)
            ->where('delivery_exec_id', $user->id)
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
        $user = $this->authUser($request);

        $task = DeliveryTask::where('id', $id)
            ->where('delivery_exec_id', $user->id)
            ->firstOrFail();

        $task->status = 'completed';
        $task->end_date = Carbon::today();
        $task->save();

        return response()->json(['message' => 'Task completed']);
    }

    // =========================
    // ✅ SUMMARY (FIXED)
    // =========================

    // GET /api/my-work/summary
    public function summary(Request $request)
    {
        $user = $this->authUser($request);
        $zoneId = $this->zoneIdOrFail($user);

        $today = Carbon::today()->toDateString();

        $q = DB::table('draft_order_items as doi')
            ->join('draft_orders as do', 'do.id', '=', 'doi.draft_order_id')
            ->join('sub_change_requests as scr', 'scr.id', '=', 'do.change_request_id')
            ->where('scr.zone_id', $zoneId)
            ->where('scr.status', 'approved')
            ->where(function ($w) {
                $w->whereNull('doi.status')->orWhere('doi.status', 'active');
            })
            ->whereDate('doi.start_date', '<=', $today)
            ->where(function ($w) use ($today) {
                $w->whereNull('doi.end_date')->orWhereDate('doi.end_date', '>=', $today);
            });

        // ✅ IMPORTANT FIX
        $q = $this->applyDeliveryBoyCustomerOnly($q, $user);

        $rows = $q->groupBy('scr.subscription_type_id')
            ->selectRaw('scr.subscription_type_id, COUNT(*) as today_count')
            ->get();

        $typeNames = DB::table('subscription_types')
            ->select('id', 'name')
            ->get()
            ->keyBy('id');

        $types = $rows->map(function ($r) use ($typeNames) {
            $sid = (int)$r->subscription_type_id;
            $name = $typeNames[$sid]->name ?? ('Type ' . $sid);

            return [
                'subscription_type_id' => $sid,
                'title' => $name,
                'today' => (int)$r->today_count,
                'pending' => 0,
                'completed' => 0,
            ];
        })->values();

        return response()->json([
            'zone_id' => (int)$zoneId,
            'date' => $today,
            'types' => $types,
        ]);
    }

    // =========================
    // ✅ ORDERS LIST (FIXED)
    // =========================

    // GET /api/my-work/orders?type=milk&status=today
    public function orders(Request $request)
    {
        $user = $this->authUser($request);
        $zoneId = $this->zoneIdOrFail($user);

        $type = strtolower($request->query('type', 'milk'));
        $today = Carbon::today()->toDateString();

        $subTypeId = $this->resolveSubscriptionTypeIdFromType($type);
        if (!$subTypeId) {
            return response()->json([
                'zone_id' => $zoneId,
                'date' => $today,
                'data' => [],
            ]);
        }

        $q = DB::table('draft_order_items as doi')
            ->join('draft_orders as do', 'do.id', '=', 'doi.draft_order_id')
            ->join('sub_change_requests as scr', 'scr.id', '=', 'do.change_request_id')
            ->join('users as u', 'u.id', '=', 'scr.for_user_id')
            ->where('scr.zone_id', $zoneId)
            ->where('scr.subscription_type_id', $subTypeId)
            ->where('scr.status', 'approved')
            ->where(function ($w) {
                $w->whereNull('doi.status')->orWhere('doi.status', 'active');
            })
            ->whereDate('doi.start_date', '<=', $today)
            ->where(function ($w) use ($today) {
                $w->whereNull('doi.end_date')->orWhereDate('doi.end_date', '>=', $today);
            });

        // ✅ IMPORTANT FIX
        $q = $this->applyDeliveryBoyCustomerOnly($q, $user);

        $items = $q->orderBy('u.name')
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
            'zone_id' => (int)$zoneId,
            'date' => $today,
            'data' => $items,
        ]);
    }

    // =========================
    // Products
    // =========================

    public function allProducts()
    {
        $products = DB::table('products as p')
            ->select('p.product_id as id', 'p.title')
            ->orderBy('p.title')
            ->get();

        $variants = DB::table('variants')
            ->select('variant_id as id', 'product_id', 'title', 'sku', 'price')
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

        return response()->json(['data' => $data->values()]);
    }
}
