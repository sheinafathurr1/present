<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\User;
use App\Services\NationalHolidaySync;
use App\Support\SchoolSettings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HolidayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(9, 0));
    }

    private function fakeFeed(): void
    {
        Http::fake(['calendar.google.com/*' => Http::response(file_get_contents(base_path('tests/Fixtures/hari-libur-indonesia.ics')))]);
    }

    public function test_parser_keeps_holidays_and_skips_observances(): void
    {
        $events = collect(app(NationalHolidaySync::class)->parseIcs(file_get_contents(base_path('tests/Fixtures/hari-libur-indonesia.ics'))))
            ->keyBy('date');

        $this->assertCount(8, $events);
        $this->assertFalse($events->has('2026-02-19'), '"1 Ramadan" adalah perayaan, bukan libur');
        $this->assertSame(['date' => '2026-02-17', 'name' => 'Tahun Baru Imlek', 'type' => 'nasional', 'tentative' => false], $events['2026-02-17']);
        $this->assertSame('cuti_bersama', $events['2026-02-16']['type']);
        $this->assertSame(['date' => '2027-01-05', 'name' => 'Isra Mikraj Nabi Muhammad', 'type' => 'nasional', 'tentative' => true], $events['2027-01-05']);
        $this->assertSame(['date' => '2027-03-25', 'name' => 'Cuti Bersama Good Friday', 'type' => 'cuti_bersama', 'tentative' => false], $events['2027-03-25']);
    }

    public function test_sync_imports_national_holidays_and_is_repeatable(): void
    {
        $this->fakeFeed();
        Holiday::create(['date' => '2026-03-20', 'name' => 'Libur sekolah buatan admin']);

        $first = app(NationalHolidaySync::class)->sync([2026, 2027]);

        $this->assertSame('internet', $first['source']);
        $this->assertSame(7, $first['added']); // 8 libur, satu tanggal sudah dipakai libur sekolah
        $this->assertSame('Libur sekolah buatan admin', Holiday::where('date', '2026-03-20')->value('name'));
        $this->assertTrue(Holiday::where('date', '2026-03-21')->value('is_auto'));
        $this->assertTrue(Holiday::where('date', '2027-01-05')->value('is_tentative'));

        // Admin memutuskan tetap masuk pada cuti bersama Imlek; pilihan ini bertahan setelah diperbarui.
        Holiday::where('date', '2026-02-16')->update(['is_school_day' => true]);
        $second = app(NationalHolidaySync::class)->sync([2026, 2027]);

        $this->assertSame(['added' => 0, 'updated' => 0, 'removed' => 0], [
            'added' => $second['added'], 'updated' => $second['updated'], 'removed' => $second['removed'],
        ]);
        $this->assertTrue(Holiday::where('date', '2026-02-16')->value('is_school_day'));
    }

    public function test_sync_removes_dates_that_were_moved_in_the_feed(): void
    {
        $this->fakeFeed();
        Holiday::create(['date' => '2026-04-01', 'name' => 'Tanggal lama', 'type' => Holiday::TYPE_NATIONAL, 'is_auto' => true]);

        $result = app(NationalHolidaySync::class)->sync([2026]);

        $this->assertSame(1, $result['removed']);
        $this->assertFalse(Holiday::where('date', '2026-04-01')->exists());
    }

    public function test_without_internet_fixed_date_holidays_are_filled(): void
    {
        Http::fake(['*' => Http::response('Service Unavailable', 503)]);

        $result = app(NationalHolidaySync::class)->sync([2026]);

        $this->assertSame('bawaan', $result['source']);
        $this->assertNotNull($result['error']);
        $this->assertSame([
            '2026-01-01', '2026-04-03', '2026-04-05', '2026-05-01', '2026-05-14', '2026-06-01', '2026-08-17', '2026-12-25',
        ], Holiday::orderBy('date')->pluck('date')->all());
    }

    public function test_easter_calculation_matches_official_dates(): void
    {
        $this->assertSame('2025-04-20', NationalHolidaySync::easterSunday(2025)->toDateString());
        $this->assertSame('2026-04-05', NationalHolidaySync::easterSunday(2026)->toDateString());
        $this->assertSame('2027-03-28', NationalHolidaySync::easterSunday(2027)->toDateString());
    }

    public function test_joint_holidays_can_count_as_school_days(): void
    {
        $this->fakeFeed();
        app(SchoolSettings::class)->update(['joint_holidays_off' => false]);
        app(NationalHolidaySync::class)->sync([2026]);

        $settings = app(SchoolSettings::class);
        $this->assertTrue($settings->isSchoolDay(CarbonImmutable::parse('2026-02-16')), 'Cuti bersama → tetap masuk');
        $this->assertFalse($settings->isSchoolDay(CarbonImmutable::parse('2026-02-17')), 'Imlek tetap libur');
    }

    public function test_changing_the_joint_holiday_setting_updates_existing_days(): void
    {
        $this->fakeFeed();
        app(NationalHolidaySync::class)->sync([2026]);

        $this->actingAs(User::factory()->admin()->create())->put('/pengaturan', [
            'school_name' => 'Sekolah Contoh',
            'late_after' => '07:15',
            'check_out_start' => '12:00',
            'tap_cooldown_seconds' => 60,
            'school_days' => [1, 2, 3, 4, 5, 6],
            'joint_holidays_off' => '0',
        ])->assertRedirect('/pengaturan');

        $this->assertTrue(Holiday::where('date', '2026-02-16')->value('is_school_day'));
        $this->assertFalse(Holiday::where('date', '2026-02-17')->value('is_school_day'));
    }

    public function test_holiday_page_shows_calendar_and_manages_days(): void
    {
        $this->fakeFeed();
        app(NationalHolidaySync::class)->sync([2026]);
        $admin = User::factory()->admin()->create();
        $imlek = Holiday::where('date', '2026-02-17')->first();

        $this->actingAs($admin)->get('/hari-libur?tahun=2026')
            ->assertOk()
            ->assertSee('Hari Libur 2026')
            ->assertSee('Desember')
            ->assertSee('data-cal-day="2026-02-17"', false)
            ->assertSee('Selasa, 17 Februari 2026: Tahun Baru Imlek (Libur nasional)');

        // Libur nasional tidak bisa dihapus, tetapi bisa ditandai tetap masuk.
        $this->delete("/hari-libur/{$imlek->id}")->assertSessionHas('error');
        $this->patch("/hari-libur/{$imlek->id}/masuk")->assertSessionHas('success');
        $this->assertTrue($imlek->fresh()->is_school_day);
        $this->assertTrue(app(SchoolSettings::class)->isSchoolDay(CarbonImmutable::parse('2026-02-17')));

        // Libur sekolah buatan admin bisa dihapus.
        $this->post('/hari-libur', ['name' => 'Libur semester', 'date' => '2026-12-21', 'end_date' => '2026-12-23']);
        $this->assertSame(3, Holiday::where('type', Holiday::TYPE_SCHOOL)->count());
        $this->delete('/hari-libur/'.Holiday::where('date', '2026-12-21')->value('id'))->assertSessionHas('success');
    }

    public function test_update_button_and_command(): void
    {
        $this->fakeFeed();

        $this->actingAs(User::factory()->admin()->create())
            ->post('/hari-libur/perbarui', ['tahun' => 2026])
            ->assertRedirect('/hari-libur?tahun=2026')
            ->assertSessionHas('success');

        $this->artisan('presensi:sinkron-libur', ['--tahun' => ['2027']])->assertSuccessful();
        $this->assertTrue(Holiday::where('date', '2027-01-05')->exists());
    }
}
