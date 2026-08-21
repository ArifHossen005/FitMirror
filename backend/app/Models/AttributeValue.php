<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AttributeValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One selectable value of a parent Attribute — "Red" under the Color
 * attribute, "XL" under Size. `hex_color` is only meaningful when the
 * parent's type is AttributeType::Color (AttributeType::supportsHexColor()).
 */
class AttributeValue extends Model
{
    /** @use HasFactory<AttributeValueFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'attribute_id',
        'value',
        'hex_color',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Attribute, $this>
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
