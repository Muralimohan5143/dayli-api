<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceRequest extends Model
{
    protected $fillable = [
        'customer_id',
        'service_id',
        'zone_id',
        'title',
        'ai_summary',
        'request_json',
        'attachments_json',
        'preferred_date',
        'preferred_time_from',
        'preferred_time_to',
        'address',
        'nagar',
        'latitude',
        'longitude',
        'status',
        'primary_provider_id',
        'secondary_provider_id',
        'current_provider_id',
        'assignment_attempts',
        'no_show_count',
        'auto_reassign_enabled',
        'last_assignment_at',
    ];

    protected $casts = [
        'request_json' => 'array',
        'attachments_json' => 'array',
        'auto_reassign_enabled' => 'boolean',
        'last_assignment_at' => 'datetime',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id', 'service_id');
    }

    public function responses()
    {
        return $this->hasMany(ServiceRequestResponse::class);
    }

    public function events()
    {
        return $this->hasMany(ServiceRequestEvent::class);
    }

    public function assignments()
    {
        return $this->hasMany(ServiceRequestAssignment::class);
    }
}
