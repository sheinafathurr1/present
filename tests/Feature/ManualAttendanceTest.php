<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_records_sick_note(): void
    {
        $guru = User::factory()->create();
        $student = Student::factory()->create();

        $this->actingAs($guru)->post('/presensi', [
            'student_id' => $student->id,
            'date' => '2026-11-11',
            'status' => 'sakit',
            'note' => 'Demam',
        ])->assertRedirect();

        $attendance = Attendance::sole();
        $this->assertSame(AttendanceStatus::Sakit, $attendance->status);
        $this->assertSame(Attendance::SOURCE_MANUAL, $attendance->source);
        $this->assertSame($guru->id, $attendance->recorded_by);
        $this->assertSame('Demam', $attendance->note);
    }

    public function test_mark_absent_skips_recorded_and_inactive_students(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(16, 0));
        $present = Student::factory()->create();
        $absent = Student::factory()->create();
        Student::factory()->inactive()->create();
        Attendance::factory()->create(['student_id' => $present->id, 'date' => '2026-11-11']);

        $this->artisan('presensi:tandai-alpa')->assertSuccessful();

        $this->assertSame(2, Attendance::count());
        $this->assertSame(AttendanceStatus::Alpa, Attendance::where('student_id', $absent->id)->first()->status);
        $this->assertSame(AttendanceStatus::Hadir, Attendance::where('student_id', $present->id)->first()->status);
    }

    public function test_mark_absent_does_nothing_on_holidays_and_weekends(): void
    {
        Student::factory()->create();
        Holiday::create(['date' => '2026-11-11', 'name' => 'Libur']);

        $this->artisan('presensi:tandai-alpa', ['--tanggal' => '2026-11-11'])->assertSuccessful();
        $this->artisan('presensi:tandai-alpa', ['--tanggal' => '2026-11-15'])->assertSuccessful(); // Minggu

        $this->assertSame(0, Attendance::count());
    }

    public function test_mark_absent_from_dashboard(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(13, 0));
        Student::factory()->count(3)->create();

        $this->actingAs(User::factory()->create())
            ->post('/presensi/tandai-alpa', ['date' => '2026-11-11'])
            ->assertSessionHas('success', '3 siswa yang belum presensi ditandai Alpa.');
    }
}
