<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_username_is_case_insensitive(): void
    {
        User::factory()->create(['username' => 'siti.aminah']);

        $this->get('/login')->assertOk()->assertSee('Username');
        $this->post('/login', ['username' => '  Siti.Aminah ', 'password' => 'password'])->assertRedirect('/presensi');
        $this->assertAuthenticated();
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['username' => 'guru']);

        $this->post('/login', ['username' => 'guru', 'password' => 'salah'])
            ->assertSessionHasErrors(['username' => 'Username atau kata sandi salah.']);
        $this->assertGuest();
    }

    public function test_inactive_accounts_cannot_log_in(): void
    {
        User::factory()->inactive()->create(['username' => 'pindah']);

        $this->post('/login', ['username' => 'pindah', 'password' => 'password'])
            ->assertSessionHasErrors(['username' => 'Akun ini sudah dinonaktifkan. Hubungi admin sekolah.']);
        $this->post('/login', ['username' => 'pindah', 'password' => 'salah'])
            ->assertSessionHasErrors(['username' => 'Username atau kata sandi salah.']);
        $this->assertGuest();
    }

    public function test_each_role_lands_on_its_home_page(): void
    {
        User::factory()->create(['username' => 'guru']);
        User::factory()->pimpinan()->create(['username' => 'kepsek']);
        User::factory()->admin()->create(['username' => 'admin']);

        $this->post('/login', ['username' => 'guru', 'password' => 'password'])->assertRedirect('/presensi');
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['username' => 'kepsek', 'password' => 'password'])->assertRedirect('/dashboard');
        $this->get('/')->assertRedirect('/dashboard');
        $this->post('/logout');
        $this->post('/login', ['username' => 'admin', 'password' => 'password'])->assertRedirect('/dashboard');
    }

    public function test_login_attempts_are_limited(): void
    {
        User::factory()->create(['username' => 'guru']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'guru', 'password' => 'salah']);
        }

        $this->post('/login', ['username' => 'guru', 'password' => 'password'])->assertStatus(429);
    }

    public function test_user_can_change_own_password(): void
    {
        $user = User::factory()->create(['username' => 'guru']);

        $this->actingAs($user)->get('/akun')->assertOk()->assertSee('Ganti kata sandi');
        $this->put('/akun/kata-sandi', ['current_password' => 'salah', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])
            ->assertSessionHasErrors(['current_password' => 'Kata sandi saat ini salah.']);
        $this->put('/akun/kata-sandi', ['current_password' => 'password', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123'])
            ->assertRedirect('/akun')->assertSessionHas('success');

        $this->post('/logout');
        $this->post('/login', ['username' => 'guru', 'password' => 'rahasia123'])->assertRedirect('/presensi');
    }
}
