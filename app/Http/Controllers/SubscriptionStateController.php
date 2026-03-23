<?php

namespace App\Http\Controllers;

use App\Services\SubscriptionStateTransitionService;
use Illuminate\Http\Request;

class SubscriptionStateController extends Controller
{
    public function pause(Request $request, SubscriptionStateTransitionService $svc)
    {
        $data = $request->validate([
            'current_doi_id' => ['required', 'integer'],
            'pause_start_date' => ['required', 'date'],
            'pause_end_date' => ['nullable', 'date'],
        ]);

        $result = $svc->pauseActive(
            currentDoiId: $data['current_doi_id'],
            pauseStartDate: $data['pause_start_date'],
            pauseEndDate: $data['pause_end_date'] ?? null,
        );

        return response()->json([
            'ok' => true,
            'data' => $result,
        ]);
    }

    public function cancel(Request $request, SubscriptionStateTransitionService $svc)
    {
        $data = $request->validate([
            'current_doi_id' => ['required', 'integer'],
            'cancel_start_date' => ['required', 'date'],
        ]);

        $result = $svc->cancelActive(
            currentDoiId: $data['current_doi_id'],
            cancelStartDate: $data['cancel_start_date'],
        );

        return response()->json([
            'ok' => true,
            'data' => $result,
        ]);
    }
}
