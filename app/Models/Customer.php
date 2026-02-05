<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Customer extends Model
{
    use SoftDeletes;

    protected $table = 'customers';

    /**
     * IMPORTANT:
     * - 'natural_key' and 'phone_normalized' are GENERATED columns in DB
     *   so DO NOT put them in $fillable.
     */
    protected $fillable = [
        // identity/contact
        'phone',
        'email',
        'first_name',
        'last_name',
        'display_name',

        // account/meta
        'account_status',
        'origin_system',
        'originating_from',

        // relations/refs
        'zone_id',
        'ops_customer_id',
        'shopify_customer_id',

        // profile
        'gender',
        'nagar',
        'address',
        'pincode',
        'zone_code',
        'default_address_json',
        'image_url',
        'avatar',

        // commerce
        'locale',
        'currency',
        'verified_email',
        'tax_exempt',
        'marketing_opt_in_level',
        'number_of_orders',
        'amount_spent',
        'total_amount_due',

        // marketing / ops
        'marketing_campaign_id',
        'marketing_executive_id',
        'tags',
        'note',
        'bio',

        // sync
        'should_sync_with',
        'sync_completed_with',
        'clickup_person_id',
        'external_refs',

        // extra
        'type',
        'name_mf',
        'phone_mf',
        'area_mf',
        'geolocation',
        'last_payment_date',
        'profile_metaobject_gid',
        'tasks_head_metaobject_gid',
        'skills',

        // timestamps from shopify
        'shopify_created_at',
        'shopify_updated_at',

        // auth
        'password',
        'remember_token',

        // login
        'last_logged_at',
    ];

    protected $casts = [
        'verified_email'        => 'boolean',
        'tax_exempt'            => 'boolean',

        'default_address_json'  => 'array',
        'marketing_opt_in_level' => 'array',
        'should_sync_with'      => 'array',
        'sync_completed_with'   => 'array',
        'external_refs'         => 'array',

        'last_logged_at'        => 'datetime',
        'shopify_created_at'    => 'datetime',
        'shopify_updated_at'    => 'datetime',
        'last_payment_date'     => 'date',

        'number_of_orders'      => 'integer',
        'amount_spent'          => 'decimal:2',
        'total_amount_due'      => 'decimal:2',
    ];

    /* ================= Relations ================= */

    public function customerIdentities(): HasMany
    {
        return $this->hasMany(CustomerIdentity::class, 'customer_id');
    }

    public function marketingExecutive(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marketing_executive_id');
    }
}
