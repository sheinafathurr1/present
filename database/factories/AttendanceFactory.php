<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'date' => today()->toDateString(),
            'status' => AttendanceStatus::Hadir,
            'check_in_at' => today()->setTime(6, 55),
            'source' => Attendance::SOURCE_RFID,
        ];
    }
}
