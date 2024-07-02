<?php

namespace Javaabu\Imports\Tests\TestSupport\Factories;

use Javaabu\Imports\Tests\TestSupport\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name,
            'email' => fake()->unique()->safeEmail,
        ];
    }
}
