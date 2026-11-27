<?php

namespace App\Support;

use App\Enums\EmployeeType;
use App\Models\Holiday;
use App\Models\Setting;
use App\Models\User;
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

    /** @var array<string, bool> */
    private array $holidayCache = [];

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
        $this->holidayCache = [];
    }

    public function schoolName(): string
    {
        return (string) $this->get('school_name', config('app.name'));
    }

    /* ---------- Lokasi ---------- */

    public function hasLocation(): bool
    {
        return is_numeric($this->get('school_lat')) && is_numeric($this->get('school_lng'));
    }

    public function schoolLat(): ?float
    {
        return $this->hasLocation() ? (float) $this->get('school_lat') : null;
    }

    public function schoolLng(): ?float
    {
        return $this->hasLocation() ? (float) $this->get('school_lng') : null;
    }

    /** Radius area presensi dari titik sekolah (meter). */
    public function radius(): int
    {
        return max(10, (int) $this->get('radius_m', 100));
    }

    /** Akurasi GPS terburuk yang masih diterima (meter). */
    public function maxAccuracy(): int
    {
        return max(10, (int) $this->get('max_accuracy_m', 100));
    }

    /* ---------- Jam kerja ---------- */

    /** Jam masuk (HH:MM) untuk orang ini: jadwal pribadinya, atau jadwal guru/tendik. */
    public function workStartFor(User $user): string
    {
        return $user->work_start ?: (string) $this->get($this->typeKey($user).'_start', '07:00');
    }

    /** Jam pulang (HH:MM) untuk orang ini. */
    public function workEndFor(User $user): string
    {
        return $user->work_end ?: (string) $this->get($this->typeKey($user).'_end', '13:00');
    }

    public function lateTolerance(): int
    {
        return max(0, (int) $this->get('late_tolerance_minutes', 15));
    }

    public function checkInOpens(): string
    {
        return (string) $this->get('check_in_opens', '05:00');
    }

    private function typeKey(User $user): string
    {
        return ($user->employee_type ?? EmployeeType::Guru)->value;
    }

    /* ---------- Hari sekolah ---------- */

    /** @return list<int> Hari sekolah, format ISO (1 = Senin ... 7 = Minggu). */
    public function schoolDays(): array
    {
        return array_values(array_map('intval', (array) $this->get('school_days', [1, 2, 3, 4, 5, 6])));
    }

    /** Apakah cuti bersama dihitung sebagai hari libur sekolah. */
    public function jointHolidaysAreOff(): bool
    {
        return (bool) $this->get('joint_holidays_off', true);
    }

    /** Lupakan hasil pengecekan hari libur (dipanggil saat data libur berubah). */
    public function flushHolidayCache(): void
    {
        $this->holidayCache = [];
    }

    /** Hari sekolah = termasuk hari aktif dan bukan hari libur. */
    public function isSchoolDay(CarbonInterface $date): bool
    {
        if (! in_array($date->dayOfWeekIso, $this->schoolDays(), true)) {
            return false;
        }

        $key = $date->toDateString();

        return ! ($this->holidayCache[$key] ??= Holiday::isHoliday($key));
    }
}
