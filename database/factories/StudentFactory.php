<?php

namespace Database\Factories;

use App\Enums\DisabilityType;
use App\Enums\FeedbackMode;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        $gender = fake()->randomElement(['L', 'P']);
        $name = fake()->name($gender === 'L' ? 'male' : 'female');

        return [
            'nis' => (string) fake()->unique()->numberBetween(2020000, 2029999),
            'name' => $name,
            'nickname' => explode(' ', $name)[0],
            'gender' => $gender,
            'disability_type' => fake()->randomElement(DisabilityType::cases()),
            'feedback_mode' => FeedbackMode::Both,
            'rfid_uid' => strtoupper(fake()->unique()->bothify('########')),
            'guardian_name' => fake()->name(),
            'guardian_phone' => '08'.fake()->numerify('##########'),
            'is_active' => true,
        ];
    }

    public function withoutCard(): static
    {
        return $this->state(fn () => ['rfid_uid' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
