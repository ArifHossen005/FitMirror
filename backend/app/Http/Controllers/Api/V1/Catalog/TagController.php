<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Catalog\StoreTagRequest;
use App\Http\Requests\Catalog\UpdateTagRequest;
use App\Models\Tag;
use App\Services\Catalog\TagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends BaseApiController
{
    public function __construct(private readonly TagService $tags) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Tag::class);

        $tags = Tag::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->get();

        return $this->success(['tags' => $tags->map(fn (Tag $tag) => $this->present($tag))->values()]);
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        $this->authorize('create', Tag::class);

        $tag = $this->tags->create($request->user()->tenant, $request->validated());

        return $this->created($this->present($tag), 'Tag created successfully.');
    }

    public function update(UpdateTagRequest $request, Tag $tag): JsonResponse
    {
        $this->authorize('update', $tag);

        $updated = $this->tags->update($tag, $request->validated());

        return $this->success($this->present($updated), 'Tag updated successfully.');
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $this->authorize('delete', $tag);

        $this->tags->delete($tag);

        return $this->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Tag $tag): array
    {
        return [
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'color' => $tag->color,
        ];
    }
}
