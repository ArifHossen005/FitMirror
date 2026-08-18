<?php

namespace Database\Factories;

use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'payment_id' => Payment::factory(),
            'amount' => $this->faker->numberBetween(499, 1299),
            'reason' => 'Tenant application rejected',
            'gateway_refund_ref' => null,
            'status' => RefundStatus::Pending,
            'raw_payload' => [],
        ];
    }
}
