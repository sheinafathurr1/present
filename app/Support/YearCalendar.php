<?php

namespace App\Support;

use App\Models\Holiday;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Data kalender satu tahun (12 bulan, minggu dimulai Senin) untuk halaman Hari Libur.
 */
class YearCalendar
{
    /** Singkatan => nama lengkap, dimulai Senin. */
    public const WEEKDAYS = ['Sen' => 'Senin', 'Sel' => 'Selasa', 'Rab' => 'Rabu', 'Kam' => 'Kamis', 'Jum' => 'Jumat', 'Sab' => 'Sabtu', 'Min' => 'Minggu'];

    /**
     * @param  Collection<string, Holiday>  $holidays  dikunci per tanggal "Y-m-d"
     * @return list<array{current: bool, label: string, month: int, weeks: list<list<array<string, mixed>|null>>, count: int}>
     */
    public static function build(int $year, Collection $holidays, SchoolSettings $settings): array
    {
        $schoolDays = $settings->schoolDays();
        $today = CarbonImmutable::today()->toDateString();
        $months = [];

        for ($month = 1; $month <= 12; $month++) {
            $first = CarbonImmutable::create($year, $month, 1);
            $weeks = [];
            $week = array_fill(0, $first->dayOfWeekIso - 1, null);
            $count = 0;

            for ($date = $first; $date->month === $month; $date = $date->addDay()) {
                $key = $date->toDateString();
                $holiday = $holidays->get($key);

                if ($holiday && ! $holiday->is_school_day) {
                    $count++;
                }

                $week[] = [
                    'date' => $key,
                    'day' => $date->day,
                    'holiday' => $holiday,
                    'weekend' => ! in_array($date->dayOfWeekIso, $schoolDays, true),
                    'today' => $key === $today,
                    'label' => $date->translatedFormat('l, d F Y'),
                ];

                if (count($week) === 7) {
                    $weeks[] = $week;
                    $week = [];
                }
            }

            if ($week !== []) {
                $weeks[] = array_pad($week, 7, null);
            }

            $months[] = [
                'current' => $year === CarbonImmutable::today()->year && $month === CarbonImmutable::today()->month,
                'label' => $first->translatedFormat('F'),
                'month' => $month,
                'weeks' => $weeks,
                'count' => $count,
            ];
        }

        return $months;
    }
}
