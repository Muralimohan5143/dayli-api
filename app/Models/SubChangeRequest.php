<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\User;
use App\Models\Order;
use App\Models\SubChangeRequestItem;


class SubChangeRequest extends Model
{
    protected $table = 'sub_change_requests';

    protected $fillable = [
        'for_user_id',
        'by_user_id',
        'from_id',
        'order_id',
        'draft_order_id',
        'zone_id',
        'subscription_type_id',
        'subscription_subtype_id',
        'subtypes_json',
        'frequency_type',
        'custom_frequency_format',
        'invoice_cycle',
        'change_reason',
        'start_date',
        'end_date',
        'action',
        'status',
        'approved_by',
        'approved_at',
        'priority',
        'payload',
        'meta',
    ];


    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'approved_at' => 'datetime',
        'payload'     => 'array',
        'meta'        => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($m) {
            if (empty($m->for_user_id)) {
                $m->for_user_id = Auth::id();
            }
            if (empty($m->by_user_id)) {
                $m->by_user_id = Auth::id();
            }
        });
    }



    // Relationships
    public function customer()
    {
        return $this->belongsTo(User::class, 'for_user_id');
    }


    public function draftOrder()
    {
        // If draft_orders has a column named change_request_id -> sub_change_requests.id
        return $this->hasOne(DraftOrder::class, 'change_request_id', 'id');

        // If your column is named sub_change_request_id instead, use:
        // return $this->hasOne(DraftOrder::class, 'sub_change_request_id', 'id');
    }

    /**
     * All contract items under this change request.
     * This traverses SubChangeRequest -> DraftOrder -> DraftOrderItem
     */
    public function draftOrderItem()
    {
        // keys: hasManyThrough(Final, Through, throughFK, finalFK, localKey, throughLocalKey)
        return $this->hasManyThrough(
            DraftOrderItem::class, // final
            DraftOrder::class,     // through
            'change_request_id',   // FK on draft_orders pointing to sub_change_requests.id
            'draft_order_id',      // FK on draft_order_items pointing to draft_orders.id
            'id',                  // local key on sub_change_requests
            'id'                   // local key on draft_orders
        );

        // If your columns are named differently, adjust accordingly, e.g.:
        // return $this->hasManyThrough(DraftOrderItem::class, DraftOrder::class,
        //     'sub_change_request_id', 'draft_order_id', 'id', 'id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'change_request_id');
    }


    // Scopes
    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', 'approved');
    }

    public function scopeActiveOn(Builder $q, $date): Builder
    {
        $d = $date instanceof Carbon ? $date->toDateString() : (string) $date;
        return $q->whereDate('start_date', '<=', $d)
            ->where(function ($w) use ($d) {
                $w->whereNull('end_date')->orWhereDate('end_date', '>=', $d);
            });
    }



    /**
     * Centralized visibility rules by actor.
     * Vendors see rows they initiated or that reference them (if you keep vendor_id).
     * Customers see rows affecting them.
     * Admin/zone roles see all (tighten by zone if needed).
     */
    public function scopeVisibleTo(Builder $q, ?int $userId = null): Builder
    {
        if (!$userId) {
            // either show nothing…
            return $q->whereRaw('1 = 0');
            // …or just return $q to show all if that's your desired behavior
        }

        $user = User::find($userId);

        if ($user && $user->hasAnyRole(['vendor', 'vendor-milk', 'vendor-vegetable', 'vendor-meat', 'vendor-grocery'])) {
            return $q->where(function ($w) use ($userId) {
                $w->where('by_user_id', $userId)
                    ->orWhere('for_user_id', $userId);
            });
        }

        if ($user && $user->hasRole('customer')) {
            return $q->where('for_user_id', $userId);
        }

        return $q; // admins/zone roles
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */




    // Helper: get the single active CR for a user on date D (latest start_date wins)
    public static function activeForUserOn(int $userId, $date): ?self
    {
        return static::approved()
            ->where('for_user_id', $userId)
            ->activeOn($date)
            ->orderByDesc('start_date')
            ->first();
    }
    public function items()
    {
        // Alias to draftOrderItem so existing code works
        return $this->draftOrderItem();
    }


    // $cr = SubChangeRequest::where('for_user_id', $userId)
    // ->where('status', 'approved')
    // ->where('start_date', '<=', now())
    // ->where(function($q){ $q->whereNull('end_date')->orWhere('end_date','>=', now()); })
    // ->orderByDesc('start_date')
    // ->first();

}
