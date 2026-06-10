<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MyDayRoutineLog extends Model
{
    protected $fillable = [
        'routine_id',
        'user_id',
        'log_date',
        'status',
        'completed_at',
        'note',
    ];

    protected $casts = [
        'log_date'     => 'date',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function routine()
    {
        return $this->belongsTo(MyDayRoutine::class, 'routine_id');
    }
}
