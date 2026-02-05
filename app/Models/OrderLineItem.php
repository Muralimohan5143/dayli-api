<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderLineItem extends Model
{
    protected $table = 'order_line_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',   // <-- add this
        'vendor_id',
        'qty',
        'unit',
        'price_applied',
        'meta',
    ];

    protected $casts = [
        'qty'           => 'decimal:2',
        'price_applied' => 'decimal:2',
        'meta'          => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function variant()
    {
        return $this->belongsTo(\App\Models\Variant::class, 'variant_id', 'variant_id');
    }
}
