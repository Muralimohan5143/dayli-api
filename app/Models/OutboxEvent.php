<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model
{
    protected $table = 'outbox_events';

    protected $fillable = [
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'correlation_id',
        'idempotency_key',
        'scheduled_at',
        'status',
        'priority',
        'attempts',
        'max_attempts',
        'locked_at',
        'locked_by',
        'started_at',
        'finished_at',
        'last_error',
        'payload',
        'result',
        'notify_on',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'locked_at'    => 'datetime',
        'started_at'   => 'datetime',
        'finished_at'  => 'datetime',
        'payload'      => 'array',
        'result'       => 'array',
    ];
}
