<?php

namespace Javaabu\Imports\Tests\TestSupport\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Javaabu\Imports\Tests\TestSupport\Models\Category;
use Javaabu\Imports\Tests\TestSupport\Models\CategoryType;

class CategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Category::class;

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
            'category_type_id' => CategoryType::factory(),
            'icon' => null,
        ];
    }

    /**
     * Indicate that the category has an icon.
     *
     * @return Factory
     */
    public function withIcon(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'icon' => 'fa-' . $this->faker->word(),
            ];
        });
    }

    /**
     * Indicate that the category belongs to a specific category type.
     *
     * @param  CategoryType|int  $categoryType
     * @return Factory
     */
    public function forCategoryType($categoryType): Factory
    {
        return $this->state(function (array $attributes) use ($categoryType) {
            return [
                'category_type_id' => $categoryType instanceof CategoryType ? $categoryType->id : $categoryType,
            ];
        });
    }
}
