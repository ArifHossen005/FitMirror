<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SSLCommerz Credentials
    |--------------------------------------------------------------------------
    |
    | Empty until a sandbox account is registered at sslcommerz.com — see
    | PROGRESS.md Phase 3.C's "Blocker to resolve first" note. Every
    | App\Services\Billing\SslCommerzService call fails fast with a clear
    | PaymentGatewayException while these are blank, rather than sending a
    | request SSLCommerz will just reject.
    |
    */

    'store_id' => env('SSLC_STORE_ID'),
    'store_password' => env('SSLC_STORE_PASSWORD'),
    'sandbox' => env('SSLC_SANDBOX', true),

    /*
    |--------------------------------------------------------------------------
    | Callback URLs
    |--------------------------------------------------------------------------
    |
    | Passed to SSLCommerz on session initiate. All four are backend API
    | routes (mounted under /api/v1, matching every other route in this
    | app) — success/fail/cancel end by redirecting the browser onward to
    | config('app.frontend_url'), the same "point at a frontend route
    | ahead of Phase 3.E building the page" pattern already used by
    | StaffInvitationNotification and the password-reset email link.
    |
    */

    'success_url' => env('SSLC_SUCCESS_URL'),
    'fail_url' => env('SSLC_FAIL_URL'),
    'cancel_url' => env('SSLC_CANCEL_URL'),
    'ipn_url' => env('SSLC_IPN_URL'),

    /*
    |--------------------------------------------------------------------------
    | API Endpoints
    |--------------------------------------------------------------------------
    |
    | session_endpoint (session initiate, gwprocess/v4/api.php) and
    | validation_endpoint (validationserverAPI.php, order validation) are
    | SSLCommerz's two documented, well-known REST endpoints and are safe
    | to hardcode per sandbox/live.
    |
    | refund_initiate_endpoint / refund_query_endpoint are deliberately
    | NOT hardcoded. Unlike the two above, this codebase has not been able
    | to exercise SSLCommerz's refund API against a real response (no
    | sandbox credentials yet — same blocker as above), so guessing a path
    | here would risk shipping a silently-wrong URL dressed up as a
    | verified one. App\Services\Billing\SslCommerzService::initiateRefund()
    | throws a clear, explicit PaymentGatewayException if these are unset
    | instead. Confirm both against the SSLCommerz merchant panel's API
    | reference once a sandbox account exists, then set them via env.
    |
    */

    'session_endpoint' => [
        'sandbox' => 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php',
        'live' => 'https://securepay.sslcommerz.com/gwprocess/v4/api.php',
    ],

    'validation_endpoint' => [
        'sandbox' => 'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php',
        'live' => 'https://securepay.sslcommerz.com/validator/api/validationserverAPI.php',
    ],

    'refund_initiate_endpoint' => env('SSLC_REFUND_INITIATE_ENDPOINT'),
    'refund_query_endpoint' => env('SSLC_REFUND_QUERY_ENDPOINT'),

];
