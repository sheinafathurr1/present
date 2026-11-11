<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Ringkasan jumlah kehadiran untuk satu tanggal (seluruh sekolah & per kelas).
 */
class DailySummary
{
    /** @var array<string, int> */
    public array $totals;

    /** @var Collection<int, array<string, mixed>> */
    public Collection $classes;

    public function __construct(public string $date)
    {
        $statusByStudent = Attendance::where('date', $date)->pluck('status', 'student_id');
        $students = Student::active()->get(['id', 'school_class_id']);

        $this->totals = $this->count($students, $statusByStudent);

        $this->classes = SchoolClass::orderBy('level')->orderBy('name')->get()
            ->map(fn (SchoolClass $class) => [
                'class' => $class,
                ...$this->count($students->where('school_class_id', $class->id), $statusByStudent),
            ])
            ->filter(fn (array $row) => $row['total'] > 0)
            ->values();
    }

    /**
     * @param  Collection<int, Student>  $students
     * @param  Collection<int, AttendanceStatus>  $statusByStudent
     * @return array<string, int>
     */
    private function count(Collection $students, Collection $statusByStudent): array
    {
        $statuses = $students->map(fn (Student $student) => $statusByStudent->get($student->id));
        $countOf = fn (AttendanceStatus $status) => $statuses->filter(fn ($value) => $value === $status)->count();

        $hadir = $countOf(AttendanceStatus::Hadir);
        $terlambat = $countOf(AttendanceStatus::Terlambat);

        return [
            'total' => $students->count(),
            'present' => $hadir + $terlambat,
            'hadir' => $hadir,
            'terlambat' => $terlambat,
            'izin' => $countOf(AttendanceStatus::Izin),
            'sakit' => $countOf(AttendanceStatus::Sakit),
            'alpa' => $countOf(AttendanceStatus::Alpa),
            'unrecorded' => $statuses->filter(fn ($value) => $value === null)->count(),
        ];
    }

    public function percentage(): int
    {
        return $this->totals['total'] > 0
            ? (int) round($this->totals['present'] / $this->totals['total'] * 100)
            : 0;
    }
}
