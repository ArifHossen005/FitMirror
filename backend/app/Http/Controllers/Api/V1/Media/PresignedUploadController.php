<?php

namespace App\Http\Controllers\Api\V1\Media;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Media\PresignedUploadRequest;
use App\Models\Product;
use App\Services\Media\PresignedUploadService;
use Illuminate\Http\JsonResponse;

/**
 * The direct-to-S3 presigned upload endpoint PROGRESS.md's 5.C checklist
 * asks for, "for large batches" — an import tool or a multi-image picker
 * can upload straight to the bucket instead of proxying every byte through
 * this app. Gated on `products.update` since product photos are the only
 * media this app has today; a future module with its own upload surface
 * gets its own gate, not this one widened.
 */
class PresignedUploadController extends BaseApiController
{
    public function __construct(private readonly PresignedUploadService $presigner) {}

    public function store(PresignedUploadRequest $request): JsonResponse
    {
        // 'create', not 'update' — this endpoint issues an upload slot
        // ahead of any specific product existing yet (a bulk import
        // attaches images before rows are created), so there is no Product
        // instance to authorize against; ProductPolicy::create() takes
        // only the user, exactly like ProductController::store()'s own gate.
        $this->authorize('create', Product::class);

        $data = $request->validated();

        $result = $this->presigner->generate($request->user()->tenant, $data['filename'], $data['content_type']);

        return $this->success($result);
    }
}
