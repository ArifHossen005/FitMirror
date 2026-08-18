<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = $this->faker->numberBetween(499, 1299);

        return [
            'invoice_id' => Invoice::factory(),
            'description' => 'Pro plan — monthly subscription',
            'qty' => 1,
            'unit_price' => $unitPrice,
            'total' => $unitPrice,
        ];
    }
}
