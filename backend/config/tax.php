<?php

return [

    /*
    |--------------------------------------------------------------------------
    | VAT Rate
    |--------------------------------------------------------------------------
    |
    | Bangladesh's standard VAT rate (15%), expressed as a decimal fraction
    | of the post-discount subtotal. Applied uniformly to every invoice —
    | App\Support\TaxCalculator is the one place this is consumed, so a
    | future per-plan or per-category VAT rule (unlikely for a SaaS
    | subscription, but possible for add-ons) only needs to change there.
    |
    */

    'vat_rate' => (float) env('VAT_RATE', 0.15),

];
