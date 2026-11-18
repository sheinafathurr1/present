<?php

namespace Database\Factories;

use App\Enums\EmployeeType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => null,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Pegawai,
            'employee_type' => EmployeeType::Guru,
            'position' => 'Guru Kelas',
            'must_attend' => true,
            'is_active' => true,
            'created_at' => now()->subYear(),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::Admin, 'position' => 'Operator Sekolah', 'employee_type' => EmployeeType::Tendik, 'must_attend' => false]);
    }

    public function pimpinan(): static
    {
        return $this->state(fn () => ['role' => UserRole::Pimpinan, 'position' => 'Kepala Sekolah']);
    }

    public function tendik(): static
    {
        return $this->state(fn () => ['employee_type' => EmployeeType::Tendik, 'position' => 'Staf Tata Usaha']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
