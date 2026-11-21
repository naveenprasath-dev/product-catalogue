<?php

declare(strict_types=1);

// Fortify drives the admin panel login only (React starter kit, trimmed). Shop customers log in
// through /api/v1/auth instead. No optional features: no registration, password reset, email
// verification, 2FA or passkeys (password reset is CLI only: `php artisan admin:create --email=x --reset`).
return [
    'guard' => 'web',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'lowercase_usernames' => true,
    'home' => '/admin',
    // After logout, back to the admin login (Fortify's default is '/', which is a 404 here).
    'redirects' => [
        'logout' => '/admin/login',
    ],
    'prefix' => 'admin',
    'domain' => null,
    'middleware' => ['web'],
    'limiters' => [
        'login' => 'login',
    ],
    'views' => true,
    'features' => [],
];
