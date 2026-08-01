<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VendorZoneService;
use App\Models\WorkmanZoneService;
use App\Models\ServiceApplicationDocument;
use App\Models\ServiceVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Pagination\LengthAwarePaginator;

class UserServiceController extends Controller
{

    protected function getApplicationModel(string $type)
    {
        return match ($type) {
            'vendor' => VendorZoneService::class,
            'workman' => WorkmanZoneService::class,
            default => null,
        };
    }

    protected function resolveApplication(string $type, int $id)
    {
        $model = $this->getApplicationModel($type);

        abort_if(!$model, 404, 'Invalid application type.');

        return $model::with([
            'documents',
            'approver',
            'serviceVariant.service',
            $type,
        ])->findOrFail($id);
    }

    protected function getUserRelation(string $role)
    {
        return match ($role) {
            'vendor' => 'vendor',
            'workman' => 'workman',
            default => null,
        };
    }

    protected function formatApplication($application, string $type): array
    {
        $provider = $type === 'vendor'
            ? $application->vendor
            : $application->workman;

        return [
            'id' => (int) $application->id,

            // Required because vendor and workman tables can have the same ID
            'application_type' => $type,
            'role_name' => $type,

            // Keep old-style user_id for Flutter/admin compatibility
            'user_id' => $type === 'vendor'
                ? (int) $application->vendor_id
                : (int) $application->workman_id,

            'vendor_id' => $type === 'vendor'
                ? (int) $application->vendor_id
                : null,

            'workman_id' => $type === 'workman'
                ? (int) $application->workman_id
                : null,

            'zone_id' => $application->zone_id
                ? (int) $application->zone_id
                : null,

            'service_variant_id' => $application->service_variant_id
                ? (int) $application->service_variant_id
                : null,

            'service_handle' => data_get(
                $application,
                'serviceVariant.meta.handle'
            ),

            'service_title' => data_get(
                $application,
                'serviceVariant.service.title'
            ),

            'service_variant_title' => data_get(
                $application,
                'serviceVariant.title'
            ),

            'service_variant_sku' => data_get(
                $application,
                'serviceVariant.sku'
            ),

            'status' => $application->status,
            'is_active' => (bool) $application->is_active,

            'approved_by' => $application->approved_by
                ? (int) $application->approved_by
                : null,

            'approved_at' => optional(
                $application->approved_at
            )->toISOString(),

            'rejection_reason' => $application->rejection_reason,

            'is_preferred' => (bool) $application->is_preferred,
            'lead_time_mins' => $application->lead_time_mins !== null
                ? (int) $application->lead_time_mins
                : null,

            'meta' => $application->meta ?? [],

            // Preserve old response key expected by screens
            'user' => $provider,

            'provider' => $provider,
            'approver' => $application->approver,

            'service_variant' => $application->serviceVariant,

            'documents' => $application->documents
                ? $application->documents->values()
                : [],

            'created_at' => optional(
                $application->created_at
            )->toISOString(),

            'updated_at' => optional(
                $application->updated_at
            )->toISOString(),
        ];
    }

    protected function resolveServiceVariantId(array $data): ?int
    {
        /*
    |--------------------------------------------------------------------------
    | New flow: Flutter sends exact service_variant_id
    |--------------------------------------------------------------------------
    */

        if (!empty($data['service_variant_id'])) {
            return ServiceVariant::query()
                ->where('variant_id', (int) $data['service_variant_id'])
                ->value('variant_id');
        }

        /*
    |--------------------------------------------------------------------------
    | Temporary legacy compatibility
    |--------------------------------------------------------------------------
    */

        $serviceHandle = strtolower(
            trim((string) ($data['service_handle'] ?? ''))
        );

        $subscriptionTypeId = !empty($data['subscription_type_id'])
            ? (int) $data['subscription_type_id']
            : null;

        /*
    |--------------------------------------------------------------------------
    | Existing milk/vendor/delivery-boy handles
    |--------------------------------------------------------------------------
    */

        if (in_array($serviceHandle, [
            'milk',
            'milk-supplier',
            'milk-and-dairy',
            'delivery-boy',
            'workman-delivery-boy',
            'milk-delivery',
        ], true)) {
            return ServiceVariant::query()
                ->where('sku', 'SERVICE-DELIVERY-MILK')
                ->value('variant_id');
        }

        /*
    |--------------------------------------------------------------------------
    | Resolve delivery variant from subscription type
    |--------------------------------------------------------------------------
    */

        if ($subscriptionTypeId) {
            $subscriptionType = DB::table('subscription_types')
                ->where('id', $subscriptionTypeId)
                ->first();

            if ($subscriptionType) {
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

                if ($sku) {
                    $variantId = ServiceVariant::query()
                        ->where('sku', $sku)
                        ->value('variant_id');

                    if ($variantId) {
                        return (int) $variantId;
                    }
                }
            }
        }

        /*
    |--------------------------------------------------------------------------
    | Match exact variant handle stored in meta
    |--------------------------------------------------------------------------
    */

        if ($serviceHandle !== '') {
            $variantId = ServiceVariant::query()
                ->whereRaw(
                    "JSON_UNQUOTE(JSON_EXTRACT(meta, '$.handle')) = ?",
                    [$serviceHandle]
                )
                ->value('variant_id');

            if ($variantId) {
                return (int) $variantId;
            }
        }

        /*
    |--------------------------------------------------------------------------
    | Temporary fallback:
    | broad service handle such as building-painter, carpenter, plumbing
    | resolves to that service's first variant.
    |--------------------------------------------------------------------------
    */

        if ($serviceHandle !== '') {
            $variantId = ServiceVariant::query()
                ->join(
                    'services',
                    'services.service_id',
                    '=',
                    'service_variants.service_id'
                )
                ->where('services.handle', $serviceHandle)
                ->orderBy('service_variants.variant_id')
                ->value('service_variants.variant_id');

            if ($variantId) {
                return (int) $variantId;
            }
        }

        return null;
    }

    protected function applicationQueryForRole(
        string $roleName,
        int $userId,
        int $zoneId,
        int $serviceVariantId
    ) {
        if ($roleName === 'vendor') {
            return VendorZoneService::query()
                ->where('vendor_id', $userId)
                ->where('zone_id', $zoneId)
                ->where('service_variant_id', $serviceVariantId);
        }

        return WorkmanZoneService::query()
            ->where('workman_id', $userId)
            ->where('zone_id', $zoneId)
            ->where('service_variant_id', $serviceVariantId);
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'pending',
                    'under_review',
                    'approved',
                    'rejected',
                    'inactive',
                    'suspended',
                ]),
            ],

            'role_name' => [
                'nullable',
                'string',
                Rule::in([
                    'vendor',
                    'workman',
                ]),
            ],

            'service_variant_id' =>
            'nullable|integer|exists:service_variants,variant_id',

            'service_handle' =>
            'nullable|string|max:100',

            'zone_id' =>
            'nullable|integer|exists:zones,id',

            'user_id' =>
            'nullable|integer|exists:users,id',

            'per_page' =>
            'nullable|integer|min:1|max:100',

            'page' =>
            'nullable|integer|min:1',
        ]);

        $perPage = (int) ($data['per_page'] ?? 20);
        $page = (int) ($data['page'] ?? 1);

        $roleName = $data['role_name'] ?? null;
        $status = $data['status'] ?? null;
        $zoneId = $data['zone_id'] ?? null;
        $userId = $data['user_id'] ?? null;
        $serviceVariantId = $data['service_variant_id'] ?? null;
        $serviceHandle = trim((string) ($data['service_handle'] ?? ''));

        $applications = collect();

        /*
    |--------------------------------------------------------------------------
    | Vendor applications
    |--------------------------------------------------------------------------
    */

        if ($roleName === null || $roleName === 'vendor') {
            $vendorApplications = VendorZoneService::query()
                ->with([
                    'vendor',
                    'documents',
                    'approver',
                    'serviceVariant.service',
                ])
                ->when(
                    $status,
                    fn($query) =>
                    $query->where('status', $status)
                )
                ->when(
                    $zoneId,
                    fn($query) =>
                    $query->where('zone_id', $zoneId)
                )
                ->when(
                    $userId,
                    fn($query) =>
                    $query->where('vendor_id', $userId)
                )
                ->when(
                    $serviceVariantId,
                    fn($query) =>
                    $query->where(
                        'service_variant_id',
                        $serviceVariantId
                    )
                )
                ->when(
                    $serviceHandle !== '',
                    function ($query) use ($serviceHandle) {
                        $query->whereHas(
                            'serviceVariant',
                            function ($variantQuery) use ($serviceHandle) {
                                $variantQuery
                                    ->where(
                                        'sku',
                                        $serviceHandle
                                    )
                                    ->orWhere(
                                        'title',
                                        $serviceHandle
                                    )
                                    ->orWhereRaw(
                                        "JSON_UNQUOTE(
                                        JSON_EXTRACT(meta, '$.handle')
                                    ) = ?",
                                        [$serviceHandle]
                                    );
                            }
                        );
                    }
                )
                ->get()
                ->map(
                    fn($application) =>
                    $this->formatApplication(
                        $application,
                        'vendor'
                    )
                );

            $applications = $applications->concat(
                $vendorApplications
            );
        }

        /*
    |--------------------------------------------------------------------------
    | Workman applications
    |--------------------------------------------------------------------------
    */

        if ($roleName === null || $roleName === 'workman') {
            $workmanApplications = WorkmanZoneService::query()
                ->with([
                    'workman',
                    'documents',
                    'approver',
                    'serviceVariant.service',
                ])
                ->when(
                    $status,
                    fn($query) =>
                    $query->where('status', $status)
                )
                ->when(
                    $zoneId,
                    fn($query) =>
                    $query->where('zone_id', $zoneId)
                )
                ->when(
                    $userId,
                    fn($query) =>
                    $query->where('workman_id', $userId)
                )
                ->when(
                    $serviceVariantId,
                    fn($query) =>
                    $query->where(
                        'service_variant_id',
                        $serviceVariantId
                    )
                )
                ->when(
                    $serviceHandle !== '',
                    function ($query) use ($serviceHandle) {
                        $query->whereHas(
                            'serviceVariant',
                            function ($variantQuery) use ($serviceHandle) {
                                $variantQuery
                                    ->where(
                                        'sku',
                                        $serviceHandle
                                    )
                                    ->orWhere(
                                        'title',
                                        $serviceHandle
                                    )
                                    ->orWhereRaw(
                                        "JSON_UNQUOTE(
                                        JSON_EXTRACT(meta, '$.handle')
                                    ) = ?",
                                        [$serviceHandle]
                                    );
                            }
                        );
                    }
                )
                ->get()
                ->map(
                    fn($application) =>
                    $this->formatApplication(
                        $application,
                        'workman'
                    )
                );

            $applications = $applications->concat(
                $workmanApplications
            );
        }

        /*
    |--------------------------------------------------------------------------
    | Sort and paginate combined results
    |--------------------------------------------------------------------------
    */

        $applications = $applications
            ->sortByDesc(function (array $row) {
                return $row['created_at']
                    ?? $row['updated_at']
                    ?? '';
            })
            ->values();

        $total = $applications->count();

        $pageItems = $applications
            ->slice(($page - 1) * $perPage, $perPage)
            ->values();

        $paginator = new LengthAwarePaginator(
            $pageItems,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return response()->json([
            'ok' => true,
            'data' => $paginator,
        ]);
    }

    public function pendingApprovals(Request $request)
    {
        $data = $request->validate([
            'role_name' => [
                'nullable',
                'string',
                Rule::in(['vendor', 'workman']),
            ],

            'service_variant_id' =>
            'nullable|integer|exists:service_variants,variant_id',

            'service_handle' =>
            'nullable|string|max:100',

            'zone_id' =>
            'nullable|integer|exists:zones,id',

            'per_page' =>
            'nullable|integer|min:1|max:100',

            'page' =>
            'nullable|integer|min:1',
        ]);

        $user = $request->user();

        if (! $user || ! $user->hasAnyRole(['admin', 'zone-manager'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $perPage = (int) ($data['per_page'] ?? 20);
        $page = (int) ($data['page'] ?? 1);

        $roleName = $data['role_name'] ?? null;
        $serviceVariantId = $data['service_variant_id'] ?? null;
        $serviceHandle = trim((string) ($data['service_handle'] ?? ''));

        $requestedZoneId = isset($data['zone_id'])
            ? (int) $data['zone_id']
            : null;

        $zoneId = $user->hasRole('zone-manager')
            ? (int) $user->zone_id
            : $requestedZoneId;

        $applications = collect();

        /*
    |--------------------------------------------------------------------------
    | Vendor pending applications
    |--------------------------------------------------------------------------
    */

        if ($roleName === null || $roleName === 'vendor') {
            $vendorApplications = VendorZoneService::query()
                ->with([
                    'vendor',
                    'documents',
                    'approver',
                    'serviceVariant.service',
                ])
                ->whereIn('status', [
                    'pending',
                    'under_review',
                ])
                ->when(
                    $zoneId,
                    fn($query) =>
                    $query->where('zone_id', $zoneId)
                )
                ->when(
                    $serviceVariantId,
                    fn($query) =>
                    $query->where(
                        'service_variant_id',
                        $serviceVariantId
                    )
                )
                ->when(
                    $serviceHandle !== '',
                    function ($query) use ($serviceHandle) {
                        $query->whereHas(
                            'serviceVariant',
                            function ($variantQuery) use ($serviceHandle) {
                                $variantQuery
                                    ->where(
                                        'sku',
                                        $serviceHandle
                                    )
                                    ->orWhere(
                                        'title',
                                        $serviceHandle
                                    )
                                    ->orWhereRaw(
                                        "JSON_UNQUOTE(
                                        JSON_EXTRACT(meta, '$.handle')
                                    ) = ?",
                                        [$serviceHandle]
                                    );
                            }
                        );
                    }
                )
                ->get()
                ->map(
                    fn($application) =>
                    $this->formatApplication(
                        $application,
                        'vendor'
                    )
                );

            $applications = $applications->concat(
                $vendorApplications
            );
        }

        /*
    |--------------------------------------------------------------------------
    | Workman pending applications
    |--------------------------------------------------------------------------
    */

        if ($roleName === null || $roleName === 'workman') {
            $workmanApplications = WorkmanZoneService::query()
                ->with([
                    'workman',
                    'documents',
                    'approver',
                    'serviceVariant.service',
                ])
                ->whereIn('status', [
                    'pending',
                    'under_review',
                ])
                ->when(
                    $zoneId,
                    fn($query) =>
                    $query->where('zone_id', $zoneId)
                )
                ->when(
                    $serviceVariantId,
                    fn($query) =>
                    $query->where(
                        'service_variant_id',
                        $serviceVariantId
                    )
                )
                ->when(
                    $serviceHandle !== '',
                    function ($query) use ($serviceHandle) {
                        $query->whereHas(
                            'serviceVariant',
                            function ($variantQuery) use ($serviceHandle) {
                                $variantQuery
                                    ->where(
                                        'sku',
                                        $serviceHandle
                                    )
                                    ->orWhere(
                                        'title',
                                        $serviceHandle
                                    )
                                    ->orWhereRaw(
                                        "JSON_UNQUOTE(
                                        JSON_EXTRACT(meta, '$.handle')
                                    ) = ?",
                                        [$serviceHandle]
                                    );
                            }
                        );
                    }
                )
                ->get()
                ->map(
                    fn($application) =>
                    $this->formatApplication(
                        $application,
                        'workman'
                    )
                );

            $applications = $applications->concat(
                $workmanApplications
            );
        }

        $applications = $applications
            ->sortByDesc(function (array $row) {
                return $row['created_at']
                    ?? $row['updated_at']
                    ?? '';
            })
            ->values();

        $total = $applications->count();

        $pageItems = $applications
            ->slice(($page - 1) * $perPage, $perPage)
            ->values();

        $paginator = new LengthAwarePaginator(
            $pageItems,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return response()->json([
            'ok' => true,
            'data' => $paginator,
        ]);
    }
    public function myServices(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthenticated',
                'data' => [],
            ], 401);
        }

        $vendorApplications = VendorZoneService::query()
            ->with([
                'vendor',
                'documents',
                'approver',
                'serviceVariant.service',
            ])
            ->where('vendor_id', $user->id)
            ->get()
            ->map(
                fn($application) =>
                $this->formatApplication(
                    $application,
                    'vendor'
                )
            );

        $workmanApplications = WorkmanZoneService::query()
            ->with([
                'workman',
                'documents',
                'approver',
                'serviceVariant.service',
            ])
            ->where('workman_id', $user->id)
            ->get()
            ->map(
                fn($application) =>
                $this->formatApplication(
                    $application,
                    'workman'
                )
            );

        $applications = $vendorApplications
            ->concat($workmanApplications)
            ->sortByDesc(function (array $row) {
                return $row['created_at']
                    ?? $row['updated_at']
                    ?? '';
            })
            ->values();

        return response()->json([
            'ok' => true,
            'data' => $applications,
        ]);
    }
    public function apply(Request $request)
    {
        $data = $request->validate([
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],

            'role_name' => [
                'required',
                'string',
                Rule::in([
                    'vendor',
                    'workman',
                ]),
            ],

            /*
        |--------------------------------------------------------------------------
        | New field
        |--------------------------------------------------------------------------
        */

            'service_variant_id' => [
                'nullable',
                'integer',
                'exists:service_variants,variant_id',
            ],

            /*
        |--------------------------------------------------------------------------
        | Temporary old-app compatibility
        |--------------------------------------------------------------------------
        */

            'service_handle' => [
                'nullable',
                'string',
                'max:100',
            ],

            'subscription_type_id' => [
                'nullable',
                'integer',
            ],

            'zone_id' => [
                'required',
                'integer',
                'exists:zones,id',
            ],

            'meta' => [
                'nullable',
                'array',
            ],
        ]);

        $serviceVariantId = $this->resolveServiceVariantId($data);

        if (!$serviceVariantId) {
            return response()->json([
                'ok' => false,
                'message' => 'Unable to identify the selected service variant.',
                'errors' => [
                    'service_variant_id' => [
                        'Select a valid service variant.',
                    ],
                ],
            ], 422);
        }

        $serviceVariant = ServiceVariant::query()
            ->with('service')
            ->where('variant_id', $serviceVariantId)
            ->firstOrFail();

        return DB::transaction(function () use (
            $data,
            $serviceVariant,
            $serviceVariantId
        ) {
            $user = User::findOrFail((int) $data['user_id']);

            $roleName = (string) $data['role_name'];
            $zoneId = (int) $data['zone_id'];

            /*
        |--------------------------------------------------------------------------
        | Assign only the base provider role
        |--------------------------------------------------------------------------
        */

            if (!$user->hasRole($roleName)) {
                $user->assignRole($roleName);
            }

            /*
        |--------------------------------------------------------------------------
        | Find existing application
        |--------------------------------------------------------------------------
        */

            $existing = $this->applicationQueryForRole(
                $roleName,
                (int) $user->id,
                $zoneId,
                $serviceVariantId
            )->first();

            /*
        |--------------------------------------------------------------------------
        | Vendor document reuse flag
        |--------------------------------------------------------------------------
        */

            $hasApprovedVendor = false;

            if ($roleName === 'vendor') {
                $hasApprovedVendor = VendorZoneService::query()
                    ->where('vendor_id', $user->id)
                    ->where('status', 'approved')
                    ->where('is_active', true)
                    ->exists();
            }

            $meta = array_merge(
                $data['meta'] ?? [],
                [
                    'skip_documents' =>
                    $roleName === 'vendor'
                        ? $hasApprovedVendor
                        : false,

                    'legacy_service_handle' =>
                    $data['service_handle'] ?? null,

                    'legacy_subscription_type_id' =>
                    $data['subscription_type_id'] ?? null,
                ]
            );

            /*
        |--------------------------------------------------------------------------
        | Already approved
        |--------------------------------------------------------------------------
        */

            if ($existing && $existing->status === 'approved') {
                $existing->load([
                    $roleName,
                    'documents',
                    'approver',
                    'serviceVariant.service',
                ]);

                return response()->json([
                    'ok' => true,
                    'message' => 'Service already approved.',
                    'data' => $this->formatApplication(
                        $existing,
                        $roleName
                    ),
                ], 200);
            }

            /*
        |--------------------------------------------------------------------------
        | Already pending / under review
        |--------------------------------------------------------------------------
        */

            if (
                $existing &&
                in_array(
                    $existing->status,
                    ['pending', 'under_review'],
                    true
                )
            ) {
                $existing->load([
                    $roleName,
                    'documents',
                    'approver',
                    'serviceVariant.service',
                ]);

                return response()->json([
                    'ok' => true,
                    'message' => 'Service request already submitted.',
                    'data' => $this->formatApplication(
                        $existing,
                        $roleName
                    ),
                ], 200);
            }

            /*
        |--------------------------------------------------------------------------
        | Rejected / inactive / suspended: resubmit same row
        |--------------------------------------------------------------------------
        */

            if (
                $existing &&
                in_array(
                    $existing->status,
                    ['rejected', 'inactive', 'suspended'],
                    true
                )
            ) {
                $existing->update([
                    'status' => 'pending',
                    'is_active' => false,
                    'approved_by' => null,
                    'approved_at' => null,
                    'rejection_reason' => null,
                    'meta' => array_merge(
                        $existing->meta ?? [],
                        $meta
                    ),
                ]);

                $existing->load([
                    $roleName,
                    'documents',
                    'approver',
                    'serviceVariant.service',
                ]);

                return response()->json([
                    'ok' => true,
                    'message' => 'Service re-submitted successfully.',
                    'data' => $this->formatApplication(
                        $existing,
                        $roleName
                    ),
                ], 200);
            }

            /*
        |--------------------------------------------------------------------------
        | Create first application
        |--------------------------------------------------------------------------
        */

            $payload = [
                'zone_id' => $zoneId,
                'service_variant_id' => $serviceVariantId,
                'status' => 'pending',
                'is_active' => false,
                'approved_by' => null,
                'approved_at' => null,
                'rejection_reason' => null,
                'is_preferred' => false,
                'lead_time_mins' => null,
                'meta' => $meta,
            ];

            if ($roleName === 'vendor') {
                $payload['vendor_id'] = (int) $user->id;

                $application = VendorZoneService::create($payload);
            } else {
                $payload['workman_id'] = (int) $user->id;

                $application = WorkmanZoneService::create($payload);
            }

            $application->load([
                $roleName,
                'documents',
                'approver',
                'serviceVariant.service',
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Service application saved successfully.',
                'data' => $this->formatApplication(
                    $application,
                    $roleName
                ),
            ], 201);
        });
    }
    public function uploadDocument(
        Request $request,
        string $type,
        int $applicationId
    ) {
        $data = $request->validate([
            'document_type' => [
                'required',
                Rule::in([
                    'profile_photo',
                    'aadhaar_front',
                    'aadhaar_back',
                    'pan_card',
                    'driving_license',
                    'vehicle_rc',
                    'bank_proof',
                    'business_proof',
                ]),
            ],
            'file' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
        ]);

        if (!in_array($type, ['vendor', 'workman'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid application type.',
            ], 422);
        }

        $application = $this->resolveApplication(
            $type,
            $applicationId
        );

        $providerId = $type === 'vendor'
            ? (int) $application->vendor_id
            : (int) $application->workman_id;

        $variantHandle = data_get(
            $application,
            'serviceVariant.meta.handle',
            'general'
        );

        $folder =
            'service-applications/user_' . $providerId .
            '/' . $type .
            '/' . $variantHandle;

        $file = $request->file('file');
        $path = $file->store($folder, 'public');

        $document = $application->documents()->create([
            'document_type' => $data['document_type'],
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'status' => 'uploaded',
            'remarks' => null,
            'meta' => null,
        ]);

        if ($application->status === 'pending') {
            $application->update([
                'status' => 'under_review',
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Document uploaded successfully.',
            'data' => $document,
        ], 201);
    }
    public function approve(
        Request $request,
        string $type,
        int $applicationId
    ) {
        $data = $request->validate([
            'remarks' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $user = $request->user();

        if (! $user || ! $user->hasAnyRole(['admin', 'zone-manager'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if (!in_array($type, ['vendor', 'workman'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid application type.',
            ], 422);
        }

        $application = $this->resolveApplication(
            $type,
            $applicationId
        );

        if (
            $user->hasRole('zone-manager') &&
            (int) $application->zone_id !== (int) $user->zone_id
        ) {
            return response()->json([
                'ok' => false,
                'message' => 'You can approve only your zone services.',
            ], 403);
        }

        $variantSku = (string) data_get(
            $application,
            'serviceVariant.sku',
            ''
        );

        $skipDocuments = (bool) data_get(
            $application->meta,
            'skip_documents',
            false
        );

        $requiredDocs = [];

        if (!$skipDocuments) {
            if ($type === 'vendor') {
                $requiredDocs = [
                    'profile_photo',
                    'aadhaar_front',
                    'aadhaar_back',
                    'pan_card',
                ];
            }

            if (
                $type === 'workman' &&
                $variantSku === 'SERVICE-DELIVERY-MILK'
            ) {
                $requiredDocs = [
                    'profile_photo',
                    'aadhaar_front',
                    'aadhaar_back',
                    'pan_card',
                    'driving_license',
                ];
            }
        }

        $uploadedDocs = $application
            ->documents()
            ->pluck('document_type')
            ->unique()
            ->values()
            ->toArray();

        $missingDocs = array_values(
            array_diff(
                $requiredDocs,
                $uploadedDocs
            )
        );

        if (!empty($missingDocs)) {
            return response()->json([
                'ok' => false,
                'message' => 'Missing required documents.',
                'missing_documents' => $missingDocs,
            ], 422);
        }

        DB::transaction(function () use (
            $application,
            $request,
            $data,
            $type,
            $variantSku
        ) {
            $application->update([
                'status' => 'approved',
                'is_active' => true,
                'approved_by' => optional(
                    $request->user()
                )->id,
                'approved_at' => now(),
                'rejection_reason' => null,
                'meta' => array_merge(
                    $application->meta ?? [],
                    [
                        'approval_remarks' =>
                        $data['remarks'] ?? null,
                    ]
                ),
            ]);

            $application->documents()
                ->whereIn('status', [
                    'uploaded',
                    'rejected',
                ])
                ->update([
                    'status' => 'verified',
                    'remarks' => $data['remarks'] ?? null,
                ]);

            $provider = $type === 'vendor'
                ? $application->vendor
                : $application->workman;

            /*
        |--------------------------------------------------------------------------
        | Specialized roles
        |--------------------------------------------------------------------------
        */

            if (
                $provider &&
                $type === 'vendor' &&
                $variantSku === 'SERVICE-DELIVERY-MILK'
            ) {
                if (!$provider->hasRole('vendor-milk')) {
                    $provider->assignRole('vendor-milk');
                }
            }

            if (
                $provider &&
                $type === 'workman' &&
                $variantSku === 'SERVICE-DELIVERY-MILK'
            ) {
                if (!$provider->hasRole('workman-delivery-boy')) {
                    $provider->assignRole('workman-delivery-boy');
                }
            }
        });

        $application = $this->resolveApplication(
            $type,
            $applicationId
        );

        return response()->json([
            'ok' => true,
            'message' => 'Service approved successfully.',
            'data' => $this->formatApplication(
                $application,
                $type
            ),
        ]);
    }

    public function reject(
        Request $request,
        string $type,
        int $applicationId
    ) {
        $data = $request->validate([
            'rejection_reason' => [
                'required',
                'string',
                'max:2000',
            ],

            'document_ids' => [
                'nullable',
                'array',
            ],

            'document_ids.*' => [
                'integer',
                'exists:service_application_documents,id',
            ],
        ]);

        $user = $request->user();

        if (! $user || ! $user->hasAnyRole(['admin', 'zone-manager'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if (!in_array($type, ['vendor', 'workman'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid application type.',
            ], 422);
        }

        $application = $this->resolveApplication(
            $type,
            $applicationId
        );

        if (
            $user->hasRole('zone-manager') &&
            (int) $application->zone_id !== (int) $user->zone_id
        ) {
            return response()->json([
                'ok' => false,
                'message' => 'You can reject only your zone services.',
            ], 403);
        }

        DB::transaction(function () use (
            $application,
            $request,
            $data
        ) {
            $application->update([
                'status' => 'rejected',
                'is_active' => false,
                'approved_by' => optional(
                    $request->user()
                )->id,
                'approved_at' => now(),
                'rejection_reason' =>
                $data['rejection_reason'],
            ]);

            if (!empty($data['document_ids'])) {
                $application->documents()
                    ->whereIn(
                        'id',
                        $data['document_ids']
                    )
                    ->update([
                        'status' => 'rejected',
                        'remarks' =>
                        $data['rejection_reason'],
                    ]);
            }
        });

        $application = $this->resolveApplication(
            $type,
            $applicationId
        );

        return response()->json([
            'ok' => true,
            'message' => 'Service rejected successfully.',
            'data' => $this->formatApplication(
                $application,
                $type
            ),
        ]);
    }

    public function deleteDocument(
        Request $request,
        int $documentId
    ) {
        $document = ServiceApplicationDocument::query()
            ->with('documentable')
            ->findOrFail($documentId);

        $application = $document->documentable;

        if (!$application) {
            return response()->json([
                'ok' => false,
                'message' => 'Service application not found.',
            ], 404);
        }

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $ownerId = $application instanceof VendorZoneService
            ? (int) $application->vendor_id
            : (int) $application->workman_id;

        $canManage = (int) $user->id === $ownerId
            || $user->hasAnyRole(['admin', 'zone-manager']);

        if (!$canManage) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if (
            $user->hasRole('zone-manager') &&
            (int) $application->zone_id !== (int) $user->zone_id
        ) {
            return response()->json([
                'ok' => false,
                'message' => 'You can manage only your zone documents.',
            ], 403);
        }

        if (
            $document->file_path &&
            Storage::disk('public')->exists($document->file_path)
        ) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Document deleted successfully.',
        ]);
    }

    public function resubmit(
        Request $request,
        string $type,
        int $applicationId
    ) {
        if (!in_array($type, ['vendor', 'workman'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid application type.',
            ], 422);
        }

        $application = $this->resolveApplication(
            $type,
            $applicationId
        );

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $ownerId = $type === 'vendor'
            ? (int) $application->vendor_id
            : (int) $application->workman_id;

        if (
            (int) $user->id !== $ownerId &&
            !$user->hasAnyRole(['admin', 'zone-manager'])
        ) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if (
            $user->hasRole('zone-manager') &&
            (int) $application->zone_id !== (int) $user->zone_id
        ) {
            return response()->json([
                'ok' => false,
                'message' => 'You can manage only your zone services.',
            ], 403);
        }

        if (!in_array(
            $application->status,
            ['rejected', 'inactive', 'suspended'],
            true
        )) {
            return response()->json([
                'ok' => false,
                'message' => 'Only rejected, inactive, or suspended applications can be resubmitted.',
                'data' => $this->formatApplication(
                    $application,
                    $type
                ),
            ], 422);
        }

        $application->update([
            'status' => 'pending',
            'is_active' => false,
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => null,
            'meta' => array_merge(
                $application->meta ?? [],
                [
                    'resubmitted_at' => now()->toDateTimeString(),
                ]
            ),
        ]);

        $application = $this->resolveApplication(
            $type,
            $applicationId
        );

        return response()->json([
            'ok' => true,
            'message' => 'Service re-submitted successfully.',
            'data' => $this->formatApplication(
                $application,
                $type
            ),
        ]);
    }

    public function show(string $type, int $applicationId)
    {
        if (!in_array($type, ['vendor', 'workman'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid application type.',
            ], 422);
        }

        $application = $this->resolveApplication(
            $type,
            $applicationId
        );

        return response()->json([
            'ok' => true,
            'data' => $this->formatApplication(
                $application,
                $type
            ),
        ]);
    }
}
