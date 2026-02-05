<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionZone extends Model
{
    use HasFactory;

    protected $table = 'subscription_zones';

    protected $fillable = [
        'zone_id',
        'subscription_type_id',
        'status',
        'available_from',
        'available_to',
        'is_default',
    ];

    protected $casts = [
        'available_from' => 'date',
        'available_to'   => 'date',
        'is_default'     => 'boolean',
    ];

    /** 
     * Relationships 
     */
    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function subscriptionType()
    {
        return $this->belongsTo(SubscriptionType::class);
    }
}
