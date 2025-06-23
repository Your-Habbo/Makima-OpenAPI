<?php
// config/cors.php - Updated for cross-origin setup

return [
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'auth/*',
    ],

    'allowed_methods' => ['*'],

    // Critical: Allow requests from localhost where the frontend runs
    'allowed_origins' => [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        // Add any other domains you might use
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'X-XSRF-TOKEN',
        'Origin',
        'User-Agent',
    ],

    'exposed_headers' => [
        'X-CSRF-TOKEN',
        'Set-Cookie',
    ],

    'max_age' => 0,

    'supports_credentials' => true,
];
