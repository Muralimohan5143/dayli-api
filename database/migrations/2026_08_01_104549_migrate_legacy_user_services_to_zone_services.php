<?php

use App\Models\VendorZoneService;
use App\Models\WorkmanZoneService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasTable('user_services') ||
            !Schema::hasTable('vendor_zone_services') ||
            !Schema::hasTable('workman_zone_services') ||
            !Schema::hasTable('service_variants')
        ) {
            return;
        }

        DB::transaction(function () {
            $deliveryVariants = DB::table('service_variants')
                ->whereIn('sku', [
                    'SERVICE-DELIVERY-MILK',
                    'SERVICE-DELIVERY-VEGETABLE',
                    'SERVICE-DELIVERY-FRUIT',
                    'SERVICE-DELIVERY-GROCERY',
                    'SERVICE-DELIVERY-MEDICINE',
                ])
                ->pluck('variant_id', 'sku');

            $milkDeliveryVariantId =
                $deliveryVariants['SERVICE-DELIVERY-MILK'] ?? null;

            if (!$milkDeliveryVariantId) {
                throw new RuntimeException(
                    'Milk Delivery service variant was not found.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 1. Migrate user_services vendor records
            |--------------------------------------------------------------------------
            */

            $vendorApplications = DB::table('user_services')
                ->where('role_name', 'vendor')
                ->orderBy('id')
                ->get();

            foreach ($vendorApplications as $old) {
                $variantId = $this->resolveVariantId(
                    $old,
                    $deliveryVariants
                );

                if (!$variantId || !$old->zone_id) {
                    continue;
                }

                $oldMeta = $this->decodeMeta($old->meta ?? null);

                VendorZoneService::updateOrCreate(
                    [
                        'vendor_id' => (int) $old->user_id,
                        'zone_id' => (int) $old->zone_id,
                        'service_variant_id' => (int) $variantId,
                    ],
                    [
                        'status' => $old->status,
                        'is_active' => (bool) $old->is_active,
                        'approved_by' => $old->approved_by,
                        'approved_at' => $old->approved_at,
                        'rejection_reason' => $old->rejection_reason,
                        'is_preferred' => false,
                        'lead_time_mins' => null,
                        'meta' => array_merge($oldMeta, [
                            'legacy_user_service_id' => (int) $old->id,
                            'legacy_service_handle' =>
                            $old->service_handle,
                            'legacy_subscription_type_id' =>
                            $old->subscription_type_id,
                            'migrated_from' => 'user_services',
                        ]),
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 2. Migrate user_services workman records
            |--------------------------------------------------------------------------
            */

            $workmanApplications = DB::table('user_services')
                ->where('role_name', 'workman')
                ->orderBy('id')
                ->get();

            foreach ($workmanApplications as $old) {
                $variantId = $this->resolveVariantId(
                    $old,
                    $deliveryVariants
                );

                if (!$variantId || !$old->zone_id) {
                    continue;
                }

                $oldMeta = $this->decodeMeta($old->meta ?? null);

                WorkmanZoneService::updateOrCreate(
                    [
                        'workman_id' => (int) $old->user_id,
                        'zone_id' => (int) $old->zone_id,
                        'service_variant_id' => (int) $variantId,
                    ],
                    [
                        'status' => $old->status,
                        'is_active' => (bool) $old->is_active,
                        'approved_by' => $old->approved_by,
                        'approved_at' => $old->approved_at,
                        'rejection_reason' => $old->rejection_reason,
                        'is_preferred' => false,
                        'lead_time_mins' => null,
                        'meta' => array_merge($oldMeta, [
                            'legacy_user_service_id' => (int) $old->id,
                            'legacy_service_handle' =>
                            $old->service_handle,
                            'legacy_subscription_type_id' =>
                            $old->subscription_type_id,
                            'migrated_from' => 'user_services',
                        ]),
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 3. Migrate existing vendor_zone_subscr records
            |--------------------------------------------------------------------------
            */

            if (Schema::hasTable('vendor_zone_subscr')) {
                $legacyVendorMappings = DB::table('vendor_zone_subscr')
                    ->orderBy('id')
                    ->get();

                foreach ($legacyVendorMappings as $old) {
                    $variantId = $this->resolveDeliveryVariantFromSubscription(
                        $old->subscription_type_id ?? null,
                        $deliveryVariants
                    );

                    if (!$variantId) {
                        continue;
                    }

                    $oldMeta = $this->decodeMeta($old->meta ?? null);

                    $record = VendorZoneService::firstOrNew([
                        'vendor_id' => (int) $old->vendor_id,
                        'zone_id' => (int) $old->zone_id,
                        'service_variant_id' => (int) $variantId,
                    ]);

                    /*
                    | Do not overwrite a richer application status already
                    | migrated from user_services.
                    */

                    if (!$record->exists) {
                        $record->status =
                            $old->status === 'active'
                            ? 'approved'
                            : 'inactive';

                        $record->is_active =
                            $old->status === 'active';
                    }

                    $record->is_preferred =
                        (bool) $old->is_preferred;

                    $record->lead_time_mins =
                        $old->lead_time_mins;

                    $record->meta = array_merge(
                        $record->meta ?? [],
                        $oldMeta,
                        [
                            'legacy_vendor_zone_subscr_id' =>
                            (int) $old->id,
                            'legacy_subscription_type_id' =>
                            (int) $old->subscription_type_id,
                            'migrated_from_vendor_zone_subscr' => true,
                        ]
                    );

                    $record->save();
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 4. Migrate documents
            |--------------------------------------------------------------------------
            */

            if (
                Schema::hasTable('user_service_documents') &&
                Schema::hasTable('service_application_documents')
            ) {
                $documents = DB::table('user_service_documents')
                    ->orderBy('id')
                    ->get();

                foreach ($documents as $document) {
                    $oldService = DB::table('user_services')
                        ->where('id', $document->user_service_id)
                        ->first();

                    if (!$oldService) {
                        continue;
                    }

                    $documentableType = null;
                    $documentableId = null;

                    if ($oldService->role_name === 'vendor') {
                        $target = VendorZoneService::query()
                            ->where('vendor_id', $oldService->user_id)
                            ->whereJsonContains(
                                'meta->legacy_user_service_id',
                                (int) $oldService->id
                            )
                            ->first();

                        /*
                        | Fallback for MySQL versions where JSON lookup
                        | does not match the stored scalar as expected.
                        */

                        if (!$target) {
                            $target = VendorZoneService::query()
                                ->where(
                                    'vendor_id',
                                    $oldService->user_id
                                )
                                ->where(
                                    'zone_id',
                                    $oldService->zone_id
                                )
                                ->get()
                                ->first(function ($row) use ($oldService) {
                                    return (int) data_get(
                                        $row->meta,
                                        'legacy_user_service_id'
                                    ) === (int) $oldService->id;
                                });
                        }

                        if ($target) {
                            $documentableType =
                                VendorZoneService::class;
                            $documentableId = $target->id;
                        }
                    }

                    if ($oldService->role_name === 'workman') {
                        $target = WorkmanZoneService::query()
                            ->where('workman_id', $oldService->user_id)
                            ->whereJsonContains(
                                'meta->legacy_user_service_id',
                                (int) $oldService->id
                            )
                            ->first();

                        if (!$target) {
                            $target = WorkmanZoneService::query()
                                ->where(
                                    'workman_id',
                                    $oldService->user_id
                                )
                                ->where(
                                    'zone_id',
                                    $oldService->zone_id
                                )
                                ->get()
                                ->first(function ($row) use ($oldService) {
                                    return (int) data_get(
                                        $row->meta,
                                        'legacy_user_service_id'
                                    ) === (int) $oldService->id;
                                });
                        }

                        if ($target) {
                            $documentableType =
                                WorkmanZoneService::class;
                            $documentableId = $target->id;
                        }
                    }

                    if (!$documentableType || !$documentableId) {
                        continue;
                    }

                    $documentMeta = $this->decodeMeta(
                        $document->meta ?? null
                    );

                    DB::table('service_application_documents')
                        ->updateOrInsert(
                            [
                                'documentable_type' =>
                                $documentableType,
                                'documentable_id' =>
                                (int) $documentableId,
                                'document_type' =>
                                $document->document_type,
                                'file_path' =>
                                $document->file_path,
                            ],
                            [
                                'file_name' =>
                                $document->file_name,
                                'mime_type' =>
                                $document->mime_type,
                                'file_size' =>
                                $document->file_size,
                                'status' =>
                                $document->status,
                                'remarks' =>
                                $document->remarks,
                                'meta' => json_encode(
                                    array_merge(
                                        $documentMeta,
                                        [
                                            'legacy_user_service_document_id' =>
                                            (int) $document->id,
                                            'legacy_user_service_id' =>
                                            (int) $document->user_service_id,
                                            'migrated_from' =>
                                            'user_service_documents',
                                        ]
                                    )
                                ),
                                'created_at' =>
                                $document->created_at ?? now(),
                                'updated_at' =>
                                $document->updated_at ?? now(),
                            ]
                        );
                }
            }
        });
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Remove only records created by this migration
        |--------------------------------------------------------------------------
        |
        | Old tables remain untouched.
        |
        */

        if (Schema::hasTable('service_application_documents')) {
            DB::table('service_application_documents')
                ->whereRaw(
                    "JSON_UNQUOTE(JSON_EXTRACT(meta, '$.migrated_from')) = ?",
                    ['user_service_documents']
                )
                ->delete();
        }

        if (Schema::hasTable('vendor_zone_services')) {
            DB::table('vendor_zone_services')
                ->where(function ($query) {
                    $query
                        ->whereRaw(
                            "JSON_UNQUOTE(JSON_EXTRACT(meta, '$.migrated_from')) = ?",
                            ['user_services']
                        )
                        ->orWhereRaw(
                            "JSON_EXTRACT(meta, '$.migrated_from_vendor_zone_subscr') = true"
                        );
                })
                ->delete();
        }

        if (Schema::hasTable('workman_zone_services')) {
            DB::table('workman_zone_services')
                ->whereRaw(
                    "JSON_UNQUOTE(JSON_EXTRACT(meta, '$.migrated_from')) = ?",
                    ['user_services']
                )
                ->delete();
        }
    }

    private function resolveVariantId(
        object $old,
        $deliveryVariants
    ): ?int {
        $handle = strtolower(
            trim((string) ($old->service_handle ?? ''))
        );

        /*
        |--------------------------------------------------------------------------
        | Existing legacy mappings
        |--------------------------------------------------------------------------
        */

        if (in_array($handle, [
            'milk',
            'milk-supplier',
            'milk-and-dairy',
            'workman-delivery-boy',
            'delivery-boy',
        ], true)) {
            return isset(
                $deliveryVariants['SERVICE-DELIVERY-MILK']
            )
                ? (int) $deliveryVariants['SERVICE-DELIVERY-MILK']
                : null;
        }

        /*
        |--------------------------------------------------------------------------
        | Use subscription type for other delivery services
        |--------------------------------------------------------------------------
        */

        return $this->resolveDeliveryVariantFromSubscription(
            $old->subscription_type_id ?? null,
            $deliveryVariants
        );
    }

    private function resolveDeliveryVariantFromSubscription(
        $subscriptionTypeId,
        $deliveryVariants
    ): ?int {
        if (!$subscriptionTypeId) {
            return null;
        }

        $subscriptionType = DB::table('subscription_types')
            ->where('id', $subscriptionTypeId)
            ->first();

        if (!$subscriptionType) {
            return null;
        }

        $search = strtolower(trim(
            implode(' ', array_filter([
                $subscriptionType->name ?? null,
                $subscriptionType->slug ?? null,
            ]))
        ));

        $sku = match (true) {
            str_contains($search, 'milk') =>
            'SERVICE-DELIVERY-MILK',

            str_contains($search, 'vegetable'),
            str_contains($search, 'veg') =>
            'SERVICE-DELIVERY-VEGETABLE',

            str_contains($search, 'fruit') =>
            'SERVICE-DELIVERY-FRUIT',

            str_contains($search, 'grocery') =>
            'SERVICE-DELIVERY-GROCERY',

            str_contains($search, 'medicine'),
            str_contains($search, 'medical'),
            str_contains($search, 'pharma') =>
            'SERVICE-DELIVERY-MEDICINE',

            default => null,
        };

        if (!$sku || !isset($deliveryVariants[$sku])) {
            return null;
        }

        return (int) $deliveryVariants[$sku];
    }

    private function decodeMeta($meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (!is_string($meta) || trim($meta) === '') {
            return [];
        }

        $decoded = json_decode($meta, true);

        return is_array($decoded) ? $decoded : [];
    }
};
