<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\RfidScan;
use App\Models\Student;
use App\Support\DailySummary;
use App\Support\SchoolSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Halaman utama guru. Biasanya dibiarkan terbuka di PC guru yang tersambung
 * ke pembaca kartu USB, sehingga angka diperbarui otomatis setelah setiap tap.
 */
class DashboardController extends Controller
{
    public function index(SchoolSettings $settings): View
    {
        $today = today();

        return view('dashboard.index', [
            'today' => $today,
            'isSchoolDay' => $settings->isSchoolDay($today),
            'summary' => new DailySummary($today->toDateString()),
            'recentScans' => $this->recentScans(),
            'trend' => $this->trend($settings),
            'unregisteredCount' => Student::active()->whereNull('rfid_uid')->count(),
        ]);
    }

    /** Data untuk pembaruan otomatis dashboard. */
    public function live(): JsonResponse
    {
        $summary = new DailySummary(today()->toDateString());

        return response()->json([
            'totals' => $summary->totals,
            'percentage' => $summary->percentage(),
            'classes' => $summary->classes->map(fn (array $row) => [
                'id' => $row['class']->id,
                'present' => $row['present'],
                'total' => $row['total'],
                'terlambat' => $row['terlambat'],
                'izin_sakit' => $row['izin'] + $row['sakit'],
                'unrecorded' => $row['unrecorded'],
                'percentage' => $row['total'] > 0 ? (int) round($row['present'] / $row['total'] * 100) : 0,
            ])->values(),
            'scans' => $this->recentScans()->map(fn (RfidScan $scan) => [
                'id' => $scan->id,
                'time' => $scan->scanned_at->format('H.i'),
                'name' => $scan->student?->name ?? 'Kartu '.$scan->uid,
                'class' => $scan->student?->schoolClass?->name,
                'result' => $scan->result->label(),
                'tone' => $scan->result->tone(),
                'source' => $scan->sourceName(),
            ])->values(),
        ]);
    }

    /** @return Collection<int, RfidScan> */
    private function recentScans(): Collection
    {
        return RfidScan::with(['student.schoolClass', 'device', 'user'])
            ->latest('scanned_at')
            ->latest('id')
            ->limit(6)
            ->get();
    }

    /**
     * Persentase kehadiran 7 hari terakhir.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function trend(SchoolSettings $settings): Collection
    {
        $start = today()->subDays(6);
        $total = max(1, Student::active()->count());

        $present = Attendance::whereBetween('date', [$start->toDateString(), today()->toDateString()])
            ->whereIn('status', [AttendanceStatus::Hadir, AttendanceStatus::Terlambat])
            ->selectRaw('date, count(*) as aggregate')
            ->groupBy('date')
            ->pluck('aggregate', 'date');

        return collect(range(0, 6))->map(function (int $offset) use ($start, $present, $total, $settings) {
            $day = $start->copy()->addDays($offset);
            $count = (int) ($present[$day->toDateString()] ?? 0);

            return [
                'date' => $day,
                'label' => $day->translatedFormat('D'),
                'count' => $count,
                'percentage' => (int) round($count / $total * 100),
                'school_day' => $settings->isSchoolDay($day),
                'today' => $day->isToday(),
            ];
        });
    }
}
