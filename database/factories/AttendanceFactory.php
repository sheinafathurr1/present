<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => today()->toDateString(),
            'status' => AttendanceStatus::Hadir,
            'check_in_at' => today()->setTime(6, 50),
            'check_in_distance' => 20,
            'check_in_accuracy' => 15,
            'source' => Attendance::SOURCE_GPS,
        ];
    }
}
