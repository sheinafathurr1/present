<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveType;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    private User $kepsek;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(9, 0));
        $this->setUpSchool();
        $this->kepsek = User::factory()->pimpinan()->create(['name' => 'Bambang Kepsek', 'must_attend' => false]);
    }

    /** @return array{0: User, 1: User, 2: User, 3: User} */
    private function staffWithToday(): array
    {
        $hadir = User::factory()->create(['name' => 'Ani Hadir']);
        $telat = User::factory()->create(['name' => 'Budi Telat']);
        $sakit = User::factory()->tendik()->create(['name' => 'Citra Sakit']);
        $belum = User::factory()->tendik()->create(['name' => 'Dodi Belum']);
        User::factory()->inactive()->create(['name' => 'Eko Nonaktif']);
        User::factory()->admin()->create(['name' => 'Opik Operator']); // tidak wajib presensi

        Attendance::factory()->for($hadir)->create(['check_in_at' => '2026-11-11 06:45:00', 'check_in_distance' => 23]);
        Attendance::factory()->for($telat)->create(['status' => AttendanceStatus::Terlambat, 'check_in_at' => '2026-11-11 07:35:00', 'late_minutes' => 35]);
        $leave = LeaveRequest::factory()->for($sakit)->create(['type' => LeaveType::Sakit, 'reason' => 'Demam tinggi']);
        app(AttendanceService::class)->recordLeave($leave);

        return [$hadir, $telat, $sakit, $belum];
    }

    public function test_dashboard_shows_today_for_everyone(): void
    {
        $this->staffWithToday();

        $this->actingAs($this->kepsek)->get('/dashboard')
            ->assertOk()
            ->assertSee('Rabu, 11 November 2026')
            ->assertSeeInOrder(['Dodi Belum', 'Budi Telat', 'Citra Sakit', 'Ani Hadir']) // yang perlu perhatian lebih dulu
            ->assertSee('23 m dari sekolah')
            ->assertSee('Terlambat 35 menit')
            ->assertSee('Demam tinggi')
            ->assertSee('50%')
            ->assertDontSee('Eko Nonaktif')
            ->assertDontSee('Opik Operator');

        $this->get('/dashboard?jenis=tendik')->assertSee('Dodi Belum')->assertDontSee('Ani Hadir');
    }

    public function test_monthly_report_and_csv_export(): void
    {
        [$hadir, $telat] = $this->staffWithToday();
        Attendance::factory()->for($hadir)->create(['date' => '2026-11-10', 'check_in_at' => '2026-11-10 06:50:00', 'check_out_at' => '2026-11-10 12:30:00', 'early_minutes' => 30]);
        Attendance::factory()->for($telat)->create(['date' => '2026-11-10', 'status' => AttendanceStatus::Alpa, 'check_in_at' => null, 'source' => Attendance::SOURCE_SYSTEM]);

        $this->actingAs($this->kepsek)->get('/rekap?bulan=2026-11')
            ->assertOk()
            ->assertSee('November 2026')
            ->assertSee('Ani Hadir')
            ->assertSee('Dinas luar');

        $csv = $this->get('/rekap/unduh?bulan=2026-11')
            ->assertOk()
            ->assertDownload('rekap-presensi-2026-11.csv')
            ->streamedContent();

        $lines = array_map(fn ($line) => str_getcsv($line, ';'), array_filter(explode("\n", trim(ltrim($csv, "\xEF\xBB\xBF")))));
        $header = $lines[0];
        $this->assertSame(['Nama', 'NIP', 'Jabatan', 'Jenis'], array_slice($header, 0, 4));
        $this->assertContains('Total terlambat (menit)', $header);

        $ani = collect($lines)->first(fn ($row) => $row[0] === 'Ani Hadir');
        $column = fn (string $name) => $ani[array_search($name, $header, true)];
        $this->assertSame('H', $ani[4 + 9], '10 November');
        $this->assertSame('L', $ani[4 + 14], 'Minggu 15 November');
        $this->assertSame('2', $column('Hadir'));
        $this->assertSame('1', $column('Pulang awal (kali)'));

        $budi = collect($lines)->first(fn ($row) => $row[0] === 'Budi Telat');
        $this->assertSame('A', $budi[4 + 9]);
        $this->assertSame('35', $budi[array_search('Total terlambat (menit)', $header, true)]);

        $this->get('/rekap/unduh?bulan=2026-11&jenis=tendik')->assertDownload('rekap-presensi-2026-11-tendik.csv');
    }

    public function test_employee_detail_shows_history_with_location(): void
    {
        [$hadir] = $this->staffWithToday();
        $guru = User::factory()->create(['name' => 'Fajar Guru', 'username' => 'fajar']);
        Attendance::factory()->for($guru)->create(['date' => '2026-11-10', 'check_in_at' => '2026-11-10 06:58:00', 'check_in_distance' => 41, 'check_in_accuracy' => 12]);
        LeaveRequest::factory()->for($guru)->create(['type' => LeaveType::DinasLuar, 'start_date' => '2026-11-12', 'end_date' => '2026-11-12', 'reason' => 'Rapat KKG']);

        $this->actingAs($this->kepsek)->get("/pegawai/{$guru->id}")
            ->assertOk()
            ->assertSee('Fajar Guru')
            ->assertSee('fajar')
            ->assertSee('41 m · ± 12 m')
            ->assertSee('Rapat KKG')
            ->assertSee('Tidak ada catatan') // 9 November
            ->assertDontSee('Koreksi');

        $this->get('/pegawai')->assertOk()->assertSee('Fajar Guru')->assertSee('Ani Hadir')->assertDontSee('Eko Nonaktif');
        $this->get('/pegawai?status=nonaktif')->assertSee('Eko Nonaktif');
        $this->get('/pegawai?cari=fajar')->assertSee('Fajar Guru')->assertDontSee('Ani Hadir');
    }

    public function test_leave_monitor_lists_requests_of_the_month(): void
    {
        $this->staffWithToday();
        LeaveRequest::factory()->create(['type' => LeaveType::Cuti, 'start_date' => '2026-10-05', 'end_date' => '2026-10-06', 'reason' => 'Cuti Oktober']);

        $this->actingAs($this->kepsek)->get('/pengajuan')
            ->assertOk()->assertSee('Citra Sakit')->assertSee('Demam tinggi')->assertDontSee('Cuti Oktober');

        $this->get('/pengajuan?bulan=2026-10')->assertSee('Cuti Oktober')->assertDontSee('Demam tinggi');
        $this->get('/pengajuan?jenis=cuti')->assertDontSee('Demam tinggi');
    }

    public function test_own_history_page(): void
    {
        [$hadir] = $this->staffWithToday();
        Attendance::factory()->for($hadir)->create(['date' => '2026-11-10', 'check_in_at' => '2026-11-10 06:50:00', 'check_out_at' => '2026-11-10 12:30:00', 'early_minutes' => 30]);

        $this->actingAs($hadir)->get('/riwayat')
            ->assertOk()
            ->assertSee('November 2026')
            ->assertSee('Pulang awal 30 menit')
            ->assertSee('Hari ini');

        $this->get('/riwayat?bulan=2026-10')->assertOk()->assertSee('Oktober 2026');
        $this->get('/riwayat?bulan=salah')->assertOk()->assertSee('November 2026');
    }
}
