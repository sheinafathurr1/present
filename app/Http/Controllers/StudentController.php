<?php

namespace App\Http\Controllers;

use App\Enums\DisabilityType;
use App\Enums\FeedbackMode;
use App\Enums\ScanResult;
use App\Http\Requests\StudentRequest;
use App\Models\RfidScan;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'kelas' => ['nullable', 'integer'],
            'kartu' => ['nullable', 'in:ada,belum'],
            'status' => ['nullable', 'in:aktif,nonaktif'],
        ]);

        $students = Student::with('schoolClass')
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$q}%")
                ->orWhere('nickname', 'like', "%{$q}%")
                ->orWhere('nis', 'like', "%{$q}%")
                ->when(Student::normalizeUid($q), fn ($uidQuery, $uid) => $uidQuery->orWhere('rfid_uid', 'like', "%{$uid}%"))))
            ->when($filters['kelas'] ?? null, fn ($query, $classId) => $query->where('school_class_id', $classId))
            ->when(($filters['kartu'] ?? null) === 'ada', fn ($query) => $query->whereNotNull('rfid_uid'))
            ->when(($filters['kartu'] ?? null) === 'belum', fn ($query) => $query->whereNull('rfid_uid'))
            ->when(($filters['status'] ?? 'aktif') === 'aktif', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'nonaktif', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('students.index', [
            'students' => $students,
            'classes' => SchoolClass::orderBy('level')->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function show(Student $student): View
    {
        $student->load('schoolClass.homeroomTeacher');

        return view('students.show', [
            'student' => $student,
            'attendances' => $student->attendances()->with(['checkInDevice', 'checkOutDevice', 'recorder'])
                ->orderByDesc('date')->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        $student = new Student([
            'rfid_uid' => $request->query('uid'),
            'school_class_id' => $request->query('kelas'),
        ]);

        return view('students.form', $this->formData($student));
    }

    public function store(StudentRequest $request): RedirectResponse
    {
        $student = new Student($request->safe()->except(['photo', 'remove_photo']));
        $this->handlePhoto($request, $student);
        $student->save();

        return redirect()->route('students.show', $student)
            ->with('success', "Data {$student->name} berhasil ditambahkan.");
    }

    public function edit(Student $student): View
    {
        return view('students.form', $this->formData($student));
    }

    public function update(StudentRequest $request, Student $student): RedirectResponse
    {
        $student->fill($request->safe()->except(['photo', 'remove_photo']));
        $this->handlePhoto($request, $student);
        $student->save();

        return redirect()->route('students.show', $student)
            ->with('success', "Data {$student->name} berhasil diperbarui.");
    }

    public function destroy(Student $student): RedirectResponse
    {
        if ($student->photo_path) {
            Storage::disk('public')->delete($student->photo_path);
        }

        $student->delete();

        return redirect()->route('students.index')
            ->with('success', "Data {$student->name} beserta riwayat presensinya telah dihapus.");
    }

    private function handlePhoto(StudentRequest $request, Student $student): void
    {
        if ($student->photo_path && ($request->boolean('remove_photo') || $request->hasFile('photo'))) {
            Storage::disk('public')->delete($student->photo_path);
            $student->photo_path = null;
        }

        if ($request->hasFile('photo')) {
            $student->photo_path = $request->file('photo')->store('students', 'public');
        }
    }

    /** @return array<string, mixed> */
    private function formData(Student $student): array
    {
        return [
            'student' => $student,
            'classes' => SchoolClass::orderBy('level')->orderBy('name')->get(),
            'disabilityTypes' => DisabilityType::cases(),
            'feedbackModes' => FeedbackMode::cases(),
            // Kartu yang baru saja ditempel di pembaca tetapi belum terdaftar.
            'unknownScans' => RfidScan::with('device')
                ->where('result', ScanResult::UnknownCard)
                ->where('scanned_at', '>=', now()->subDay())
                ->whereNotIn('uid', Student::whereNotNull('rfid_uid')->select('rfid_uid'))
                ->latest('scanned_at')
                ->get()
                ->unique('uid')
                ->take(5),
        ];
    }
}
