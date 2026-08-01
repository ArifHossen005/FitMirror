<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | FitMirror is API-first: every browser client (dashboard, kiosk, portal,
    | mission control) is a separate origin talking to this API over fetch/
    | axios, so every request path needs a CORS policy, not just "api/*".
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'up'],

    'allowed_methods' => ['*'],

    /*
    | Explicit allow-list rather than "*" — Sanctum's SPA cookie auth and any
    | future credentialed request require a concrete origin list, and "*"
    | cannot be combined with supports_credentials=true per the CORS spec.
    | Each FRONTEND_URL/KIOSK_URL/PORTAL_URL/MISSION_URL is already defined
    | in .env for exactly this purpose (see §5.1 of DOCUMENTATION.md).
    */
    'allowed_origins' => array_values(array_filter([
        env('FRONTEND_URL'),
        env('KIOSK_URL'),
        env('PORTAL_URL'),
        env('MISSION_URL'),
    ])),

    'allowed_origins_patterns' => [
        // Tenant subdomains, e.g. https://acme-boutique.fitmirror.com
        '#^https://[a-z0-9-]+\.' . preg_quote(env('TENANT_ROOT_DOMAIN', 'fitmirror.com'), '#') . '$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    /*
    | Sanctum's SPA cookie auth requires this to be true. Public API consumers
    | (Phase 12, X-API-Key auth) never send cookies so this has no effect on
    | them.
    */
    'supports_credentials' => true,

];
