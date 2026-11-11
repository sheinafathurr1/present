<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Hadir = 'hadir';
    case Terlambat = 'terlambat';
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Alpa = 'alpa';

    public function label(): string
    {
        return match ($this) {
            self::Hadir => 'Hadir',
            self::Terlambat => 'Terlambat',
            self::Izin => 'Izin',
            self::Sakit => 'Sakit',
            self::Alpa => 'Alpa',
        };
    }

    /** Kode singkat untuk rekap bulanan (H/T/I/S/A). */
    public function code(): string
    {
        return match ($this) {
            self::Hadir => 'H',
            self::Terlambat => 'T',
            self::Izin => 'I',
            self::Sakit => 'S',
            self::Alpa => 'A',
        };
    }

    /** Siswa dianggap hadir di sekolah (tepat waktu atau terlambat). */
    public function isPresent(): bool
    {
        return in_array($this, [self::Hadir, self::Terlambat], true);
    }

    /**
     * Nama warna status di CSS (--hadir, --terlambat, --izin, --sakit, --alpa).
     * Setiap status punya warna sendiri, dan selalu disertai huruf/kata.
     */
    public function tone(): string
    {
        return $this->value;
    }
}
