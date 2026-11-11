<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris presensi = satu siswa pada satu tanggal.
 *
 * Kolom `date` sengaja disimpan sebagai string "Y-m-d" agar perbandingan
 * tanggal bekerja sama persis di MySQL maupun SQLite.
 */
#[Fillable([
    'student_id', 'date', 'status', 'check_in_at', 'check_out_at',
    'check_in_device_id', 'check_out_device_id', 'source', 'note', 'recorded_by',
])]
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    public const SOURCE_RFID = 'rfid';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_SYSTEM = 'system';

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
        ];
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_RFID => 'Kartu RFID',
            self::SOURCE_MANUAL => 'Dicatat guru',
            self::SOURCE_SYSTEM => 'Otomatis',
            default => (string) $this->source,
        };
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Device, $this> */
    public function checkInDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'check_in_device_id');
    }

    /** @return BelongsTo<Device, $this> */
    public function checkOutDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'check_out_device_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
