<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcurementDeliveryException extends Model
{
    protected $table = 'procurement_delivery_exceptions';

    protected $fillable = [
        'zone_id',
        'delivery_date',
        'subscription_type_id',
        'variant_id',
        'exception_type',
        'direction',
        'qty',
        'reason_code',
        'discussion',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'meta_json',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'approved_at' => 'datetime',
        'qty' => 'decimal:2',
        'meta_json' => 'array',
    ];
}
