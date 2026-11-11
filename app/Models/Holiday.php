<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Hari libur: libur nasional & cuti bersama (diambil otomatis), atau libur
 * sekolah yang ditambahkan admin.
 *
 * Kolom `date` disimpan sebagai string "Y-m-d" (lihat catatan di Attendance).
 * Baris dengan `is_school_day = true` tetap tercatat (mis. libur nasional yang
 * diputuskan tetap masuk), tetapi TIDAK dianggap hari libur.
 */
#[Fillable(['date', 'name', 'type', 'is_auto', 'is_tentative', 'is_school_day'])]
class Holiday extends Model
{
    public const TYPE_NATIONAL = 'nasional';

    public const TYPE_JOINT = 'cuti_bersama';

    public const TYPE_SCHOOL = 'sekolah';

    protected $attributes = [
        'type' => self::TYPE_SCHOOL,
        'is_auto' => false,
        'is_tentative' => false,
        'is_school_day' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_auto' => 'boolean',
            'is_tentative' => 'boolean',
            'is_school_day' => 'boolean',
        ];
    }

    public static function isHoliday(string $date): bool
    {
        return static::off()->where('date', $date)->exists();
    }

    /**
     * Hanya hari yang benar-benar libur (bukan yang ditandai tetap masuk).
     *
     * @param  Builder<Holiday>  $query
     */
    public function scopeOff(Builder $query): void
    {
        $query->where('is_school_day', false);
    }

    /** @param  Builder<Holiday>  $query */
    public function scopeInYear(Builder $query, int $year): void
    {
        $query->whereBetween('date', ["{$year}-01-01", "{$year}-12-31"]);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_NATIONAL => 'Libur nasional',
            self::TYPE_JOINT => 'Cuti bersama',
            default => 'Libur sekolah',
        };
    }
}
