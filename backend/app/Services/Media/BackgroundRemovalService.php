<?php

namespace App\Services\Media;

use App\Exceptions\MediaProcessingException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP client around whichever background-removal provider
 * `config('background_removal')` points at — see that config file's own
 * docblock for the assumed request/response contract and why it hasn't
 * been exercised against a real account yet (same shape as
 * App\Services\Billing\SslCommerzService's still-unverified refund
 * endpoints, PROGRESS.md Decision D-18).
 */
class BackgroundRemovalService
{
    /**
     * @throws MediaProcessingException
     */
    public function removeBackground(string $imageBinary, string $originalFilename): string
    {
        $endpoint = config('background_removal.endpoint');
        $key = config('background_removal.key');

        if (!is_string($endpoint) || $endpoint === '' || !is_string($key) || $key === '') {
            throw new MediaProcessingException(
                'Background removal is not configured — set BG_REMOVAL_ENDPOINT and BG_REMOVAL_KEY.',
            );
        }

        try {
            $response = Http::withHeaders(['X-Api-Key' => $key])
                ->timeout((int) config('background_removal.timeout_seconds', 30))
                ->attach('image_file', $imageBinary, $originalFilename)
                ->post($endpoint);
        } catch (ConnectionException $e) {
            throw new MediaProcessingException('Could not reach the background removal service: ' . $e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new MediaProcessingException(
                'Background removal request failed: HTTP ' . $response->status() . ' — ' . $response->body(),
            );
        }

        $body = $response->body();

        if ($body === '') {
            throw new MediaProcessingException('Background removal service returned an empty response.');
        }

        return $body;
    }
}
