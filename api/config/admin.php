<?php

declare(strict_types=1);

return [
    // The /admin panel is off unless explicitly enabled (.env.example enables it for local).
    'enabled' => (bool) env('ADMIN_ENABLED', false),

    // First admin, created by `php artisan admin:create --from-env` (idempotent; never resets a password).
    'bootstrap' => [
        'name' => (string) env('ADMIN_NAME', 'Admin'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],
];
