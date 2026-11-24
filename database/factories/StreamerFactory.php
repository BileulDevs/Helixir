<?php

namespace Database\Factories;

use App\Models\Streamer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Streamer>
 */
class StreamerFactory extends Factory
{
    public function definition(): array
    {
        $login = fake()->unique()->userName();

        return [
            'id' => (string) fake()->unique()->numberBetween(1000, 99999999),
            'login' => strtolower($login),
            'display_name' => $login,
        ];
    }
}
