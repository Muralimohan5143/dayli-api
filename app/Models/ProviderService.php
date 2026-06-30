<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderService extends Model
{
    protected $fillable = [
        'provider_id',
        'service_id',
        'variant_id',
        'description',
        'starting_price',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starting_price' => 'decimal:2',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id', 'service_id');
    }

    public function variant()
    {
        return $this->belongsTo(ServiceVariant::class, 'variant_id', 'variant_id');
    }
}
