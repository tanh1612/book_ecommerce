<?php

namespace Database\Factories;

use App\Enums\Book\BookFormat;
use App\Enums\Book\BookLanguage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BookDetail>
 */
class BookDetailFactory extends Factory
{
    protected $model = \App\Models\BookDetail::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'description' => fake()->paragraph(),
            'language' => BookLanguage::VI,
            'translator' => null,
            'publication_year' => (int) fake()->year(),
            'weight' => fake()->randomFloat(2, 100, 900),
            'dimensions' => '20x14cm',
            'num_pages' => fake()->numberBetween(50, 500),
            'format' => BookFormat::PAPERBACK,
        ];
    }
}
