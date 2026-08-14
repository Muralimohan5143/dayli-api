<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;

use App\Models\Address;
use App\Models\Zone;
use App\Models\SubChangeRequest;
use App\Models\SubscriptionType;
use App\Models\MyDayLike;
use App\Models\MyDayNote;
use App\Models\MyDayTodo;
use App\Models\MyDayRoutine;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $table = 'users';

    /**
     * Merge of old User + Customer model fillable fields
     */
    protected $fillable = [
        // CUSTOMER FIELDS
        'shopify_customer_id',
        'name',
        'first_name',
        'last_name',
        'display_name',
        'phone',
        'email',
        'gender',
        'nagar',
        'address',
        'pincode',
        'zone_code',
        'image_url',
        'avatar',
        'locale',
        'currency',
        'verified_email',
        'tax_exempt',
        'marketing_opt_in_level',
        'number_of_orders',
        'amount_spent',
        'total_amount_due',
        'tags',
        'note',
        'bio',
        'origin_system',
        'account_status',
        'originating_from',
        'should_sync_with',
        'sync_completed_with',
        'profile_metaobject_gid',
        'skills',
        'shopify_created_at',
        'shopify_updated_at',
        'default_address_json',

        // EXISTING USER MODEL
        'password',
        'remember_token',
        'zone_id',
        'ops_customer_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'verified_email'         => 'boolean',
        'tax_exempt'             => 'boolean',
        'marketing_opt_in_level' => 'array',
        'shopify_created_at'     => 'datetime',
        'shopify_updated_at'     => 'datetime',
        'default_address_json'   => 'array',
    ];

    /* ------------------------- Role helpers (Spatie) ------------------------- */
    public function isAdmin()
    {
        return $this->hasRole('admin');
    }
    public function isZonesHead()
    {
        return $this->hasRole('zones-head');
    }
    public function isZonesDirector()
    {
        return $this->hasRole('zones-director');
    }
    public function isZoneManager()
    {
        return $this->hasRole('zone-manager');
    }
    public function isVendor()
    {
        return $this->hasRole('vendor');
    }
    public function isWorkman()
    {
        return $this->hasRole('workman');
    }
    public function isCustomer()
    {
        return $this->hasRole('customer');
    }

    public function hasVendorType($t)
    {
        return $this->hasRole("vendor-$t");
    }
    public function hasWorkmanType($t)
    {
        return $this->hasRole("workman-$t");
    }

    /* ------------------------------ Relations -------------------------------- */
    public function addresses()
    {
        return $this->morphMany(Address::class, 'addressable')->latest();
    }

    public function defaultAddress()
    {
        return $this->morphOne(Address::class, 'addressable')
            ->where('is_default', true);
    }
    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function currentZone(): ?Zone
    {
        return optional($this->defaultAddress)->zone
            ?? ($this->zone ?? null);
    }
    public function changeRequests()
    {
        return $this->hasMany(SubChangeRequest::class, 'customer_id');
    }

    // public function zonesServed()
    // {
    //     return $this->belongsToMany(Zone::class, 'vendor_zone_subscr', 'vendor_id', 'zone_id')
    //         ->withPivot(['subscr_type_id', 'status', 'is_preferred', 'lead_time_mins', 'meta'])
    //         ->withTimestamps();
    // }

    // public function subscriptionTypesServed()
    // {
    //     return $this->belongsToMany(
    //         SubscriptionType::class,
    //         'vendor_zone_subscr',
    //         'vendor_id',
    //         'subscr_type_id'
    //     )->withPivot(['zone_id', 'status', 'is_preferred', 'lead_time_mins', 'meta'])
    //         ->withTimestamps();
    // }

    /* ------------------- Accessor / Mutator for "contact" -------------------- */
    public function getContactAttribute(): ?string
    {
        return $this->phone ?? $this->email;
    }

    public function setContactAttribute(?string $value): void
    {
        $value = $value ? trim($value) : null;

        if (!$value) return;

        if (str_contains($value, '@')) {
            $this->email = $value;
        } else {
            $this->phone = preg_replace('/[^\d+]/', '', $value);
        }
    }




    public function myDayLikes()
    {
        return $this->hasMany(MyDayLike::class);
    }

    public function myDayNotes()
    {
        return $this->hasMany(MyDayNote::class);
    }

    public function myDayTodos()
    {
        return $this->hasMany(MyDayTodo::class);
    }

    public function myDayRoutines()
    {
        return $this->hasMany(MyDayRoutine::class);
    }

    public function vendorZoneServices()
    {
        return $this->hasMany(
            \App\Models\VendorZoneService::class,
            'vendor_id'
        );
    }

    public function workmanZoneServices()
    {
        return $this->hasMany(
            \App\Models\WorkmanZoneService::class,
            'workman_id'
        );
    }

    public function approvedVendorZoneServices()
    {
        return $this->vendorZoneServices()
            ->where('status', 'approved')
            ->where('is_active', true);
    }

    public function approvedWorkmanZoneServices()
    {
        return $this->workmanZoneServices()
            ->where('status', 'approved')
            ->where('is_active', true);
    }

    public function hasApprovedService(
        string $roleName,
        ?string $serviceHandle = null
    ): bool {
        if (!in_array($roleName, ['vendor', 'workman'], true)) {
            return false;
        }

        $relation = $roleName === 'vendor'
            ? $this->vendorZoneServices()
            : $this->workmanZoneServices();

        $query = $relation
            ->where('status', 'approved')
            ->where('is_active', true);

        if ($serviceHandle !== null && trim($serviceHandle) !== '') {
            $serviceHandle = strtolower(trim($serviceHandle));

            $query->whereHas(
                'serviceVariant',
                function ($variantQuery) use ($serviceHandle) {
                    if (in_array($serviceHandle, [
                        'workman-delivery-boy',
                        'delivery-boy',
                        'milk',
                        'milk-supplier',
                        'milk-and-dairy',
                        'milk-delivery',
                    ], true)) {
                        $variantQuery->where(
                            'sku',
                            'SERVICE-DELIVERY-MILK'
                        );

                        return;
                    }

                    $variantQuery
                        ->where('sku', $serviceHandle)
                        ->orWhereRaw(
                            "JSON_UNQUOTE(JSON_EXTRACT(meta, '$.handle')) = ?",
                            [$serviceHandle]
                        )
                        ->orWhereHas(
                            'service',
                            function ($serviceQuery) use ($serviceHandle) {
                                $serviceQuery->where(
                                    'handle',
                                    $serviceHandle
                                );
                            }
                        );
                }
            );
        }

        return $query->exists();
    }

    public function getApprovedService(
        ?string $roleName = null,
        ?string $serviceHandle = null
    ) {
        if (!in_array($roleName, ['vendor', 'workman'], true)) {
            return null;
        }

        $relation = $roleName === 'vendor'
            ? $this->vendorZoneServices()
            : $this->workmanZoneServices();

        $query = $relation
            ->with([
                'serviceVariant.service',
                'documents',
                'approver',
            ])
            ->where('status', 'approved')
            ->where('is_active', true);

        if ($serviceHandle !== null && trim($serviceHandle) !== '') {
            $serviceHandle = strtolower(trim($serviceHandle));

            $query->whereHas(
                'serviceVariant',
                function ($variantQuery) use ($serviceHandle) {
                    if (in_array($serviceHandle, [
                        'workman-delivery-boy',
                        'delivery-boy',
                        'milk',
                        'milk-supplier',
                        'milk-and-dairy',
                        'milk-delivery',
                    ], true)) {
                        $variantQuery->where(
                            'sku',
                            'SERVICE-DELIVERY-MILK'
                        );

                        return;
                    }

                    $variantQuery
                        ->where('sku', $serviceHandle)
                        ->orWhereRaw(
                            "JSON_UNQUOTE(JSON_EXTRACT(meta, '$.handle')) = ?",
                            [$serviceHandle]
                        )
                        ->orWhereHas(
                            'service',
                            function ($serviceQuery) use ($serviceHandle) {
                                $serviceQuery->where(
                                    'handle',
                                    $serviceHandle
                                );
                            }
                        );
                }
            );
        }

        return $query
            ->latest('id')
            ->first();
    }
}
