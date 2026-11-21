<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\EnquiryController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

// Every route reads from our database only; none of them calls an upstream product source.
// Product routes take an optional ?currency=XXX (ProductQueryRequest).
Route::prefix('v1')->group(function (): void {
    Route::get('products/featured', [ProductController::class, 'featured']);
    Route::get('products', [ProductController::class, 'index']);
    // Up to 18 digits fits a Postgres bigint; anything else (non-numeric, too long) is a 404.
    Route::get('products/{id}', [ProductController::class, 'show'])->where('id', '[0-9]{1,18}');

    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{slug}/products', [CategoryController::class, 'products'])
        ->where('slug', '[a-z0-9-]{1,100}');

    // Enabled currencies and their stored rates (live rates are synced separately, never per request).
    Route::get('currencies', [CurrencyController::class, 'index']);

    Route::post('enquiries', [EnquiryController::class, 'store'])->middleware('throttle:enquiries');

    // Shop customer auth: Sanctum SPA cookie auth, so these only work from the shop origin (a session).
    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:shop-register');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:shop-login');
        Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
        Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    });
});
