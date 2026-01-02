<?php

use App\Http\Controllers\CustomerBookingController;
use App\Http\Controllers\ServiceProviderController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/service-providers', [ServiceProviderController::class, 'index']);
Route::get('/service-providers/{serviceProvider}/tasks', [ServiceProviderController::class, 'tasks']);

// Customer authenticated routes
Route::middleware('customer.auth')->prefix('customer')->group(function () {
    Route::get('/booking-requests', [CustomerBookingController::class, 'index']);
    Route::post('/booking-requests', [CustomerBookingController::class, 'store']);
    Route::get('/booking-requests/{bookingRequest}', [CustomerBookingController::class, 'show']);
    Route::post('/booking-requests/{bookingRequest}/submit', [CustomerBookingController::class, 'submit']);
    Route::post('/booking-requests/{bookingRequest}/cancel', [CustomerBookingController::class, 'cancel']);
});
