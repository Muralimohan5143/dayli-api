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
use App\Http\Controllers\Api\ZoneApiController;
use App\Http\Controllers\Api\AdminBillingController;


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

// Webhooks
Route::post('/ses/feedback', [\App\Http\Controllers\SesWebhookController::class, 'handle']);

// Catalog / discovery
Route::get('/subscriptions', [MobileController::class, 'getSubscriptions']);
Route::get('/service-types', [MobileController::class, 'getServiceTypes']);
Route::get('/subscription-sub-types/{id}', [MobileController::class, 'getSubscriptionSubTypes']);

Route::get('/products/{subTypeId}', [MobileController::class, 'productsBySubType']);
Route::get('/products/{productId}/variants', [MobileController::class, 'productVariants']);

// Serviceability helpers
Route::get('/check-pincode/{pincode}', [MobileController::class, 'checkPincode']);
Route::get('/check-location', [MobileController::class, 'checkLocation']);

// ✅ Zone resolve must be PUBLIC (needed during login/signup before token)
Route::get('/zone/resolve', [ZoneApiController::class, 'resolveFromLatLng']);


// ==============================
// PROTECTED (Sanctum Bearer token)
// ==============================
Route::middleware('auth:sanctum')->group(function () {

    // User info
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Role-aware profile for app
    Route::get('/me', [MeController::class, 'show']);
    Route::get('/my-work/summary', [MyWorkController::class, 'summary']);

    Route::get('/my-work/orders', [MeController::class, 'myWorkOrders']);

    Route::get('/my-work/add-item-products', [MeController::class, 'getAddItemProducts']);
    Route::post('/my-work/item/update-qty', [MeController::class, 'updateItemQty']);
    Route::post('/my-work/create-order', [MeController::class, 'createOrderFromMyWork']);



    Route::get('/my-work/add-item-options', [MeController::class, 'addItemOptions']);


    Route::get('/my-work/subscription-types', [MeController::class, 'myWorkSubscriptionTypes']);



    Route::get('/admin/billing/search', [AdminBillingController::class, 'search']);
    Route::get('/admin/billing/user-unpaid', [AdminBillingController::class, 'userUnpaid']);
    Route::get('/admin/billing/unpaid-invoices', [AdminBillingController::class, 'unpaidInvoices']);
    Route::post('/admin/billing/collect-payment', [AdminBillingController::class, 'collectPayment']);
    Route::post('/admin/billing/inward-payment', [AdminBillingController::class, 'storeInwardPayment']);
    Route::post('/admin/billing/inward-payment-auto', [AdminBillingController::class, 'storeInwardPaymentAuto']);
    Route::post('/admin/billing/inward-payment-allocations', [AdminBillingController::class, 'storeInwardPaymentAllocations']);






    // Orders
    Route::get('/my-orders', [MyOrdersController::class, 'index']);
    Route::get('/orders/{id}', [OrderApiController::class, 'show']); // protected only

    // Subscriptions
    Route::get('/my-subscriptions', [SubscriptionsApiController::class, 'index']);

    // Add subscriptions from selection (bulk)
    Route::post('/my-subscriptions/store-from-selection', [
        SubscriptionSelectionController::class,
        'store',
    ]);

    // Update subscription item (edit)
    Route::put('/my-subscriptions/items/{item}', [
        SubscriptionSelectionController::class,
        'updateItem',
    ]);

    // Pause / Cancel / Resume / Restart
    Route::post('/my-subscriptions/items/{item}/pause', [
        SubscriptionsApiController::class,
        'pause',
    ]);

    Route::post('/my-subscriptions/items/{item}/cancel', [
        SubscriptionsApiController::class,
        'cancel',
    ]);

    Route::post('/my-subscriptions/items/{item}/resume', [
        SubscriptionsApiController::class,
        'resume',
    ]);

    Route::post('/my-subscriptions/items/{item}/restart', [
        SubscriptionsApiController::class,
        'restart',
    ]);

    Route::post('/subscriptions/raise-dispute', [SubscriptionsApiController::class, 'raiseDispute']);


    // Change endpoint (pause/cancel request payload)
    Route::post('/my-subscriptions/change', [
        SubscriptionChangeController::class,
        'store',
    ]);

    // ✅ My Work (Delivery Boy)
    Route::get('/my-work', [MyWorkController::class, 'index']);
    Route::post('/my-work/{id}/start', [MyWorkController::class, 'start']);
    Route::post('/my-work/{id}/complete', [MyWorkController::class, 'complete']);
    Route::get('/my-work/all-products', [\App\Http\Controllers\Api\MyWorkController::class, 'allProducts']);
});
