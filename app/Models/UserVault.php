<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserVault extends Model
{
    protected $fillable = [
        'user_id',
        'vault_json',
        'last_unlocked_at',
        'is_locked',
    ];

    protected $casts = [
        'vault_json' => 'array',
        'last_unlocked_at' => 'datetime',
        'is_locked' => 'boolean',
    ];
}
