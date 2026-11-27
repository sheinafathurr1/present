<?php

namespace Database\Factories;

use App\Enums\LeaveType;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => LeaveType::Izin,
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
            'reason' => 'Keperluan keluarga',
        ];
    }
}
