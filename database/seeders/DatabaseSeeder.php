<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Data awal minimal: satu akun admin dan satu perangkat pembaca USB.
 *
 * Data contoh (kelas, siswa, riwayat presensi) tersedia terpisah:
 *   php artisan db:seed --class=DemoSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! User::where('role', UserRole::Admin)->exists()) {
            $email = env('ADMIN_EMAIL', 'admin@sekolah.test');
            $password = env('ADMIN_PASSWORD') ?: Str::password(12, symbols: false);

            User::create([
                'name' => 'Administrator',
                'email' => $email,
                'password' => $password,
                'role' => UserRole::Admin,
            ]);

            $this->command?->getOutput()->writeln('');
            $this->command?->info('Akun admin dibuat — catat dan segera ganti kata sandinya:');
            $this->command?->line("  Email      : {$email}");
            $this->command?->line("  Kata sandi : {$password}");
            $this->command?->getOutput()->writeln('');
        }

        Device::firstOrCreate(
            ['name' => 'Gerbang Utama'],
            ['location' => 'Pintu masuk sekolah', 'type' => Device::TYPE_USB, 'is_active' => true],
        );
    }
}
