<?php

namespace App\Http\Controllers;

use App\Enums\EmployeeType;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\MonthlyReport;
use App\Support\SchoolSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Data guru & staf tendik. Pimpinan hanya melihat; admin mengelola.
 */
class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('cari', ''));
        $type = EmployeeType::tryFrom((string) $request->query('jenis'));
        $state = $request->query('status', 'aktif');
        $today = today()->toDateString();

        $employees = User::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")
                ->orWhere('nip', 'like', "%{$search}%")
                ->orWhere('position', 'like', "%{$search}%")))
            ->when($type, fn ($query) => $query->where('employee_type', $type))
            ->when($state === 'aktif', fn ($query) => $query->where('is_active', true))
            ->when($state === 'nonaktif', fn ($query) => $query->where('is_active', false))
            ->with(['attendances' => fn ($query) => $query->where('date', $today)])
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('employees.index', [
            'employees' => $employees,
            'search' => $search,
            'type' => $type,
            'state' => $state,
            'counts' => [
                'guru' => User::where('is_active', true)->where('employee_type', EmployeeType::Guru)->count(),
                'tendik' => User::where('is_active', true)->where('employee_type', EmployeeType::Tendik)->count(),
            ],
        ]);
    }

    public function show(Request $request, User $user, SchoolSettings $settings): View
    {
        $month = HistoryController::month($request);
        $report = MonthlyReport::build($month, $settings, users: collect([$user]));

        return view('employees.show', [
            'employee' => $user,
            'month' => $month,
            'days' => $report['days'],
            'row' => $report['rows'][0],
            'workStart' => $settings->workStartFor($user),
            'workEnd' => $settings->workEndFor($user),
            'leaves' => $user->leaveRequests()
                ->overlapping($month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString())
                ->orderBy('start_date')
                ->get(),
            'today' => CarbonImmutable::today(),
        ]);
    }

    public function create(): View
    {
        return view('employees.form', ['employee' => new User, 'settings' => app(SchoolSettings::class)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $user = User::create($data);

        return redirect()->route('employees.show', $user)
            ->with('success', "Akun {$user->name} dibuat. Username: {$user->username}. Berikan kata sandinya secara pribadi.");
    }

    public function edit(User $user): View
    {
        return view('employees.form', ['employee' => $user, 'settings' => app(SchoolSettings::class)]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        // Admin tidak bisa mengunci dirinya sendiri.
        if ($user->is($request->user())) {
            if (($data['role'] ?? null) !== UserRole::Admin->value || ! $data['is_active']) {
                return back()->withInput()->with('error', 'Anda tidak bisa mencabut peran admin atau menonaktifkan akun Anda sendiri.');
            }
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('employees.show', $user)->with('success', "Data {$user->name} disimpan.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Anda tidak bisa menghapus akun Anda sendiri.');
        }

        if ($user->attendances()->exists() || $user->leaveRequests()->exists()) {
            return back()->with('error', "{$user->name} sudah punya riwayat presensi, jadi tidak dihapus. Nonaktifkan akunnya agar riwayat tetap tersimpan.");
        }

        $user->delete();

        return redirect()->route('employees.index')->with('success', "Akun {$user->name} dihapus.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?User $user = null): array
    {
        $request->merge(['username' => mb_strtolower(trim((string) $request->input('username')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'string', Password::min(8)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'employee_type' => ['required', Rule::enum(EmployeeType::class)],
            'position' => ['nullable', 'string', 'max:100'],
            'nip' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 .]+$/'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 +()-]+$/'],
            'work_start' => ['nullable', 'date_format:H:i', 'required_with:work_end'],
            'work_end' => ['nullable', 'date_format:H:i', 'required_with:work_start', 'after:work_start'],
        ], [
            'username.regex' => 'Username hanya boleh huruf kecil, angka, titik, garis bawah, atau tanda minus (tanpa spasi).',
            'username.unique' => 'Username ini sudah dipakai.',
            'nip.regex' => 'NIP hanya berisi angka.',
            'phone.regex' => 'Nomor HP hanya berisi angka.',
            'work_end.after' => 'Jam pulang harus setelah jam masuk.',
        ]);

        $data['must_attend'] = $request->boolean('must_attend');
        $data['is_active'] = $user ? $request->boolean('is_active') : true;
        $data['nip'] = isset($data['nip']) ? preg_replace('/\D/', '', $data['nip']) : null;

        return $data;
    }
}
