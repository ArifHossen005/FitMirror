<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Background Removal Provider
    |--------------------------------------------------------------------------
    |
    | Empty until a real account is registered with a background-removal
    | provider — the same shape as config/sslcommerz.php's blocker (see
    | PROGRESS.md Decision D-18). App\Services\Media\BackgroundRemovalService
    | fails fast with a clear MediaProcessingException while these are
    | blank, rather than sending a request nobody will answer.
    |
    | The client assumes a remove.bg-compatible contract: POST multipart
    | with the image under `image_file`, an API key header, response body
    | is the raw processed PNG bytes on success or a JSON error body
    | otherwise. This is the most common shape for this class of API and
    | was chosen because BG_REMOVAL_ENDPOINT/BG_REMOVAL_KEY (already present
    | in .env.example ahead of this phase) implies exactly that — a single
    | endpoint plus a single key, not OAuth or a multi-step upload/poll
    | flow. It has not been exercised against a real response (no account
    | exists yet), so treat the exact field/header names as the one thing
    | to re-verify against the chosen provider's own docs before going live
    | — everything else (retry, storage, is_tryon_ready wiring) does not
    | change regardless of which provider answers this contract.
    |
    */

    'endpoint' => env('BG_REMOVAL_ENDPOINT'),
    'key' => env('BG_REMOVAL_KEY'),

    'timeout_seconds' => env('BG_REMOVAL_TIMEOUT_SECONDS', 30),

];
