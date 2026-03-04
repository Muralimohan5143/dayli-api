<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'device_id',
        'app_version',
        'last_seen_at',
        'is_valid'
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'is_valid' => 'boolean',
    ];
}
