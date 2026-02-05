<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorZoneSubscr extends Model
{
    use HasFactory;

    protected $table = 'vendor_zone_subscr';

    protected $fillable = [
        'vendor_id',
        'zone_id',
        'subscription_type_id',
        'status',
        'is_preferred',
        'lead_time_mins',
        'meta',
    ];

    protected $casts = [
        'is_preferred' => 'boolean',
        'lead_time_mins' => 'integer',
        'meta' => 'array',
    ];

    public $timestamps = true;
}
