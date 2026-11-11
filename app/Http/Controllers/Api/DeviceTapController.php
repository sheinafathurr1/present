<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTapController extends Controller
{
    /**
     * Terima tap kartu dari pembaca jaringan.
     *
     * Selalu membalas HTTP 200 untuk tap yang berhasil diproses (termasuk
     * kartu tidak dikenal), sehingga perangkat cukup membaca field "tone"
     * dan "display" untuk menyalakan buzzer/LED/LCD.
     */
    public function store(Request $request, AttendanceService $attendance): JsonResponse
    {
        $data = $request->validate([
            'uid' => ['required', 'string', 'max:64', 'regex:/[A-Za-z0-9]/'],
        ]);

        /** @var Device $device */
        $device = $request->attributes->get('device');

        return response()->json($attendance->handleTap($data['uid'], $device)->toArray());
    }

    public function ping(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $device->forceFill(['last_seen_at' => now()])->saveQuietly();

        return response()->json([
            'ok' => true,
            'device' => $device->name,
            'server_time' => now()->toIso8601String(),
            'display' => ['Terhubung', now()->format('H:i')],
        ]);
    }
}
