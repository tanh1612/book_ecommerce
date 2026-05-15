<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\BookDetail;
use App\Models\Publisher;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Book>
 */
class BookFactory extends Factory
{
    protected $model = Book::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->sentence(3);

        return [
            'supplier_id' => Supplier::factory(),
            'publisher_id' => Publisher::factory(),
            'name' => $name,
            'slug' => Str::slug($name.'-'.fake()->unique()->numerify('####')),
            'sku' => fake()->unique()->ean13(),
            'thumbnail' => null,
            'original_price' => 120_000,
            'selling_price' => 100_000,
            'review_count' => 0,
            'average_rating' => 0.00,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function configure(): static
    {
        return $this->has(BookDetail::factory(), 'detail');
    }
}
