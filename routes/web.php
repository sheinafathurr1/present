<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScanLogController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\TapController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect(auth()->user()->homeRoute())
    : redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:login');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // Guru & admin.
    Route::middleware('role:admin,guru')->group(function () {
        // Tap kartu dari pembaca USB yang dicolok ke PC guru.
        Route::post('tap', [TapController::class, 'store'])->middleware('throttle:tap')->name('tap.store');
        Route::get('heartbeat', [TapController::class, 'heartbeat'])->name('heartbeat');

        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboard/live', [DashboardController::class, 'live'])->name('dashboard.live');

        Route::get('presensi', [AttendanceController::class, 'index'])->name('attendances.index');
        Route::post('presensi', [AttendanceController::class, 'store'])->name('attendances.store');
        Route::post('presensi/tandai-alpa', [AttendanceController::class, 'markAbsent'])->name('attendances.mark-absent');

        Route::get('rekap', [ReportController::class, 'monthly'])->name('reports.monthly');
        Route::get('rekap/unduh', [ReportController::class, 'export'])->name('reports.export');

        Route::get('log-scan', [ScanLogController::class, 'index'])->name('scans.index');

        Route::get('siswa', [StudentController::class, 'index'])->name('students.index');
    });

    // Khusus admin.
    Route::middleware('role:admin')->group(function () {
        Route::get('siswa/impor', [StudentImportController::class, 'create'])->name('students.import');
        Route::post('siswa/impor', [StudentImportController::class, 'store'])->name('students.import.store');
        Route::get('siswa/impor/template', [StudentImportController::class, 'template'])->name('students.import.template');
        Route::resource('siswa', StudentController::class)
            ->except(['index', 'show'])
            ->parameters(['siswa' => 'student'])
            ->names('students');

        Route::resource('kelas', SchoolClassController::class)
            ->except('show')
            ->parameters(['kelas' => 'schoolClass'])
            ->names('classes');

        Route::resource('perangkat', DeviceController::class)
            ->except('show')
            ->parameters(['perangkat' => 'device'])
            ->names('devices');
        Route::post('perangkat/{device}/token', [DeviceController::class, 'regenerateToken'])->name('devices.token');

        Route::get('hari-libur', [HolidayController::class, 'index'])->name('holidays.index');
        Route::post('hari-libur', [HolidayController::class, 'store'])->name('holidays.store');
        Route::post('hari-libur/perbarui', [HolidayController::class, 'sync'])->name('holidays.sync');
        Route::patch('hari-libur/{holiday}/masuk', [HolidayController::class, 'toggle'])->name('holidays.toggle');
        Route::delete('hari-libur/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');

        Route::resource('pengguna', UserController::class)
            ->except('show')
            ->parameters(['pengguna' => 'user'])
            ->names('users');

        Route::get('pengaturan', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('pengaturan', [SettingController::class, 'update'])->name('settings.update');
    });

    // Detail siswa (guru & admin) — didaftarkan setelah "siswa/create" agar tidak bentrok.
    Route::get('siswa/{student}', [StudentController::class, 'show'])
        ->middleware('role:admin,guru')
        ->name('students.show');
});
