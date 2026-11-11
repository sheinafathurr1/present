<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Kelas '.fake()->unique()->numberBetween(1, 12).fake()->randomElement(['A', 'B', 'C']),
            'level' => fake()->randomElement(SchoolClass::LEVELS),
        ];
    }
}
