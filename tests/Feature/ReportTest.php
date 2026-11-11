<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_report_counts_statuses_and_missing_days(): void
    {
        // Rabu 4 November 2026; hari sekolah sebelumnya bulan ini: Senin 2 & Selasa 3.
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(9, 0));
        $class = SchoolClass::factory()->create(['name' => 'Kelas 1A']);
        $student = Student::factory()->create(['school_class_id' => $class->id, 'name' => 'Budi Santoso']);

        Attendance::factory()->create(['student_id' => $student->id, 'date' => '2026-11-02', 'status' => AttendanceStatus::Hadir]);
        Attendance::factory()->create(['student_id' => $student->id, 'date' => '2026-11-04', 'status' => AttendanceStatus::Terlambat]);
        // 3 November tidak ada catatan → dihitung alpa. 4 November (hari ini) sudah tercatat.

        $response = $this->actingAs(User::factory()->create())->get("/rekap?bulan=2026-11&kelas={$class->id}");

        $response->assertOk()->assertSee('Budi Santoso');
        $row = $response->viewData('report')->rows->first();
        $this->assertSame(['H' => 1, 'T' => 1, 'I' => 0, 'S' => 0, 'A' => 1], $row['counts']);
        $this->assertSame(67, $row['percentage']);
    }

    public function test_today_without_record_is_not_counted_absent_yet(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 3)->setTime(8, 0));
        $student = Student::factory()->create();
        Attendance::factory()->create(['student_id' => $student->id, 'date' => '2026-11-02']);

        $row = $this->actingAs(User::factory()->create())->get('/rekap?bulan=2026-11')->viewData('report')->rows->first();

        $this->assertSame(0, $row['counts']['A']);
        $this->assertSame(100, $row['percentage']);
    }

    public function test_csv_export_uses_semicolon_and_bom(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(9, 0));
        Student::factory()->create(['name' => 'Budi Santoso', 'nis' => '2024001']);

        $response = $this->actingAs(User::factory()->create())->get('/rekap/unduh?bulan=2026-11');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('2024001;"Budi Santoso"', $content);
    }
}
