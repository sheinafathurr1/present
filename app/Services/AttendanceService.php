<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Exceptions\PresenceException;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\Geo;
use App\Support\Location;
use App\Support\SchoolSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Semua aturan presensi guru & staf ada di sini.
 *
 *  1. Presensi masuk dan pulang dari HP masing-masing, hanya di dalam radius
 *     sekolah dan dengan sinyal GPS yang cukup akurat. Jam diambil dari server.
 *  2. Masuk lewat dari jam masuk + toleransi = "Terlambat"; menit terlambat
 *     dihitung sejak jam masuk. Pulang sebelum jam pulang dicatat "pulang awal".
 *  3. Satu kali masuk dan satu kali pulang per hari.
 *  4. Izin/sakit/cuti/dinas luar dari HP langsung tercatat (tanpa persetujuan)
 *     untuk hari sekolah di rentangnya, kecuali hari yang sudah presensi masuk.
 *  5. Yang tidak presensi dan tidak mengajukan apa pun ditandai "Alpa" sore hari.
 */
class AttendanceService
{
    public function __construct(private SchoolSettings $settings) {}

    public function todayFor(User $user, ?CarbonInterface $at = null): ?Attendance
    {
        return Attendance::where('user_id', $user->id)
            ->where('date', ($at ?? now())->toDateString())
            ->first();
    }

    /** Alasan hari ini tidak perlu presensi (bukan hari sekolah / libur), atau null. */
    public function dayOffReason(CarbonInterface $date): ?string
    {
        if (! in_array($date->dayOfWeekIso, $this->settings->schoolDays(), true)) {
            return $date->translatedFormat('l').' bukan hari sekolah.';
        }

        $holiday = Holiday::off()->where('date', $date->toDateString())->first();

        return $holiday ? "Hari ini libur: {$holiday->name}." : null;
    }

    /* ---------- Presensi dari HP ---------- */

    public function checkIn(User $user, Location $location, ?CarbonInterface $at = null): Attendance
    {
        $now = CarbonImmutable::instance($at ?? now())->startOfSecond();

        $this->ensureAttends($user);

        if ($reason = $this->dayOffReason($now)) {
            throw new PresenceException($reason.' Presensi tidak diperlukan.', 'libur');
        }

        $opens = $this->settings->checkInOpens();
        if ($now->format('H:i') < $opens) {
            throw new PresenceException("Presensi masuk baru dibuka pukul {$opens}.", 'belum_dibuka');
        }

        $distance = $this->verifyLocation($location);

        return $this->withDayLocked($user, $now, function (?Attendance $attendance) use ($user, $location, $now, $distance) {
            if ($attendance?->check_in_at) {
                throw new PresenceException('Anda sudah presensi masuk pukul '.$attendance->check_in_at->format('H:i').'.', 'sudah_masuk');
            }

            if ($attendance && $attendance->status->isExcused()) {
                throw new PresenceException(
                    "Hari ini Anda tercatat {$attendance->status->label()}. Batalkan pengajuannya di menu Izin bila ternyata masuk.",
                    'sedang_izin',
                );
            }

            $late = $this->lateMinutes($user, $now);

            $attendance ??= new Attendance(['user_id' => $user->id, 'date' => $now->toDateString()]);
            $attendance->fill([
                'status' => $late > 0 ? AttendanceStatus::Terlambat : AttendanceStatus::Hadir,
                'late_minutes' => $late,
                'check_in_at' => $now,
                'check_in_lat' => $location->lat,
                'check_in_lng' => $location->lng,
                'check_in_accuracy' => (int) round($location->accuracy),
                'check_in_distance' => $distance,
                'leave_request_id' => null,
                'source' => Attendance::SOURCE_GPS,
                'note' => null,
                'recorded_by' => null,
            ])->save();

            return $attendance;
        });
    }

    public function checkOut(User $user, Location $location, ?CarbonInterface $at = null): Attendance
    {
        $now = CarbonImmutable::instance($at ?? now())->startOfSecond();

        $this->ensureAttends($user);
        $distance = $this->verifyLocation($location);

        return $this->withDayLocked($user, $now, function (?Attendance $attendance) use ($user, $location, $now, $distance) {
            if (! $attendance?->check_in_at) {
                throw new PresenceException('Anda belum presensi masuk hari ini.', 'belum_masuk');
            }

            if ($attendance->check_out_at) {
                throw new PresenceException('Anda sudah presensi pulang pukul '.$attendance->check_out_at->format('H:i').'.', 'sudah_pulang');
            }

            $attendance->fill([
                'check_out_at' => $now,
                'check_out_lat' => $location->lat,
                'check_out_lng' => $location->lng,
                'check_out_accuracy' => (int) round($location->accuracy),
                'check_out_distance' => $distance,
                'early_minutes' => $this->earlyMinutes($user, $now),
            ])->save();

            return $attendance;
        });
    }

    /** Menit terlambat (0 bila masih dalam toleransi). */
    public function lateMinutes(User $user, CarbonInterface $at): int
    {
        $minutes = $this->minutesAfterStart($user, $at);

        return $minutes > $this->settings->lateTolerance() ? $minutes : 0;
    }

    /** Menit pulang lebih awal dari jam pulang (0 bila tepat/lebih lambat). */
    public function earlyMinutes(User $user, CarbonInterface $at): int
    {
        $end = CarbonImmutable::instance($at)->setTimeFromTimeString($this->settings->workEndFor($user));

        return $at < $end ? (int) floor($at->diffInSeconds($end, true) / 60) : 0;
    }

    private function minutesAfterStart(User $user, CarbonInterface $at): int
    {
        $start = CarbonImmutable::instance($at)->setTimeFromTimeString($this->settings->workStartFor($user));

        return $at > $start ? (int) floor($start->diffInSeconds($at, true) / 60) : 0;
    }

    private function ensureAttends(User $user): void
    {
        if (! $user->is_active) {
            throw new PresenceException('Akun Anda nonaktif. Hubungi admin sekolah.', 'nonaktif');
        }

        if (! $user->must_attend) {
            throw new PresenceException('Akun Anda tidak diwajibkan presensi.', 'tidak_wajib');
        }
    }

    /** Periksa lokasi HP terhadap titik & radius sekolah. Mengembalikan jarak (meter). */
    private function verifyLocation(Location $location): int
    {
        if (! $this->settings->hasLocation()) {
            throw new PresenceException('Lokasi sekolah belum diatur. Hubungi admin sekolah.', 'lokasi_belum_diatur');
        }

        if ($location->accuracy > $this->settings->maxAccuracy()) {
            throw new PresenceException(sprintf(
                'Sinyal GPS kurang akurat (± %d m, batas %d m). Nyalakan lokasi akurat/GPS, tunggu sebentar di tempat terbuka, lalu coba lagi.',
                round($location->accuracy), $this->settings->maxAccuracy(),
            ), 'akurasi');
        }

        $distance = (int) round(Geo::distance(
            $this->settings->schoolLat(), $this->settings->schoolLng(), $location->lat, $location->lng,
        ));

        if ($distance > $this->settings->radius()) {
            throw new PresenceException(sprintf(
                'Anda berada %s dari sekolah. Presensi hanya bisa dilakukan dalam radius %d m dari sekolah.',
                self::formatDistance($distance), $this->settings->radius(),
            ), 'di_luar_area');
        }

        return $distance;
    }

    public static function formatDistance(int $meters): string
    {
        return $meters < 1000
            ? "{$meters} m"
            : str_replace('.', ',', (string) round($meters / 1000, 1)).' km';
    }

    /**
     * Jalankan perubahan presensi satu orang pada satu tanggal dengan baris terkunci,
     * agar tombol yang tertekan dua kali tidak menghasilkan dua catatan.
     *
     * @param  Closure(?Attendance): Attendance  $callback
     */
    private function withDayLocked(User $user, CarbonInterface $day, Closure $callback): Attendance
    {
        $attempt = fn () => DB::transaction(fn () => $callback(
            Attendance::where('user_id', $user->id)->where('date', $day->toDateString())->lockForUpdate()->first(),
        ));

        try {
            return $attempt();
        } catch (UniqueConstraintViolationException) {
            // Permintaan lain baru saja membuat barisnya: ulangi dengan baris itu.
            return $attempt();
        }
    }

    /* ---------- Izin, sakit, cuti, dinas luar ---------- */

    /**
     * Hari sekolah dalam rentang tanggal.
     *
     * @return list<string> tanggal "Y-m-d"
     */
    public function schoolDaysBetween(CarbonInterface $start, CarbonInterface $end): array
    {
        $days = [];

        for ($date = CarbonImmutable::instance($start)->startOfDay(); $date->lte($end); $date = $date->addDay()) {
            if ($this->settings->isSchoolDay($date)) {
                $days[] = $date->toDateString();
            }
        }

        return $days;
    }

    /**
     * Catat pengajuan ke presensi harian. Hari yang sudah presensi masuk dilewati.
     *
     * @return array{recorded: list<string>, skipped: list<string>}
     */
    public function recordLeave(LeaveRequest $leave): array
    {
        $recorded = [];
        $skipped = [];

        DB::transaction(function () use ($leave, &$recorded, &$skipped) {
            foreach ($this->schoolDaysBetween($leave->start(), $leave->end()) as $date) {
                $attendance = Attendance::firstOrNew(['user_id' => $leave->user_id, 'date' => $date]);

                if ($attendance->check_in_at) {
                    $skipped[] = $date;

                    continue;
                }

                $attendance->fill([
                    'status' => $leave->type->status(),
                    'late_minutes' => 0,
                    'early_minutes' => 0,
                    'leave_request_id' => $leave->id,
                    'source' => Attendance::SOURCE_LEAVE,
                    'note' => null,
                    'recorded_by' => null,
                ])->save();

                $recorded[] = $date;
            }
        });

        return ['recorded' => $recorded, 'skipped' => $skipped];
    }

    /**
     * Batalkan pengajuan oleh pemiliknya.
     *  - Belum dimulai / dimulai hari ini: dihapus seluruhnya.
     *  - Sedang berlangsung: hari yang sudah lewat tetap tercatat, mulai hari ini dihapus.
     *
     * @return 'dihapus'|'dipersingkat'
     */
    public function cancelLeave(LeaveRequest $leave, ?CarbonInterface $today = null): string
    {
        $today = CarbonImmutable::instance($today ?? today())->toDateString();

        return DB::transaction(function () use ($leave, $today) {
            $leave->attendances()->where('date', '>=', $today)->whereNull('check_in_at')->delete();

            if ($leave->start_date >= $today) {
                if ($leave->attachment_path) {
                    Storage::disk('local')->delete($leave->attachment_path);
                }
                $leave->delete();

                return 'dihapus';
            }

            $leave->update(['end_date' => CarbonImmutable::parse($today)->subDay()->toDateString()]);

            return 'dipersingkat';
        });
    }

    /* ---------- Koreksi admin & penandaan alpa ---------- */

    /**
     * Admin mengubah presensi satu orang pada satu tanggal.
     * Menit terlambat/pulang awal dihitung ulang dari jam yang diisi.
     */
    public function correct(
        User $employee,
        string $date,
        AttendanceStatus $status,
        ?string $checkIn,
        ?string $checkOut,
        string $note,
        User $admin,
    ): Attendance {
        $day = CarbonImmutable::parse($date)->startOfDay();
        $attendance = Attendance::firstOrNew(['user_id' => $employee->id, 'date' => $day->toDateString()]);

        $in = $status->isPresent() && $checkIn ? $day->setTimeFromTimeString($checkIn) : null;
        $out = $status->isPresent() && $checkOut ? $day->setTimeFromTimeString($checkOut) : null;

        // Koordinat hanya dipertahankan bila jamnya tidak diubah.
        if ($attendance->check_in_at?->format('H:i') !== $in?->format('H:i')) {
            $attendance->fill(['check_in_lat' => null, 'check_in_lng' => null, 'check_in_accuracy' => null, 'check_in_distance' => null]);
        }
        if ($attendance->check_out_at?->format('H:i') !== $out?->format('H:i')) {
            $attendance->fill(['check_out_lat' => null, 'check_out_lng' => null, 'check_out_accuracy' => null, 'check_out_distance' => null]);
        }

        $attendance->fill([
            'status' => $status,
            'check_in_at' => $in,
            'check_out_at' => $out,
            'late_minutes' => $status === AttendanceStatus::Terlambat && $in ? $this->minutesAfterStart($employee, $in) : 0,
            'early_minutes' => $out ? $this->earlyMinutes($employee, $out) : 0,
            'leave_request_id' => null,
            'source' => Attendance::SOURCE_ADMIN,
            'note' => $note,
            'recorded_by' => $admin->id,
        ])->save();

        return $attendance;
    }

    /**
     * Tandai Alpa semua yang wajib presensi tetapi belum punya catatan.
     *
     * @return int|null jumlah yang ditandai, atau null bila bukan hari sekolah
     */
    public function markAbsent(CarbonInterface $date): ?int
    {
        $date = CarbonImmutable::instance($date)->startOfDay();

        if (! $this->settings->isSchoolDay($date)) {
            return null;
        }

        $key = $date->toDateString();
        $ids = User::attending()
            ->where('created_at', '<=', $date->endOfDay())
            ->whereDoesntHave('attendances', fn ($query) => $query->where('date', $key))
            ->pluck('id');

        foreach ($ids as $id) {
            Attendance::firstOrCreate(['user_id' => $id, 'date' => $key], [
                'status' => AttendanceStatus::Alpa,
                'source' => Attendance::SOURCE_SYSTEM,
            ]);
        }

        return $ids->count();
    }
}
