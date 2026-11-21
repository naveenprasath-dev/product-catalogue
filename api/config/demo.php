<?php

declare(strict_types=1);

return [
    // Seed demo data (admin, demo customer, sample enquiries) on container start and in
    // `migrate --seed`. Local/reviewer convenience only: DemoSeeder refuses to run in production.
    'seed_on_start' => filter_var(env('SEED_DEMO_DATA', false), FILTER_VALIDATE_BOOL),

    // Demo shop customer created by DemoSeeder (idempotent; an existing account is never changed).
    // LOCAL-ONLY placeholder credentials, like ADMIN_PASSWORD.
    'customer' => [
        'name' => 'Demo Customer',
        'email' => env('DEMO_CUSTOMER_EMAIL', 'customer@example.com'),
        'password' => env('DEMO_CUSTOMER_PASSWORD', 'local-customer-password'),
    ],
];
