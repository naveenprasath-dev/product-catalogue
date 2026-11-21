<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EnquiryController;
use App\Http\Middleware\AdminSecurityHeaders;
use App\Http\Middleware\RedirectAdminsFromLogin;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware\EncryptHistory;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

// Admin panel (Inertia + React starter kit). Off unless ADMIN_ENABLED=true: then /admin/* is a plain 404.
if (! config('admin.enabled')) {
    return;
}

// Login/logout: Fortify's controllers (FortifyServiceProvider only lets admins in), registered here
// instead of Fortify's own route file so no unused auth routes exist. Same names/middleware as Fortify.
// AdminSecurityHeaders: no-store + no framing on every admin response. EncryptHistory: Inertia page data in
// the browser history is encrypted with a per-session key, so it is unreadable after logout (QA B3).
Route::prefix('admin')->middleware([AdminSecurityHeaders::class, EncryptHistory::class])->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->middleware(RedirectAdminsFromLogin::class)->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware([RedirectAdminsFromLogin::class, 'throttle:login'])->name('login.store');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->middleware('auth:web')->name('logout');
});

// Every other admin route needs a logged-in admin (guests → /admin/login, everyone else → 403).
Route::prefix('admin')->name('admin.')->middleware([AdminSecurityHeaders::class, 'auth', 'can:admin', EncryptHistory::class])->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('enquiries', [EnquiryController::class, 'index'])->name('enquiries.index');
    Route::get('enquiries/{enquiry}', [EnquiryController::class, 'show'])
        ->where('enquiry', '[0-9]{1,18}')->name('enquiries.show');
    Route::post('enquiries/{enquiry}/resend', [EnquiryController::class, 'resend'])
        ->where('enquiry', '[0-9]{1,18}')->middleware('throttle:6,1')->name('enquiries.resend');

    // Currencies + exchange rates. {currency} is the upper-case ISO 4217 code.
    Route::get('currencies', [CurrencyController::class, 'index'])->name('currencies.index');
    Route::get('currencies/create', [CurrencyController::class, 'create'])->name('currencies.create');
    Route::post('currencies', [CurrencyController::class, 'store'])
        ->middleware('throttle:30,1')->name('currencies.store');
    // Calls Frankfurter (the only admin action that leaves the box), so a tighter limit.
    Route::post('currencies/sync', [CurrencyController::class, 'sync'])
        ->middleware('throttle:6,1')->name('currencies.sync');
    Route::get('currencies/{currency}/edit', [CurrencyController::class, 'edit'])
        ->where('currency', '[A-Z]{3}')->name('currencies.edit');
    Route::put('currencies/{currency}', [CurrencyController::class, 'update'])
        ->where('currency', '[A-Z]{3}')->middleware('throttle:30,1')->name('currencies.update');
    Route::post('currencies/{currency}/default', [CurrencyController::class, 'makeDefault'])
        ->where('currency', '[A-Z]{3}')->middleware('throttle:30,1')->name('currencies.default');
    Route::delete('currencies/{currency}', [CurrencyController::class, 'destroy'])
        ->where('currency', '[A-Z]{3}')->middleware('throttle:30,1')->name('currencies.destroy');
});
