<?php

namespace App\Services;

use App\Enums\ScanResult;
use App\Models\Attendance;
use App\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Hasil pemrosesan satu kali tap kartu, lengkap dengan teks yang ditampilkan
 * dan diucapkan di PC guru maupun dikirim ke pembaca jaringan.
 */
final readonly class TapOutcome
{
    public function __construct(
        public ScanResult $result,
        public string $uid,
        public CarbonInterface $at,
        public ?Student $student = null,
        public ?Attendance $attendance = null,
        public ?string $lateAfter = null,
        public ?string $checkOutStart = null,
    ) {}

    public function headline(): string
    {
        return match ($this->result) {
            ScanResult::CheckIn => 'Masuk',
            ScanResult::Late => 'Terlambat',
            ScanResult::CheckOut => 'Pulang',
            ScanResult::Duplicate, ScanResult::AlreadyIn, ScanResult::AlreadyOut => 'Sudah presensi',
            ScanResult::UnknownCard => 'Kartu belum terdaftar',
            ScanResult::Inactive => 'Kartu tidak aktif',
        };
    }

    /** Kalimat yang ditampilkan di layar. */
    public function message(): string
    {
        $name = $this->student?->callName();
        $time = $this->at->format('H.i');

        return match ($this->result) {
            ScanResult::CheckIn => "{$this->greeting()}, {$name}! Presensi masuk pukul {$time}.",
            ScanResult::Late => "{$this->greeting()}, {$name}! Kamu masuk pukul {$time}. Besok datang lebih pagi, ya.",
            ScanResult::CheckOut => "Sampai jumpa, {$name}! Hati-hati di jalan.",
            ScanResult::Duplicate => "{$name}, kartumu sudah tercatat. Tidak perlu ditempel lagi.",
            ScanResult::AlreadyIn => "{$name}, kamu sudah presensi masuk pukul {$this->checkInTime('H.i')}. Presensi pulang mulai pukul ".str_replace(':', '.', (string) $this->checkOutStart).'.',
            ScanResult::AlreadyOut => "{$name}, kamu sudah presensi pulang pukul {$this->checkOutTime('H.i')}.",
            ScanResult::UnknownCard => 'Kartu ini belum terdaftar. Silakan minta bantuan guru.',
            ScanResult::Inactive => 'Kartu ini tidak aktif. Silakan minta bantuan guru.',
        };
    }

    /** Kalimat singkat untuk diucapkan (text-to-speech). */
    public function speech(): string
    {
        $name = $this->student?->callName();

        return match ($this->result) {
            ScanResult::CheckIn, ScanResult::Late => "{$this->greeting()}, {$name}. Presensi masuk berhasil.",
            ScanResult::CheckOut => "Sampai jumpa, {$name}. Hati-hati di jalan.",
            ScanResult::Duplicate, ScanResult::AlreadyIn => "{$name}, kamu sudah presensi.",
            ScanResult::AlreadyOut => "{$name}, kamu sudah presensi pulang.",
            ScanResult::UnknownCard => 'Kartu belum terdaftar. Silakan minta bantuan guru.',
            ScanResult::Inactive => 'Kartu tidak aktif. Silakan minta bantuan guru.',
        };
    }

    /**
     * Kalimat lebih lengkap untuk siswa yang mengandalkan suara (mis. tunanetra):
     * menyebut jam dan status secara jelas.
     */
    public function detailedSpeech(): string
    {
        $name = $this->student?->callName();
        $spokenTime = self::spokenTime($this->at);

        return match ($this->result) {
            ScanResult::CheckIn => "{$this->greeting()}, {$name}. Presensi masuk berhasil, {$spokenTime}. Selamat belajar.",
            ScanResult::Late => "{$this->greeting()}, {$name}. Presensi masuk berhasil, {$spokenTime}. Hari ini kamu terlambat.",
            ScanResult::CheckOut => "Sampai jumpa, {$name}. Presensi pulang berhasil, {$spokenTime}. Hati-hati di jalan.",
            ScanResult::Duplicate => "{$name}, kartumu sudah tercatat. Tidak perlu ditempel lagi.",
            ScanResult::AlreadyIn => "{$name}, kamu sudah presensi masuk. Presensi pulang mulai ".self::spokenTime($this->checkOutStart).'.',
            ScanResult::AlreadyOut => "{$name}, kamu sudah presensi pulang hari ini.",
            ScanResult::UnknownCard, ScanResult::Inactive => $this->speech(),
        };
    }

    /**
     * Dua baris pendek (maks. 16 karakter, ASCII) untuk layar LCD 16x2 di
     * pembaca jaringan.
     *
     * @return array{0: string, 1: string}
     */
    public function displayLines(): array
    {
        $line1 = $this->student ? $this->student->callName() : 'Kartu tdk dikenal';
        $line2 = match ($this->result) {
            ScanResult::CheckIn => 'MASUK '.$this->at->format('H:i'),
            ScanResult::Late => 'TERLAMBAT '.$this->at->format('H:i'),
            ScanResult::CheckOut => 'PULANG '.$this->at->format('H:i'),
            ScanResult::Duplicate, ScanResult::AlreadyIn, ScanResult::AlreadyOut => 'SUDAH PRESENSI',
            ScanResult::UnknownCard => 'Hubungi guru',
            ScanResult::Inactive => 'KARTU NONAKTIF',
        };

        return [
            Str::limit(Str::ascii($line1), 16, ''),
            Str::limit(Str::ascii($line2), 16, ''),
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $student = $this->student;

        return [
            'ok' => $this->result->isRecorded(),
            'result' => $this->result->value,
            'uid' => $this->uid,
            'tone' => $this->result->tone(),
            'headline' => $this->headline(),
            'message' => $this->message(),
            'speech' => $this->speech(),
            'speech_detailed' => $this->detailedSpeech(),
            'display' => $this->displayLines(),
            'time' => $this->at->format('H.i'),
            'student' => $student ? [
                'id' => $student->id,
                'name' => $student->name,
                'call_name' => $student->callName(),
                'initials' => $student->initials(),
                'class' => $student->schoolClass?->name,
                'level' => $student->schoolClass?->level,
                'nis' => $student->nis,
                'photo_url' => $student->photoUrl(),
                'feedback_mode' => $student->feedback_mode->value,
            ] : null,
            'attendance' => $this->attendance?->exists ? [
                'status' => $this->attendance->status->value,
                'status_label' => $this->attendance->status->label(),
                'status_code' => $this->attendance->status->code(),
                'status_tone' => $this->attendance->status->tone(),
                'check_in' => $this->attendance->check_in_at?->format('H.i'),
                'check_out' => $this->attendance->check_out_at?->format('H.i'),
            ] : null,
        ];
    }

    private function greeting(): string
    {
        return match (true) {
            $this->at->hour < 11 => 'Selamat pagi',
            $this->at->hour < 15 => 'Selamat siang',
            $this->at->hour < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };
    }

    private function checkInTime(string $format): string
    {
        return $this->attendance?->check_in_at?->format($format) ?? '-';
    }

    private function checkOutTime(string $format): string
    {
        return $this->attendance?->check_out_at?->format($format) ?? '-';
    }

    /**
     * Ubah jam menjadi kalimat yang enak diucapkan mesin suara berbahasa
     * Indonesia, mis. "pukul 7 lewat 5 menit" atau "pukul 12 tepat".
     */
    public static function spokenTime(CarbonInterface|string|null $time): string
    {
        if ($time === null || $time === '') {
            return '';
        }

        [$hour, $minute] = $time instanceof CarbonInterface
            ? [$time->hour, $time->minute]
            : array_map('intval', explode(':', $time) + [1 => 0]);

        return $minute === 0 ? "pukul {$hour} tepat" : "pukul {$hour} lewat {$minute} menit";
    }
}
