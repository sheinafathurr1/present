<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi pembaca kartu jaringan memakai token perangkat.
 *
 * Kirim token di header "Authorization: Bearer <token>" atau "X-Device-Token".
 */
class AuthenticateDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $device = Device::findByToken($request->bearerToken() ?? $request->header('X-Device-Token'));

        if ($device === null) {
            return response()->json([
                'ok' => false,
                'result' => 'unauthorized',
                'message' => 'Token perangkat tidak valid.',
                'display' => ['Token salah', 'Cek pengaturan'],
            ], 401);
        }

        if (! $device->is_active) {
            return response()->json([
                'ok' => false,
                'result' => 'device_inactive',
                'message' => 'Perangkat dinonaktifkan oleh admin.',
                'display' => ['Perangkat', 'NONAKTIF'],
            ], 403);
        }

        $request->attributes->set('device', $device);

        return $next($request);
    }
}
