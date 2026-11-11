<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\AttendanceService;
use App\Support\SchoolSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    /** Presensi harian: daftar siswa beserta status pada tanggal tertentu. */
    public function index(Request $request, SchoolSettings $settings): View
    {
        $filters = $request->validate([
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'kelas' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $date = CarbonImmutable::parse($filters['tanggal'] ?? today()->toDateString());
        $dateString = $date->toDateString();

        $students = Student::active()
            ->with(['schoolClass', 'attendances' => fn ($query) => $query->where('date', $dateString)->with('recorder')])
            ->when($filters['kelas'] ?? null, fn ($query, $classId) => $query->where('school_class_id', $classId))
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$q}%")
                ->orWhere('nis', 'like', "%{$q}%")))
            ->orderBy('school_class_id')
            ->orderBy('name')
            ->get()
            ->each(fn (Student $student) => $student->setRelation('today', $student->attendances->first()));

        if ($status = $filters['status'] ?? null) {
            $students = $students->filter(fn (Student $student) => $status === 'belum'
                ? $student->today === null
                : $student->today?->status->value === $status)->values();
        }

        return view('attendances.index', [
            'date' => $date,
            'isSchoolDay' => $settings->isSchoolDay($date),
            'students' => $students,
            'classes' => SchoolClass::orderBy('level')->orderBy('name')->get(),
            'filters' => $filters,
            'statuses' => AttendanceStatus::cases(),
        ]);
    }

    /** Catat atau ubah status presensi secara manual. */
    public function store(Request $request, AttendanceService $service): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', Rule::enum(AttendanceStatus::class)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        $attendance = $service->recordManual(
            $student,
            $data['date'],
            AttendanceStatus::from($data['status']),
            $data['note'] ?? null,
            $request->user(),
        );

        return back()
            ->with('success', "Status {$student->name} disimpan: {$attendance->status->label()}.")
            ->withFragment('siswa-'.$student->id);
    }

    /** Tandai semua siswa yang belum presensi pada tanggal ini sebagai Alpa. */
    public function markAbsent(Request $request, AttendanceService $service): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        $count = $service->markAbsent(CarbonImmutable::parse($data['date']), $request->user());

        if ($count === null) {
            return back()->with('error', 'Tanggal tersebut bukan hari sekolah, tidak ada yang ditandai.');
        }

        return back()->with('success', "{$count} siswa yang belum presensi ditandai Alpa.");
    }
}
