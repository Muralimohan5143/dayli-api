<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DraftOrderItem extends Model
{

    protected $table = 'draft_order_items';   // <- IMPORTANT
    public $timestamps = true;

    protected $fillable = [
        'draft_order_id',
        'original_item_id',   // 👈 new
        'change_action',      // 👈 new
        'product_id',
        'variant_id',
        'vendor_id',
        'frequency_type',   // 👈 IMPORTANT
        'qty',
        'unit',
        'price_snapshot',
        'start_date',       // 👈 IMPORTANT
        'end_date',         // 👈 IMPORTANT
        'status',          // 👈 add this
        'meta',
    ];

    protected $casts = [
        'qty'        => 'decimal:2',
        'price_snapshot' => 'array',
        'start_date' => 'date',
        'end_date'   => 'date',
        'meta'       => 'array',
    ];

    public function draftOrder()
    {
        return $this->belongsTo(DraftOrder::class, 'draft_order_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
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
