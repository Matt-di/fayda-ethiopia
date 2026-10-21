<?php

declare(strict_types=1);

$claims = env('FAYDA_CLAIMS');
$claims = is_string($claims) && trim($claims) !== '' ? json_decode($claims, true) : null;

return [
    /*
    |--------------------------------------------------------------------------
    | Issuer and discovery
    |--------------------------------------------------------------------------
    |
    | When any endpoint is omitted, the package fetches
    | {issuer}/.well-known/openid-configuration and caches the result.
    |
    */
    'issuer' => env('FAYDA_ISSUER'),
    'discovery' => [
        'enabled' => (bool) env('FAYDA_DISCOVERY', true),
        'cache_ttl' => (int) env('FAYDA_DISCOVERY_CACHE_TTL', 3600),
    ],

    'endpoints' => [
        'authorization' => env('FAYDA_AUTHORIZATION_ENDPOINT'),
        'token' => env('FAYDA_TOKEN_ENDPOINT'),
        'userinfo' => env('FAYDA_USERINFO_ENDPOINT'),
        'jwks' => env('FAYDA_JWKS_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Client credentials
    |--------------------------------------------------------------------------
    |
    | Fayda/eSignet uses private_key_jwt. Provide ONE private key source:
    |   - private_key:          raw PEM string
    |   - private_key_path:     path to a PEM file
    |   - private_jwk:          JWK array or JSON string
    |   - private_jwk_base64:   base64 encoded JWK JSON (common Fayda format)
    |
    */
    'client' => [
        'id' => env('FAYDA_CLIENT_ID'),
        'assertion_type' => env(
            'FAYDA_CLIENT_ASSERTION_TYPE',
            'urn:ietf:params:oauth:client-assertion-type:jwt-bearer'
        ),
        'assertion_ttl' => (int) env('FAYDA_ASSERTION_TTL', 300),
        'private_key' => env('FAYDA_PRIVATE_KEY'),
        'private_key_path' => env('FAYDA_PRIVATE_KEY_PATH'),
        'private_jwk' => env('FAYDA_PRIVATE_JWK'),
        'private_jwk_base64' => env('FAYDA_PRIVATE_JWK_BASE64'),
        'key_id' => env('FAYDA_KEY_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization request
    |--------------------------------------------------------------------------
    */
    'redirect_uri' => env('FAYDA_REDIRECT_URI'),

    'scopes' => array_values(array_filter(
        explode(' ', (string) env('FAYDA_SCOPES', 'openid profile email'))
    )),

    'acr_values' => array_values(array_filter(
        explode(' ', (string) env('FAYDA_ACR_VALUES', 'mosip:idp:acr:generated-code mosip:idp:acr:linked-wallet'))
    )),

    'claims' => is_array($claims) ? $claims : [
        'userinfo' => [
            'name' => ['essential' => true],
            'phone_number' => ['essential' => true],
            'email' => ['essential' => true],
            'picture' => ['essential' => false],
            'gender' => ['essential' => false],
            'birthdate' => ['essential' => false],
            'address' => ['essential' => false],
        ],
        'id_token' => [],
    ],

    'ui_locales' => env('FAYDA_UI_LOCALES', 'en'),
    'display' => env('FAYDA_DISPLAY', 'page'),
    'response_mode' => env('FAYDA_RESPONSE_MODE'),
    'response_type' => env('FAYDA_RESPONSE_TYPE', 'code'),
    'pkce' => (bool) env('FAYDA_PKCE', true),
    'state_ttl' => (int) env('FAYDA_STATE_TTL', 600),

    /*
    |--------------------------------------------------------------------------
    | Token and userinfo verification
    |--------------------------------------------------------------------------
    */
    'require_id_token' => (bool) env('FAYDA_REQUIRE_ID_TOKEN', true),
    'fetch_userinfo' => (bool) env('FAYDA_FETCH_USERINFO', true),
    'clock_skew' => (int) env('FAYDA_CLOCK_SKEW', 60),
    'jwks_cache_ttl' => (int) env('FAYDA_JWKS_CACHE_TTL', 3600),

    'http' => [
        'timeout' => (float) env('FAYDA_HTTP_TIMEOUT', 15),
        'connect_timeout' => (float) env('FAYDA_HTTP_CONNECT_TIMEOUT', 5),
    ],

    'cache_store' => env('FAYDA_CACHE_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Laravel routes
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'enabled' => (bool) env('FAYDA_ROUTES', true),
        'prefix' => env('FAYDA_ROUTE_PREFIX', 'auth/fayda'),
        'middleware' => ['web'],
        'redirect_name' => 'fayda.redirect',
        'callback_name' => 'fayda.callback',
    ],

    /*
    |--------------------------------------------------------------------------
    | Optional guard login and user resolution
    |--------------------------------------------------------------------------
    */
    'auth' => [
        'login' => (bool) env('FAYDA_AUTH_LOGIN', false),
        'guard' => env('FAYDA_AUTH_GUARD', 'web'),
        'redirect_after_login' => env('FAYDA_REDIRECT_AFTER_LOGIN', '/'),
    ],

    // Optional class-string implementing Mattdi\Fayda\Laravel\Auth\FaydaUserResolver.
    'user_resolver' => env('FAYDA_USER_RESOLVER'),
];
