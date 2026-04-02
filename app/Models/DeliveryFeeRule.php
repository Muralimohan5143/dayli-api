<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryFeeRule extends Model
{
    protected $fillable = [
        'product_id',
        'variant_id',
        'customer_id', // added
        'title',       // added
        'fixed_fee',
        'formula_fee',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'fixed_fee' => 'decimal:2',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];
}
