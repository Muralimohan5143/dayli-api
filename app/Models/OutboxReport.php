<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboxReport extends Model
{
    protected $fillable = [
        'zone_manager_id',
        'report_type',
        'subscription_type_id',
        'service_type_id',
        'status',
        'start_date',
        'end_date',
        'payload_json',
        'generated_at',
        'processed_at',
        'sent_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'generated_at' => 'datetime',
        'processed_at' => 'datetime',
        'sent_at' => 'datetime',
        'payload_json' => 'array',
    ];

    public function zoneManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'zone_manager_id');
    }

    public function subscriptionType(): BelongsTo
    {
        return $this->belongsTo(SubscriptionType::class, 'subscription_type_id');
    }
}
