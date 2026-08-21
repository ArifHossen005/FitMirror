<?php

namespace Database\Factories;

use App\Models\SizeChart;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SizeChart>
 */
class SizeChartFactory extends Factory
{
    protected $model = SizeChart::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement(['Men\'s Panjabi', 'Women\'s Kurti', 'Kids Frock']) . ' Chart',
            'rows' => [
                ['size' => 'S', 'measurements' => ['chest' => '38', 'length' => '28']],
                ['size' => 'M', 'measurements' => ['chest' => '40', 'length' => '29']],
                ['size' => 'L', 'measurements' => ['chest' => '42', 'length' => '30']],
            ],
            'unit' => 'in',
        ];
    }
}
