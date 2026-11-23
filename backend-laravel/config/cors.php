<?php

/*
 * CORS: which websites may call this API from a browser (WST criterion 6).
 * Only the SmileSync site itself (and the local dev servers) are allowed.
 * Add more origins in .env as a comma-separated list: CORS_ALLOWED_ORIGINS=https://a.com,https://b.com
 */
return [
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        'https://smilesync.projectsmiledentalclinic.com,http://localhost:5173,http://127.0.0.1:5173,http://smilesync.test'
    ))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With', 'X-Clinic-Id'],

    'exposed_headers' => ['Retry-After'],

    'max_age' => 3600,

    // We send a Bearer token, not cookies, so credentials are not needed
    'supports_credentials' => false,
];
