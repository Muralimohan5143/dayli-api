<?php

use Illuminate\Support\Facades\Route;
use App\Http\Livewire\SubChangeRequests\GroupedByType;
use App\Http\Livewire\SubChangeRequests\Index as SubChangeRequestsIndex;
use App\Http\Livewire\SubChangeRequests\Create as SubChangeRequestsCreate;
use App\Http\Livewire\SubChangeRequests\Edit as SubChangeRequestsEdit;
use App\Http\Livewire\SubChangeRequests\Delete as SubChangeRequestsDelete;

use App\Http\Livewire\SubDeliveryActuals\Index as SubDeliveryActualsIndex;
use App\Http\Livewire\SubDeliveryActuals\Create as SubDeliveryActualsCreate;
use App\Http\Livewire\SubDeliveryActuals\Edit as SubDeliveryActualsEdit;
use App\Http\Livewire\SubDeliveryActuals\Delete as SubDeliveryActualsDelete;


use App\Http\Livewire\Dashboard\DashboardDefault;
use App\Http\Livewire\Dashboard\Vr\VirtualReality;
use App\Http\Livewire\Dashboard\Vr\VirtualInfo;
use App\Http\Livewire\Dashboard\Automotive;
use App\Http\Livewire\Dashboard\SmartHome;
use App\Http\Livewire\Dashboard\Crm;

use App\Http\Livewire\Pages\Profile\Overview;
use App\Http\Livewire\Pages\Profile\AllProjects;
use App\Http\Livewire\Pages\Profile\Teams;

use App\Http\Livewire\MyWork\VendorTypeActuals;
use App\Http\Livewire\MyWork\DeliveryActuals; // (stub included below)




use App\Http\Livewire\Pages\Users\Reports;
use App\Http\Livewire\Pages\Users\NewUser;

use App\Http\Livewire\Pages\Account\Settings;
use App\Http\Livewire\Pages\Account\Billing;
use App\Http\Livewire\Pages\Account\Invoice;
use App\Http\Livewire\Pages\Account\Security;

use App\Http\Livewire\Pages\Projects\General;
use App\Http\Livewire\Pages\Projects\Timeline;
use App\Http\Livewire\Pages\Projects\NewProject;

use App\Http\Livewire\Pages\Pricing;
use App\Http\Livewire\Pages\Rtl;
use App\Http\Livewire\Pages\Widgets;

use App\Http\Livewire\Applications\Analytics;
use App\Http\Livewire\Applications\Calendar;
use App\Http\Livewire\Applications\Datatables;
use App\Http\Livewire\Applications\Kanban;
use App\Http\Livewire\Applications\Wizard;

use App\Http\Livewire\Ecommerce\EcommerceOverview;
use App\Http\Livewire\Ecommerce\Referral;

use App\Http\Livewire\Ecommerce\Products\EditProduct;
use App\Http\Livewire\Ecommerce\Products\NewProduct;
use App\Http\Livewire\Ecommerce\Products\ProductPage;
use App\Http\Livewire\Ecommerce\Products\ProductsList;

use App\Http\Livewire\Ecommerce\Orders\OrderDetails;
use App\Http\Livewire\Ecommerce\Orders\OrderList;

use App\Http\Livewire\Authentication\Error\Error404;
use App\Http\Livewire\Authentication\Error\Error500;

use App\Http\Livewire\Authentication\Lock\LockBasic;
use App\Http\Livewire\Authentication\Lock\LockCover;
use App\Http\Livewire\Authentication\Lock\LockIllustration;

use App\Http\Livewire\Authentication\Reset\ResetBasic;
use App\Http\Livewire\Authentication\Reset\ResetCover;
use App\Http\Livewire\Authentication\Reset\ResetIllustration;

use App\Http\Livewire\Authentication\Signin\SigninBasic;
use App\Http\Livewire\Authentication\Signin\SigninCover;
use App\Http\Livewire\Authentication\Signin\SigninIllustration;

use App\Http\Livewire\Authentication\Signup\SignupBasic;
use App\Http\Livewire\Authentication\Signup\SignupCover;
use App\Http\Livewire\Authentication\Signup\SignupIllustration;

use App\Http\Livewire\Authentication\Verification\VerificationBasic;
use App\Http\Livewire\Authentication\Verification\VerificationCover;
use App\Http\Livewire\Authentication\Verification\VerificationIllustration;

use App\Http\Livewire\Auth\Login;
use App\Http\Livewire\Auth\Register;
use App\Http\Livewire\Auth\ForgotPassword;
use App\Http\Livewire\Auth\ResetPassword;

use App\Http\Livewire\LaravelExamples\UsersManagement;
use App\Http\Livewire\LaravelExamples\EditUser;
use App\Http\Livewire\LaravelExamples\LaravelNewUser;
use App\Http\Livewire\LaravelExamples\UserProfile;
use App\Http\Livewire\LaravelExamples\RolesManagement;
use App\Http\Livewire\LaravelExamples\EditRole;
// use App\Http\Livewire\LaravelExamples\NewRole;
use App\Http\Livewire\LaravelExamples\CategoryManagement;
use App\Http\Livewire\LaravelExamples\NewCategory;
use App\Http\Livewire\LaravelExamples\EditCategory;
use App\Http\Livewire\LaravelExamples\TagsManagement;
use App\Http\Livewire\LaravelExamples\NewTag;
use App\Http\Livewire\LaravelExamples\EditTag;
use App\Http\Livewire\LaravelExamples\ItemsManagement;
use App\Http\Livewire\LaravelExamples\NewItem;
use App\Http\Livewire\LaravelExamples\EditItem;
use App\Http\Livewire\LaravelExamples\Error\PageError;

use App\Http\Controllers\PriceController;
use App\Http\Livewire\Ecommerce\ManagePrices;
use App\Http\Livewire\Ecommerce\Subscriptions\SubscrTypesManagement;
use App\Http\Livewire\Ecommerce\Subscriptions\NewSubscrType;
use App\Http\Livewire\Ecommerce\Subscriptions\EditSubscrType;

use App\Http\Livewire\Pages\Users\UserAttrTypesManagement;
use App\Http\Livewire\Pages\Users\NewUserAttrType;
use App\Http\Livewire\Pages\Users\EditUserAttrType;

use App\Http\Livewire\Pages\Users\PermissionsManagement;
use App\Http\Livewire\Pages\Users\NewPermission;
use App\Http\Livewire\Pages\Users\EditPermission;


use App\Http\Livewire\Leads\CreateLead;
use App\Http\Livewire\Leads\EditLead;
use App\Http\Livewire\Leads\LeadList;
use App\Http\Controllers\LeadController;
use App\Http\Livewire\Auth\OtpLogin;
use App\Http\Livewire\Auth\Signin;
use App\Http\Livewire\Zones\Index as ZonesIndex;
use App\Http\Controllers\SubscriptionPagesController;
use App\Http\Controllers\Vendor\Milk\DashboardController;
use App\Http\Livewire\VendorSignup\VendorWizard;
use App\Http\Livewire\Auth\VendorWorkmanLogin;
use App\Http\Livewire\Signup\VendorSignupWizard;
use App\Http\Livewire\Vendor\Milk\Dashboard as VendorMilkDashboard;


Route::get('/vendor-workman-login', VendorWorkmanLogin::class)
    ->name('vendor.workman.login');


// ✅ Route for Vendor/Workman Login







/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group.  Now create something great!
|
*/


Route::get('/', function () {
    return redirect('/login');
})->name('default');

Route::get('/downloadapp', function () {
    return view('downloadapp');
})->name('downloadapp');

// Route::get('/vendor-signup', function () {
//     $step = (int) request('step', 1);         // 1=signin, 2=contract, 3=profile
//     $type = request('type', 'milk');          // milk | veg | ...
//     return view('livewire.vendor-signup.index   ', compact('step', 'type'));
// })->name('vendor.signup');


Route::get('/vendor-signup', VendorSignupWizard::class)->name('vendor.signup');
Route::get('/login', Signin::class)->name('login');
//Route::get('/pages-profile-overview', ...)->name('overview');
//Route::get('/vendor/contract', VendorSignupWizard::class)->name('vendor.signup2');
Route::get('/register', Register::class)->name('register');
Route::get('/forgot-password', ForgotPassword::class)->name('forgot-password');
Route::get('/reset-password/{id}', ResetPassword::class)->name('reset-password')->middleware('signed');


Route::middleware('auth')->group(function () {

    // ✅ Default page after login
    Route::get('/overview', Overview::class)->name('overview');

    // ✅ Subscriptions
    Route::prefix('sub-change-requests')->name('sub-change-requests.')->group(function () {
        Route::get('/', GroupedByType::class)->name('grouped');
        Route::get('/index', SubChangeRequestsIndex::class)->name('index');
        // Route::get('/create', SubChangeRequestsCreate::class)->name('create');
        Route::get('/{id}/edit', SubChangeRequestsEdit::class)->name('edit');
        Route::get('/{id}/delete', SubChangeRequestsDelete::class)->name('delete');
    });

    Route::prefix('sub-delivery-actuals')->name('sub-delivery-actuals.')->group(function () {
        Route::get('/', SubDeliveryActualsIndex::class)->name('index');
        // Route::get('/create', SubDeliveryActualsCreate::class)->name('create');
        Route::get('/{id}/edit', SubDeliveryActualsEdit::class)->name('edit');
        Route::get('/{id}/delete', SubDeliveryActualsDelete::class)->name('delete');
    });

    Route::get('/zones', ZonesIndex::class)->name('zones.index');



    // ✅ My Work section
    Route::get('/mywork/overview', fn() => view('mywork.overview'))
        ->name('mywork.overview')
        ->middleware(['role:admin|zones-director|zones-head|zone-manager']);

    Route::get('/mywork/reconciliation', fn() => view('mywork.reconciliation'))
        ->name('mywork.reconciliation')
        ->middleware(['role:admin|zones-director|zones-head|zone-manager']);

    Route::get('/mywork/delivery/actuals', DeliveryActuals::class)
        ->name('mywork.delivery.actuals')
        ->middleware(['role:workman|workman-delivery-boy|workman-delivery-boy-milk|workman-delivery-boy-grocery']);

    Route::get('/mywork/vendor/{typeId}', VendorTypeActuals::class)
        ->whereNumber('typeId')
        ->name('mywork.vendor.type')
        ->middleware(['role:vendor|vendor-milk|vendor-vegetable|vendor-meat|vendor-grocery']);








    // Coming Soon placeholder view
    Route::view('/coming-soon', 'coming-soon')->name('coming-soon');

    // ===================== User Management =====================
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', fn() => redirect()->route('coming-soon'))->name('index');
        Route::get('/create', fn() => redirect()->route('coming-soon'))->name('create');
        Route::get('/{id}/edit', fn() => redirect()->route('coming-soon'))->name('edit');
    });

    Route::prefix('roles')->name('roles.')->group(function () {
        Route::get('/', fn() => redirect()->route('coming-soon'))->name('index');
        Route::get('/create', fn() => redirect()->route('coming-soon'))->name('create');
        Route::get('/{id}/edit', fn() => redirect()->route('coming-soon'))->name('edit');
    });

    // ===================== Technology =====================
    Route::prefix('tech')->name('tech.')->group(function () {
        Route::get('/email-status', fn() => redirect()->route('coming-soon'))->name('email-status');
        Route::get('/platform-health', fn() => redirect()->route('coming-soon'))->name('platform-health');
    });
});





Route::middleware(['auth'])->group(function () {
    Route::view('/subscriptions', 'subscriptions.index')->name('subs.index');
    Route::get('/subscriptions/products/{type}', [SubscriptionPagesController::class, 'product'])->name('subs.products.show');
    Route::get('/subscriptions/services/{type}', [SubscriptionPagesController::class, 'service'])->name('subs.services.show');
});





    // Route::get('/dashboard-default', DashboardDefault::class)->name('default');
    // Route::get('/dashboard-virtual-reality', VirtualReality::class)->name('virtual-reality');
    // Route::get('/dashboard-virtual-info', VirtualInfo::class)->name('virtual-info');
    // Route::get('/dashboard-automotive', Automotive::class)->name('automotive');
    // Route::get('/dashboard-smart-home', SmartHome::class)->name('smart-home');
    // Route::get('/dashboard-crm', Crm::class)->name('crm');

    // Route::get('/pages-profile-overview', Overview::class)->name('overview');
    // Route::get('/pages-profile-all-projects', AllProjects::class)->name('all-projects');
    // Route::get('/pages-profile-teams', Teams::class)->name('teams');

    // Route::get('/pages-users-reports', Reports::class)->name('reports');
    // Route::get('/pages-users-new', NewUser::class)->name('new-user');


    // Route::get('/pages-account-settings', Settings::class)->name('settings');
    // Route::get('/pages-account-billing', Billing::class)->name('billing');
    // Route::get('/pages-account-invoice', Invoice::class)->name('invoice');
    // Route::get('/pages-account-security', Security::class)->name('security');

    // Route::get('/pages-projects-general', General::class)->name('general');
    // Route::get('/pages-projects-timeline', Timeline::class)->name('timeline');
    // Route::get('/pages-projects-new-project', NewProject::class)->name('new-project');

    // Route::get('/pages-pricing', Pricing::class)->name('pricing');
    // Route::get('/pages-widgets', Widgets::class)->name('widgets');
    // Route::get('/pages-rtl', Rtl::class)->name('rtl');

    // Route::get('/applications-analytics', Analytics::class)->name('analytics');
    // Route::get('/applications-calendar', Calendar::class)->name('calendar');
    // Route::get('/applications-datatables', Datatables::class)->name('datatables');
    // Route::get('/applications-kanban', Kanban::class)->name('kanban');
    // Route::get('/applications-wizard', Wizard::class)->name('wizard');

    // Route::get('/ecommerce-overview', EcommerceOverview::class)->name('ecommerce-overview');
    // Route::get('/ecommerce-referral', Referral::class)->name('referral');

    // Route::get('/ecommerce-products-edit-product', EditProduct::class)->name('edit-product');
    // Route::get('/ecommerce-products-new-product', NewProduct::class)->name('new-product');
    // Route::get('/ecommerce-products-product-page', ProductPage::class)->name('product-page');
    // Route::get('/ecommerce-products-products-list', ProductsList::class)->name('products-list');

    // Route::get('/ecommerce-orders-order-list', OrderList::class)->name('order-list');
    // Route::get('/ecommerce-orders-order-details', OrderDetails::class)->name('order-details');

    // Route::get('/authentication-error404', Error404::class)->name('404');
    // Route::get('/authentication-error500', Error500::class)->name('500');

    // Route::get('/authentication-lock-basic', LockBasic::class)->name('lock-basic');
    // Route::get('/authentication-lock-cover', LockCover::class)->name('lock-cover');
    // Route::get('/authentication-lock-illustration', LockIllustration::class)->name('lock-illustration');

    // Route::get('/authentication-reset-basic', ResetBasic::class)->name('reset-basic');
    // Route::get('/authentication-reset-cover', ResetCover::class)->name('reset-cover');
    // Route::get('/authentication-reset-illustration', ResetIllustration::class)->name('reset-illustration');

    // Route::get('/authentication-signin-basic', SigninBasic::class)->name('signin-basic');
    // Route::get('/authentication-signin-cover', SigninCover::class)->name('signin-cover');
    // Route::get('/authentication-signin-illustration', SigninIllustration::class)->name('signin-illustration');

    // Route::get('/authentication-signup-basic', SignupBasic::class)->name('signup-basic');
    // Route::get('/authentication-signup-cover', SignupCover::class)->name('signup-cover');
    // Route::get('/authentication-signup-illustration', SignupIllustration::class)->name('signup-illustration');

    // Route::get('/authentication-verification-basic', VerificationBasic::class)->name('verification-basic');
    // Route::get('/authentication-verification-cover', VerificationCover::class)->name('verification-cover');
    // Route::get('/authentication-verification-illustration', VerificationIllustration::class)->name('verification-illustration');

    // Route::get('/laravel-users-management', UsersManagement::class)->name('users-management');
    // Route::get('/laravel-edit-user/{id}', EditUser::class)->name('edit-user');
    // Route::get('/laravel-user-profile', UserProfile::class)->name('user-profile');
    // Route::get('/laravel-new-user', LaravelNewUser::class)->name('laravel-new-user');
    // Route::get('/laravel-roles-management', RolesManagement::class)->name('roles-management');
    // Route::get('/laravel-edit-role/{id}', EditRole::class)->name('edit-role');
    // // Route::get('/laravel-new-role', NewRole::class)->name('new-role');
    // Route::get('/laravel-category-management', CategoryManagement::class)->name('category-management');
    // Route::get('/laravel-new-category', NewCategory::class)->name('new-category');
    // Route::get('/laravel-edit-category/{id}', EditCategory::class)->name('edit-category');
    // Route::get('/laravel-tags-management', TagsManagement::class)->name('tags-management');
    // Route::get('/laravel-new-tag', NewTag::class)->name('new-tag');
    // Route::get('/laravel-edit-tag/{id}', EditTag::class)->name('edit-tag');
    // Route::get('/laravel-items-management', ItemsManagement::class)->name('items-management');
    // Route::get('/laravel-new-item', NewItem::class)->name('new-item');
    // Route::get('/laravel-edit-item/{id}', EditItem::class)->name('edit-item');
    // Route::get('/laravel-page-error', PageError::class)->name('page-error');

    // // Dayli
    // //Route::get('/ecommerce-products-price-list', [PriceController::class, 'showPriceList'])->name('price-list');
    // Route::get('/ecommerce-products-products-list', ProductsList::class)->name('products-list');
    // Route::get('/ecommerce-manage-prices', ManagePrices::class)->name('manage-prices');

    // Route::get('/ecommerce-subscr-manage-subscr-types', SubscrTypesManagement::class)->name('manage-subscr-types');
    // Route::get('/ecommerce-subscr-new-subscr-type', NewSubscrType::class)->name('new-subscr-type');
    // Route::get('/ecommerce-subscr-new-subscr-type/{id}', EditSubscrType::class)->name('edit-subscr-type');

    // Route::get('/pages-manage-user-attrs', UserAttrTypesManagement::class)->name('manage-user-attrs');
    // Route::get('/pages-new-user-attr', NewUserAttrType::class)->name('new-user-attr');
    // Route::get('/pages-edit-user-attr/{id}', EditUserAttrType::class)->name('edit-user-attr');

    // Route::get('/pages-manage-permissions', PermissionsManagement::class)->name('manage-permissions');
    // Route::get('/pages-new-permission', NewPermission::class)->name('new-permission');
    // Route::get('/pages-edit-permission/{id}', EditPermission::class)->name('edit-permission');

    // //    Route::get('/leads/create', CreateLead::class)->name('leads.create');
    // //Route::get('/leads/update', EditLead::class)->name('leads.create');
    // Route::get('/leads', LeadList::class)->name('leads.index');


    // Route::get('/leads/create', function () {
    //     return view('livewire.leads.create');
    // })->name('leads.create');

    // Route::post('/leads/store', [LeadController::class, 'store'])->name('leads.store');

    // Route::get('/leads/success', function () {
    //     return view('livewire.leads.success');
    // })->name('leads.success');
//});
