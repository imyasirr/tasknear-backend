<?php

use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\CityController;
use App\Modules\Events\Http\Controllers\EventController;
use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Marketplace\Http\Controllers\CatererProfileController;
use App\Modules\Marketplace\Http\Controllers\VendorOfferController;
use App\Modules\Money\Http\Controllers\CatererPayoutController;
use App\Modules\Money\Http\Controllers\PaymentController;
use App\Modules\Ops\Http\Controllers\AdminController;
use App\Modules\Ops\Http\Controllers\AdminMatchingController;
use App\Modules\Subscriptions\Http\Controllers\AdminSubscriptionController;
use App\Modules\Subscriptions\Http\Controllers\SubscriptionController;
use App\Modules\Tasks\Http\Controllers\TaskController;
use App\Modules\Trust\Http\Controllers\TrustController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/otp/request', [AuthController::class, 'requestOtp']);
    Route::post('auth/otp/verify', [AuthController::class, 'verifyOtp']);
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'loginWithPassword']);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('cities', [CityController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::put('me', [AuthController::class, 'updateMe']);
        Route::post('me/password', [AuthController::class, 'updatePassword']);
        Route::post('me/avatar', [AuthController::class, 'updateAvatar']);
        Route::get('me/avatar', [AuthController::class, 'avatar']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('caterer/profile', [CatererProfileController::class, 'show']);
        Route::post('caterer/profile', [CatererProfileController::class, 'upsert']);
        Route::post('caterer/skills', [CatererProfileController::class, 'skills']);
        Route::post('caterer/availability', [CatererProfileController::class, 'availability']);
        Route::get('caterer/offers', [VendorOfferController::class, 'mine']);
        Route::get('caterer/offers/{offer}', [VendorOfferController::class, 'show']);
        Route::post('caterer/offers/{offer}/accept', [VendorOfferController::class, 'accept']);
        Route::post('caterer/offers/{offer}/decline', [VendorOfferController::class, 'decline']);
        Route::post('caterer/offers/{offer}/check-in', [VendorOfferController::class, 'checkIn']);
        Route::post('caterer/offers/{offer}/check-out', [VendorOfferController::class, 'checkOut']);
        Route::get('caterer/payouts', [CatererPayoutController::class, 'index']);
        Route::post('caterer/payouts/{payout}/confirm', [CatererPayoutController::class, 'confirm']);
        Route::get('events', [EventController::class, 'index']);
        Route::post('events', [EventController::class, 'store']);
        Route::get('events/{event}', [EventController::class, 'show']);
        Route::post('events/{event}/repost', [EventController::class, 'repost']);

        Route::post('payments/{payment}/dev-pay', [PaymentController::class, 'devPay']);
        Route::post('ratings', [TrustController::class, 'rate']);
        Route::post('reports', [TrustController::class, 'report']);
        Route::get('tasks', [TaskController::class, 'index']);
        Route::post('tasks', [TaskController::class, 'store']);
        Route::get('tasks/{task}', [TaskController::class, 'show']);
        Route::post('tasks/{task}/repost', [TaskController::class, 'repost']);

        Route::get('pricing/quote', [SubscriptionController::class, 'quote']);
        Route::get('subscription-plans', [SubscriptionController::class, 'plans']);
        Route::get('me/subscription', [SubscriptionController::class, 'mine']);
        Route::post('subscription-plans/{plan}/buy', [SubscriptionController::class, 'buy']);

        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::get('dashboard', [AdminController::class, 'dashboard']);
            Route::get('users', [AdminController::class, 'users']);
            Route::get('bookings', [AdminController::class, 'bookings']);
            Route::post('bookings/{booking}/rematch', [AdminController::class, 'rematch']);
            Route::post('workers/{profile}/status', [AdminController::class, 'setWorkerStatus']);
            Route::post('reports/{report}/status', [AdminController::class, 'resolveReport']);
            Route::get('kyc', [AdminController::class, 'kyc']);
            Route::post('kyc/profiles/{profile}/approve', [AdminController::class, 'approveKyc']);
            Route::post('kyc/profiles/{profile}/reject', [AdminController::class, 'rejectKyc']);
            Route::get('fill-board', [AdminController::class, 'fillBoard']);
            Route::get('shifts/{shift}/eligible-workers', [AdminController::class, 'eligibleWorkers']);
            Route::post('shifts/{shift}/assign', [AdminController::class, 'assign']);
            Route::post('assignments/{assignment}/replace', [AdminController::class, 'replace']);
            Route::get('tasks', [AdminController::class, 'taskBoard']);
            Route::get('tasks/{task}/eligible-workers', [AdminController::class, 'eligibleTaskWorkers']);
            Route::post('tasks/{task}/assign', [AdminController::class, 'assignTask']);
            Route::get('notifications', [AdminController::class, 'notifications']);
            Route::get('reports', [AdminController::class, 'reports']);
            Route::get('payouts', [AdminController::class, 'payouts']);
            Route::post('payouts/{payout}/send', [AdminController::class, 'sendPayout']);
            Route::post('payouts/{payout}/release', [AdminController::class, 'releasePayout']);
            Route::get('audit', [AdminController::class, 'audit']);
            Route::get('cities', [CityController::class, 'adminIndex']);
            Route::post('cities', [CityController::class, 'store']);
            Route::put('cities/{city}', [CityController::class, 'update']);
            Route::get('billing', [AdminSubscriptionController::class, 'settings']);
            Route::put('billing', [AdminSubscriptionController::class, 'updateSettings']);
            Route::get('matching', [AdminMatchingController::class, 'show']);
            Route::put('matching', [AdminMatchingController::class, 'update']);
            Route::get('subscription-plans', [AdminSubscriptionController::class, 'plans']);
            Route::post('subscription-plans', [AdminSubscriptionController::class, 'storePlan']);
            Route::put('subscription-plans/{plan}', [AdminSubscriptionController::class, 'updatePlan']);
            Route::get('subscriptions', [AdminSubscriptionController::class, 'subscriptions']);
        });
    });
});
