<?php

namespace App\Support;

use App\Models\Holiday;
use App\Models\SchoolClass;
use App\Models\Setting;
use Carbon\CarbonInterface;

/**
 * Pengaturan sekolah yang bisa diubah admin lewat dashboard.
 *
 * Nilai disimpan di tabel `settings` (JSON) dan digabung dengan nilai bawaan
 * dari config/presensi.php. Didaftarkan sebagai singleton sehingga database
 * hanya dibaca sekali per request.
 */
class SchoolSettings
{
    /** @var array<string, mixed>|null */
    private ?array $values = null;

    /** @return array<string, mixed> */
    public function all(): array
    {
        if ($this->values === null) {
            $stored = Setting::query()->pluck('value', 'key')
                ->map(fn (?string $value) => json_decode((string) $value, true))
                ->all();

            $this->values = array_merge(config('presensi.defaults', []), $stored);
        }

        return $this->values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /** @param  array<string, mixed>  $values */
    public function update(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
        }

        $this->values = null;
    }

    public function schoolName(): string
    {
        return (string) $this->get('school_name', config('app.name'));
    }

    /** Batas jam masuk (HH:MM). Tap setelah menit ini dianggap terlambat. */
    public function lateAfter(?SchoolClass $class = null): string
    {
        return $class?->late_after ?: (string) $this->get('late_after', '07:15');
    }

    /** Mulai jam berapa tap kedua dihitung sebagai presensi pulang (HH:MM). */
    public function checkOutStart(?SchoolClass $class = null): string
    {
        return $class?->check_out_start ?: (string) $this->get('check_out_start', '12:00');
    }

    /** Jeda minimal (detik) agar kartu yang tertempel dua kali tidak dihitung ganda. */
    public function cooldownSeconds(): int
    {
        return max(0, (int) $this->get('tap_cooldown_seconds', 60));
    }

    /** @return list<int> Hari sekolah, format ISO (1 = Senin ... 7 = Minggu). */
    public function schoolDays(): array
    {
        return array_values(array_map('intval', (array) $this->get('school_days', [1, 2, 3, 4, 5, 6])));
    }

    public function kioskMessage(): string
    {
        return (string) $this->get('kiosk_message', 'Tempelkan kartu kamu di alat pembaca');
    }

    /** Hari sekolah = termasuk hari aktif dan bukan hari libur. */
    public function isSchoolDay(CarbonInterface $date): bool
    {
        return in_array($date->dayOfWeekIso, $this->schoolDays(), true)
            && ! Holiday::isHoliday($date->toDateString());
    }
}
