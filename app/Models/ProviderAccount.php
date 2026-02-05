<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProviderAccount extends Model
{
    protected $table = 'provider_accounts';

    protected $fillable = [
        'provider',
        'account_code',
        'display_name',
        'is_active',
        'config',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'config'    => 'array',
    ];

    public function customerIdentities(): HasMany
    {
        return $this->hasMany(CustomerIdentity::class, 'provider_account_id');
    }
}
