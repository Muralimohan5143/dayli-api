<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'orders';

    protected $fillable = [
        'name', 'customer_id', 'email', 'phone',
        'financial_status', 'fulfillment_status', 'currency',
        'subtotal_price', 'total_price', 'total_tax', 'total_discounts', 'total_weight',
        'source_name', 'order_number', 'note', 'status',
        'processed_at', 'cancelled_at',
        'billing_address', 'shipping_address', 'tags',
        'change_request_id', 'service_date',
    ];

    protected $casts = [
        'processed_at'    => 'datetime',
        'cancelled_at'    => 'datetime',
        'billing_address' => 'array',
        'shipping_address'=> 'array',
        'tags'            => 'array',
        'service_date'    => 'date',
        'subtotal_price'  => 'decimal:2',
        'total_price'     => 'decimal:2',
        'total_tax'       => 'decimal:2',
        'total_discounts' => 'decimal:2',
    ];

    public function customer() { return $this->belongsTo(User::class, 'customer_id'); }
    public function changeRequest() { return $this->belongsTo(SubChangeRequest::class, 'change_request_id'); }
    public function items() { return $this->hasMany(OrderLineItem::class); }

    // tiny helper
    public function recalcTotals(): void
    {
        $subtotal = $this->items->sum(function ($i) {
            return (float) $i->qty * (float) $i->price_applied;
        });
        $this->subtotal_price = $subtotal;
        $this->total_tax = 0;          // plug tax engine here
        $this->total_discounts = 0;    // plug discounts here
        $this->total_price = $subtotal + $this->total_tax - $this->total_discounts;
        $this->save();
    }
}
