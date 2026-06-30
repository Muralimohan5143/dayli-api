<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceRequestEvent extends Model
{
    protected $fillable = [
        'service_request_id',
        'actor_id',
        'actor_type',
        'event_type',
        'event_json',
        'notes',
    ];

    protected $casts = [
        'event_json' => 'array',
    ];

    public function serviceRequest()
    {
        return $this->belongsTo(ServiceRequest::class, 'service_request_id');
    }
}
