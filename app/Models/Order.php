<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $table = 'orders';

    protected $fillable = [
        'shopify_id',
        'order_type',
        'shopify_order_gid',
        'shopify_legacy_id',
        'order_number',
        'shopify_name',
        'name',
        'confirmation_number',

        'customer_id',
        'vendor_id',
        'zone_id',
        'draft_order_id',

        'number',
        'status',
        'confirmed',
        'closed',
        'requires_shipping',
        'taxes_included',
        'tax_exempt',
        'test',
        'unpaid',

        'delivery_date',
        'delivery_status',
        'delivered_at',
        'delivered_by',

        'financial_status',
        'display_financial_status',
        'fulfillment_status',
        'display_fulfillment_status',

        'email',
        'phone',
        'order_status_url',
        'status_page_url',

        'item_count',
        'currency',
        'currency_code',

        'subtotal',
        'tax',
        'discount',
        'total',

        'current_subtotal',
        'current_tax',
        'current_discounts',
        'current_shipping',
        'current_total',

        'shipping_address',
        'shipping_address_json',
        'billing_address_json',
        'shipping_methods',
        'discounts',
        'tags',
        'meta',
        'source_name',
        'note',

        'created_at_shopify',
        'processed_at_shopify',
        'updated_at_shopify',
        'cancelled_at_shopify',
        'closed_at_shopify',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',

        'tags' => 'array',
        'shipping_address' => 'array',
        'shipping_address_json' => 'array',
        'billing_address_json' => 'array',
        'shipping_methods' => 'array',
        'discounts' => 'array',
        'meta' => 'array',

        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'delivery_date' => 'date',
        'delivered_at' => 'datetime',
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
