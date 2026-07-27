<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->name(),
            'access_code' => Str::random(32),
            'role_id' => fn () => Role::query()->firstOrCreate(
                ['slug' => UserRole::Leader->value],
                ['name' => UserRole::Leader->label()],
            )->id,
            'remember_token' => Str::random(10),
        ];
    }
}
