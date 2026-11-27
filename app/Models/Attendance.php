<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris presensi = satu guru/staf pada satu tanggal.
 *
 * Kolom `date` sengaja dibaca sebagai string "Y-m-d" agar perbandingan
 * tanggal bekerja sama persis di MySQL maupun SQLite.
 */
#[Fillable([
    'user_id', 'date', 'status',
    'check_in_at', 'check_in_lat', 'check_in_lng', 'check_in_accuracy', 'check_in_distance',
    'check_out_at', 'check_out_lat', 'check_out_lng', 'check_out_accuracy', 'check_out_distance',
    'late_minutes', 'early_minutes', 'leave_request_id', 'source', 'note', 'recorded_by',
])]
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    /** Presensi dari HP dengan lokasi GPS. */
    public const SOURCE_GPS = 'gps';

    /** Dari pengajuan izin/sakit/cuti/dinas luar. */
    public const SOURCE_LEAVE = 'pengajuan';

    /** Dikoreksi admin. */
    public const SOURCE_ADMIN = 'admin';

    /** Ditandai Alpa oleh sistem. */
    public const SOURCE_SYSTEM = 'sistem';

    protected $attributes = [
        'late_minutes' => 0,
        'early_minutes' => 0,
        'source' => self::SOURCE_GPS,
    ];

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'check_in_lat' => 'float',
            'check_in_lng' => 'float',
            'check_out_lat' => 'float',
            'check_out_lng' => 'float',
            'check_in_accuracy' => 'integer',
            'check_in_distance' => 'integer',
            'check_out_accuracy' => 'integer',
            'check_out_distance' => 'integer',
            'late_minutes' => 'integer',
            'early_minutes' => 'integer',
        ];
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_GPS => 'HP (GPS)',
            self::SOURCE_LEAVE => 'Pengajuan',
            self::SOURCE_ADMIN => 'Koreksi admin',
            self::SOURCE_SYSTEM => 'Otomatis',
            default => (string) $this->source,
        };
    }

    /** Keterangan singkat: terlambat / pulang awal / belum presensi pulang. */
    public function remarks(bool $isToday = false): array
    {
        $notes = [];

        if ($this->late_minutes > 0) {
            $notes[] = 'Terlambat '.self::minutes($this->late_minutes);
        }
        if ($this->early_minutes > 0) {
            $notes[] = 'Pulang awal '.self::minutes($this->early_minutes);
        }
        if ($this->check_in_at && ! $this->check_out_at && ! $isToday) {
            $notes[] = 'Tidak presensi pulang';
        }

        return $notes;
    }

    /** "1 jam 5 menit", "45 menit". */
    public static function minutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return trim(($hours ? "{$hours} jam " : '').($rest || ! $hours ? "{$rest} menit" : ''));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<LeaveRequest, $this> */
    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
