<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\SchoolClass;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Rekap bulanan per kelas: matriks siswa × tanggal berisi kode H/T/I/S/A.
 *
 * Hari sekolah yang sudah lewat tetapi tidak ada catatan presensinya
 * ditampilkan "–" dan dihitung sebagai Alpa (tanpa keterangan). Hari ini
 * belum dihitung alpa karena hari sekolah masih berjalan.
 */
class MonthlyReport
{
    /** @var list<array{date: CarbonImmutable, day: int, school_day: bool, past: bool, today: bool, holiday: ?string}> */
    public array $days = [];

    /** @var Collection<int, array<string, mixed>> */
    public Collection $rows;

    /** Hari sekolah yang sudah berjalan (termasuk hari ini). */
    public int $schoolDaysElapsed = 0;

    /** Hari sekolah sebelum hari ini. */
    private int $schoolDaysBeforeToday = 0;

    private bool $todayIsSchoolDay = false;

    public function __construct(
        public CarbonImmutable $month,
        public ?SchoolClass $class,
        SchoolSettings $settings,
    ) {
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();
        $today = CarbonImmutable::today();

        $holidays = Holiday::off()->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('name', 'date');

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $isSchoolDay = in_array($date->dayOfWeekIso, $settings->schoolDays(), true)
                && ! $holidays->has($date->toDateString());
            $isPast = $date->lt($today);
            $isToday = $date->equalTo($today);

            $this->days[] = [
                'date' => $date,
                'day' => $date->day,
                'school_day' => $isSchoolDay,
                'past' => $isPast,
                'today' => $isToday,
                'holiday' => $holidays->get($date->toDateString()),
            ];

            if ($isSchoolDay && $isPast) {
                $this->schoolDaysBeforeToday++;
            }

            if ($isSchoolDay && $isToday) {
                $this->todayIsSchoolDay = true;
            }
        }

        $this->schoolDaysElapsed = $this->schoolDaysBeforeToday + ($this->todayIsSchoolDay ? 1 : 0);

        $students = Student::query()
            ->when($class, fn ($query) => $query->where('school_class_id', $class->id))
            ->where(fn ($query) => $query->where('is_active', true)->orWhereHas('attendances', fn ($q) => $q
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])))
            ->with('schoolClass')
            ->orderBy('name')
            ->get();

        $attendances = Attendance::whereIn('student_id', $students->pluck('id'))
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $items) => $items->keyBy('date'));

        $this->rows = $students->map(fn (Student $student) => $this->buildRow(
            $student,
            $attendances->get($student->id, collect()),
        ));
    }

    /**
     * @param  Collection<string, Attendance>  $attendances
     * @return array<string, mixed>
     */
    private function buildRow(Student $student, Collection $attendances): array
    {
        $counts = array_fill_keys(['H', 'T', 'I', 'S', 'A'], 0);
        $cells = [];
        $elapsed = $this->schoolDaysBeforeToday;

        foreach ($this->days as $day) {
            $attendance = $attendances->get($day['date']->toDateString());

            if ($attendance !== null && $day['today'] && $this->todayIsSchoolDay) {
                $elapsed++;
            }

            if ($attendance !== null) {
                $code = $attendance->status->code();
                $counts[$code]++;
                $cells[] = ['code' => $code, 'tone' => $attendance->status->tone(), 'title' => $attendance->status->label()];
            } elseif ($day['school_day'] && $day['past']) {
                $counts['A']++;
                $cells[] = ['code' => '–', 'tone' => 'missing', 'title' => 'Tidak ada catatan (dihitung alpa)'];
            } else {
                $cells[] = ['code' => '', 'tone' => $day['school_day'] ? 'future' : 'off', 'title' => $day['holiday'] ?? ''];
            }
        }

        $present = $counts['H'] + $counts['T'];

        return [
            'student' => $student,
            'cells' => $cells,
            'counts' => $counts,
            'percentage' => $elapsed > 0
                ? (int) round(min(100, $present / $elapsed * 100))
                : null,
        ];
    }

    public function title(): string
    {
        return 'Rekap Presensi '.($this->class?->name ?? 'Semua Kelas').' — '.$this->month->translatedFormat('F Y');
    }

    /** @return array<string, string> */
    public static function legend(): array
    {
        return collect(AttendanceStatus::cases())
            ->mapWithKeys(fn (AttendanceStatus $status) => [$status->code() => $status->label()])
            ->put('–', 'Tidak ada catatan (dihitung alpa)')
            ->all();
    }
}
