<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Device;
use App\Models\Student;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KioskController extends Controller
{
    /** Pilih perangkat yang akan dipakai komputer ini. */
    public function index(): View
    {
        $devices = Device::where('type', Device::TYPE_USB)->where('is_active', true)->orderBy('name')->get();

        return view('kiosk.index', compact('devices'));
    }

    public function show(Device $device): View
    {
        abort_unless($device->is_active, 404);

        return view('kiosk.show', [
            'device' => $device,
            'stats' => $this->todayStats(),
        ]);
    }

    public function tap(Request $request, Device $device, AttendanceService $attendance): JsonResponse
    {
        abort_unless($device->is_active, 404);

        $data = $request->validate([
            'uid' => ['required', 'string', 'max:64', 'regex:/[A-Za-z0-9]/'],
        ]);

        return response()->json([
            ...$attendance->handleTap($data['uid'], $device)->toArray(),
            'stats' => $this->todayStats(),
        ]);
    }

    /** Dipanggil berkala oleh layar kiosk: menjaga sesi tetap aktif & memperbarui angka. */
    public function stats(Device $device): JsonResponse
    {
        $device->forceFill(['last_seen_at' => now()])->saveQuietly();

        return response()->json(['stats' => $this->todayStats()]);
    }

    /** @return array{present: int, total: int} */
    private function todayStats(): array
    {
        return [
            'present' => Attendance::where('date', today()->toDateString())
                ->whereIn('status', [AttendanceStatus::Hadir, AttendanceStatus::Terlambat])
                ->count(),
            'total' => Student::active()->count(),
        ];
    }
}
