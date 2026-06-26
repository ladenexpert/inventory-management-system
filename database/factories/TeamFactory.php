<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        $name = fake()->unique()->company() . ' Team';

        return [
            'code' => Str::upper(fake()->unique()->bothify('TM-###')),
            'name' => $name,
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
