<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Catalog\StoreAttributeRequest;
use App\Http\Requests\Catalog\UpdateAttributeRequest;
use App\Models\Attribute;
use App\Services\Catalog\AttributeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttributeController extends BaseApiController
{
    public function __construct(private readonly AttributeService $attributes) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Attribute::class);

        $attributes = Attribute::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with('values')
            ->orderBy('sort_order')
            ->get();

        return $this->success([
            'attributes' => $attributes->map(fn (Attribute $attribute) => $this->present($attribute))->values(),
        ]);
    }

    public function store(StoreAttributeRequest $request): JsonResponse
    {
        $this->authorize('create', Attribute::class);

        $attribute = $this->attributes->create($request->user()->tenant, $request->validated());

        return $this->created($this->present($attribute), 'Attribute created successfully.');
    }

    public function show(Attribute $attribute): JsonResponse
    {
        $this->authorize('view', $attribute);

        return $this->success($this->present($attribute->load('values')));
    }

    public function update(UpdateAttributeRequest $request, Attribute $attribute): JsonResponse
    {
        $this->authorize('update', $attribute);

        $updated = $this->attributes->update($attribute, $request->validated());

        return $this->success($this->present($updated), 'Attribute updated successfully.');
    }

    public function destroy(Attribute $attribute): JsonResponse
    {
        $this->authorize('delete', $attribute);

        $this->attributes->delete($attribute);

        return $this->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Attribute $attribute): array
    {
        return [
            'id' => $attribute->id,
            'name' => $attribute->name,
            'slug' => $attribute->slug,
            'type' => $attribute->type->value,
            'type_label' => $attribute->type->label(),
            'supports_hex_color' => $attribute->type->supportsHexColor(),
            'sort_order' => $attribute->sort_order,
            'status' => $attribute->status->value,
            'values' => $attribute->relationLoaded('values')
                ? $attribute->values->map(fn ($value) => [
                    'id' => $value->id,
                    'value' => $value->value,
                    'hex_color' => $value->hex_color,
                    'sort_order' => $value->sort_order,
                ])->values()
                : [],
        ];
    }
}
