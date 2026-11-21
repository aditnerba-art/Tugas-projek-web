<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'no_nota' => 'SRV-'.now()->format('ymd').'-'.fake()->unique()->numerify('#####'),
            'customer_id' => Customer::factory(),
            'jenis_unit' => fake()->randomElement(['Laptop', 'PC', 'Printer']),
            'merek_tipe' => fake()->bothify('Model ?? ##'),
            'keluhan' => fake()->sentence(),
            'status' => 'masuk',
            'total_biaya' => 0,
        ];
    }
}
