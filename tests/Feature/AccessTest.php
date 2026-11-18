<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    private const MONITORING = ['/dashboard', '/rekap', '/rekap/unduh', '/pengajuan', '/pegawai'];

    private const ADMIN_ONLY = ['/pegawai/tambah', '/pengaturan', '/hari-libur'];

    public function test_guests_are_sent_to_login(): void
    {
        foreach (['/presensi', '/riwayat', '/izin', '/akun', ...self::MONITORING] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_staff_only_see_their_own_pages(): void
    {
        $guru = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($guru);
        foreach (['/presensi', '/riwayat', '/izin', '/izin/ajukan', '/akun'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach ([...self::MONITORING, ...self::ADMIN_ONLY, "/pegawai/{$other->id}"] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_leaders_can_view_but_not_change_anything(): void
    {
        $kepsek = User::factory()->pimpinan()->create();
        $guru = User::factory()->create();

        $this->actingAs($kepsek);
        foreach ([...self::MONITORING, "/pegawai/{$guru->id}", '/presensi', '/izin'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach ([...self::ADMIN_ONLY, "/pegawai/{$guru->id}/ubah", "/pegawai/{$guru->id}/koreksi/2026-11-10"] as $url) {
            $this->get($url)->assertForbidden();
        }

        $this->post('/pegawai', ['name' => 'X'])->assertForbidden();
        $this->put("/pegawai/{$guru->id}", ['name' => 'X'])->assertForbidden();
        $this->delete("/pegawai/{$guru->id}")->assertForbidden();
        $this->put("/pegawai/{$guru->id}/koreksi/2026-11-10", ['status' => 'alpa'])->assertForbidden();
        $this->put('/pengaturan', [])->assertForbidden();
        $this->post('/hari-libur', [])->assertForbidden();
    }

    public function test_admin_can_open_everything(): void
    {
        $guru = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create());
        foreach ([...self::MONITORING, ...self::ADMIN_ONLY, "/pegawai/{$guru->id}", "/pegawai/{$guru->id}/ubah", '/akun'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_navigation_matches_the_role(): void
    {
        $this->actingAs(User::factory()->create())->get('/presensi')
            ->assertSee('Riwayat Saya')->assertDontSee('Rekap Bulanan')->assertDontSee('Pengaturan');

        $this->actingAs(User::factory()->pimpinan()->create())->get('/dashboard')
            ->assertSee('Rekap Bulanan')->assertSee('Izin Saya')->assertDontSee('Pengaturan');

        $this->actingAs(User::factory()->admin()->create())->get('/dashboard')
            ->assertSee('Pengaturan')->assertSee('Hari Libur')->assertDontSee('Riwayat Saya');
    }
}
