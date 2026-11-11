<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Guru = 'guru';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Guru => 'Guru',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Akses penuh: data siswa, kelas, perangkat, pengguna, dan pengaturan.',
            self::Guru => 'Menerima tap kartu siswa di PC-nya, mencatat izin/sakit, dan melihat rekap.',
        };
    }
}
