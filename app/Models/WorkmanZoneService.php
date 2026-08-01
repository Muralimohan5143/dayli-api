<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkmanZoneService extends Model
{
    use HasFactory;

    protected $table = 'workman_zone_services';

    protected $fillable = [
        'workman_id',
        'zone_id',
        'service_variant_id',
        'status',
        'is_active',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'is_preferred',
        'lead_time_mins',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_preferred' => 'boolean',
        'approved_at' => 'datetime',
        'meta' => 'array',
    ];

    public function workman()
    {
        return $this->belongsTo(User::class, 'workman_id');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function serviceVariant()
    {
        return $this->belongsTo(
            ServiceVariant::class,
            'service_variant_id',
            'variant_id'
        );
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function documents()
    {
        return $this->morphMany(
            ServiceApplicationDocument::class,
            'documentable'
        );
    }
}
