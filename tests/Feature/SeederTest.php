<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\ScanResult;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Device;
use App\Models\Holiday;
use App\Models\RfidScan;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Rabu pukul 09.00: hari sekolah, sebagian siswa sudah tap.
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(9, 0));
    }

    public function test_demo_seeder_fills_complete_trial_data(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(1, User::where('role', UserRole::Admin)->count());
        $this->assertSame(4, User::where('role', UserRole::Guru)->count());
        $this->assertSame(4, SchoolClass::count());
        $this->assertSame(25, Student::count());
        $this->assertSame(1, Student::where('is_active', false)->count());
        $this->assertSame(3, Student::whereNull('rfid_uid')->count());
        $this->assertTrue(Student::where('rfid_uid', '1001')->exists());
        $this->assertSame(2, Holiday::count());
        $this->assertSame(1, Device::count());

        // Riwayat sejak awal bulan lalu, dengan berbagai status.
        $history = Attendance::where('date', '<', '2026-11-11')->get();
        $this->assertGreaterThan(500, $history->count());
        foreach (AttendanceStatus::cases() as $status) {
            $this->assertTrue($history->contains('status', $status), "Tidak ada status {$status->value} di riwayat");
        }
        $this->assertFalse(Attendance::where('date', '2026-10-21')->exists(), 'Hari libur contoh harus kosong');

        // Hari ini: tap lewat PC wali kelas, termasuk tap ulang dan kartu tak dikenal.
        $this->assertGreaterThan(0, Attendance::where('date', '2026-11-11')->whereNotNull('check_in_at')->count());
        $this->assertTrue(RfidScan::where('result', ScanResult::Duplicate)->exists());
        $this->assertTrue(RfidScan::where('uid', DemoSeeder::UNKNOWN_CARD)->where('result', ScanResult::UnknownCard)->exists());
        $this->assertSame(0, RfidScan::whereNull('user_id')->count());
    }

    public function test_trial_accounts_can_log_in_and_open_pages(): void
    {
        $this->seed(DemoSeeder::class);

        $this->post('/login', ['email' => 'guru@sekolah.test', 'password' => DemoSeeder::PASSWORD])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk()->assertSee('Kelas 1 SDLB-A');
        $this->get('/rekap?bulan=2026-10&kelas='.SchoolClass::first()->id)->assertOk()->assertSee('Aditya Pratama');
    }

    public function test_running_the_seeder_twice_does_not_duplicate_data(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(25, Student::count());
        $this->assertSame(5, User::count());
    }

    public function test_main_seeder_fills_trial_data_only_in_local_environment(): void
    {
        // Sama seperti `composer run setup` (migrate --force --seed).
        $this->app['env'] = 'production';
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertSame(1, User::count());
        $this->assertSame(0, Student::count());

        $this->app['env'] = 'local';
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertSame(25, Student::count());
    }
}
