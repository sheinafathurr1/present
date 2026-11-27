<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeType;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Rekap kehadiran bulanan guru & staf: matriks orang × tanggal beserta jumlahnya.
 */
class MonthlyReport
{
    /**
     * @param  Collection<int, User>|null  $users  bawaan: semua yang wajib presensi
     * @return array{
     *     month: CarbonImmutable,
     *     days: list<array{date: string, day: int, weekday: string, school: bool, holiday: ?string, today: bool, future: bool}>,
     *     rows: list<array{user: User, cells: array<string, ?Attendance>, totals: array<string, int>, missing: int, late_minutes: int, early_count: int, school_days: int, percentage: int}>,
     *     school_day_count: int,
     *     totals: array<string, int>,
     * }
     */
    public static function build(CarbonImmutable $month, SchoolSettings $settings, ?EmployeeType $type = null, ?Collection $users = null): array
    {
        $users ??= User::attending()
            ->when($type, fn ($query) => $query->where('employee_type', $type))
            ->orderBy('name')
            ->get();

        return ['month' => $month->startOfMonth()] + self::period($month->startOfMonth(), $month->endOfMonth()->startOfDay(), $settings, $users);
    }

    /**
     * Rekap untuk rentang tanggal bebas (dipakai juga untuk ekspor).
     *
     * @param  Collection<int, User>  $users
     */
    public static function period(CarbonImmutable $start, CarbonImmutable $end, SchoolSettings $settings, Collection $users): array
    {
        $start = $start->startOfDay();
        $end = $end->startOfDay();
        $today = CarbonImmutable::today();

        $holidays = Holiday::off()->whereBetween('date', [$start->toDateString(), $end->toDateString()])->pluck('name', 'date');
        $schoolDays = $settings->schoolDays();

        $days = [];
        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $key = $date->toDateString();
            $days[] = [
                'date' => $key,
                'day' => $date->day,
                'weekday' => $date->translatedFormat('D'),
                'label' => $date->translatedFormat('l, d F Y'),
                'school' => in_array($date->dayOfWeekIso, $schoolDays, true) && ! isset($holidays[$key]),
                'holiday' => $holidays[$key] ?? null,
                'today' => $date->isSameDay($today),
                'future' => $date->gt($today),
            ];
        }

        $attendances = Attendance::whereIn('user_id', $users->pluck('id'))
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->with('leaveRequest')
            ->get()
            ->groupBy('user_id');

        $statusKeys = array_map(fn (AttendanceStatus $status) => $status->value, AttendanceStatus::cases());
        $grand = array_fill_keys($statusKeys, 0);
        $countable = array_filter($days, fn (array $day) => $day['school'] && ! $day['future']);

        $rows = [];
        foreach ($users as $user) {
            $byDate = ($attendances[$user->id] ?? collect())->keyBy('date');
            $totals = array_fill_keys($statusKeys, 0);
            $cells = [];
            $missing = 0;

            foreach ($days as $day) {
                $attendance = $byDate[$day['date']] ?? null;
                $cells[$day['date']] = $attendance;

                if ($attendance) {
                    $totals[$attendance->status->value]++;
                    $grand[$attendance->status->value]++;
                } elseif ($day['school'] && ! $day['future'] && ! $day['today']) {
                    $missing++;
                }
            }

            $present = $totals['hadir'] + $totals['terlambat'];
            $schoolDaysSoFar = count($countable);

            $rows[] = [
                'user' => $user,
                'cells' => $cells,
                'totals' => $totals,
                'missing' => $missing,
                'late_minutes' => (int) $byDate->sum('late_minutes'),
                'early_count' => $byDate->where('early_minutes', '>', 0)->count(),
                'early_minutes' => (int) $byDate->sum('early_minutes'),
                'school_days' => $schoolDaysSoFar,
                'percentage' => $schoolDaysSoFar > 0 ? (int) round($present / $schoolDaysSoFar * 100) : 0,
            ];
        }

        return [
            'days' => $days,
            'rows' => $rows,
            'school_day_count' => count(array_filter($days, fn (array $day) => $day['school'])),
            'totals' => $grand,
        ];
    }
}
