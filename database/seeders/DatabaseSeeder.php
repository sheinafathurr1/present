<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeder utama (`php artisan db:seed`, juga dipanggil `composer run setup`).
 *
 *  - Selalu: membuat satu akun admin bila belum ada.
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

        if ($forTrial) {
            $this->call(DemoSeeder::class);
        }
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
