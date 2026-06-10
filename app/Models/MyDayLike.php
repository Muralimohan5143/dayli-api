<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MyDayLike extends Model
{
    protected $fillable = [
        'user_id',
        'interest_key',
        'interest_title',
        'category',
        'is_enabled',
        'sort_order',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
