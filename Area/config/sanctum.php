<?php

use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | These domains are considered "stateful". Requests from these hosts
    | will receive stateful cookies for browser-based authentication.
    |
    */

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | Sanctum will check these authentication guards when trying to
    | authenticate a request. If none authenticate, Sanctum will fallback
    | to Bearer token (API tokens).
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Token Expiration
    |--------------------------------------------------------------------------
    |
    | Number of minutes before an API token expires. Null = never expires.
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Optional prefix added to generated tokens to protect against accidental
    | leak detection by security scanners.
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware used for stateful authentication (SPA).
    | IMPORTANT: For pure API + mobile + React frontend, we should keep only
    | necessary middleware to work with tokens.
    |
    */

    'middleware' => [
        // Required to authenticate SPA sessions
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,

        // Encrypt cookies (SPA only, safe to keep)
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,

        // FIXED : Correct CSRF middleware for Sanctum (your version was outdated)
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
