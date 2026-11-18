<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeType;
use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Ringkasan kehadiran guru & staf pada satu hari (untuk dashboard pimpinan).
 */
class DailySummary
{
    /** Urutan tampil: yang perlu perhatian lebih dulu. */
    private const ORDER = ['belum' => 0, 'alpa' => 1, 'terlambat' => 2, 'izin' => 3, 'sakit' => 4, 'cuti' => 5, 'dinas_luar' => 6, 'hadir' => 7];

    /**
     * @return array{
     *     rows: Collection<int, array{user: User, attendance: ?Attendance, key: string}>,
     *     totals: array<string, int>, total: int, present: int, excused: int, percentage: int
     * }
     */
    public static function for(CarbonImmutable $date, ?EmployeeType $type = null): array
    {
        $key = $date->toDateString();

        $users = User::attending()
            ->when($type, fn ($query) => $query->where('employee_type', $type))
            ->with(['attendances' => fn ($query) => $query->where('date', $key)->with('leaveRequest')])
            ->orderBy('name')
            ->get();

        $totals = array_fill_keys(array_keys(self::ORDER), 0);

        $rows = $users->map(function (User $user) use (&$totals) {
            $attendance = $user->attendances->first();
            $status = $attendance?->status->value ?? 'belum';
            $totals[$status]++;

            return ['user' => $user, 'attendance' => $attendance, 'key' => $status];
        })->sortBy(fn (array $row) => sprintf('%d-%s', self::ORDER[$row['key']], mb_strtolower($row['user']->name)))->values();

        $total = $users->count();
        $present = $totals['hadir'] + $totals['terlambat'];

        return [
            'rows' => $rows,
            'totals' => $totals,
            'total' => $total,
            'present' => $present,
            'excused' => $totals['izin'] + $totals['sakit'] + $totals['cuti'] + $totals['dinas_luar'],
            'percentage' => $total > 0 ? (int) round($present / $total * 100) : 0,
        ];
    }

    /**
     * Persentase hadir pada beberapa hari sekolah terakhir (termasuk hari ini bila hari sekolah).
     *
     * @return list<array{date: string, label: string, percentage: int, today: bool}>
     */
    public static function trend(CarbonImmutable $today, SchoolSettings $settings, int $days = 7): array
    {
        $dates = [];
        for ($date = $today, $guard = 0; count($dates) < $days && $guard < 40; $date = $date->subDay(), $guard++) {
            if ($settings->isSchoolDay($date)) {
                $dates[] = $date;
            }
        }
        $dates = array_reverse($dates);

        if ($dates === []) {
            return [];
        }

        $total = max(1, User::attending()->count());
        $present = Attendance::query()
            ->whereBetween('date', [$dates[0]->toDateString(), end($dates)->toDateString()])
            ->whereIn('status', [AttendanceStatus::Hadir, AttendanceStatus::Terlambat])
            ->whereHas('user', fn ($query) => $query->attending())
            ->selectRaw('date, count(*) as aggregate')
            ->groupBy('date')
            ->pluck('aggregate', 'date');

        return array_map(fn (CarbonImmutable $date) => [
            'date' => $date->toDateString(),
            'label' => $date->translatedFormat('D'),
            'full' => $date->translatedFormat('l, d F'),
            'percentage' => (int) round(((int) ($present[$date->toDateString()] ?? 0)) / $total * 100),
            'today' => $date->isSameDay($today),
        ], $dates);
    }
}
