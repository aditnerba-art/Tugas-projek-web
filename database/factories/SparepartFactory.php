<?php

namespace Database\Factories;

use App\Models\Sparepart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sparepart>
 */
class SparepartFactory extends Factory
{
    public function definition(): array
    {
        $purchasePrice = fake()->numberBetween(10000, 500000);

        return [
            'kode_barang' => 'PART-'.fake()->unique()->numerify('#####'),
            'nama_barang' => fake()->words(2, true),
            'stok' => fake()->numberBetween(0, 100),
            'stok_minimum' => 5,
            'harga_beli' => $purchasePrice,
            'harga_jual' => $purchasePrice + fake()->numberBetween(1000, 200000),
        ];
    }
}
