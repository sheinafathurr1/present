<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Hadir = 'hadir';
    case Terlambat = 'terlambat';
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Cuti = 'cuti';
    case DinasLuar = 'dinas_luar';
    case Alpa = 'alpa';

    public function label(): string
    {
        return match ($this) {
            self::Hadir => 'Hadir',
            self::Terlambat => 'Terlambat',
            self::Izin => 'Izin',
            self::Sakit => 'Sakit',
            self::Cuti => 'Cuti',
            self::DinasLuar => 'Dinas luar',
            self::Alpa => 'Alpa',
        };
    }

    /** Kode singkat untuk rekap bulanan. */
    public function code(): string
    {
        return match ($this) {
            self::Hadir => 'H',
            self::Terlambat => 'T',
            self::Izin => 'I',
            self::Sakit => 'S',
            self::Cuti => 'C',
            self::DinasLuar => 'D',
            self::Alpa => 'A',
        };
    }

    /** Datang ke sekolah (tepat waktu atau terlambat). */
    public function isPresent(): bool
    {
        return in_array($this, [self::Hadir, self::Terlambat], true);
    }

    /** Tidak hadir dengan keterangan (izin, sakit, cuti, dinas luar). */
    public function isExcused(): bool
    {
        return in_array($this, [self::Izin, self::Sakit, self::Cuti, self::DinasLuar], true);
    }

    /** Nama warna status di CSS. */
    public function tone(): string
    {
        return $this->value;
    }
}
