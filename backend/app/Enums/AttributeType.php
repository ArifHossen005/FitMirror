<?php

namespace App\Enums;

/**
 * What an attribute's values represent. `Color` and `Size` are the two
 * variant-defining types — a product_variants row picks exactly one value
 * of each via `color_attr_id`/`size_attr_id` (see App\Models\
 * ProductVariant). `Fabric` and `Custom` are descriptive-only: their values
 * attach directly to a product as a whole through the `product_attribute`
 * pivot and never create a variant axis.
 *
 * Deliberately excludes "occasion" despite the product document listing it
 * alongside color/size/fabric as an attribute example — FitMirror models
 * occasions as their own flat taxonomy (`occasions` table, `product_occasion`
 * pivot), not as attribute values, because an occasion is a merchandising
 * tag applied uniformly to a product ("this whole listing is Eid wear"),
 * never a per-variant axis the way color and size are. See PROGRESS.md
 * Decision D-24.
 */
enum AttributeType: string
{
    case Color = 'color';
    case Size = 'size';
    case Fabric = 'fabric';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Color => 'Color',
            self::Size => 'Size',
            self::Fabric => 'Fabric',
            self::Custom => 'Custom',
        };
    }

    /**
     * Whether this attribute's values are permitted in `attribute_values
     * .hex_color` — only Color values render as swatches.
     */
    public function supportsHexColor(): bool
    {
        return $this === self::Color;
    }

    /**
     * Whether this attribute type may be selected as a variant axis
     * (`product_variants.color_attr_id`/`size_attr_id`). Fabric/Custom
     * attributes describe the product as a whole instead.
     */
    public function definesVariantAxis(): bool
    {
        return $this === self::Color || $this === self::Size;
    }
}
