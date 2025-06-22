<?php

use Knuckles\Scribe\Extracting\Strategies;
use Knuckles\Scribe\Config\Defaults;
use Knuckles\Scribe\Config\AuthIn;
use function Knuckles\Scribe\Config\{removeStrategies, configureStrategy};

return [
    // The HTML <title> for the generated documentation.
    'title' => config('app.name').' API Documentation',

    // A short description of your API. Will be included in the docs webpage, Postman collection and OpenAPI spec.
    'description' => 'A comprehensive authentication API with Two-Factor Authentication (2FA), Role-Based Access Control (RBAC), and user management features. Built with Laravel 12 and designed for modern applications requiring secure user authentication.',

    // The base URL displayed in the docs.
    'base_url' => config("app.url"),

    // Routes to include in the docs
    'routes' => [
        [
            'match' => [
                // Match only routes whose paths match this pattern
                'prefixes' => ['api/*'],
                'domains' => ['*'],
            ],

            // Include these routes even if they did not match the rules above.
            'include' => [
                // Add any specific routes you want to include
            ],

            // Exclude these routes even if they matched the rules above.
            'exclude' => [
                'sanctum/csrf-cookie',
                'api/admin/*', // Exclude admin routes for security
                '_ignition/*',
                'telescope/*',
                'horizon/*',
                '_debugbar/*',
            ],
        ],
    ],

    // The type of documentation output to generate.
    'type' => 'laravel',

    // Theme for the documentation
    'theme' => 'default',

    'static' => [
        'output_path' => 'public/docs',
    ],

    'laravel' => [
        // Whether to automatically create a docs route
        'add_routes' => true,

        // URL path to use for the docs endpoint
        'docs_url' => '/docs',

        // Directory within `public` in which to store CSS and JS assets
        'assets_directory' => null,

        // Middleware to attach to the docs endpoint
        'middleware' => [],
    ],

    'external' => [
        'html_attributes' => []
    ],

    'try_it_out' => [
        // Add a Try It Out button to endpoints
        'enabled' => true,

        // The base URL to use in the API tester
        'base_url' => null,

        // Fetch a CSRF token before each request (for Sanctum)
        'use_csrf' => false,

        // The URL to fetch the CSRF token from
        'csrf_url' => '/sanctum/csrf-cookie',
    ],

    // Authentication configuration
    'auth' => [
        // Set this to true if ANY endpoints in your API use authentication
        'enabled' => true,

        // Set this to true if your API should be authenticated by default
        'default' => false,

        // Where is the auth value meant to be sent in a request?
        'in' => AuthIn::BEARER->value,

        // The name of the auth parameter or header
        'name' => 'Authorization',

        // The value of the parameter to be used by Scribe for response calls
        'use_value' => env('SCRIBE_AUTH_KEY'),

        // Placeholder users will see for the auth parameter
        'placeholder' => 'Bearer {YOUR_ACCESS_TOKEN}',

        // Extra authentication info for users
        'extra_info' => 'You can retrieve your access token by logging in via the <code>/api/auth/login</code> endpoint. Include the token in the Authorization header as <code>Bearer {token}</code>. Most endpoints require authentication except for login and registration.',
    ],

    // Introduction text
    'intro_text' => <<<INTRO
        This API provides comprehensive authentication and user management features including:

        ## Authentication Features
        - **User Registration & Login** - Support for both email and username authentication
        - **Two-Factor Authentication (2FA)** - TOTP (Google Authenticator) and Email OTP support
        - **Role-Based Access Control (RBAC)** - Manage user roles and permissions
        - **Secure Token Management** - Laravel Sanctum for API authentication

        ## User Management
        - **User Profile Management** - Update profiles, upload avatars, manage preferences
        - **Admin Controls** - User management, role assignment, permission control
        - **Security Features** - Rate limiting, login attempt tracking, and audit logs

        ## Getting Started

        1. **Register a new user** via `POST /api/auth/register`
        2. **Login** via `POST /api/auth/login` to get your access token
        3. **Include the token** in the `Authorization` header: `Bearer {your_token}`
        4. **Access protected endpoints** with your authenticated token

        ## 2FA Setup

        1. **Enable 2FA** via `POST /api/2fa/enable` to get QR code
        2. **Scan QR code** with Google Authenticator or similar app
        3. **Confirm setup** via `POST /api/2fa/confirm` with verification code
        4. **Future logins** will require the 2FA code

        <aside>The API uses standard HTTP status codes and returns JSON responses. All timestamps are in UTC format.</aside>
    INTRO,

    // Example languages to show in docs
    'example_languages' => [
        'bash',
        'javascript',
        'php',
        'python',
    ],

    // Generate a Postman collection
    'postman' => [
        'enabled' => true,
        'overrides' => [
            'info.name' => config('app.name') . ' API',
            'info.description' => 'Authentication API with 2FA and RBAC',
            'info.version' => '1.0.0',
        ],
    ],

    // Generate an OpenAPI spec
    'openapi' => [
        'enabled' => true,
        'overrides' => [
            'info.version' => '1.0.0',
            'info.contact' => [
                'name' => 'API Support',
                'email' => 'support@' . parse_url(config('app.url'), PHP_URL_HOST),
            ],
            'info.license' => [
                'name' => 'MIT',
            ],
        ],
        'generators' => [],
    ],

    // Group configuration
    'groups' => [
        // Default group for ungrouped endpoints
        'default' => 'General',

        // Custom ordering of groups and endpoints
        'order' => [
            'Authentication' => [
                'POST /api/auth/register',
                'POST /api/auth/login',
                'POST /api/auth/logout',
                'POST /api/auth/logout-all',
                'GET /api/auth/me',
            ],
            'Two-Factor Authentication' => [
                'POST /api/2fa/enable',
                'POST /api/2fa/confirm',
                'POST /api/2fa/verify',
                'POST /api/2fa/disable',
                'GET /api/2fa/recovery-codes',
                'POST /api/2fa/recovery-codes/regenerate',
                'POST /api/2fa/email/send',
                'POST /api/2fa/email/verify',
            ],
            'User Profile' => [
                'GET /api/profile',
                'PUT /api/profile',
                'POST /api/profile/avatar',
            ],
            '*', // All other groups come after specified ones
        ],
    ],

    // Custom logo (set to false if not using)
    'logo' => false,

    // Last updated format
    'last_updated' => 'Last updated: {date:F j, Y}',

    'examples' => [
        // Generate consistent examples
        'faker_seed' => 1234,

        // Model source strategies for generating example data
        'models_source' => ['factoryCreate', 'factoryMake', 'databaseFirst'],
    ],

    // Extraction strategies
    'strategies' => [
        'metadata' => [
            ...Defaults::METADATA_STRATEGIES,
        ],
        'headers' => [
            ...Defaults::HEADERS_STRATEGIES,
            // Add default headers for all requests
            Strategies\StaticData::withSettings(data: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]),
        ],
        'urlParameters' => [
            ...Defaults::URL_PARAMETERS_STRATEGIES,
        ],
        'queryParameters' => [
            ...Defaults::QUERY_PARAMETERS_STRATEGIES,
        ],
        'bodyParameters' => [
            ...Defaults::BODY_PARAMETERS_STRATEGIES,
        ],
        'responses' => configureStrategy(
            Defaults::RESPONSES_STRATEGIES,
            Strategies\Responses\ResponseCalls::withSettings(
                // Only make response calls for GET endpoints to avoid side effects
                only: ['GET *'],
                // Disable debug mode to avoid exposing stack traces
                config: [
                    'app.debug' => false,
                ],
                // Don't send any specific query params
                queryParams: [],
                // Don't send any specific body params
                bodyParams: [],
                // Don't send any files
                fileParams: [],
                // Don't send any cookies
                cookies: [],
            )
        ),
        'responseFields' => [
            ...Defaults::RESPONSE_FIELDS_STRATEGIES,
        ]
    ],

    // Database connections to transact (for safe response calls)
    'database_connections_to_transact' => [config('database.default')],

    // Fractal configuration (if using transformers)
    'fractal' => [
        'serializer' => null,
    ],
];
