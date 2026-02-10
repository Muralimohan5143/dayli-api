<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class OrderItem extends Model
{
    use LogsActivity;
    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'sku',
        'title',
        'variant',
        'brand',
        'quantity',
        'unit_price',
        'line_total',
        'actuals_date',
        'product_url',
        'image_url',
        'meta',
    ];
    protected $casts = [
        'quantity'   => 'integer',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'meta'       => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['quantity', 'unit_price', 'line_total'])
            ->logOnlyDirty()
            ->useLogName('order_item');
    }
}
