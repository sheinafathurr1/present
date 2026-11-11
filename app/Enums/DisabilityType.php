<?php

namespace App\Enums;

/**
 * Kategori kekhususan siswa mengikuti penggolongan yang umum dipakai SLB.
 */
enum DisabilityType: string
{
    case Tunanetra = 'A';
    case Tunarungu = 'B';
    case TunagrahitaRingan = 'C';
    case TunagrahitaSedang = 'C1';
    case Tunadaksa = 'D';
    case Tunalaras = 'E';
    case Ganda = 'G';
    case Autis = 'Q';
    case Lainnya = 'L';

    public function label(): string
    {
        return match ($this) {
            self::Tunanetra => 'Tunanetra (A)',
            self::Tunarungu => 'Tunarungu / Tunawicara (B)',
            self::TunagrahitaRingan => 'Tunagrahita Ringan (C)',
            self::TunagrahitaSedang => 'Tunagrahita Sedang (C1)',
            self::Tunadaksa => 'Tunadaksa (D)',
            self::Tunalaras => 'Tunalaras (E)',
            self::Ganda => 'Tunaganda (G)',
            self::Autis => 'Autis (Q)',
            self::Lainnya => 'Lainnya',
        };
    }

    /** Mode umpan balik saat tap kartu yang disarankan untuk kategori ini. */
    public function suggestedFeedback(): FeedbackMode
    {
        return match ($this) {
            self::Tunanetra => FeedbackMode::Audio,
            self::Tunarungu => FeedbackMode::Visual,
            default => FeedbackMode::Both,
        };
    }

    /**
     * Cari kategori dari kode ("A") atau sebagian label ("tunanetra").
     */
    public static function fromInput(?string $value): ?self
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if ($type = self::tryFrom(strtoupper($value))) {
            return $type;
        }

        foreach (self::cases() as $case) {
            if (str_contains(strtolower($case->label()), strtolower($value))) {
                return $case;
            }
        }

        return null;
    }
}
