<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorrectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guru;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(9, 0));
        $this->admin = User::factory()->admin()->create();
        $this->guru = User::factory()->create(['name' => 'Siti Aminah']);
    }

    public function test_admin_records_a_forgotten_presence(): void
    {
        $this->actingAs($this->admin)->get("/pegawai/{$this->guru->id}")->assertSee('Koreksi');
        $this->get("/pegawai/{$this->guru->id}/koreksi/2026-11-10")->assertOk()->assertSee('Selasa, 10 November 2026');

        $this->put("/pegawai/{$this->guru->id}/koreksi/2026-11-10", [
            'status' => 'terlambat', 'check_in' => '07:40', 'check_out' => '12:40', 'note' => 'HP rusak, konfirmasi kepala sekolah',
        ])->assertRedirect("/pegawai/{$this->guru->id}?bulan=2026-11")->assertSessionHas('success');

        $attendance = $this->guru->attendances()->sole();
        $this->assertSame(AttendanceStatus::Terlambat, $attendance->status);
        $this->assertSame('2026-11-10 07:40', $attendance->check_in_at->format('Y-m-d H:i'));
        $this->assertSame(40, $attendance->late_minutes);
        $this->assertSame(20, $attendance->early_minutes);
        $this->assertSame(Attendance::SOURCE_ADMIN, $attendance->source);
        $this->assertSame($this->admin->id, $attendance->recorded_by);

        $this->get("/pegawai/{$this->guru->id}")->assertSee('Koreksi admin: HP rusak');
    }

    public function test_changing_to_an_absence_clears_the_times(): void
    {
        $attendance = Attendance::factory()->for($this->guru)->create(['date' => '2026-11-10', 'check_in_at' => '2026-11-10 06:50:00', 'check_in_lat' => -6.2, 'check_in_lng' => 106.8]);

        $this->actingAs($this->admin)->put("/pegawai/{$this->guru->id}/koreksi/2026-11-10", [
            'status' => 'dinas_luar', 'check_in' => '06:50', 'note' => 'Ternyata rapat di dinas',
        ])->assertSessionHasNoErrors();

        $attendance->refresh();
        $this->assertSame(AttendanceStatus::DinasLuar, $attendance->status);
        $this->assertNull($attendance->check_in_at);
        $this->assertNull($attendance->check_in_lat);
    }

    public function test_keeping_the_time_keeps_the_gps_reading(): void
    {
        $attendance = Attendance::factory()->for($this->guru)->create(['date' => '2026-11-10', 'check_in_at' => '2026-11-10 07:20:00', 'status' => AttendanceStatus::Terlambat, 'late_minutes' => 20, 'check_in_lat' => -6.2]);

        $this->actingAs($this->admin)->put("/pegawai/{$this->guru->id}/koreksi/2026-11-10", [
            'status' => 'hadir', 'check_in' => '07:20', 'note' => 'Terlambat karena tugas sekolah',
        ]);

        $attendance->refresh();
        $this->assertSame(AttendanceStatus::Hadir, $attendance->status);
        $this->assertSame(0, $attendance->late_minutes);
        $this->assertSame(-6.2, $attendance->check_in_lat);
    }

    public function test_validation_and_dates(): void
    {
        $url = "/pegawai/{$this->guru->id}/koreksi/2026-11-10";
        $this->actingAs($this->admin);

        $this->put($url, ['status' => 'hadir', 'note' => 'x'])->assertSessionHasErrors(['check_in' => 'Isi jam masuk untuk status Hadir atau Terlambat.']);
        $this->put($url, ['status' => 'hadir', 'check_in' => '08:00', 'check_out' => '07:00', 'note' => 'x'])->assertSessionHasErrors('check_out');
        $this->put($url, ['status' => 'hadir', 'check_in' => '08:00'])->assertSessionHasErrors('note');
        $this->put($url, ['status' => 'alpa', 'check_out' => '07:00', 'note' => 'Tidak datang'])->assertSessionHasNoErrors();

        $this->get("/pegawai/{$this->guru->id}/koreksi/2026-11-12")->assertNotFound();
        $this->get("/pegawai/{$this->guru->id}/koreksi/kemarin")->assertNotFound();
    }
}
