<?php

declare(strict_types=1);

return [

    /*
    | Where catalogue:import reads products from: 'fakestore' (live, default) or 'file'
    | (the committed snapshot, no network). API requests never use a source.
    */
    'source' => env('CATALOGUE_SOURCE', 'fakestore'),

    'source_url' => rtrim((string) env('CATALOGUE_SOURCE_URL', 'https://fakestoreapi.com'), '/'),

    // HTTP timeout (seconds) and retry attempts for the Fake Store call.
    'source_timeout' => 10,
    'source_retries' => 2,

    // When the live source fails, import the committed snapshot instead.
    'fallback_to_file' => filter_var(env('CATALOGUE_FALLBACK_TO_FILE', true), FILTER_VALIDATE_BOOL),

    // Relative paths are resolved from the api/ base path.
    'snapshot_path' => env('CATALOGUE_SNAPSHOT_PATH', 'database/data/fakestore-products.json'),

    // The 5 curated "top rated" products for the home page, as Fake Store ids (= products.external_id), in display order.
    'featured_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('FEATURED_PRODUCT_IDS', '12,11,3,18,5'))))),

    // How many products in a category count as "top rated"; the rest are "others".
    'top_rated_per_category' => 3,

    // Where product enquiries are emailed.
    'sales_inbox' => env('SALES_INBOX_ADDRESS', 'sales@example.com'),

    // POST /enquiries requests allowed per minute per IP.
    'enquiry_rate_limit' => 5,
];
