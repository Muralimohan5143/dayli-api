<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DraftOrderItem extends Model
{
    protected $table = 'draft_order_items';
    public $timestamps = true;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_CANCELLED = 'cancelled';

    public const ACTION_CREATE = 'create';
    public const ACTION_MODIFY = 'modify';
    public const ACTION_PAUSE = 'pause';
    public const ACTION_RESUME = 'resume';
    public const ACTION_CANCEL = 'cancel';
    public const ACTION_REACTIVATE = 'reactivate';
    public const ACTION_SYSTEM = 'system';

    protected $fillable = [
        'draft_order_id',
        'original_item_id',
        'change_action',
        'product_id',
        'variant_id',
        'vendor_id',
        'frequency_type',
        'qty',
        'unit',
        'price_snapshot',
        'start_date',
        'end_date',
        'status',
        'supersedes_doi_id',
        'created_from_action',
        'closed_by_action',
        'meta',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'price_snapshot' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'meta' => 'array',
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

    public function supersedes()
    {
        return $this->belongsTo(self::class, 'supersedes_doi_id');
    }

    public function supersededBy()
    {
        return $this->hasMany(self::class, 'supersedes_doi_id');
    }
}
