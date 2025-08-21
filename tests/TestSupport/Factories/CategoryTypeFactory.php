<?php

namespace Javaabu\Imports\Tests\TestSupport\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Javaabu\Imports\Tests\TestSupport\Models\CategoryType;

class CategoryTypeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CategoryType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'has_icon' => $this->faker->boolean(),
        ];
    }

    /**
     * Indicate that the category type has icons.
     *
     * @return Factory
     */
    public function withIcons(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'has_icon' => true,
            ];
        });
    }

    /**
     * Indicate that the category type doesn't have icons.
     *
     * @return Factory
     */
    public function withoutIcons(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'has_icon' => false,
            ];
        });
    }
}
