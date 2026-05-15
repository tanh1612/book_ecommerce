<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\BookImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BookImage>
 */
class BookImageFactory extends Factory
{
    protected $model = BookImage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'public_id' => 'tests/book-'.fake()->unique()->numerify('####'),
            'image_url' => 'https://example.com/book-'.fake()->unique()->numerify('####').'.jpg',
            'sort_order' => 0,
        ];
    }

    /**
     * Tránh gọi Cloudinary trong observer khi seed/test.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create($attributes = [], ?Model $parent = null)
    {
        return BookImage::withoutEvents(fn () => parent::create($attributes, $parent));
    }
}
