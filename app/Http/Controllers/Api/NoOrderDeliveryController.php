<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OutboxEvent;
use Illuminate\Http\Request;

class NoOrderDeliveryController extends Controller
{
    public function send(Request $request)
    {
        $validated = $request->validate([
            'zone_id' => 'required|integer',
            'subscription_type_id' => 'required|integer',
            'delivery_date' => 'required|date',
            'reason' => 'required|string|max:255',
            'template_key' => 'required|string',
        ]);

        OutboxEvent::create([
            'event_type' => 'zone.no_delivery.notify',
            'aggregate_type' => 'zone',
            'aggregate_id' => $validated['zone_id'],
            'status' => 'pending',
            'payload' => [
                'zone_id' => $validated['zone_id'],
                'subscription_type_id' => $validated['subscription_type_id'],
                'delivery_date' => $validated['delivery_date'],
                'reason' => $validated['reason'],
                'template_key' => $validated['template_key'],

                'days_count' => $request->input('days_count', '1'),
                'from_date' => $request->input('from_date', $validated['delivery_date']),
                'to_date' => $request->input('to_date', $validated['delivery_date']),
            ],
            'scheduled_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification queued successfully',
        ]);
    }
}
