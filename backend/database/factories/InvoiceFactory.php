<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = $this->faker->numberBetween(499, 1299);

        return [
            'tenant_id' => Tenant::factory(),
            'subscription_id' => null,
            'number' => 'INV-' . now()->format('Y') . '-' . str_pad((string) $this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'subtotal' => $amount,
            'discount' => 0,
            'vat' => 0,
            'total' => $amount,
            'currency' => 'BDT',
            'status' => InvoiceStatus::Pending,
            'issued_at' => now(),
            'due_at' => now()->addDays(3),
        ];
    }

    public function status(InvoiceStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
