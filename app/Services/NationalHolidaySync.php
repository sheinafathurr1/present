<?php

namespace App\Services;

use App\Models\Holiday;
use App\Support\SchoolSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Mengisi hari libur nasional & cuti bersama Indonesia secara otomatis.
 *
 * Sumber utama: kalender publik "Hari libur di Indonesia" (format iCalendar),
 * alamatnya bisa diganti lewat PRESENSI_HOLIDAY_ICS_URL. Bila server tidak
 * terhubung internet, dipakai data bawaan: libur bertanggal tetap dan libur
 * yang mengikuti Paskah (bisa dihitung). Libur berdasarkan kalender bulan
 * (Idul Fitri, Imlek, Nyepi, Waisak, dst.) hanya bisa didapat dari internet.
 *
 * Libur sekolah yang ditambahkan admin tidak pernah ditimpa, dan pilihan
 * "tetap masuk sekolah" pada libur nasional dipertahankan saat diperbarui.
 */
class NationalHolidaySync
{
    public const SOURCE_INTERNET = 'internet';

    public const SOURCE_BUILT_IN = 'bawaan';

    public function __construct(private SchoolSettings $settings) {}

    /**
     * @param  list<int>  $years
     * @return array{source: string, added: int, updated: int, removed: int, error: ?string}
     */
    public function sync(array $years): array
    {
        $years = array_values(array_unique($years));
        $events = null;
        $error = null;

        try {
            $response = Http::timeout(15)->get(config('presensi.holiday_calendar_url'));
            $response->throw();
            $events = $this->parseIcs($response->body());

            if ($events === []) {
                throw new RuntimeException('Kalender libur kosong atau formatnya tidak dikenali.');
            }
        } catch (Throwable $e) {
            $events = null;
            $error = $e->getMessage();
        }

        $stats = ['added' => 0, 'updated' => 0, 'removed' => 0];

        foreach ($years as $year) {
            $forYear = $events === null
                ? []
                : array_values(array_filter($events, fn (array $event) => str_starts_with($event['date'], $year.'-')));

            if ($forYear !== []) {
                $this->apply($year, $forYear, removeMissing: true, stats: $stats);
            } elseif (! Holiday::inYear($year)->where('is_auto', true)->exists()) {
                // Tahun ini belum punya data sama sekali: isi dengan data bawaan dulu.
                $this->apply($year, self::builtIn($year), removeMissing: false, stats: $stats);
            }
        }

        $source = $events === null ? self::SOURCE_BUILT_IN : self::SOURCE_INTERNET;

        $this->settings->update([
            'holidays_synced_at' => now()->toIso8601String(),
            'holidays_source' => $source,
            'holidays_error' => $error,
        ]);

        return [...$stats, 'source' => $source, 'error' => $error];
    }

    /**
     * @param  list<array{date: string, name: string, type: string, tentative: bool}>  $entries
     * @param  array{added: int, updated: int, removed: int}  $stats
     */
    private function apply(int $year, array $entries, bool $removeMissing, array &$stats): void
    {
        // Gabungkan bila ada dua libur di tanggal yang sama.
        $byDate = [];
        foreach ($entries as $entry) {
            $date = $entry['date'];

            if (! isset($byDate[$date])) {
                $byDate[$date] = $entry;

                continue;
            }

            if (! str_contains($byDate[$date]['name'], $entry['name'])) {
                $byDate[$date]['name'] .= ' / '.$entry['name'];
            }
            if ($entry['type'] === Holiday::TYPE_NATIONAL) {
                $byDate[$date]['type'] = Holiday::TYPE_NATIONAL;
            }
            $byDate[$date]['tentative'] = $byDate[$date]['tentative'] || $entry['tentative'];
        }

        $existing = Holiday::inYear($year)->get()->keyBy('date');

        foreach ($byDate as $date => $entry) {
            $row = $existing->get($date);

            // Libur sekolah buatan admin tidak ditimpa.
            if ($row && ! $row->is_auto) {
                continue;
            }

            if ($row) {
                $row->fill([
                    'name' => mb_substr($entry['name'], 0, 150),
                    'type' => $entry['type'],
                    'is_tentative' => $entry['tentative'],
                ]);

                if ($row->isDirty()) {
                    $row->save();
                    $stats['updated']++;
                }

                continue;
            }

            Holiday::create([
                'date' => $date,
                'name' => mb_substr($entry['name'], 0, 150),
                'type' => $entry['type'],
                'is_auto' => true,
                'is_tentative' => $entry['tentative'],
                'is_school_day' => $entry['type'] === Holiday::TYPE_JOINT && ! $this->settings->jointHolidaysAreOff(),
            ]);
            $stats['added']++;
        }

        if ($removeMissing) {
            $stats['removed'] += Holiday::inYear($year)
                ->where('is_auto', true)
                ->whereNotIn('date', array_keys($byDate))
                ->delete();
        }
    }

    /**
     * Ambil libur nasional & cuti bersama dari teks iCalendar (.ics).
     * Acara berjenis "Perayaan" (bukan hari libur) dilewati.
     *
     * @return list<array{date: string, name: string, type: string, tentative: bool}>
     */
    public function parseIcs(string $ics): array
    {
        // Baris yang dilipat (diawali spasi/tab) disambung ke baris sebelumnya.
        $ics = preg_replace("/\r?\n[ \t]/", '', $ics) ?? '';
        preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $ics, $matches);

        $events = [];

        foreach ($matches[1] as $block) {
            $fields = [];
            foreach (preg_split("/\r?\n/", trim($block)) ?: [] as $line) {
                if (! str_contains($line, ':')) {
                    continue;
                }
                [$key, $value] = explode(':', $line, 2);
                $fields[strtoupper(explode(';', $key)[0])] = $this->unescape($value);
            }

            $summary = trim($fields['SUMMARY'] ?? '');
            $description = $fields['DESCRIPTION'] ?? '';
            $isJoint = (bool) preg_match('/cuti bersama|joint holiday/i', $summary);

            if ($summary === '' || (! $isJoint && ! preg_match('/libur nasional|public holiday/i', $description))) {
                continue;
            }

            $start = $this->parseDate($fields['DTSTART'] ?? '');
            if ($start === null) {
                continue;
            }
            $end = $this->parseDate($fields['DTEND'] ?? '') ?? $start->addDay();

            $tentative = (bool) preg_match('/belum pasti|tentatif|tentative/i', $summary.' '.$description);
            $name = trim(preg_replace(['/\s*\(belum pasti\)/i', '/^Joint Holiday for /i'], ['', 'Cuti Bersama '], $summary));

            for ($date = $start; $date->lt($end); $date = $date->addDay()) {
                $events[] = [
                    'date' => $date->toDateString(),
                    'name' => $name,
                    'type' => $isJoint ? Holiday::TYPE_JOINT : Holiday::TYPE_NATIONAL,
                    'tentative' => $tentative,
                ];
            }
        }

        return $events;
    }

    /**
     * Libur yang tanggalnya bisa dipastikan tanpa internet.
     *
     * @return list<array{date: string, name: string, type: string, tentative: bool}>
     */
    public static function builtIn(int $year): array
    {
        $easter = self::easterSunday($year);

        $list = [
            [CarbonImmutable::create($year, 1, 1), 'Tahun Baru Masehi'],
            [$easter->subDays(2), 'Wafat Isa Almasih'],
            [$easter, 'Hari Paskah'],
            [CarbonImmutable::create($year, 5, 1), 'Hari Buruh Internasional'],
            [$easter->addDays(39), 'Kenaikan Isa Almasih'],
            [CarbonImmutable::create($year, 6, 1), 'Hari Lahir Pancasila'],
            [CarbonImmutable::create($year, 8, 17), 'Hari Kemerdekaan Republik Indonesia'],
            [CarbonImmutable::create($year, 12, 25), 'Hari Raya Natal'],
        ];

        return array_map(fn (array $item) => [
            'date' => $item[0]->toDateString(),
            'name' => $item[1],
            'type' => Holiday::TYPE_NATIONAL,
            'tentative' => false,
        ], $list);
    }

    /** Tanggal Paskah (kalender Gregorian, algoritma Meeus/Jones/Butcher). */
    public static function easterSunday(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day)->startOfDay();
    }

    private function parseDate(string $value): ?CarbonImmutable
    {
        return preg_match('/^(\d{4})(\d{2})(\d{2})/', trim($value), $m)
            ? CarbonImmutable::create((int) $m[1], (int) $m[2], (int) $m[3])->startOfDay()
            : null;
    }

    private function unescape(string $value): string
    {
        return str_replace(['\\n', '\\N', '\\,', '\\;', '\\\\'], ["\n", "\n", ',', ';', '\\'], trim($value));
    }
}
