<?php

namespace App\Models;

use App\Enums\ScanResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan setiap kali kartu ditempel, termasuk kartu yang belum terdaftar.
 */
#[Fillable(['uid', 'device_id', 'user_id', 'student_id', 'result', 'message', 'scanned_at'])]
class RfidScan extends Model
{
    use Prunable;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'result' => ScanResult::class,
            'scanned_at' => 'datetime',
        ];
    }

    /** @return Builder<RfidScan> */
    public function prunable(): Builder
    {
        return static::where('scanned_at', '<', now()->subDays(config('presensi.scan_retention_days', 180)));
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Guru yang PC-nya menerima tap (pembaca USB).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Nama sumber tap untuk ditampilkan: nama perangkat atau "PC <nama guru>". */
    public function sourceName(): ?string
    {
        if ($this->device) {
            return $this->device->name;
        }

        return $this->user ? 'PC '.$this->user->name : null;
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
