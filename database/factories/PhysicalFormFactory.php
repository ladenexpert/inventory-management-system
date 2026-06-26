<?php

namespace Database\Factories;

use App\Models\PhysicalForm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PhysicalForm>
 */
class PhysicalFormFactory extends Factory
{
    protected $model = PhysicalForm::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Liquid', 'Powder', 'Wax', 'Others', 'Paste', 'Granule']);

        return [
            'code' => Str::of($name)->lower()->replace(' ', '_')->toString(),
            'name' => $name,
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
