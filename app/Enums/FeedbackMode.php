<?php

namespace App\Enums;

/**
 * Cara aplikasi memberi tahu siswa bahwa presensinya berhasil, saat kartu
 * ditempel di pembaca pada PC guru.
 */
enum FeedbackMode: string
{
    /** Suara + tampilan layar (bawaan). */
    case Both = 'both';

    /** Diutamakan suara: kalimat lebih lengkap dan lebih lambat (mis. siswa tunanetra). */
    case Audio = 'audio';

    /** Tanpa suara ucapan: layar berkedip & tampil lebih lama (mis. siswa tunarungu). */
    case Visual = 'visual';

    public function label(): string
    {
        return match ($this) {
            self::Both => 'Suara dan tampilan layar',
            self::Audio => 'Utamakan suara (lebih jelas & lambat)',
            self::Visual => 'Utamakan tampilan (layar berkedip, tanpa ucapan)',
        };
    }
}
