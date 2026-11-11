<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\NationalHolidaySync;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeder utama (`php artisan db:seed`, juga dipanggil `composer run setup`).
 *
 *  - Selalu: membuat satu akun admin bila belum ada, dan mengisi libur
 *    nasional & cuti bersama tahun ini dan tahun depan.
 *  - Bila APP_ENV=local: juga mengisi data uji coba lengkap (DemoSeeder) dan
 *    memakai kata sandi "password" agar mudah dicoba.
 *  - Bila APP_ENV=production: hanya admin, dengan kata sandi acak yang
 *    ditampilkan sekali (atau ADMIN_PASSWORD dari .env).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $forTrial = app()->environment('local');

        self::ensureAdmin($forTrial ? DemoSeeder::PASSWORD : null, $this->command);
        $this->seedNationalHolidays();

        if ($forTrial) {
            $this->call(DemoSeeder::class);
        }
    }

    private function seedNationalHolidays(): void
    {
        $result = app(NationalHolidaySync::class)->sync([now()->year, now()->year + 1]);

        $this->command?->info($result['source'] === NationalHolidaySync::SOURCE_INTERNET
            ? "Libur nasional & cuti bersama diambil dari internet ({$result['added']} hari)."
            : 'Tanpa internet: baru libur bertanggal tetap yang diisi. Jalankan `php artisan presensi:sinkron-libur` saat online.');
    }

    /**
     * Buat akun admin bila belum ada satu pun.
     */
    public static function ensureAdmin(?string $defaultPassword, $command = null): void
    {
        if (User::where('role', UserRole::Admin)->exists()) {
            return;
        }

        $email = env('ADMIN_EMAIL', 'admin@sekolah.test');
        $password = env('ADMIN_PASSWORD') ?: ($defaultPassword ?? Str::password(12, symbols: false));

        User::create([
            'name' => 'Administrator',
            'email' => $email,
            'password' => $password,
            'role' => UserRole::Admin,
        ]);

        $command?->getOutput()->writeln('');
        $command?->info('Akun admin dibuat'.($defaultPassword ? ':' : ' — catat dan segera ganti kata sandinya:'));
        $command?->line("  Email      : {$email}");
        $command?->line("  Kata sandi : {$password}");
        $command?->getOutput()->writeln('');
    }
}
