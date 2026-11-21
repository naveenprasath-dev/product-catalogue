<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EnquiryController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

// Admin panel (Inertia + React starter kit). Off unless ADMIN_ENABLED=true: then /admin/* is a plain 404.
if (! config('admin.enabled')) {
    return;
}

// Login/logout: Fortify's controllers (FortifyServiceProvider only lets admins in), registered here
// instead of Fortify's own route file so no unused auth routes exist. Same names/middleware as Fortify.
Route::prefix('admin')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->middleware('guest:web')->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware(['guest:web', 'throttle:login'])->name('login.store');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->middleware('auth:web')->name('logout');
});

// Every other admin route needs a logged-in admin (guests → /admin/login, everyone else → 403).
Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:admin'])->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('enquiries', [EnquiryController::class, 'index'])->name('enquiries.index');
    Route::get('enquiries/{enquiry}', [EnquiryController::class, 'show'])
        ->where('enquiry', '[0-9]{1,18}')->name('enquiries.show');
    Route::post('enquiries/{enquiry}/resend', [EnquiryController::class, 'resend'])
        ->where('enquiry', '[0-9]{1,18}')->middleware('throttle:6,1')->name('enquiries.resend');
});
