<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserService extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role_name',
        'service_handle',
        'subscription_type_id',
        'zone_id',
        'status',
        'is_active',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'meta',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'approved_at' => 'datetime',
        'meta'        => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function documents()
    {
        return $this->hasMany(UserServiceDocument::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
