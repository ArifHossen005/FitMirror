<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Common / Shared Language Lines
    |--------------------------------------------------------------------------
    |
    | Base keys shared across API responses, notifications, and any server-
    | rendered fragments (e.g. invoice PDFs). Module-specific strings live in
    | their own files (e.g. lang/en/tryon.php) as those modules are built.
    |
    */

    'success' => 'Success.',
    'error' => 'Something went wrong.',
    'created' => ':item created successfully.',
    'updated' => ':item updated successfully.',
    'deleted' => ':item deleted successfully.',
    'not_found' => ':item not found.',
    'unauthorized' => 'You are not authorized to perform this action.',
    'unauthenticated' => 'Please log in to continue.',
    'validation_failed' => 'The given data was invalid.',
    'too_many_requests' => 'Too many requests. Please slow down and try again shortly.',
    'server_error' => 'An unexpected error occurred. Please try again.',
    'maintenance_mode' => 'FitMirror is undergoing scheduled maintenance. Please check back shortly.',

    'tenant' => [
        'suspended' => 'Your account has been suspended. Please contact support.',
        'expired' => 'Your subscription has expired. Please renew to continue.',
        'pending_approval' => 'Your account is pending approval. We will notify you once it is reviewed.',
    ],

    'mission' => [
        'suspended' => 'This Mission Control account has been suspended.',
    ],

    'auth' => [
        'invalid_credentials' => 'These credentials do not match our records.',
    ],

    'plan' => [
        'limit_exceeded' => 'You have reached the limit for your current plan.',
        'feature_locked' => 'This feature is not available on your current plan.',
        'upgrade_required' => 'Upgrade your plan to unlock this feature.',
    ],

    'days' => [
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
        'sunday' => 'Sunday',
    ],

];
