<?php

use App\Http\Controllers\Api\DeviceTapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API untuk pembaca kartu jaringan (ESP32 / ESP8266 / Raspberry Pi)
|--------------------------------------------------------------------------
|
| Semua endpoint memakai token perangkat (menu Perangkat di dashboard).
|
|   POST /api/v1/taps   {"uid": "A1B2C3D4"}   → catat tap kartu
|   GET  /api/v1/ping                          → cek koneksi & jam server
|
*/

Route::prefix('v1')->middleware(['device', 'throttle:device'])->group(function () {
    Route::post('taps', [DeviceTapController::class, 'store'])->name('api.taps.store');
    Route::get('ping', [DeviceTapController::class, 'ping'])->name('api.ping');
});
