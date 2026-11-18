<?php

namespace App\Http\Controllers;

use App\Exceptions\PresenceException;
use App\Models\Attendance;
use App\Services\AttendanceService;
use App\Support\Location;
use App\Support\SchoolSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Presensi masuk & pulang dari HP masing-masing.
 */
class PresenceController extends Controller
{
    public function show(Request $request, AttendanceService $service, SchoolSettings $settings): View
    {
        $user = $request->user();
        $today = now();

        return view('presence.show', [
            'user' => $user,
            'attendance' => $service->todayFor($user),
            'dayOff' => $service->dayOffReason($today),
            'workStart' => $settings->workStartFor($user),
            'workEnd' => $settings->workEndFor($user),
            'tolerance' => $settings->lateTolerance(),
            'opens' => $settings->checkInOpens(),
            'settings' => $settings,
            'recent' => Attendance::where('user_id', $user->id)
                ->where('date', '<', $today->toDateString())
                ->orderByDesc('date')
                ->limit(5)
                ->get(),
        ]);
    }

    public function checkIn(Request $request, AttendanceService $service): RedirectResponse
    {
        return $this->record($request, function (Location $location) use ($request, $service) {
            $attendance = $service->checkIn($request->user(), $location);

            return $attendance->late_minutes > 0
                ? 'Presensi masuk tercatat pukul '.$attendance->check_in_at->format('H:i').' · terlambat '.Attendance::minutes($attendance->late_minutes).'.'
                : 'Presensi masuk tercatat pukul '.$attendance->check_in_at->format('H:i').'. Selamat bekerja!';
        });
    }

    public function checkOut(Request $request, AttendanceService $service): RedirectResponse
    {
        return $this->record($request, function (Location $location) use ($request, $service) {
            $attendance = $service->checkOut($request->user(), $location);

            return $attendance->early_minutes > 0
                ? 'Presensi pulang tercatat pukul '.$attendance->check_out_at->format('H:i').' · pulang awal '.Attendance::minutes($attendance->early_minutes).'.'
                : 'Presensi pulang tercatat pukul '.$attendance->check_out_at->format('H:i').'. Terima kasih, hati-hati di jalan!';
        });
    }

    /** @param  callable(Location): string  $action */
    private function record(Request $request, callable $action): RedirectResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:100000'],
        ], [
            'required' => 'Lokasi belum terbaca. Izinkan akses lokasi di browser, tunggu sampai lokasi tampil, lalu coba lagi.',
        ]);

        try {
            $message = $action(Location::fromArray($data));
        } catch (PresenceException $e) {
            return redirect()->route('presence.show')->with('error', $e->getMessage());
        }

        return redirect()->route('presence.show')->with('success', $message);
    }
}
