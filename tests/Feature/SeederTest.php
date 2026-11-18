<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\SchoolSettings;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Rabu pukul 09.00: hari sekolah, sebagian guru & staf sudah presensi.
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(9, 0));
    }

    public function test_demo_seeder_fills_complete_trial_data(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(17, User::count());
        $this->assertSame(1, User::where('role', UserRole::Admin)->count());
        $this->assertSame(3, User::where('role', UserRole::Pimpinan)->count());
        $this->assertSame(1, User::where('is_active', false)->count());
        $this->assertTrue(app(SchoolSettings::class)->hasLocation());

        // Riwayat sejak awal bulan lalu, dengan semua status.
        $history = Attendance::where('date', '<', '2026-11-11')->get();
        $this->assertGreaterThan(400, $history->count());
        foreach (AttendanceStatus::cases() as $status) {
            $this->assertTrue($history->contains('status', $status), "Tidak ada status {$status->value} di riwayat");
        }
        $this->assertTrue($history->contains(fn (Attendance $a) => $a->early_minutes > 0));
        $this->assertTrue($history->every(fn (Attendance $a) => $a->check_in_distance === null || $a->check_in_distance <= 100));

        $sample = Holiday::where('type', Holiday::TYPE_SCHOOL)->value('date');
        $this->assertFalse(Attendance::where('date', $sample)->exists(), 'Hari libur contoh harus kosong');

        // Hari ini: sebagian sudah masuk, dua orang izin, dua belum presensi.
        $today = Attendance::where('date', '2026-11-11')->get();
        $this->assertGreaterThan(5, $today->whereNotNull('check_in_at')->count());
        $this->assertSame(2, $today->filter(fn (Attendance $a) => $a->status->isExcused())->count());
        $this->assertSame(13, $today->count());
        $this->assertSame(7, LeaveRequest::count());
    }

    public function test_trial_accounts_can_log_in_and_open_pages(): void
    {
        $this->seed(DemoSeeder::class);

        $this->post('/login', ['username' => 'kepsek', 'password' => DemoSeeder::PASSWORD])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk()->assertSee('Kehadiran Hari Ini')->assertSee('Siti Aminah');
        $this->get('/rekap?bulan=2026-10')->assertOk()->assertSee('Nur Hidayah');
        $this->get('/pengajuan?bulan=2026-10')->assertOk()->assertSee('Demam berdarah');
        $this->post('/logout');

        $this->post('/login', ['username' => 'guru', 'password' => DemoSeeder::PASSWORD])->assertRedirect('/presensi');
        $this->get('/presensi')->assertOk()->assertSee('Presensi Pulang');
        $this->get('/riwayat?bulan=2026-10')->assertOk();
    }

    public function test_running_the_seeder_twice_does_not_duplicate_data(): void
    {
        $this->seed(DemoSeeder::class);
        $count = Attendance::count();
        $this->seed(DemoSeeder::class);

        $this->assertSame(17, User::count());
        $this->assertSame($count, Attendance::count());
    }

    public function test_main_seeder_fills_trial_data_only_in_local_environment(): void
    {
        // Sama seperti `composer run setup` (migrate --force --seed).
        $this->app['env'] = 'production';
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertSame(1, User::count());
        $this->assertFalse(User::first()->must_attend);
        $this->assertSame(0, Attendance::count());

        $this->app['env'] = 'local';
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertSame(17, User::count());
    }
}
