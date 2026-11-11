<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\RfidScan;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pembaca USB dicolok ke PC guru; siswa menempelkan kartu saat guru membuka dashboard.
 */
class TeacherPcTapTest extends TestCase
{
    use RefreshDatabase;

    public function test_tap_on_teacher_pc_records_attendance_and_returns_feedback(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(7, 5));
        $guru = User::factory()->create(['name' => 'Ibu Sari']);
        $student = Student::factory()->create([
            'rfid_uid' => '0004283792', 'name' => 'Rina Wulandari', 'nickname' => 'Rina', 'feedback_mode' => 'audio',
        ]);

        $this->actingAs($guru)
            ->postJson('/tap', ['uid' => '0004283792'])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'result' => 'check_in',
                'tone' => 'success',
                'headline' => 'Masuk',
                'student' => ['id' => $student->id, 'name' => 'Rina Wulandari', 'call_name' => 'Rina', 'feedback_mode' => 'audio', 'initials' => 'RW'],
                'attendance' => ['status' => 'hadir', 'status_code' => 'H', 'check_in' => '07.05', 'check_out' => null],
            ])
            ->assertJsonPath('speech_detailed', 'Selamat pagi, Rina. Presensi masuk berhasil, pukul 7 lewat 5 menit. Selamat belajar.');

        $this->assertSame($student->id, Attendance::sole()->student_id);

        $scan = RfidScan::sole();
        $this->assertSame($guru->id, $scan->user_id);
        $this->assertNull($scan->device_id);
        $this->assertSame('PC Ibu Sari', $scan->sourceName());
    }

    public function test_unknown_card_on_teacher_pc_is_reported(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/tap', ['uid' => 'KARTUBARU1'])
            ->assertOk()
            ->assertJson(['ok' => false, 'result' => 'unknown_card', 'tone' => 'error', 'student' => null, 'attendance' => null]);
    }

    public function test_uid_is_required(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/tap', ['uid' => '---'])->assertUnprocessable();
    }

    public function test_dashboard_shows_today_numbers_per_class(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(7, 30));
        $class = SchoolClass::factory()->create(['name' => 'Kelas 1A', 'level' => 'SDLB']);
        Student::factory()->create(['school_class_id' => $class->id]);
        $present = Student::factory()->create(['school_class_id' => $class->id]);
        Attendance::factory()->create(['student_id' => $present->id, 'date' => '2026-11-11']);

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Siap menerima kartu')
            ->assertSee('Kehadiran hari ini')
            ->assertSee('Kelas 1A')
            ->assertDontSee('SLB');

        $this->getJson('/dashboard/live')
            ->assertOk()
            ->assertJsonPath('totals.present', 1)
            ->assertJsonPath('totals.unrecorded', 1)
            ->assertJsonPath('classes.0.id', $class->id)
            ->assertJsonPath('classes.0.present', 1)
            ->assertJsonPath('classes.0.percentage', 50);
    }

    public function test_heartbeat_keeps_session_alive(): void
    {
        $this->actingAs(User::factory()->create())->get('/heartbeat')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->getJson('/heartbeat')->assertUnauthorized();
    }
}
