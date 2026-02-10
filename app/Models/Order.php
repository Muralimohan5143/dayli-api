<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $table = 'orders';

    protected $fillable = [
        'customer_id',
        'vendor_id',
        'zone_id',
        'draft_order_id',
        'number',
        'status',
        'subtotal',
        'tax',
        'discount',
        'total',
        'meta',
        'delivery_date',
        'delivery_status',
        'delivered_at',
        'delivered_by',
    ];

    protected $casts = [
        'subtotal'   => 'decimal:2',
        'tax'        => 'decimal:2',
        'discount'   => 'decimal:2',
        'total'      => 'decimal:2',
        'meta'       => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'delivery_date' => 'date',
        'delivered_at'  => 'datetime',
    ];

    /**
     * 🔗 Dayli customer (FK: orders.customer_id → dayli_customers.id)
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    /**
     * 🔗 Vendor (user) – optional, if you use it
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id', 'id');
    }

    /**
     * 🔗 Zone – optional
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_id', 'id');
    }

    /**
     * 🔗 Draft order / contract – optional
     */
    public function draftOrder(): BelongsTo
    {
        return $this->belongsTo(DraftOrder::class, 'draft_order_id', 'id');
    }

    /**
     * 🔗 Line items
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }
}
