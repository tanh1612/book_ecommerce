<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventory>
 */
class InventoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'warehouse_id' => fn (): int => (int) (Warehouse::query()->value('id') ?? Warehouse::factory()->create()->id),
            'quantity' => fake()->numberBetween(0, 100),
            'sold_quantity' => 0,
            'reserved_quantity' => 0,
            'location_code' => 'A'.fake()->numerify('#'),
        ];
    }
}
