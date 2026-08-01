<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnsureApprovedService
{
    public function handle(
        Request $request,
        Closure $next,
        string $roleName,
        ?string $serviceHandle = null
    ) {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!in_array($roleName, ['vendor', 'workman'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid service role.',
                'required_role' => $roleName,
                'required_service_handle' => $serviceHandle,
            ], 422);
        }

        $approved = $this->hasApprovedService(
            userId: (int) $user->id,
            roleName: $roleName,
            serviceHandle: $serviceHandle
        );

        if (!$approved) {
            return response()->json([
                'ok' => false,
                'message' => 'Your service account is not approved yet.',
                'required_role' => $roleName,
                'required_service_handle' => $serviceHandle,
            ], 403);
        }

        return $next($request);
    }

    private function hasApprovedService(
        int $userId,
        string $roleName,
        ?string $serviceHandle = null
    ): bool {
        $table = $roleName === 'vendor'
            ? 'vendor_zone_services'
            : 'workman_zone_services';

        $userColumn = $roleName === 'vendor'
            ? 'vendor_id'
            : 'workman_id';

        $query = DB::table($table . ' as zs')
            ->join(
                'service_variants as sv',
                'sv.variant_id',
                '=',
                'zs.service_variant_id'
            )
            ->join(
                'services as s',
                's.service_id',
                '=',
                'sv.service_id'
            )
            ->where('zs.' . $userColumn, $userId)
            ->where('zs.status', 'approved')
            ->where('zs.is_active', true);

        if ($serviceHandle !== null && trim($serviceHandle) !== '') {
            $serviceHandle = strtolower(trim($serviceHandle));

            $query->where(function ($q) use ($serviceHandle) {
                /*
                |--------------------------------------------------------------------------
                | Legacy route handles
                |--------------------------------------------------------------------------
                */

                if (in_array($serviceHandle, [
                    'workman-delivery-boy',
                    'delivery-boy',
                    'milk',
                    'milk-supplier',
                    'milk-and-dairy',
                    'milk-delivery',
                ], true)) {
                    $q->where('sv.sku', 'SERVICE-DELIVERY-MILK');
                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | New service / variant handles
                |--------------------------------------------------------------------------
                */

                $q->where('s.handle', $serviceHandle)
                    ->orWhereRaw(
                        "JSON_UNQUOTE(JSON_EXTRACT(sv.meta, '$.handle')) = ?",
                        [$serviceHandle]
                    )
                    ->orWhere('sv.sku', $serviceHandle);
            });
        }

        return $query->exists();
    }
}
