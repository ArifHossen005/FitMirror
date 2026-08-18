<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by App\Services\Billing\SslCommerzService or App\Services\Billing\
 * PaymentService whenever a gateway round trip can't be trusted: SSLCommerz
 * returned a non-success session status, order validation failed or the
 * validated amount didn't match, or a refund endpoint is unconfigured (see
 * config/sslcommerz.php). Caught centrally by App\Support\
 * ApiExceptionRenderer and rendered as 502, mirroring how
 * PlanLimitExceededException gets one shared shape for every caller.
 */
class PaymentGatewayException extends RuntimeException {}
