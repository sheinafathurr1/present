<?php

namespace App\Models;

use App\Enums\LeaveType;
use Carbon\CarbonImmutable;
use Database\Factories\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pengajuan izin, sakit, cuti, atau dinas luar dari HP.
 * Langsung tercatat tanpa persetujuan; pimpinan dapat melihatnya.
 *
 * `start_date` & `end_date` dibaca sebagai string "Y-m-d".
 */
#[Fillable(['user_id', 'type', 'start_date', 'end_date', 'reason', 'attachment_path'])]
class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => LeaveType::class,
        ];
    }

    public function start(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->start_date);
    }

    public function end(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->end_date);
    }

    /** Jumlah hari kalender. */
    public function calendarDays(): int
    {
        return (int) $this->start()->diffInDays($this->end()) + 1;
    }

    /** "12 Nov 2026" atau "12–14 Nov 2026" atau "28 Nov – 2 Des 2026". */
    public function periodLabel(): string
    {
        $start = $this->start();
        $end = $this->end();

        if ($start->equalTo($end)) {
            return $start->translatedFormat('D, d M Y');
        }
        if ($start->format('Y-m') === $end->format('Y-m')) {
            return $start->translatedFormat('d').'–'.$end->translatedFormat('d M Y');
        }
        if ($start->year === $end->year) {
            return $start->translatedFormat('d M').' – '.$end->translatedFormat('d M Y');
        }

        return $start->translatedFormat('d M Y').' – '.$end->translatedFormat('d M Y');
    }

    /** Sudah lewat seluruhnya. */
    public function isPast(?CarbonImmutable $today = null): bool
    {
        return $this->end_date < ($today ?? CarbonImmutable::today())->toDateString();
    }

    /** Sedang berlangsung (hari ini termasuk). */
    public function isOngoing(?CarbonImmutable $today = null): bool
    {
        $today = ($today ?? CarbonImmutable::today())->toDateString();

        return $this->start_date <= $today && $this->end_date >= $today;
    }

    /** Pemilik bisa membatalkan selama belum lewat seluruhnya. */
    public function canBeCancelledBy(User $user): bool
    {
        return $user->id === $this->user_id && ! $this->isPast();
    }

    /** Pemilik, admin, dan pimpinan boleh melihat lampiran. */
    public function canBeViewedBy(User $user): bool
    {
        return $user->id === $this->user_id || $user->canMonitor();
    }

    /**
     * Pengajuan yang bersinggungan dengan rentang tanggal.
     *
     * @param  Builder<LeaveRequest>  $query
     */
    public function scopeOverlapping(Builder $query, string $start, string $end): void
    {
        $query->where('start_date', '<=', $end)->where('end_date', '>=', $start);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Attendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
