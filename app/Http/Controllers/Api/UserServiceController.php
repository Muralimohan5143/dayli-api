<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserService;
use App\Models\UserServiceDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserServiceController extends Controller
{

    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => 'nullable|string|in:pending,under_review,approved,rejected,inactive,suspended',
            'role_name' => 'nullable|string|in:customer,vendor,workman',
            'service_handle' => 'nullable|string|max:100',
            'zone_id' => 'nullable|integer',
            'user_id' => 'nullable|integer|exists:users,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = $data['per_page'] ?? 20;

        $query = \App\Models\UserService::query()
            ->with(['user', 'documents', 'approver'])
            ->latest('id');

        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (!empty($data['role_name'])) {
            $query->where('role_name', $data['role_name']);
        }

        if (!empty($data['service_handle'])) {
            $query->where('service_handle', $data['service_handle']);
        }

        if (!empty($data['zone_id'])) {
            $query->where('zone_id', $data['zone_id']);
        }

        if (!empty($data['user_id'])) {
            $query->where('user_id', $data['user_id']);
        }

        return response()->json([
            'ok' => true,
            'data' => $query->paginate($perPage),
        ]);
    }

    public function pendingApprovals(Request $request)
    {
        $data = $request->validate([
            'role_name' => 'nullable|string|in:vendor,workman',
            'service_handle' => 'nullable|string|max:100',
            'zone_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = $data['per_page'] ?? 20;

        $query = \App\Models\UserService::query()
            ->with(['user', 'documents'])
            ->whereIn('status', ['pending', 'under_review'])
            ->latest('id');

        if (!empty($data['role_name'])) {
            $query->where('role_name', $data['role_name']);
        }

        if (!empty($data['service_handle'])) {
            $query->where('service_handle', $data['service_handle']);
        }

        if (!empty($data['zone_id'])) {
            $query->where('zone_id', $data['zone_id']);
        }

        return response()->json([
            'ok' => true,
            'data' => $query->paginate($perPage),
        ]);
    }

    public function myServices(Request $request)
    {
        $user = $request->user();

        $services = $user->userServices()
            ->with(['documents', 'approver'])
            ->latest('id')
            ->get();

        return response()->json([
            'ok' => true,
            'data' => $services,
        ]);
    }
    public function apply(Request $request)
    {
        $data = $request->validate([
            'user_id'               => 'required|exists:users,id',
            'role_name'             => ['required', Rule::in(['customer', 'vendor', 'workman'])],
            'service_handle'        => 'nullable|string|max:100',
            'subscription_type_id'  => 'nullable|integer',
            'zone_id'               => 'nullable|integer',
            'meta'                  => 'nullable|array',
        ]);

        return DB::transaction(function () use ($data) {
            $user = User::findOrFail($data['user_id']);

            // assign spatie role
            if (! $user->hasRole($data['role_name'])) {
                $user->assignRole($data['role_name']);
            }

            // optional specialized role for current existing app logic
            if ($data['role_name'] === 'vendor' && ($data['service_handle'] ?? null) === 'milk') {
                if (! $user->hasRole('vendor-milk')) {
                    $user->assignRole('vendor-milk');
                }
            }

            if ($data['role_name'] === 'workman' && ($data['service_handle'] ?? null) === 'delivery-boy') {
                if (! $user->hasRole('workman-delivery-boy')) {
                    $user->assignRole('workman-delivery-boy');
                }
            }

            $userService = UserService::updateOrCreate(
                [
                    'user_id'              => $user->id,
                    'role_name'            => $data['role_name'],
                    'service_handle'       => $data['service_handle'] ?? null,
                    'subscription_type_id' => $data['subscription_type_id'] ?? null,
                    'zone_id'              => $data['zone_id'] ?? null,
                ],
                [
                    'status'      => $data['role_name'] === 'customer' ? 'approved' : 'pending',
                    'is_active'   => true,
                    'meta'        => $data['meta'] ?? null,
                ]
            );

            return response()->json([
                'ok' => true,
                'message' => 'Service application saved successfully.',
                'data' => $userService->load('documents'),
            ]);
        });
    }

    public function uploadDocument(Request $request, $userServiceId)
    {
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
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $userService = UserService::findOrFail($userServiceId);

        $folder = 'user-services/user_' . $userService->user_id . '/' .
            $userService->role_name . '-' . ($userService->service_handle ?: 'general');

        $file = $request->file('file');
        $path = $file->store($folder, 'public');

        $doc = UserServiceDocument::create([
            'user_service_id' => $userService->id,
            'document_type'   => $data['document_type'],
            'file_path'       => $path,
            'file_name'       => $file->getClientOriginalName(),
            'mime_type'       => $file->getClientMimeType(),
            'file_size'       => $file->getSize(),
            'status'          => 'uploaded',
        ]);

        if ($userService->status === 'pending') {
            $userService->update(['status' => 'under_review']);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Document uploaded successfully.',
            'data' => $doc,
        ]);
    }

    public function approve(Request $request, $userServiceId)
    {
        $data = $request->validate([
            'remarks' => 'nullable|string',
        ]);

        $userService = \App\Models\UserService::with(['user', 'documents'])->findOrFail($userServiceId);

        $requiredDocs = match (true) {
            $userService->role_name === 'vendor' => [
                'profile_photo',
                'aadhaar_front',
                'aadhaar_back',
                'pan_card',
            ],
            $userService->role_name === 'workman' && $userService->service_handle === 'delivery-boy' => [
                'profile_photo',
                'aadhaar_front',
                'aadhaar_back',
                'pan_card',
                'driving_license',
            ],
            default => [],
        };

        $uploadedDocs = $userService->documents()
            ->pluck('document_type')
            ->unique()
            ->values()
            ->toArray();

        $missingDocs = array_values(array_diff($requiredDocs, $uploadedDocs));

        if (!empty($missingDocs)) {
            return response()->json([
                'ok' => false,
                'message' => 'Missing required documents.',
                'missing_documents' => $missingDocs,
            ], 422);
        }

        DB::transaction(function () use ($userService, $request, $data) {
            $userService->update([
                'status'           => 'approved',
                'is_active'        => true,
                'approved_by'      => optional($request->user())->id,
                'approved_at'      => now(),
                'rejection_reason' => null,
                'meta'             => array_merge($userService->meta ?? [], [
                    'approval_remarks' => $data['remarks'] ?? null,
                ]),
            ]);

            $userService->documents()
                ->whereIn('status', ['uploaded', 'rejected'])
                ->update([
                    'status' => 'verified',
                    'remarks' => $data['remarks'] ?? null,
                ]);

            if (
                $userService->role_name === 'workman' &&
                $userService->service_handle === 'delivery-boy'
            ) {
                // optional future logic
                // create delivery assignment seed row or enable delivery flow
            }
        });

        return response()->json([
            'ok' => true,
            'message' => 'Service approved successfully.',
            'data' => $userService->fresh()->load(['user', 'documents', 'approver']),
        ]);
    }

    public function reject(Request $request, $userServiceId)
    {
        $data = $request->validate([
            'rejection_reason' => 'required|string|max:2000',
            'document_ids' => 'nullable|array',
            'document_ids.*' => 'integer|exists:user_service_documents,id',
        ]);

        $userService = \App\Models\UserService::with('documents')->findOrFail($userServiceId);

        DB::transaction(function () use ($userService, $request, $data) {
            $userService->update([
                'status'           => 'rejected',
                'is_active'        => false,
                'approved_by'      => optional($request->user())->id,
                'approved_at'      => now(),
                'rejection_reason' => $data['rejection_reason'],
            ]);

            if (!empty($data['document_ids'])) {
                $userService->documents()
                    ->whereIn('id', $data['document_ids'])
                    ->update([
                        'status' => 'rejected',
                        'remarks' => $data['rejection_reason'],
                    ]);
            }
        });

        return response()->json([
            'ok' => true,
            'message' => 'Service rejected successfully.',
            'data' => $userService->fresh()->load(['user', 'documents', 'approver']),
        ]);
    }

    public function deleteDocument(Request $request, $documentId)
    {
        $document = \App\Models\UserServiceDocument::with('userService')->findOrFail($documentId);

        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Document deleted successfully.',
        ]);
    }

    public function show($userServiceId)
    {
        $userService = UserService::with(['user', 'documents', 'approver'])->findOrFail($userServiceId);

        return response()->json([
            'ok' => true,
            'data' => $userService,
        ]);
    }
}
