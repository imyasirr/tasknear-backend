<?php

namespace App\Providers;

use App\Modules\Marketplace\Models\ServiceRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::bind('event', function (string $value) {
            return ServiceRequest::applyKey(
                ServiceRequest::query()->where('type', 'event'),
                $value
            )->firstOrFail();
        });

        Route::bind('task', function (string $value) {
            return ServiceRequest::applyKey(
                ServiceRequest::query()->where('type', 'task'),
                $value
            )->firstOrFail();
        });

        Route::bind('booking', function (string $value) {
            return ServiceRequest::applyKey(ServiceRequest::query(), $value)->firstOrFail();
        });
    }
}
