<?php

namespace App\Services\Media;

use App\Exceptions\MediaProcessingException;
use App\Models\Tenant;
use App\Support\TenantStorage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Direct-to-S3 presigned uploads for large batches (PROGRESS.md's 5.C
 * checklist) — lets the browser/import tool upload straight to the bucket
 * instead of proxying every byte through this app's own PHP process.
 *
 * Only works when the `tenant` disk's driver is actually `s3` — Laravel's
 * temporaryUploadUrl() has no meaning for the `local` driver this app runs
 * on in development (Decision D-01: no S3 access locally). Rather than
 * fake a URL that would silently 404, this throws a clear
 * MediaProcessingException so the caller (and this app's own tests, via
 * Http::fake()-style assertions on the exception) can tell the difference
 * from a real failure — the same "be honest about what can't be verified
 * yet" instinct as SSLCommerz's unset refund endpoints (Decision D-18).
 */
class PresignedUploadService
{
    private const DIRECTORY = 'products';

    /**
     * @return array{url: string, path: string, headers: array<string, string>, expires_in_seconds: int}
     *
     * @throws MediaProcessingException
     */
    public function generate(Tenant $tenant, string $filename, string $contentType): array
    {
        $directory = TenantStorage::path($tenant, self::DIRECTORY . '/' . Str::random(8));
        $path = $directory . '/' . Str::random(24) . '-' . $filename;
        $expiresInSeconds = 900;

        try {
            // Always an array on a real S3-backed adapter — {"url", "headers"}.
            $generated = Storage::disk('tenant')->temporaryUploadUrl(
                $path,
                now()->addSeconds($expiresInSeconds),
                ['ContentType' => $contentType],
            );
        } catch (RuntimeException $e) {
            throw new MediaProcessingException(
                'Direct upload is only available when the tenant disk is backed by S3/R2 (production) — the local development disk cannot generate a presigned URL.',
                previous: $e,
            );
        }

        return [
            'url' => $generated['url'],
            'path' => $path,
            'headers' => $generated['headers'] ?? [],
            'expires_in_seconds' => $expiresInSeconds,
        ];
    }
}
