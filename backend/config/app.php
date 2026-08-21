<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    /*
     * FitMirror stores every timestamp in UTC, both in the application and in
     * MySQL (see my.ini default-time-zone='+00:00'). Tenants span timezones,
     * so conversion to the tenant's local zone (Asia/Dhaka by default) happens
     * at the presentation layer, never at storage. Do not change this to a
     * local timezone — analytics rollups depend on a single canonical zone.
     */
    'timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy
    |--------------------------------------------------------------------------
    |
    | The root domain tenant subdomains are resolved against, e.g. a tenant
    | with slug "acme" is reachable at "acme.{tenant_root_domain}". Consumed
    | by App\Http\Middleware\ResolveTenant — see PROGRESS.md Phase 2.A and
    | DOCUMENTATION.md §5.1.
    |
    */

    'tenant_root_domain' => env('TENANT_ROOT_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Tenant Presentation Timezone
    |--------------------------------------------------------------------------
    |
    | Storage is always UTC (Decision D-07); this is the timezone a new
    | branch is created in by default and the one dates are rendered in for
    | a tenant that has not set their own. TENANT_DEFAULT_TIMEZONE has been
    | in .env.example since Phase 1.A but was never exposed through config,
    | so every read of it would have broken under `config:cache` — env() is
    | only safe inside a config file.
    |
    */

    'tenant_default_timezone' => env('TENANT_DEFAULT_TIMEZONE', 'Asia/Dhaka'),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Frontend URL
    |--------------------------------------------------------------------------
    |
    | The shop-owner dashboard's own origin (apps/dashboard) — distinct from
    | KIOSK_URL/PORTAL_URL/MISSION_URL (config/cors.php). Used to build
    | links that must open in the dashboard SPA, e.g. the staff invitation
    | acceptance link (App\Notifications\StaffInvitationNotification).
    |
    */

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', '')),
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
