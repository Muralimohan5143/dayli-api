<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryTask extends Model
{
    protected $table = 'delivery_tasks';

    protected $fillable = [
        'delivery_task',
        'delivery_exec_id',
        'zone_id',
        'subscription_type_id',  // ✅ add
        'status',
        'start_date',
        'end_date',
        'meta',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'meta'       => 'array',
    ];

    /* ================= Relations ================= */

    public function executor()
    {
        return $this->belongsTo(User::class, 'delivery_exec_id');
    }

    // public function zone()
    // {
    //     return $this->belongsTo(Zone::class);
    // }
}
