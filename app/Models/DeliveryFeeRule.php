<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryFeeRule extends Model
{
    protected $fillable = [
        'product_id',
        'variant_id',
        'customer_id',
        'title',
        'fee_formula', // renamed
        'priority',
        'is_active',
    ];

    protected $casts = [
        'fixed_fee' => 'decimal:2',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];
}
