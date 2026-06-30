<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceRequestResponse extends Model
{
    protected $fillable = [
        'service_request_id',
        'provider_id',
        'provider_service_id',
        'response_json',
        'message',
        'quoted_price',
        'proposed_date',
        'proposed_time_from',
        'proposed_time_to',
        'status',
    ];

    protected $casts = [
        'response_json' => 'array',
        'quoted_price' => 'decimal:2',
    ];

    public function serviceRequest()
    {
        return $this->belongsTo(ServiceRequest::class, 'service_request_id');
    }

    public function providerService()
    {
        return $this->belongsTo(ProviderService::class, 'provider_service_id');
    }
}
