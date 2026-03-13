<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureApprovedService
{
    public function handle(Request $request, Closure $next, string $roleName, ?string $serviceHandle = null)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $approved = $user->hasApprovedService($roleName, $serviceHandle);

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
}
