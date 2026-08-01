<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceVariant extends Model
{
    protected $primaryKey = 'variant_id';

    protected $fillable = [
        'service_id',
        'title',
        'sku',
        'duration_minutes',
        'currency',
        'price',
        'compare_at_price',
        'taxable',
        'max_parallel_jobs',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'taxable' => 'boolean',
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id', 'service_id');
    }

    public function vendorZoneServices()
    {
        return $this->hasMany(
            VendorZoneService::class,
            'service_variant_id',
            'variant_id'
        );
    }

    public function workmanZoneServices()
    {
        return $this->hasMany(
            WorkmanZoneService::class,
            'service_variant_id',
            'variant_id'
        );
    }
}
