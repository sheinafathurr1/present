<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeType;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Rabu, hari sekolah. Jam kerja guru bawaan 07:00–13:00, toleransi 15 menit.
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(6, 50));
        $this->setUpSchool();
    }

    public function test_check_in_inside_the_school_area_is_recorded(): void
    {
        $guru = User::factory()->create();

        $this->actingAs($guru)->post('/presensi/masuk', $this->gps(30, 12))
            ->assertRedirect('/presensi')
            ->assertSessionHas('success', 'Presensi masuk tercatat pukul 06:50. Selamat bekerja!');

        $attendance = Attendance::sole();
        $this->assertSame($guru->id, $attendance->user_id);
        $this->assertSame('2026-11-11', $attendance->date);
        $this->assertSame(AttendanceStatus::Hadir, $attendance->status);
        $this->assertSame('2026-11-11 06:50:00', $attendance->check_in_at->format('Y-m-d H:i:s'));
        $this->assertSame(30, $attendance->check_in_distance);
        $this->assertSame(12, $attendance->check_in_accuracy);
        $this->assertSame(0, $attendance->late_minutes);
        $this->assertSame(Attendance::SOURCE_GPS, $attendance->source);
    }

    public function test_late_only_after_the_tolerance_counted_from_start_time(): void
    {
        [$onTime, $late] = User::factory()->count(2)->create();

        $this->travelTo(now()->setTime(7, 15, 30));
        $this->actingAs($onTime)->post('/presensi/masuk', $this->gps());

        $this->travelTo(now()->setTime(7, 16, 10));
        $this->actingAs($late)->post('/presensi/masuk', $this->gps())
            ->assertSessionHas('success', 'Presensi masuk tercatat pukul 07:16 · terlambat 16 menit.');

        $this->assertSame(AttendanceStatus::Hadir, $onTime->attendances()->sole()->status);
        $this->assertSame(0, $onTime->attendances()->sole()->late_minutes);
        $this->assertSame(AttendanceStatus::Terlambat, $late->attendances()->sole()->status);
        $this->assertSame(16, $late->attendances()->sole()->late_minutes);
    }

    public function test_schedule_follows_employee_type_and_personal_hours(): void
    {
        $this->setUpSchool(['tendik_start' => '07:30', 'late_tolerance_minutes' => 0]);
        $guru = User::factory()->create();
        $tendik = User::factory()->tendik()->create();
        $siang = User::factory()->create(['work_start' => '09:00', 'work_end' => '15:00']);

        $this->travelTo(now()->setTime(7, 40));
        foreach ([$guru, $tendik, $siang] as $user) {
            $this->actingAs($user)->post('/presensi/masuk', $this->gps());
        }

        $this->assertSame(40, $guru->attendances()->sole()->late_minutes);
        $this->assertSame(10, $tendik->attendances()->sole()->late_minutes);
        $this->assertSame(EmployeeType::Tendik, $tendik->employee_type);
        $this->assertSame(AttendanceStatus::Hadir, $siang->attendances()->sole()->status);
    }

    public function test_check_in_outside_the_radius_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())->post('/presensi/masuk', $this->gps(150))
            ->assertRedirect('/presensi')
            ->assertSessionHas('error', 'Anda berada 150 m dari sekolah. Presensi hanya bisa dilakukan dalam radius 100 m dari sekolah.');

        $this->assertSame(0, Attendance::count());
    }

    public function test_inaccurate_gps_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())->post('/presensi/masuk', $this->gps(10, 180))
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'kurang akurat (± 180 m, batas 100 m)'));

        $this->assertSame(0, Attendance::count());
    }

    public function test_location_is_required(): void
    {
        $this->actingAs(User::factory()->create())->post('/presensi/masuk', [])
            ->assertSessionHasErrors(['lat', 'lng', 'accuracy']);

        $this->post('/presensi/masuk', ['lat' => 120, 'lng' => 106.8, 'accuracy' => 10])->assertSessionHasErrors('lat');
    }

    public function test_presence_needs_the_school_location(): void
    {
        $this->setUpSchool(['school_lat' => null, 'school_lng' => null]);

        $this->actingAs(User::factory()->create())->post('/presensi/masuk', $this->gps())
            ->assertSessionHas('error', 'Lokasi sekolah belum diatur. Hubungi admin sekolah.');
    }

    public function test_checking_in_twice_keeps_the_first_time(): void
    {
        $guru = User::factory()->create();
        $this->actingAs($guru)->post('/presensi/masuk', $this->gps());

        $this->travelTo(now()->setTime(7, 30));
        $this->post('/presensi/masuk', $this->gps())
            ->assertSessionHas('error', 'Anda sudah presensi masuk pukul 06:50.');

        $this->assertSame('06:50', $guru->attendances()->sole()->check_in_at->format('H:i'));
    }

    public function test_check_out_records_early_leave_once(): void
    {
        $guru = User::factory()->create();
        $this->actingAs($guru)->post('/presensi/masuk', $this->gps());

        $this->travelTo(now()->setTime(12, 15));
        $this->post('/presensi/pulang', $this->gps(40, 20))
            ->assertSessionHas('success', 'Presensi pulang tercatat pukul 12:15 · pulang awal 45 menit.');

        $attendance = $guru->attendances()->sole();
        $this->assertSame(AttendanceStatus::Hadir, $attendance->status);
        $this->assertSame(45, $attendance->early_minutes);
        $this->assertSame(40, $attendance->check_out_distance);

        $this->travelTo(now()->setTime(13, 5));
        $this->post('/presensi/pulang', $this->gps())->assertSessionHas('error', 'Anda sudah presensi pulang pukul 12:15.');
    }

    public function test_check_out_after_work_end_is_not_early(): void
    {
        $guru = User::factory()->create();
        $this->actingAs($guru)->post('/presensi/masuk', $this->gps());

        $this->travelTo(now()->setTime(13, 2));
        $this->post('/presensi/pulang', $this->gps())
            ->assertSessionHas('success', 'Presensi pulang tercatat pukul 13:02. Terima kasih, hati-hati di jalan!');

        $this->assertSame(0, $guru->attendances()->sole()->early_minutes);
    }

    public function test_check_out_requires_check_in_and_the_school_area(): void
    {
        $guru = User::factory()->create();

        $this->actingAs($guru)->post('/presensi/pulang', $this->gps())
            ->assertSessionHas('error', 'Anda belum presensi masuk hari ini.');

        $this->post('/presensi/masuk', $this->gps());
        $this->post('/presensi/pulang', $this->gps(2500))
            ->assertSessionHas('error', fn (string $message) => str_starts_with($message, 'Anda berada 2,5 km dari sekolah.'));
        $this->assertNull($guru->attendances()->sole()->check_out_at);
    }

    public function test_no_presence_on_holidays_and_non_school_days(): void
    {
        $guru = User::factory()->create();
        Holiday::create(['date' => '2026-11-11', 'name' => 'Libur contoh']);

        $this->actingAs($guru)->post('/presensi/masuk', $this->gps())
            ->assertSessionHas('error', 'Hari ini libur: Libur contoh. Presensi tidak diperlukan.');

        $this->get('/presensi')->assertOk()->assertSee('Tidak ada presensi hari ini')->assertDontSee('data-presence-form', false);

        $this->travelTo(now()->setDate(2026, 11, 15)->setTime(7, 0)); // Minggu
        $this->post('/presensi/masuk', $this->gps())
            ->assertSessionHas('error', 'Minggu bukan hari sekolah. Presensi tidak diperlukan.');

        $this->assertSame(0, Attendance::count());
    }

    public function test_holiday_marked_as_school_day_allows_presence(): void
    {
        Holiday::create(['date' => '2026-11-11', 'name' => 'Cuti bersama', 'type' => Holiday::TYPE_JOINT, 'is_auto' => true, 'is_school_day' => true]);

        $this->actingAs(User::factory()->create())->post('/presensi/masuk', $this->gps())->assertSessionHas('success');
    }

    public function test_check_in_opens_at_the_configured_time(): void
    {
        $this->travelTo(now()->setTime(4, 30));

        $this->actingAs(User::factory()->create())->post('/presensi/masuk', $this->gps())
            ->assertSessionHas('error', 'Presensi masuk baru dibuka pukul 05:00.');
    }

    public function test_exempt_and_inactive_accounts_cannot_check_in(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post('/presensi/masuk', $this->gps())
            ->assertSessionHas('error', 'Akun Anda tidak diwajibkan presensi.');

        $this->actingAs(User::factory()->inactive()->create())->post('/presensi/masuk', $this->gps())
            ->assertSessionHas('error', 'Akun Anda nonaktif. Hubungi admin sekolah.');

        $this->assertSame(0, Attendance::count());
    }

    public function test_check_in_replaces_an_automatic_absent_mark(): void
    {
        $guru = User::factory()->create();
        Attendance::factory()->for($guru)->create(['status' => AttendanceStatus::Alpa, 'check_in_at' => null, 'source' => Attendance::SOURCE_SYSTEM]);

        $this->travelTo(now()->setTime(16, 30));
        $this->actingAs($guru)->post('/presensi/masuk', $this->gps())->assertSessionHas('success');

        $attendance = $guru->attendances()->sole();
        $this->assertSame(AttendanceStatus::Terlambat, $attendance->status);
        $this->assertSame(570, $attendance->late_minutes);
    }

    public function test_presence_page_follows_the_day(): void
    {
        $guru = User::factory()->create(['name' => 'Siti Aminah']);

        $this->actingAs($guru)->get('/presensi')
            ->assertOk()
            ->assertSee('Siti Aminah')
            ->assertSee('Jam kerja 07:00–13:00')
            ->assertSee('Presensi Masuk')
            ->assertSee('data-lat="'.self::LAT.'"', false)
            ->assertSee('data-radius="100"', false)
            ->assertSee('Tidak masuk hari ini?');

        $this->post('/presensi/masuk', $this->gps());
        $this->get('/presensi')->assertSee('Presensi Pulang')->assertSee('data-work-end="13:00"', false)->assertDontSee('Tidak masuk hari ini?');

        $this->travelTo(now()->setTime(13, 10));
        $this->post('/presensi/pulang', $this->gps());
        $this->get('/presensi')->assertSee('Presensi hari ini lengkap')->assertDontSee('data-presence-form', false);
    }

    public function test_presence_buttons_are_rate_limited(): void
    {
        $guru = User::factory()->create();

        for ($i = 0; $i < 12; $i++) {
            $this->actingAs($guru)->post('/presensi/masuk', $this->gps(500));
        }

        $this->post('/presensi/masuk', $this->gps())->assertStatus(429);
    }
}
