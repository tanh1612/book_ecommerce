<?php

namespace Database\Factories;

use App\Models\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    protected $model = Banner::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $suffix = strtolower(str_replace('-', '', uniqid()));
        $publicId = 'book_ecommerce/banners/home/home-banner-'.$suffix;

        return [
            'title' => fake()->sentence(3),
            'public_id' => $publicId,
            'image_url' => 'https://res.cloudinary.com/test/image/upload/'.$publicId.'.jpg',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
