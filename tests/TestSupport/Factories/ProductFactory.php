<?php

namespace Javaabu\Imports\Tests\TestSupport\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Javaabu\Imports\Tests\TestSupport\Models\Brand;
use Javaabu\Imports\Tests\TestSupport\Models\Product;

class ProductFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->productName();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'brand_id' => Brand::factory(),
        ];
    }

    /**
     * Indicate that the product has no brand.
     *
     * @return Factory
     */
    public function withoutBrand(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'brand_id' => null,
            ];
        });
    }
}
