<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionType extends Model
{
    use HasFactory;

    protected $table = 'subscription_types';

    protected $fillable = [
        'name',
        'description',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    public function subtypes()
    {
        return $this->hasMany(SubscriptionType::class, 'subscription_type_id');
    }


    // Vendors that serve this subscription type
    public function vendors()
    {
        return $this->belongsToMany(User::class, 'vendor_zone_subscr', 'subscription_type_id', 'vendor_id')
            ->withPivot(['zone_id', 'status', 'is_preferred', 'lead_time_mins', 'meta'])
            ->withTimestamps();
    }

    // Zones where this subscription type is available
    public function zones()
    {
        return $this->belongsToMany(Zone::class, 'vendor_zone_subscr', 'subscription_type_id', 'zone_id')
            ->withPivot(['vendor_id', 'status', 'is_preferred', 'lead_time_mins', 'meta'])
            ->withTimestamps();
    }
}
