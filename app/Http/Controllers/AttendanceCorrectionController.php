<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\SchoolSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin mengoreksi presensi seseorang pada satu tanggal
 * (mis. HP rusak, lupa presensi pulang, dinas luar mendadak).
 */
class AttendanceCorrectionController extends Controller
{
    public function edit(User $user, string $date, SchoolSettings $settings): View
    {
        $day = $this->parseDate($date);

        return view('employees.correct', [
            'employee' => $user,
            'day' => $day,
            'attendance' => Attendance::where('user_id', $user->id)->where('date', $day->toDateString())->first(),
            'statuses' => AttendanceStatus::cases(),
            'workStart' => $settings->workStartFor($user),
            'workEnd' => $settings->workEndFor($user),
            'isSchoolDay' => $settings->isSchoolDay($day),
        ]);
    }

    public function update(Request $request, User $user, string $date, AttendanceService $service): RedirectResponse
    {
        $day = $this->parseDate($date);

        $data = $request->validate([
            'status' => ['required', Rule::enum(AttendanceStatus::class)],
            'check_in' => ['exclude_unless:status,hadir,terlambat', 'required', 'date_format:H:i'],
            'check_out' => ['exclude_unless:status,hadir,terlambat', 'nullable', 'date_format:H:i', 'after:check_in'],
            'note' => ['required', 'string', 'max:255'],
        ], [
            'check_in.required' => 'Isi jam masuk untuk status Hadir atau Terlambat.',
            'check_out.after' => 'Jam pulang harus setelah jam masuk.',
        ]);

        $service->correct(
            $user,
            $day->toDateString(),
            AttendanceStatus::from($data['status']),
            $data['check_in'] ?? null,
            $data['check_out'] ?? null,
            $data['note'],
            $request->user(),
        );

        return redirect()->route('employees.show', ['user' => $user, 'bulan' => $day->format('Y-m')])
            ->with('success', "Presensi {$user->name} pada {$day->translatedFormat('l, d F Y')} dikoreksi.");
    }

    private function parseDate(string $date): CarbonImmutable
    {
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) !== false, 404);

        $day = CarbonImmutable::parse($date)->startOfDay();
        abort_if($day->isFuture(), 404);

        return $day;
    }
}
