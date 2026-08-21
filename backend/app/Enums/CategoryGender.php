<?php

namespace App\Enums;

/**
 * The audience a category is merchandised for. Drives the default
 * Bangladeshi apparel taxonomy seeded by App\Services\Catalog\
 * CategoryTaxonomyService (Boys/Girls top-level categories) and lets the
 * kiosk/portal filter the catalog by shopper.
 */
enum CategoryGender: string
{
    case Boys = 'boys';
    case Girls = 'girls';
    case Men = 'men';
    case Women = 'women';
    case Unisex = 'unisex';

    public function label(): string
    {
        return match ($this) {
            self::Boys => 'Boys',
            self::Girls => 'Girls',
            self::Men => 'Men',
            self::Women => 'Women',
            self::Unisex => 'Unisex',
        };
    }
}
