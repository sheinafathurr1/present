<?php

namespace App\Enums;

/**
 * Hasil dari satu kali kartu ditempel di pembaca.
 */
enum ScanResult: string
{
    case CheckIn = 'check_in';
    case Late = 'late';
    case CheckOut = 'check_out';
    case Duplicate = 'duplicate';
    case AlreadyIn = 'already_in';
    case AlreadyOut = 'already_out';
    case UnknownCard = 'unknown_card';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::CheckIn => 'Masuk',
            self::Late => 'Masuk (terlambat)',
            self::CheckOut => 'Pulang',
            self::Duplicate => 'Tempel ulang',
            self::AlreadyIn => 'Sudah masuk',
            self::AlreadyOut => 'Sudah pulang',
            self::UnknownCard => 'Kartu tidak dikenal',
            self::Inactive => 'Siswa nonaktif',
        };
    }

    /** Apakah tap ini mengubah data presensi. */
    public function isRecorded(): bool
    {
        return in_array($this, [self::CheckIn, self::Late, self::CheckOut], true);
    }

    /** Warna/nada umpan balik: success, warning, checkout, info, error. */
    public function tone(): string
    {
        return match ($this) {
            self::CheckIn => 'success',
            self::Late => 'warning',
            self::CheckOut => 'checkout',
            self::Duplicate, self::AlreadyIn, self::AlreadyOut => 'info',
            self::UnknownCard, self::Inactive => 'error',
        };
    }
}
