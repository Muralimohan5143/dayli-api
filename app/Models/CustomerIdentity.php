<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerIdentity extends Model
{
    protected $table = 'customer_identities';

    protected $fillable = [
        'customer_id',
        'provider_account_id',
        'external_id',
        'external_legacy_id',
        'status',
        'last_synced_at',
        'meta',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'meta'           => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function providerAccount(): BelongsTo
    {
        return $this->belongsTo(ProviderAccount::class, 'provider_account_id');
    }
}
