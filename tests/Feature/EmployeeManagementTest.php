<?php

namespace Tests\Feature;

use App\Enums\EmployeeType;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['username' => 'admin']);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Siti Aminah, S.Pd.',
            'username' => 'siti.aminah',
            'password' => 'rahasia123',
            'role' => 'pegawai',
            'employee_type' => 'guru',
            'position' => 'Guru Kelas 1',
            'nip' => '19800101 200501 2 001',
            'phone' => '0812-3456-7890',
            'must_attend' => '1',
            'work_start' => '',
            'work_end' => '',
            ...$overrides,
        ];
    }

    public function test_admin_creates_an_account_that_can_log_in(): void
    {
        $this->actingAs($this->admin)->get('/pegawai/tambah')->assertOk()->assertSee('Kepala sekolah, wakil, atau bendahara');

        $this->post('/pegawai', $this->payload(['username' => ' Siti.Aminah ']))
            ->assertRedirect()
            ->assertSessionHas('success', 'Akun Siti Aminah, S.Pd. dibuat. Username: siti.aminah. Berikan kata sandinya secara pribadi.');

        $user = User::where('username', 'siti.aminah')->sole();
        $this->assertSame(UserRole::Pegawai, $user->role);
        $this->assertSame(EmployeeType::Guru, $user->employee_type);
        $this->assertSame('198001012005012001', $user->nip);
        $this->assertTrue($user->must_attend);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->work_start);
        $this->assertTrue(Hash::check('rahasia123', $user->password));

        $this->post('/logout');
        $this->post('/login', ['username' => 'siti.aminah', 'password' => 'rahasia123'])->assertRedirect('/presensi');
    }

    public function test_validation(): void
    {
        $this->actingAs($this->admin);

        $this->post('/pegawai', $this->payload(['username' => 'admin']))->assertSessionHasErrors(['username' => 'Username ini sudah dipakai.']);
        $this->post('/pegawai', $this->payload(['username' => 'siti aminah']))->assertSessionHasErrors('username');
        $this->post('/pegawai', $this->payload(['password' => '']))->assertSessionHasErrors('password');
        $this->post('/pegawai', $this->payload(['password' => 'pendek']))->assertSessionHasErrors('password');
        $this->post('/pegawai', $this->payload(['role' => 'guru']))->assertSessionHasErrors('role');
        $this->post('/pegawai', $this->payload(['work_start' => '13:00', 'work_end' => '09:00']))->assertSessionHasErrors('work_end');
        $this->post('/pegawai', $this->payload(['work_start' => '13:00']))->assertSessionHasErrors('work_end');

        $this->assertSame(1, User::count());
    }

    public function test_update_keeps_the_password_unless_a_new_one_is_given(): void
    {
        $guru = User::factory()->create(['username' => 'guru']);
        $this->actingAs($this->admin)->get("/pegawai/{$guru->id}/ubah")->assertOk()->assertSee('Kosongkan bila tidak diganti');

        $this->put("/pegawai/{$guru->id}", $this->payload(['username' => 'guru', 'password' => '', 'role' => 'pimpinan', 'position' => 'Wakil Kepala Sekolah', 'is_active' => '1', 'work_start' => '07:30', 'work_end' => '14:00']))
            ->assertRedirect("/pegawai/{$guru->id}");

        $guru->refresh();
        $this->assertSame(UserRole::Pimpinan, $guru->role);
        $this->assertSame('07:30', $guru->work_start);
        $this->assertTrue(Hash::check('password', $guru->password));

        $this->put("/pegawai/{$guru->id}", $this->payload(['username' => 'guru', 'password' => 'barubaru1', 'is_active' => '1']));
        $this->assertTrue(Hash::check('barubaru1', $guru->fresh()->password));
    }

    public function test_deactivated_accounts_keep_history_but_cannot_log_in(): void
    {
        $guru = User::factory()->create(['username' => 'guru']);
        Attendance::factory()->for($guru)->create();

        $this->actingAs($this->admin)->put("/pegawai/{$guru->id}", $this->payload(['username' => 'guru', 'password' => '', 'is_active' => '0']));
        $this->assertFalse($guru->fresh()->is_active);

        $this->delete("/pegawai/{$guru->id}")->assertSessionHas('error');
        $this->assertSame(1, $guru->attendances()->count());

        $this->post('/logout');
        $this->post('/login', ['username' => 'guru', 'password' => 'password'])->assertSessionHasErrors('username');
    }

    public function test_accounts_without_history_can_be_deleted(): void
    {
        $salah = User::factory()->create();

        $this->actingAs($this->admin)->delete("/pegawai/{$salah->id}")->assertRedirect('/pegawai');
        $this->assertModelMissing($salah);
    }

    public function test_admin_cannot_lock_themselves_out(): void
    {
        $this->actingAs($this->admin);

        $this->put("/pegawai/{$this->admin->id}", $this->payload(['username' => 'admin', 'password' => '', 'role' => 'pegawai', 'is_active' => '1']))
            ->assertSessionHas('error');
        $this->put("/pegawai/{$this->admin->id}", $this->payload(['username' => 'admin', 'password' => '', 'role' => 'admin', 'is_active' => '0']))
            ->assertSessionHas('error');
        $this->delete("/pegawai/{$this->admin->id}")->assertSessionHas('error');

        $this->assertTrue($this->admin->fresh()->isAdmin());
        $this->assertTrue($this->admin->fresh()->is_active);
    }
}
