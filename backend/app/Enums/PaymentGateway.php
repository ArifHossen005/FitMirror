<?php

namespace App\Enums;

/**
 * Backed enum, not a MySQL ENUM column, per DOCUMENTATION.md §4.4.1.
 * 'manual' is Mission Control's offline/bank-transfer recording path
 * (App\Services\Billing\PaymentService::recordOffline()) — same Payment/
 * Invoice/Subscription state machine as a real SSLCommerz payment, just
 * with no gateway round trip.
 */
enum PaymentGateway: string
{
    case SslCommerz = 'sslcommerz';
    case Manual = 'manual';
}
