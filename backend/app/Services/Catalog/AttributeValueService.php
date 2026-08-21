<?php

namespace App\Services\Catalog;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Services\BaseService;
use Illuminate\Validation\ValidationException;

/**
 * Value CRUD nested under a parent Attribute. `hex_color` is only ever
 * persisted for a Color-type attribute — see AttributeType::
 * supportsHexColor() — enforced here because the check needs the already-
 * loaded parent, which a form request does not have.
 */
class AttributeValueService extends BaseService
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(Attribute $attribute, array $data): AttributeValue
    {
        $this->assertHexColorAllowed($attribute, $data['hex_color'] ?? null);
        $this->assertValueIsUnique($attribute, $data['value']);

        return AttributeValue::query()->create([
            'tenant_id' => $attribute->tenant_id,
            'attribute_id' => $attribute->id,
            'value' => $data['value'],
            'hex_color' => $data['hex_color'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(AttributeValue $value, array $data): AttributeValue
    {
        if (array_key_exists('hex_color', $data)) {
            $this->assertHexColorAllowed($value->attribute, $data['hex_color']);
        }

        if (array_key_exists('value', $data) && $data['value'] !== $value->value) {
            $this->assertValueIsUnique($value->attribute, $data['value'], $value->id);
        }

        $value->fill($data)->save();

        return $value->refresh();
    }

    /**
     * A value already selected by a live variant is not deleted — the FK is
     * nullOnDelete() precisely so this never has to be a hard block, unlike
     * AttributeService::delete()'s whole-attribute check. Removing a color
     * a product no longer sells should not be gated on that product's
     * unrelated history.
     */
    public function delete(AttributeValue $value): void
    {
        $value->delete();
    }

    private function assertHexColorAllowed(Attribute $attribute, ?string $hexColor): void
    {
        if ($hexColor !== null && !$attribute->type->supportsHexColor()) {
            throw ValidationException::withMessages([
                'hex_color' => ['Only Color attributes may have a hex color value.'],
            ]);
        }
    }

    private function assertValueIsUnique(Attribute $attribute, string $value, ?int $ignoreId = null): void
    {
        $query = AttributeValue::query()->where('attribute_id', $attribute->id)->where('value', $value);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'value' => ['This value already exists for this attribute.'],
            ]);
        }
    }
}
