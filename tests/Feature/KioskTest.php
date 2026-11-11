<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KioskTest extends TestCase
{
    use RefreshDatabase;

    public function test_kiosk_tap_returns_feedback_and_today_stats(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(7, 5));
        $device = Device::factory()->create();
        Student::factory()->create(['rfid_uid' => '0004283792', 'name' => 'Rina Wulandari', 'nickname' => 'Rina', 'feedback_mode' => 'audio']);
        Student::factory()->create();

        $this->actingAs(User::factory()->kiosk()->create())
            ->postJson("/kiosk/{$device->id}/tap", ['uid' => '0004283792'])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'result' => 'check_in',
                'headline' => 'Masuk',
                'student' => ['name' => 'Rina Wulandari', 'call_name' => 'Rina', 'feedback_mode' => 'audio', 'initials' => 'RW'],
                'stats' => ['present' => 1, 'total' => 2],
            ])
            ->assertJsonPath('speech_detailed', 'Selamat pagi, Rina. Presensi masuk berhasil, pukul 7 lewat 5 menit. Selamat belajar.');
    }

    public function test_inactive_device_cannot_be_used_as_kiosk(): void
    {
        $device = Device::factory()->create(['is_active' => false]);

        $this->actingAs(User::factory()->kiosk()->create())
            ->postJson("/kiosk/{$device->id}/tap", ['uid' => 'A1B2C3D4'])
            ->assertNotFound();
    }

    public function test_kiosk_page_and_stats_load(): void
    {
        $device = Device::factory()->create(['name' => 'Gerbang Utama']);
        $user = User::factory()->kiosk()->create();

        $this->actingAs($user)->get("/kiosk/{$device->id}")->assertOk()->assertSee('Gerbang Utama');
        $this->actingAs($user)->getJson("/kiosk/{$device->id}/stats")->assertOk()->assertJsonStructure(['stats' => ['present', 'total']]);
    }
}
