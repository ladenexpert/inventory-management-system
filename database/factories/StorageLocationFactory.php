<?php

namespace Database\Factories;

use App\Models\StorageLocation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StorageLocation>
 */
class StorageLocationFactory extends Factory
{
    protected $model = StorageLocation::class;

    public function definition(): array
    {
        $code = 'LOC-' . Str::upper(fake()->unique()->bothify('??##'));

        return [
            'code' => $code,
            'name' => fake()->words(3, true),
            'type' => fake()->randomElement(['room', 'rack', 'shelf', 'bin', 'other']),
            'parent_id' => null,
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
