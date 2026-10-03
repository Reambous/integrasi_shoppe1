<?php

use App\Http\Controllers\ShopeeAuthController;
use Illuminate\Support\Facades\Route;

// Throttle ringan agar menghormati rate limit Shopee & anti-spam callback.
Route::middleware('throttle:30,1')->prefix('shopee')->group(function () {
    Route::get('/auth/redirect', [ShopeeAuthController::class, 'redirect']);
    Route::get('/callback', [ShopeeAuthController::class, 'callback']);
});
