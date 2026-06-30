<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceRequestAssignment extends Model
{
    protected $fillable = [
        'service_request_id',
        'provider_id',
        'provider_service_id',
        'service_request_response_id',
        'priority_order',
        'assignment_type',
        'status',
        'assigned_at',
        'accepted_at',
        'enroute_at',
        'started_at',
        'completed_at',
        'failed_at',
        'failure_reason',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'accepted_at' => 'datetime',
        'enroute_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function serviceRequest()
    {
        return $this->belongsTo(
            ServiceRequest::class,
            'service_request_id'
        );
    }

    public function response()
    {
        return $this->belongsTo(
            ServiceRequestResponse::class,
            'service_request_response_id'
        );
    }

    public function providerService()
    {
        return $this->belongsTo(
            ProviderService::class,
            'provider_service_id'
        );
    }
}
