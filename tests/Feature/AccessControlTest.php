<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/kiosk')->assertRedirect('/login');
        $this->get('/')->assertRedirect('/login');
    }

    public function test_user_can_log_in_and_out(): void
    {
        $user = User::factory()->create(['email' => 'guru@sekolah.test', 'password' => 'rahasia123']);

        $this->post('/login', ['email' => 'guru@sekolah.test', 'password' => 'salah'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'guru@sekolah.test', 'password' => 'rahasia123'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        User::factory()->create(['email' => 'guru@sekolah.test']);

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['email' => 'guru@sekolah.test', 'password' => 'salah']);
        }

        $this->post('/login', ['email' => 'guru@sekolah.test', 'password' => 'salah'])->assertTooManyRequests();
    }

    public function test_kiosk_account_lands_on_kiosk_and_cannot_open_dashboard(): void
    {
        User::factory()->kiosk()->create(['email' => 'kiosk@sekolah.test', 'password' => 'rahasia123']);

        $this->post('/login', ['email' => 'kiosk@sekolah.test', 'password' => 'rahasia123'])->assertRedirect('/kiosk');

        $this->get('/dashboard')->assertRedirect('/kiosk');
        $this->get('/siswa')->assertRedirect('/kiosk');
        $this->getJson('/dashboard/live')->assertForbidden();
    }

    public function test_teacher_can_view_but_not_manage_master_data(): void
    {
        $guru = User::factory()->create();

        $this->actingAs($guru);
        $this->get('/dashboard')->assertOk();
        $this->get('/presensi')->assertOk();
        $this->get('/rekap')->assertOk();
        $this->get('/siswa')->assertOk();
        $this->get('/log-scan')->assertOk();

        $this->get('/siswa/tambah')->assertForbidden();
        $this->get('/kelas')->assertForbidden();
        $this->get('/perangkat')->assertForbidden();
        $this->get('/pengguna')->assertForbidden();
        $this->get('/pengaturan')->assertForbidden();
    }

    public function test_admin_can_open_every_page(): void
    {
        $admin = User::factory()->admin()->create();
        $device = Device::factory()->create();

        $this->actingAs($admin);

        foreach (['/dashboard', '/presensi', '/rekap', '/log-scan', '/siswa', '/siswa/tambah', '/siswa/impor',
            '/kelas', '/kelas/tambah', '/perangkat', '/perangkat/tambah', '/hari-libur', '/pengguna',
            '/pengguna/tambah', '/pengaturan', '/kiosk', "/kiosk/{$device->id}"] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
