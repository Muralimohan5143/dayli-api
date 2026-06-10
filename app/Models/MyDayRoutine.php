<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MyDayRoutine extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'icon',
        'frequency_type',
        'days_of_week',
        'time_of_day',
        'remind_at',
        'is_active',
        'current_streak',
        'best_streak',
        'sort_order',
    ];

    protected $casts = [
        'days_of_week'  => 'array',
        'remind_at'     => 'datetime',
        'is_active'     => 'boolean',
        'current_streak' => 'integer',
        'best_streak'   => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function logs()
    {
        return $this->hasMany(\App\Models\MyDayRoutineLog::class, 'routine_id');
    }
}
