<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\MyOrdersController;
use App\Http\Controllers\MobileController;

use App\Http\Controllers\Api\OtpAuthController;
use App\Http\Controllers\Api\OrderApiController;
use App\Http\Controllers\Api\SubscriptionSelectionController;
use App\Http\Controllers\Api\SubscriptionsApiController;
use App\Http\Controllers\Api\SubscriptionChangeController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\MyWorkController;
use App\Http\Controllers\Api\MySuppliesController; // ✅ ADD (create controller)
use App\Http\Controllers\Api\ZoneApiController;
use App\Http\Controllers\Api\AdminBillingController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\UserServiceController;
use App\Http\Controllers\SesWebhookController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\OutboxReportController;
use App\Http\Controllers\Api\NoOrderDeliveryController;
use App\Http\Controllers\Api\MobileHomeController;
use App\Http\Controllers\Api\MobileCategoryController;
use App\Http\Controllers\Api\MobileMyDayController;
use App\Http\Controllers\Api\MyDayNoteController;
use App\Http\Controllers\Api\MyDayTodoController;
use App\Http\Controllers\Api\MyDayRoutineController;
use App\Http\Controllers\Api\MyDayLikeController;
use App\Http\Controllers\Api\MobileMyDayVaultController;
use App\Http\Controllers\Api\FoodMenuController;
use App\Http\Controllers\Api\FoodRegimenController;
use App\Http\Controllers\Api\HomeFoodOrderController;
use App\Http\Controllers\Api\FoodMenuTodayController;



use App\Http\Controllers\Api\ShopifyCartController;
use App\Http\Controllers\Api\ShopifyWebhookController;
use App\Services\FcmService;
use App\Services\MyDayFeedService;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Public routes + Sanctum protected routes (Bearer token)
|--------------------------------------------------------------------------
*/

// ==============================
// PUBLIC (no auth)
// ==============================

// OTP auth (public)
Route::post('/auth/send-otp', [OtpAuthController::class, 'sendOtp']);
Route::post('/auth/verify-otp', [OtpAuthController::class, 'verifyOtp']);
Route::middleware('auth:sanctum')->post('/logout', [OtpAuthController::class, 'logout']);

// Webhooks
Route::post('/ses/feedback', [SesWebhookController::class, 'handle']);

// Catalog / discovery
Route::get('/subscriptions', [MobileController::class, 'getSubscriptions']);
Route::get('/service-types', [MobileController::class, 'getServiceTypes']);
Route::get('/subscription-sub-types/{id}', [MobileController::class, 'getSubscriptionSubTypes']);
Route::get('/subscription-types', [MobileController::class, 'getSubscriptionTypes']);

Route::get('/products/{subTypeId}', [MobileController::class, 'productsBySubType']);
Route::get('/products/{productId}/variants', [MobileController::class, 'productVariants']);

// Serviceability helpers
Route::get('/check-pincode/{pincode}', [MobileController::class, 'checkPincode']);
Route::get('/check-location', [MobileController::class, 'checkLocation']);

// ✅ Zone resolve must be PUBLIC (needed during login/signup before token)
Route::get('/zone/resolve', [ZoneApiController::class, 'resolveFromLatLng']);

Route::get('/mobile/home', [MobileHomeController::class, 'index']);
Route::get('/mobile/category', [MobileCategoryController::class, 'index']);











Route::post('/mobile/shopify/cart/create', [ShopifyCartController::class, 'create']);
Route::post('/mobile/cart/add', [ShopifyCartController::class, 'add']);
Route::get('/mobile/cart', [ShopifyCartController::class, 'show']);
Route::post('/mobile/cart/update-qty', [ShopifyCartController::class, 'updateQty']);
Route::post('/mobile/cart/remove', [ShopifyCartController::class, 'remove']);
Route::post('/mobile/cart/checkout', [ShopifyCartController::class, 'checkout']);
Route::get('/mobile/shopify-cart/latest-order', [ShopifyCartController::class, 'latestShopifyOrder']);

Route::post('/webhooks/shopify/orders-create', [ShopifyWebhookController::class, 'ordersCreate']);

Route::post('/no-order-delivery/send', [NoOrderDeliveryController::class, 'send']);
Route::get('/no-order-delivery/options', [NoOrderDeliveryController::class, 'options']);
Route::get('/no-order-delivery/products', [NoOrderDeliveryController::class, 'products']);
Route::get('/no-order-delivery/vendors', [NoOrderDeliveryController::class, 'vendors']);
Route::get('/no-order-delivery/vendor-products', [NoOrderDeliveryController::class, 'vendorProducts']);
Route::get('/no-order-delivery/delivery-execs', [NoOrderDeliveryController::class, 'deliveryExecs']);
// ==============================
// PROTECTED (Sanctum Bearer token)
// ==============================

// ================= HOME FOOD PUBLIC =================

Route::get('/home-food/products/{mealType}', [FoodMenuController::class, 'getProductsByMealType']);
Route::get('/home-food/menu/{productId}', [FoodMenuController::class, 'getChefMenus']);

Route::middleware('auth:sanctum')->group(function () {

    // --------------------------
    // User / Profile
    // --------------------------
    Route::get('/user', fn(Request $request) => $request->user());
    Route::get('/me', [MeController::class, 'show']);
    Route::get('/notifications', [MeController::class, 'notifications']);
    Route::get('/operator/customers', [MeController::class, 'operatorCustomers']);
    Route::get('/operator/customer-subscriptions', [SubscriptionsApiController::class, 'operatorCustomerSubscriptions']);
    Route::post('/profile/service', [ProfileController::class, 'saveServiceProfile']);

    // =============================
    // DAYLI SERVICES
    // =============================

    Route::get(
        '/mobile/services/categories',
        [MobileController::class, 'mobileServiceCategories']
    );

    Route::get(
        '/mobile/services',
        [MobileController::class, 'mobileServicesByCategory']
    );

    Route::get(
        '/mobile/services/{serviceId}/variants',
        [MobileController::class, 'mobileServiceVariants']
    );

    Route::get(
        '/mobile/my/services',
        [MobileController::class, 'getMyProviderServices']
    );

    Route::post(
        '/mobile/my/services/add',
        [MobileController::class, 'addMyProviderService']
    );

    Route::get(
        '/mobile/my/service-requests',
        [MobileController::class, 'getMyProviderServiceRequests']
    );

    // AI Service Request Flow


    Route::post('/mobile/service-requests/create', [MobileController::class, 'createMobileServiceRequest']);

    Route::get('/mobile/my/service-requests', [MobileController::class, 'getMyProviderServiceRequests']);

    Route::post('/mobile/service-requests/{requestId}/respond', [MobileController::class, 'submitServiceRequestResponse']);

    Route::get('/mobile/service-requests/{requestId}/feed', [MobileController::class, 'getServiceRequestFeed']);

    Route::post('/mobile/service-requests/{requestId}/select-providers', [MobileController::class, 'selectServiceRequestProviders']);

    Route::get(
        '/mobile/customer/service-requests',
        [MobileController::class, 'getCustomerServiceRequests']
    );

    Route::get('/mobile/my/service-assignments', [MobileController::class, 'getMyServiceAssignments']);

    Route::post('/mobile/service-assignments/{assignmentId}/status', [MobileController::class, 'updateServiceAssignmentStatus']);


    // --------------------------
    // User Services (User side)
    // --------------------------
    Route::get('/my-user-services', [UserServiceController::class, 'myServices']);

    Route::prefix('user-services')->group(function () {
        Route::post('/apply', [UserServiceController::class, 'apply']);
        Route::post('/{userServiceId}/documents', [UserServiceController::class, 'uploadDocument']);
        Route::get('/{userServiceId}', [UserServiceController::class, 'show']);
    });

    Route::delete('/user-service-documents/{documentId}', [UserServiceController::class, 'deleteDocument']);

    // Device tokens (Push notifications)
    Route::post('/device-tokens', [DeviceTokenController::class, 'store']);

    // --------------------------
    // Orders
    // --------------------------
    Route::get('/my-orders', [MyOrdersController::class, 'index']);
    Route::get('/orders/{id}', [OrderApiController::class, 'show']); // protected only

    // --------------------------
    // Subscriptions (Customer view)
    // --------------------------
    Route::get('/my-subscriptions', [SubscriptionsApiController::class, 'index']);

    // Add subscriptions from selection (bulk)
    Route::post('/my-subscriptions/store-from-selection', [SubscriptionSelectionController::class, 'store']);

    // Update subscription item (edit)
    Route::put('/my-subscriptions/items/{item}', [SubscriptionSelectionController::class, 'updateItem']);

    // Pause / Cancel / Resume / Restart
    Route::post('/my-subscriptions/items/{item}/pause',   [SubscriptionsApiController::class, 'pause']);
    Route::post('/my-subscriptions/items/{item}/cancel',  [SubscriptionsApiController::class, 'cancel']);
    Route::post('/my-subscriptions/items/{item}/resume',  [SubscriptionsApiController::class, 'resume']);
    Route::post('/my-subscriptions/items/{item}/restart', [SubscriptionsApiController::class, 'restart']);

    Route::post('/subscriptions/raise-dispute', [SubscriptionsApiController::class, 'raiseDispute']);

    // Change endpoint (pause/cancel request payload)
    Route::post('/my-subscriptions/change', [SubscriptionChangeController::class, 'store']);

    // --------------------------
    // Admin billing
    // --------------------------
    Route::prefix('admin/billing')->group(function () {
        Route::get('/search', [AdminBillingController::class, 'search']);
        Route::get('/user-unpaid', [AdminBillingController::class, 'userUnpaid']);
        Route::get('/unpaid-invoices', [AdminBillingController::class, 'unpaidInvoices']);
        Route::post('/collect-payment', [AdminBillingController::class, 'collectPayment']);
        Route::post('/inward-payment', [AdminBillingController::class, 'storeInwardPayment']);
        Route::post('/inward-payment-auto', [AdminBillingController::class, 'storeInwardPaymentAuto']);
        Route::post('/inward-payment-allocations', [AdminBillingController::class, 'storeInwardPaymentAllocations']);
    });

    Route::prefix('zone-manager')->group(function () {
        Route::get('/outbox-reports', [OutboxReportController::class, 'index']);
        Route::get('/outbox-reports/{id}', [OutboxReportController::class, 'show']);
        Route::post('/outbox-reports/{id}/generate', [OutboxReportController::class, 'generate']);
        Route::post('/zone-manager/outbox-reports/{id}/send', [OutboxReportController::class, 'send']);
        Route::get('/my-invoices', [OutboxReportController::class, 'myInvoices']);
    });

    // ==============================
    // Reconciliation Reports
    Route::middleware(['auth:sanctum'])->prefix('reconcile-reports')->group(function () {

        Route::get('/', [\App\Http\Controllers\Api\ReconcileReportController::class, 'index']);

        // save exception from UI
        Route::post('/exception', [\App\Http\Controllers\Api\ReconcileReportController::class, 'storeException']);
        Route::get('/exceptions', [\App\Http\Controllers\Api\ReconcileReportController::class, 'exceptionReports']);

        Route::get('/{id}', [\App\Http\Controllers\Api\ReconcileReportController::class, 'show']);
    });

    // --------------------------
    // My Work (Delivery Boy)
    // --------------------------
    Route::middleware(['approved.service:workman,workman-delivery-boy'])->prefix('my-work')->group(function () {
        // Screen summary
        Route::get('/summary', [MyWorkController::class, 'summary']);

        // Orders list + date dropdown + task list
        Route::get('/orders', [MeController::class, 'myWorkOrders']);

        // Add-item flow
        Route::get('/add-item-products', [MeController::class, 'getAddItemProducts']);
        Route::get('/add-item-options',  [MeController::class, 'addItemOptions']);
        Route::post('/item/update-qty',  [MeController::class, 'updateItemQty']);
        Route::post('/create-order',     [MeController::class, 'createOrderFromMyWork']);
        Route::get('/manual-today-order-preview', [MeController::class, 'manualTodayOrderPreview']);
        Route::post('/manual-today-order', [MeController::class, 'createManualTodayOrder']);
        Route::post('/manual-new-customer-order', [MeController::class, 'createManualNewCustomerOrder']);

        // Subscription type picker
        Route::get('/subscription-types', [MeController::class, 'myWorkSubscriptionTypes']);

        // Task lifecycle
        Route::get('/',               [MyWorkController::class, 'index']);
        Route::post('/{id}/start',    [MyWorkController::class, 'start']);
        Route::post('/{id}/complete', [MyWorkController::class, 'complete']);

        // Product master
        Route::get('/all-products', [MyWorkController::class, 'allProducts']);
    });

    // --------------------------
    // My Supplies (Vendor)
    // NOTE: Create controller MySuppliesController with same methods as MyWorkController/MeController flow.
    // Backend must filter supplier side: scr.party_type = 'supplier'
    // --------------------------
    Route::middleware(['approved.service:vendor,milk-and-dairy'])->prefix('my-supplies')->group(function () {
        Route::get('/summary', [MySuppliesController::class, 'summary']);
        Route::get('/orders',  [MySuppliesController::class, 'orders']);

        Route::get('/add-item-products', [MySuppliesController::class, 'getAddItemProducts']);
        Route::get('/add-item-options',  [MySuppliesController::class, 'addItemOptions']);
        Route::post('/item/update-qty',  [MySuppliesController::class, 'updateItemQty']);
        Route::post('/create-order',     [MySuppliesController::class, 'createOrderFromMySupplies']);

        Route::get('/subscription-types', [MySuppliesController::class, 'subscriptionTypes']);

        Route::get('/',               [MySuppliesController::class, 'index']);
        Route::post('/{id}/start',    [MySuppliesController::class, 'start']);
        Route::post('/{id}/complete', [MySuppliesController::class, 'complete']);

        Route::get('/all-products', [MySuppliesController::class, 'allProducts']);
    });

    Route::post('/push/test', function (Request $request) {
        $user = $request->user();

        $tokenRow = \App\Models\DeviceToken::where('user_id', $user->id)
            ->where('is_valid', true)
            ->latest()
            ->firstOrFail();

        $payload = [
            'title' => 'Dayli Test',
            'body'  => 'Push working ✅',
            'data'  => [
                'type' => 'test',
                'entity_id' => '0',
            ],
        ];

        $res = app(\App\Services\FcmService::class)->sendToToken($tokenRow->token, $payload);

        return response()->json(['ok' => true, 'fcm' => $res]);
    });

    Route::get('/mobile/my-day', [MobileMyDayController::class, 'index']);

    Route::get('/mobile/myday/vault', [MobileMyDayVaultController::class, 'show']);

    Route::put('/mobile/myday/vault', [MobileMyDayVaultController::class, 'update']);

    Route::post('/mobile/myday/vault/upload', [MobileMyDayVaultController::class, 'upload']);

    Route::get('/mobile/myday/feed', function (MyDayFeedService $service) {

        $rawKeys = request('keys', 'gita,health,fun_fact');

        $allowedKeys = [
            'weather',
            'astro',
            'quote',
            'gita',
            'story',
            'movies',
            'music',
            'news',
            'cricket',
            'gold',
            'silver',
            'petrol',
            'diesel',
            'health',
            'recipe',
            'fun_fact',
        ];

        $keys = collect(explode(',', $rawKeys))
            ->map(fn($key) => trim((string) $key))
            ->filter(fn($key) => $key !== '' && in_array($key, $allowedKeys, true))
            ->unique()
            ->take(5)
            ->values();

        $items = $keys->map(function ($key) use ($service) {
            try {
                return $service->makeFeedItem($key) + ['interest_key' => $key];
            } catch (\Throwable $e) {
                return [
                    'interest_key' => $key,
                    'title' => ucfirst(str_replace('_', ' ', $key)),
                    'subtitle' => 'MyDay update',
                    'body' => 'This update is temporarily unavailable.',
                    'payload_json' => ['error' => $e->getMessage()],
                ];
            }
        })->values();

        return response()->json([
            'success' => true,
            'items' => $items,
        ]);
    });

    Route::get('/mobile/my-day/notes', [MyDayNoteController::class, 'index']);
    Route::post('/mobile/my-day/notes', [MyDayNoteController::class, 'store']);
    Route::put('/mobile/my-day/notes/{id}', [MyDayNoteController::class, 'update']);
    Route::delete('/mobile/my-day/notes/{id}', [MyDayNoteController::class, 'destroy']);

    Route::get('/mobile/my-day/todos', [MyDayTodoController::class, 'index']);
    Route::post('/mobile/my-day/todos', [MyDayTodoController::class, 'store']);
    Route::put('/mobile/my-day/todos/{id}', [MyDayTodoController::class, 'update']);
    Route::post('/mobile/my-day/todos/{id}/complete', [MyDayTodoController::class, 'complete']);
    Route::post('/mobile/my-day/todos/{id}/reopen', [MyDayTodoController::class, 'reopen']);
    Route::delete('/mobile/my-day/todos/{id}', [MyDayTodoController::class, 'destroy']);

    Route::get('/mobile/my-day/routines', [MyDayRoutineController::class, 'index']);
    Route::post('/mobile/my-day/routines', [MyDayRoutineController::class, 'store']);
    Route::put('/mobile/my-day/routines/{id}', [MyDayRoutineController::class, 'update']);
    Route::post('/mobile/my-day/routines/{id}/complete', [MyDayRoutineController::class, 'complete']);
    Route::post('/mobile/my-day/routines/{id}/skip', [MyDayRoutineController::class, 'skip']);
    Route::post('/mobile/my-day/routines/{id}/reopen', [MyDayRoutineController::class, 'reopen']);
    Route::delete('/mobile/my-day/routines/{id}', [MyDayRoutineController::class, 'destroy']);

    Route::get('/mobile/my-day/likes/options', [MyDayLikeController::class, 'options']);
    Route::get('/mobile/my-day/likes', [MyDayLikeController::class, 'index']);
    Route::post('/mobile/my-day/likes', [MyDayLikeController::class, 'save']);

    // ----------------------------------
    // Home-made Food
    // ----------------------------------
    Route::apiResource('food-menus', FoodMenuController::class);

    Route::apiResource('food-regimens', FoodRegimenController::class);

    Route::post(
        '/home-food/orders',
        [HomeFoodOrderController::class, 'store']
    );
    Route::get('/home-food/chef/orders', [HomeFoodOrderController::class, 'chefOrders']);

    // ================== HOME FOOD ==================

    Route::post('/chef/food-menus', [FoodMenuController::class, 'store']);
    Route::get('/chef/food-menus', [FoodMenuController::class, 'index']);
    Route::get('/chef/food-menus/{id}', [FoodMenuController::class, 'show']);
    Route::put('/chef/food-menus/{id}', [FoodMenuController::class, 'update']);
    Route::delete('/chef/food-menus/{id}', [FoodMenuController::class, 'destroy']);

    // Today's Menu
    Route::get('/chef/food-menu-catalogue', [FoodMenuTodayController::class, 'chefFoodMenus']);
    Route::post('/chef/food-menu-today', [FoodMenuTodayController::class, 'store']);
    Route::get('/chef/food-menu-today', [FoodMenuTodayController::class, 'index']);

    Route::put('/chef/food-menu-today/{id}', [FoodMenuTodayController::class, 'update']);
    Route::delete('/chef/food-menu-today/{id}', [FoodMenuTodayController::class, 'destroy']);

    Route::post('/chef/food-menu-today/broadcast', [FoodMenuTodayController::class, 'broadcastAllToday']);
    Route::post('/chef/food-menu-today/{id}/broadcast', [FoodMenuTodayController::class, 'broadcast']);

    // Customer
    Route::get('/food/today', [FoodMenuTodayController::class, 'customerTodayFood']);
    Route::post('/food/preorder', [FoodMenuTodayController::class, 'preorder']);

    // Chef Preorders
    Route::get('/chef/food-preorders', [FoodMenuTodayController::class, 'preorders']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/admin/user-services', [UserServiceController::class, 'index']);
    Route::get('/admin/user-services/pending-approvals', [UserServiceController::class, 'pendingApprovals']);
    Route::post('/admin/user-services/{userServiceId}/approve', [UserServiceController::class, 'approve']);
    Route::post('/admin/user-services/{userServiceId}/reject', [UserServiceController::class, 'reject']);
});


// Route::get('/mobile/home', [MobileHomeController::class, 'index']);
// Route::get('/mobile/category', [MobileCategoryController::class, 'index']);
