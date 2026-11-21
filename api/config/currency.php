<?php

declare(strict_types=1);

return [

    /*
    | The currency product prices are stored in (Fake Store prices are USD). Its rate is fixed at 1
    | and it can't be disabled or deleted. Not an env var: changing it would need re-priced products.
    */
    'base' => 'USD',

    /*
    | Live exchange rates: Frankfurter (open source, MIT, no API key, ECB reference rates).
    | Only `currencies:sync-rates` and the admin "Sync live rates" button call it, never a shop request.
    */
    'rates_url' => rtrim((string) env('CURRENCY_RATES_URL', 'https://api.frankfurter.dev/v2'), '/'),

    // HTTP timeout (seconds) and retry attempts for the rates call.
    'rates_timeout' => 10,
    'rates_retries' => 2,
];
