<?php

namespace App\Enums;

/**
 * Jenis pengajuan tidak hadir dari HP guru/staf.
 */
enum LeaveType: string
{
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Cuti = 'cuti';
    case DinasLuar = 'dinas_luar';

    public function label(): string
    {
        return $this->status()->label();
    }

    public function hint(): string
    {
        return match ($this) {
            self::Izin => 'Keperluan pribadi/keluarga.',
            self::Sakit => 'Lampirkan foto surat dokter bila ada.',
            self::Cuti => 'Cuti tahunan, melahirkan, dan sebagainya.',
            self::DinasLuar => 'Tugas di luar sekolah, mis. pelatihan atau rapat dinas. Lampirkan surat tugas bila ada.',
        };
    }

    public function status(): AttendanceStatus
    {
        return AttendanceStatus::from($this->value);
    }
}
