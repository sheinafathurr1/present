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
            'type' => Device::TYPE_USB,
            'is_active' => true,
        ];
    }

    public function network(): static
    {
        return $this->state(fn () => ['type' => Device::TYPE_NETWORK]);
    }
}
