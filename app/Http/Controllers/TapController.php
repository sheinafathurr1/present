<?php

namespace App\Http\Controllers;

use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Tap kartu dari pembaca USB yang dicolok ke PC guru.
 *
 * Pembaca USB bekerja seperti keyboard: saat kartu ditempel ia "mengetik"
 * nomor kartu lalu menekan Enter. public/js/card-reader.js menangkap ketikan
 * itu di halaman dashboard mana pun yang sedang dibuka guru, lalu mengirimnya
 * ke sini.
 */
class TapController extends Controller
{
    public function store(Request $request, AttendanceService $attendance): JsonResponse
    {
        $data = $request->validate([
            'uid' => ['required', 'string', 'max:64', 'regex:/[A-Za-z0-9]/'],
        ]);

        $outcome = $attendance->handleTap($data['uid'], user: $request->user());

        return response()->json($outcome->toArray());
    }

    /** Dipanggil berkala oleh halaman yang terbuka agar sesi login tidak habis. */
    public function heartbeat(): Response
    {
        return response()->noContent();
    }
}
