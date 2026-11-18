<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkAbsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_people_without_any_record_are_marked_absent(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(16, 0));

        $hadir = User::factory()->create();
        $alpa = User::factory()->create();
        User::factory()->inactive()->create();
        User::factory()->admin()->create(); // tidak wajib presensi
        User::factory()->create(['created_at' => '2026-11-12 08:00:00']); // baru terdaftar besok
        Attendance::factory()->for($hadir)->create();

        $this->artisan('presensi:tandai-alpa')->expectsOutputToContain('1 guru/staf ditandai Alpa')->assertSuccessful();

        $this->assertSame(AttendanceStatus::Alpa, $alpa->attendances()->sole()->status);
        $this->assertSame(Attendance::SOURCE_SYSTEM, $alpa->attendances()->sole()->source);
        $this->assertSame(2, Attendance::count());

        // Dijalankan ulang: tidak ada yang ditandai dua kali.
        $this->artisan('presensi:tandai-alpa')->expectsOutputToContain('0 guru/staf')->assertSuccessful();
    }

    public function test_holidays_are_skipped(): void
    {
        User::factory()->create();
        Holiday::create(['date' => '2026-11-11', 'name' => 'Libur contoh']);

        $this->artisan('presensi:tandai-alpa', ['--tanggal' => '2026-11-11'])->expectsOutputToContain('bukan hari sekolah')->assertSuccessful();
        $this->artisan('presensi:tandai-alpa', ['--tanggal' => '2026-11-15'])->expectsOutputToContain('bukan hari sekolah'); // Minggu

        $this->assertSame(0, Attendance::count());
    }
}
