<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserWhitelist extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'role',
        'zone_id',
        'is_active',
        'approved_at',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_active' => 'boolean',
        'approved_at' => 'datetime',
    ];
}
