<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SchoolSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public static function validPayload(): array
    {
        return [
            'school_name' => 'SD Negeri Contoh 01',
            'school_lat' => '-6.2000000',
            'school_lng' => '106.8166660',
            'radius_m' => '120',
            'max_accuracy_m' => '80',
            'guru_start' => '07:00',
            'guru_end' => '13:00',
            'tendik_start' => '07:30',
            'tendik_end' => '15:30',
            'late_tolerance_minutes' => '10',
            'check_in_opens' => '05:30',
            'school_days' => ['1', '2', '3', '4', '5'],
            'joint_holidays_off' => '1',
        ];
    }

    public function test_admin_sets_location_and_schedule(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/pengaturan')
            ->assertOk()
            ->assertSee('Lokasi belum diatur')
            ->assertSee('Gunakan lokasi saya sekarang');

        $this->put('/pengaturan', self::validPayload())->assertRedirect('/pengaturan')->assertSessionHas('success');

        $settings = app(SchoolSettings::class);
        $this->assertTrue($settings->hasLocation());
        $this->assertSame(-6.2, $settings->schoolLat());
        $this->assertSame(120, $settings->radius());
        $this->assertSame(80, $settings->maxAccuracy());
        $this->assertSame(10, $settings->lateTolerance());
        $this->assertSame([1, 2, 3, 4, 5], $settings->schoolDays());
        $this->assertSame('07:30', $settings->workStartFor(User::factory()->tendik()->make()));
        $this->assertSame('13:00', $settings->workEndFor(User::factory()->make()));

        $this->get('/pengaturan')->assertDontSee('Lokasi belum diatur')->assertSee('value="-6.2"', false);
    }

    public function test_validation(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->put('/pengaturan', [...self::validPayload(), 'school_lat' => '-95'])->assertSessionHasErrors('school_lat');
        $this->put('/pengaturan', [...self::validPayload(), 'school_lng' => ''])->assertSessionHasErrors('school_lng');
        $this->put('/pengaturan', [...self::validPayload(), 'radius_m' => '5'])->assertSessionHasErrors('radius_m');
        $this->put('/pengaturan', [...self::validPayload(), 'guru_end' => '06:00'])->assertSessionHasErrors(['guru_end' => 'Jam pulang guru harus setelah jam masuk.']);
        $this->put('/pengaturan', [...self::validPayload(), 'school_days' => []])->assertSessionHasErrors('school_days');

        $this->assertFalse(app(SchoolSettings::class)->hasLocation());
    }

    public function test_location_can_be_cleared(): void
    {
        $this->setUpSchool();

        $this->actingAs(User::factory()->admin()->create())
            ->put('/pengaturan', [...self::validPayload(), 'school_lat' => '', 'school_lng' => ''])
            ->assertSessionHasNoErrors();

        $this->assertFalse(app(SchoolSettings::class)->hasLocation());
    }
}
