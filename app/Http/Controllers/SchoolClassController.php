<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SchoolClassController extends Controller
{
    public function index(): View
    {
        return view('classes.index', [
            'classes' => SchoolClass::with('homeroomTeacher')
                ->withCount(['students' => fn ($query) => $query->where('is_active', true)])
                ->orderBy('level')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('classes.form', $this->formData(new SchoolClass));
    }

    public function store(Request $request): RedirectResponse
    {
        $class = SchoolClass::create($this->validated($request));

        return redirect()->route('classes.index')->with('success', "Kelas {$class->name} ditambahkan.");
    }

    public function edit(SchoolClass $schoolClass): View
    {
        return view('classes.form', $this->formData($schoolClass));
    }

    public function update(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $schoolClass->update($this->validated($request, $schoolClass));

        return redirect()->route('classes.index')->with('success', "Kelas {$schoolClass->name} diperbarui.");
    }

    public function destroy(SchoolClass $schoolClass): RedirectResponse
    {
        if ($schoolClass->students()->exists()) {
            return back()->with('error', "Kelas {$schoolClass->name} masih memiliki siswa. Pindahkan siswanya terlebih dahulu.");
        }

        $schoolClass->delete();

        return redirect()->route('classes.index')->with('success', "Kelas {$schoolClass->name} dihapus.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?SchoolClass $class = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('school_classes', 'name')->ignore($class)],
            'level' => ['nullable', Rule::in(SchoolClass::LEVELS)],
            'homeroom_teacher_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'late_after' => ['nullable', 'date_format:H:i'],
            'check_out_start' => ['nullable', 'date_format:H:i'],
        ]);
    }

    /** @return array<string, mixed> */
    private function formData(SchoolClass $class): array
    {
        return [
            'schoolClass' => $class,
            'teachers' => User::whereIn('role', [UserRole::Guru, UserRole::Admin])->orderBy('name')->get(),
            'levels' => SchoolClass::LEVELS,
        ];
    }
}
