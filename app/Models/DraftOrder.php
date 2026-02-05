<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class DraftOrder extends Model
{

    use HasFactory;
    protected $table = 'draft_orders';   // <- ensure correct table
    protected $guarded = [];             // <- unblock mass assignment while debugging
    // protected $fillable = [
    //     'change_request_id',
    //     'customer_id',
    //     'vendor_id',
    //     'zone_id',
    //     'subscription_type_id',
    //     'subscription_subtype_id',
    //     'cadence',
    //     'custom_frequency_format',
    //     'invoice_cycle',
    //     'start_date',
    //     'end_date',
    //     'status',
    //     'timezone',
    //     'meta',
    // ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'meta'       => 'array',
    ];


    public function changeRequest()
    {
        // FK is change_request_id (not sub_change_request_id)
        return $this->belongsTo(SubChangeRequest::class, 'change_request_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
    public function items()
    {
        return $this->hasMany(DraftOrderItem::class, 'draft_order_id', 'id');
    }

    // public function variant()
    // {
    //     return $this->belongsTo(\App\Models\Variant::class, 'variant_id', 'variant_id');
    // }
}
