<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Device;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_without_token_is_rejected(): void
    {
        $this->postJson('/api/v1/taps', ['uid' => 'A1B2C3D4'])
            ->assertUnauthorized()
            ->assertJsonPath('result', 'unauthorized');
    }

    public function test_request_with_wrong_token_is_rejected(): void
    {
        Device::factory()->create()->issueToken();

        $this->withToken('dev_salah')->postJson('/api/v1/taps', ['uid' => 'A1B2C3D4'])->assertUnauthorized();
    }

    public function test_inactive_device_is_rejected(): void
    {
        $device = Device::factory()->create(['is_active' => false]);
        $token = $device->issueToken();

        $this->withToken($token)->postJson('/api/v1/taps', ['uid' => 'A1B2C3D4'])->assertForbidden();
    }

    public function test_valid_tap_returns_display_lines_for_lcd(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(6, 50));
        $device = Device::factory()->create();
        $token = $device->issueToken();
        Student::factory()->create(['rfid_uid' => 'A1B2C3D4', 'nickname' => 'Siti']);

        $this->withToken($token)
            ->postJson('/api/v1/taps', ['uid' => 'a1b2c3d4'])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'result' => 'check_in',
                'tone' => 'success',
                'display' => ['Siti', 'MASUK 06:50'],
            ]);

        $this->assertSame($device->id, Attendance::sole()->check_in_device_id);
    }

    public function test_token_can_also_be_sent_in_custom_header(): void
    {
        $token = Device::factory()->create()->issueToken();

        $this->withHeader('X-Device-Token', $token)
            ->postJson('/api/v1/taps', ['uid' => 'BELUMADA'])
            ->assertOk()
            ->assertJson(['ok' => false, 'result' => 'unknown_card', 'tone' => 'error']);
    }

    public function test_uid_is_required(): void
    {
        $token = Device::factory()->create()->issueToken();

        $this->withToken($token)->postJson('/api/v1/taps', [])->assertUnprocessable();
    }

    public function test_ping_reports_server_time(): void
    {
        $token = Device::factory()->create(['name' => 'Asrama'])->issueToken();

        $this->withToken($token)->getJson('/api/v1/ping')
            ->assertOk()
            ->assertJson(['ok' => true, 'device' => 'Asrama']);
    }

    public function test_regenerated_token_invalidates_the_old_one(): void
    {
        $device = Device::factory()->create();
        $old = $device->issueToken();
        $new = $device->issueToken();

        $this->withToken($old)->getJson('/api/v1/ping')->assertUnauthorized();
        $this->withToken($new)->getJson('/api/v1/ping')->assertOk();
    }
}
