<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Guru = 'guru';
    case Kiosk = 'kiosk';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Guru => 'Guru',
            self::Kiosk => 'Akun Kiosk',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Akses penuh: data siswa, kelas, perangkat, pengguna, dan pengaturan.',
            self::Guru => 'Melihat dashboard, mencatat izin/sakit, dan melihat rekap.',
            self::Kiosk => 'Hanya bisa membuka layar presensi (untuk komputer di gerbang).',
        };
    }
}
