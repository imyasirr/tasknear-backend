<?php

use App\Modules\Catalog\Http\Controllers\AdminProviderTypeController;
use App\Modules\Catalog\Http\Controllers\ProviderTypeController;
use App\Modules\Catalog\Http\Controllers\CityController;
use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Money\Http\Controllers\CheckoutController;
use App\Modules\Money\Http\Controllers\PaymentController;
use App\Modules\Ops\Http\Controllers\AdminController;
use App\Modules\Trust\Http\Controllers\TrustController;
use App\Modules\Venues\Http\Controllers\VenueBookingController;
use App\Modules\Venues\Http\Controllers\VenueBrowseController;
use App\Modules\Venues\Http\Controllers\VenueManageController;
use App\Modules\Venues\Http\Controllers\VenuePartnerProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/otp/request', [AuthController::class, 'requestOtp']);
    Route::post('auth/otp/verify', [AuthController::class, 'verifyOtp']);
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'loginWithPassword']);
    Route::get('provider-types', [ProviderTypeController::class, 'index']);
    Route::get('cities', [CityController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::put('me', [AuthController::class, 'updateMe']);
        Route::post('me/password', [AuthController::class, 'updatePassword']);
        Route::post('me/avatar', [AuthController::class, 'updateAvatar']);
        Route::get('me/avatar', [AuthController::class, 'avatar']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('checkout/config', [CheckoutController::class, 'config']);
        Route::post('checkout/verify', [CheckoutController::class, 'verify']);
        Route::post('payments/{payment}/checkout', [CheckoutController::class, 'bookingCheckout']);
        Route::post('payments/{payment}/dev-pay', [PaymentController::class, 'devPay']);
        Route::post('ratings', [TrustController::class, 'rate']);
        Route::post('reports', [TrustController::class, 'report']);

        Route::get('venues/meta', [VenueBrowseController::class, 'meta']);
        Route::get('venues', [VenueBrowseController::class, 'index']);
        Route::get('venues/{slug}', [VenueBrowseController::class, 'show']);
        Route::post('venue-bookings', [VenueBookingController::class, 'store']);
        Route::get('venue-bookings/mine', [VenueBookingController::class, 'mine']);
        Route::get('venue-bookings/{booking:slug}', [VenueBookingController::class, 'show']);

        Route::get('venue-partner/profile', [VenuePartnerProfileController::class, 'show']);
        Route::post('venue-partner/profile', [VenuePartnerProfileController::class, 'upsert']);
        Route::get('venue-partner/venues', [VenueManageController::class, 'index']);
        Route::post('venue-partner/venues', [VenueManageController::class, 'store']);
        Route::get('venue-partner/venues/{venue}', [VenueManageController::class, 'show']);
        Route::put('venue-partner/venues/{venue}', [VenueManageController::class, 'update']);
        Route::post('venue-partner/venues/{venue}/publish', [VenueManageController::class, 'publish']);
        Route::post('venue-partner/venues/{venue}/photos', [VenueManageController::class, 'storePhoto']);
        Route::delete('venue-partner/venues/{venue}/photos/{photo}', [VenueManageController::class, 'destroyPhoto']);
        Route::get('venue-partner/venues/{venue}/calendar', [VenueManageController::class, 'calendar']);
        Route::get('venue-partner/bookings', [VenueBookingController::class, 'partnerIndex']);
        Route::post('venue-partner/bookings', [VenueBookingController::class, 'partnerStore']);

        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::get('dashboard', [AdminController::class, 'dashboard']);
            Route::get('users', [AdminController::class, 'users']);
            Route::post('reports/{report}/status', [AdminController::class, 'resolveReport']);
            Route::get('notifications', [AdminController::class, 'notifications']);
            Route::get('reports', [AdminController::class, 'reports']);
            Route::get('payouts', [AdminController::class, 'payouts']);
            Route::post('payouts/{payout}/send', [AdminController::class, 'sendPayout']);
            Route::post('payouts/{payout}/release', [AdminController::class, 'releasePayout']);
            Route::get('audit', [AdminController::class, 'audit']);
            Route::get('cities', [CityController::class, 'adminIndex']);
            Route::post('cities', [CityController::class, 'store']);
            Route::put('cities/{city}', [CityController::class, 'update']);
            Route::get('provider-types', [AdminProviderTypeController::class, 'index']);
            Route::post('provider-types', [AdminProviderTypeController::class, 'store']);
            Route::put('provider-types/{providerType}', [AdminProviderTypeController::class, 'update']);
        });
    });
});
