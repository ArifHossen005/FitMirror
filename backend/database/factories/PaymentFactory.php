<?php

namespace Database\Factories;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'invoice_id' => Invoice::factory(),
            'gateway' => PaymentGateway::SslCommerz,
            'gateway_txn_id' => 'FM' . now()->format('YmdHis') . Str::upper(Str::random(8)),
            'val_id' => null,
            'amount' => $this->faker->numberBetween(499, 1299),
            'currency' => 'BDT',
            'method' => null,
            'status' => PaymentStatus::Pending,
            'raw_payload' => [],
        ];
    }

    public function status(PaymentStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }

    public function gateway(PaymentGateway $gateway): static
    {
        return $this->state(fn (array $attributes) => ['gateway' => $gateway]);
    }
}
