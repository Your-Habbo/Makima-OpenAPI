<?php
// config/webauthn.php

return [
    /*
    |--------------------------------------------------------------------------
    | Relying Party ID
    |--------------------------------------------------------------------------
    |
    | This is the domain that your WebAuthn credentials will be bound to.
    | It must be the current domain or a registrable domain suffix.
    | For localhost development, use 'localhost'
    | For IP addresses, use the exact IP
    |
    */
    'rp_id' => env('WEBAUTHN_RP_ID', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),

    /*
    |--------------------------------------------------------------------------
    | Relying Party Name
    |--------------------------------------------------------------------------
    |
    | A human-readable identifier for the relying party
    |
    */
    'rp_name' => env('WEBAUTHN_RP_NAME', env('APP_NAME', 'Laravel App')),

    /*
    |--------------------------------------------------------------------------
    | WebAuthn Timeout
    |--------------------------------------------------------------------------
    |
    | Timeout in milliseconds for WebAuthn operations
    |
    */
    'timeout' => env('WEBAUTHN_TIMEOUT', 60000),

    /*
    |--------------------------------------------------------------------------
    | Challenge Length
    |--------------------------------------------------------------------------
    |
    | Length of the challenge in bytes (will be base64 encoded)
    |
    */
    'challenge_length' => env('WEBAUTHN_CHALLENGE_LENGTH', 32),

    /*
    |--------------------------------------------------------------------------
    | User Verification Requirement
    |--------------------------------------------------------------------------
    |
    | Whether user verification is required, preferred, or discouraged
    | Options: required, preferred, discouraged
    |
    */
    'user_verification' => env('WEBAUTHN_USER_VERIFICATION', 'preferred'),

    /*
    |--------------------------------------------------------------------------
    | Authenticator Attachment
    |--------------------------------------------------------------------------
    |
    | Whether to prefer platform or cross-platform authenticators
    | Options: platform, cross-platform, null (no preference)
    |
    */
    'authenticator_attachment' => env('WEBAUTHN_AUTHENTICATOR_ATTACHMENT', null),

    /*
    |--------------------------------------------------------------------------
    | Resident Key Requirement
    |--------------------------------------------------------------------------
    |
    | Whether resident keys (discoverable credentials) are required
    | Options: required, preferred, discouraged
    |
    */
    'resident_key' => env('WEBAUTHN_RESIDENT_KEY', 'preferred'),

    /*
    |--------------------------------------------------------------------------
    | Attestation Conveyance Preference
    |--------------------------------------------------------------------------
    |
    | This member is intended to select the appropriate trade-offs between
    | attestation conveyance, verification complexity and privacy.
    | Options: none, indirect, direct, enterprise
    |
    */
    'attestation' => env('WEBAUTHN_ATTESTATION', 'none'),

    /*
    |--------------------------------------------------------------------------
    | Supported Algorithms
    |--------------------------------------------------------------------------
    |
    | List of supported cryptographic algorithms for credential generation
    |
    */
    'algorithms' => [
        -7,   // ES256 (Elliptic Curve Digital Signature Algorithm using P-256 and SHA-256)
        -257, // RS256 (RSASSA-PKCS1-v1_5 using SHA-256)
        -37,  // PS256 (RSASSA-PSS using SHA-256)
    ],
];
