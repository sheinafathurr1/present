<?php

namespace App\Enums;

enum UserRole: string
{
    /** TU/operator: mengelola data guru & staf, pengaturan, hari libur, koreksi presensi. */
    case Admin = 'admin';

    /** Kepala sekolah, wakil kepala sekolah, bendahara: memantau kehadiran semua (hanya melihat). */
    case Pimpinan = 'pimpinan';

    /** Guru & staf tendik: presensi, riwayat, dan pengajuan izin miliknya sendiri. */
    case Pegawai = 'pegawai';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin (TU/Operator)',
            self::Pimpinan => 'Pimpinan',
            self::Pegawai => 'Guru/Staf',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Mengelola data guru & staf, pengaturan sekolah, hari libur, dan mengoreksi presensi.',
            self::Pimpinan => 'Kepala sekolah, wakil, atau bendahara: melihat kehadiran semua guru & staf dan mengunduh rekap.',
            self::Pegawai => 'Presensi dari HP, melihat riwayat sendiri, dan mengajukan izin/sakit/cuti/dinas luar.',
        };
    }

    /** Boleh melihat kehadiran semua guru & staf. */
    public function canMonitor(): bool
    {
        return $this !== self::Pegawai;
    }
}
