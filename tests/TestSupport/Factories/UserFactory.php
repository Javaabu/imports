<?php

namespace Javaabu\Imports\Tests\TestSupport\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Javaabu\Imports\Tests\TestSupport\Models\User;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
        ];
    }
}
