<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\ScanResult;
use App\Events\AttendanceRecorded;
use App\Models\Attendance;
use App\Models\Device;
use App\Models\RfidScan;
use App\Models\Student;
use App\Models\User;
use App\Support\SchoolSettings;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;

/**
 * Semua aturan presensi ada di sini, supaya kiosk (pembaca USB) dan API
 * (pembaca jaringan) selalu berperilaku sama.
 *
 * Aturan tap kartu:
 *  1. Tap pertama hari itu  = presensi MASUK. Lewat batas terlambat → "Terlambat".
 *  2. Tap berikutnya sebelum jam pulang → hanya diberi tahu "sudah presensi".
 *  3. Tap berikutnya mulai jam pulang   = presensi PULANG.
 *  4. Tap berulang dalam jeda singkat (cooldown) diabaikan agar tidak ganda.
 */
class AttendanceService
{
    public function __construct(private SchoolSettings $settings) {}

    public function handleTap(string $rawUid, ?Device $device = null, ?CarbonInterface $at = null): TapOutcome
    {
        $uid = Student::normalizeUid($rawUid);

        if ($uid === null) {
            throw new InvalidArgumentException('UID kartu kosong.');
        }

        $now = ($at ?? now())->copy()->startOfSecond();
        $student = Student::with('schoolClass')->where('rfid_uid', $uid)->first();

        if ($device !== null) {
            $device->forceFill(['last_seen_at' => $now])->saveQuietly();
        }

        if ($student === null) {
            return $this->log(new TapOutcome(ScanResult::UnknownCard, $uid, $now), $device);
        }

        if (! $student->is_active) {
            return $this->log(new TapOutcome(ScanResult::Inactive, $uid, $now, $student), $device);
        }

        try {
            $outcome = $this->applyTap($student, $uid, $now, $device);
        } catch (UniqueConstraintViolationException) {
            // Dua tap hampir bersamaan dari pembaca berbeda: yang pertama sudah tercatat.
            $outcome = new TapOutcome(ScanResult::Duplicate, $uid, $now, $student);
        }

        if ($outcome->result->isRecorded() && $outcome->attendance !== null) {
            AttendanceRecorded::dispatch($outcome->attendance, $outcome->result);
        }

        return $this->log($outcome, $device);
    }

    private function applyTap(Student $student, string $uid, CarbonInterface $now, ?Device $device): TapOutcome
    {
        $class = $student->schoolClass;
        $lateAfter = $this->settings->lateAfter($class);
        $checkOutStart = $this->settings->checkOutStart($class);
        $make = fn (ScanResult $result, ?Attendance $attendance) => new TapOutcome(
            $result, $uid, $now, $student, $attendance, $lateAfter, $checkOutStart,
        );

        $attendance = Attendance::firstOrNew([
            'student_id' => $student->id,
            'date' => $now->toDateString(),
        ]);

        $lastTap = $attendance->check_out_at ?? $attendance->check_in_at;

        if ($lastTap !== null && abs($lastTap->diffInSeconds($now)) < $this->settings->cooldownSeconds()) {
            return $make(ScanResult::Duplicate, $attendance);
        }

        if ($attendance->check_in_at === null) {
            $isLate = $this->settings->isSchoolDay($now) && $now->format('H:i') > $lateAfter;

            $attendance->fill([
                'status' => $isLate ? AttendanceStatus::Terlambat : AttendanceStatus::Hadir,
                'check_in_at' => $now,
                'check_in_device_id' => $device?->id,
                'source' => Attendance::SOURCE_RFID,
            ])->save();

            return $make($isLate ? ScanResult::Late : ScanResult::CheckIn, $attendance);
        }

        if ($attendance->check_out_at !== null) {
            return $make(ScanResult::AlreadyOut, $attendance);
        }

        if ($now->format('H:i') < $checkOutStart) {
            return $make(ScanResult::AlreadyIn, $attendance);
        }

        $attendance->fill([
            'check_out_at' => $now,
            'check_out_device_id' => $device?->id,
        ])->save();

        return $make(ScanResult::CheckOut, $attendance);
    }

    private function log(TapOutcome $outcome, ?Device $device): TapOutcome
    {
        RfidScan::create([
            'uid' => $outcome->uid,
            'device_id' => $device?->id,
            'student_id' => $outcome->student?->id,
            'result' => $outcome->result,
            'message' => $outcome->message(),
            'scanned_at' => $outcome->at,
        ]);

        return $outcome;
    }

    /**
     * Guru mencatat/mengubah status presensi secara manual (izin, sakit, dll).
     */
    public function recordManual(Student $student, string $date, AttendanceStatus $status, ?string $note, User $by): Attendance
    {
        $attendance = Attendance::firstOrNew(['student_id' => $student->id, 'date' => $date]);

        $attendance->fill([
            'status' => $status,
            'note' => $note,
            'recorded_by' => $by->id,
        ]);

        if (! $attendance->exists) {
            $attendance->source = Attendance::SOURCE_MANUAL;
        }

        $attendance->save();

        return $attendance;
    }

    /**
     * Tandai siswa aktif yang belum punya catatan presensi pada tanggal ini
     * sebagai "Alpa". Tidak melakukan apa pun di hari libur / bukan hari sekolah.
     *
     * @return int|null Jumlah siswa yang ditandai, atau null bila bukan hari sekolah.
     */
    public function markAbsent(CarbonInterface $date, ?User $by = null): ?int
    {
        if (! $this->settings->isSchoolDay($date)) {
            return null;
        }

        $dateString = $date->toDateString();
        $count = 0;

        Student::active()
            ->whereDoesntHave('attendances', fn ($query) => $query->where('date', $dateString))
            ->select('id')
            ->chunkById(200, function ($students) use ($dateString, $by, &$count) {
                foreach ($students as $student) {
                    Attendance::create([
                        'student_id' => $student->id,
                        'date' => $dateString,
                        'status' => AttendanceStatus::Alpa,
                        'source' => Attendance::SOURCE_SYSTEM,
                        'recorded_by' => $by?->id,
                    ]);
                    $count++;
                }
            });

        return $count;
    }
}
