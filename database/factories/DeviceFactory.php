<?php

namespace Database\Factories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Gerbang Utama', 'Pintu Samping', 'Asrama', 'Ruang Guru']),
            'location' => fake()->streetName(),
            'is_active' => true,
        ];
    }
}
