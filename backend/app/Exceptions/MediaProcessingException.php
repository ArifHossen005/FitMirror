<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by App\Services\Media\* whenever a media operation can't be
 * trusted: the background-removal provider is unconfigured (see
 * config/background_removal.php) or returned a non-success response, or
 * the storage disk driver doesn't support the requested operation (e.g. a
 * presigned upload URL requested against the local disk in development).
 * Caught centrally by App\Support\ApiExceptionRenderer, the same shared
 * shape PaymentGatewayException gets.
 */
class MediaProcessingException extends RuntimeException {}
