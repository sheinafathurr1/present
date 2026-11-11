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
 * ke pembaca kartu USB, sehingga angka & daftar diperbarui otomatis.
 */
class DashboardController extends Controller
{
    private const MISSING_LIMIT = 12;

    public function index(SchoolSettings $settings): View
    {
        $today = today();
        $summary = new DailySummary($today->toDateString());

        return view('dashboard.index', [
            'today' => $today,
            'isSchoolDay' => $settings->isSchoolDay($today),
            'summary' => $summary,
            'recentScans' => $this->recentScans(),
            'missing' => $this->missingStudents(),
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
            'scans' => $this->recentScans()->map(fn (RfidScan $scan) => [
                'id' => $scan->id,
                'time' => $scan->scanned_at->format('H.i'),
                'name' => $scan->student?->name ?? 'Kartu '.$scan->uid,
                'class' => $scan->student?->schoolClass?->name,
                'result' => $scan->result->label(),
                'tone' => $scan->result->tone(),
                'source' => $scan->sourceName(),
            ])->values(),
            'missing' => $this->missingStudents()->map(fn (Student $student) => [
                'id' => $student->id,
                'name' => $student->name,
                'class' => $student->schoolClass?->name,
                'url' => route('students.show', $student),
            ])->values(),
        ]);
    }

    /** @return Collection<int, RfidScan> */
    private function recentScans(): Collection
    {
        return RfidScan::with(['student.schoolClass', 'device', 'user'])
            ->latest('scanned_at')
            ->latest('id')
            ->limit(8)
            ->get();
    }

    /**
     * Siswa aktif yang hari ini belum punya catatan presensi sama sekali.
     *
     * @return Collection<int, Student>
     */
    private function missingStudents(): Collection
    {
        return Student::active()
            ->with('schoolClass')
            ->whereDoesntHave('attendances', fn ($query) => $query->where('date', today()->toDateString()))
            ->orderBy('school_class_id')
            ->orderBy('name')
            ->limit(self::MISSING_LIMIT)
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
            ];
        });
    }
}
