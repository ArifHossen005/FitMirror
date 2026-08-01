<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     *
     * Super admins are identified by email against SUPER_ADMIN_EMAIL, which
     * accepts a comma-separated list. Phase 1.C introduces a dedicated
     * `super_admins` table and guard — once that lands, this check should be
     * switched to `auth('super_admin')->check()` instead of an email allow-list.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            $allowedEmails = array_filter(array_map(
                'trim',
                explode(',', (string) env('SUPER_ADMIN_EMAIL', '')),
            ));

            return in_array(optional($user)->email, $allowedEmails, true);
        });
    }
}
