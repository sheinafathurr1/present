<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AttendanceCorrectionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeImportController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\PresenceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
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

    // Semua guru & staf (termasuk pimpinan): presensi dari HP, riwayat, izin, akun.
    Route::get('presensi', [PresenceController::class, 'show'])->name('presence.show');
    Route::post('presensi/masuk', [PresenceController::class, 'checkIn'])->middleware('throttle:presence')->name('presence.check-in');
    Route::post('presensi/pulang', [PresenceController::class, 'checkOut'])->middleware('throttle:presence')->name('presence.check-out');

    Route::get('riwayat', [HistoryController::class, 'index'])->name('history.index');
    Route::get('riwayat/unduh', [ExportController::class, 'own'])->name('history.export');

    Route::get('izin', [LeaveRequestController::class, 'index'])->name('leaves.index');
    Route::get('izin/ajukan', [LeaveRequestController::class, 'create'])->name('leaves.create');
    Route::post('izin', [LeaveRequestController::class, 'store'])->middleware('throttle:presence')->name('leaves.store');
    Route::delete('izin/{leave}', [LeaveRequestController::class, 'destroy'])->name('leaves.destroy');
    Route::get('izin/{leave}/lampiran', [LeaveRequestController::class, 'attachment'])->name('leaves.attachment');

    Route::get('akun', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('akun/kata-sandi', [AccountController::class, 'updatePassword'])->name('account.password');

    // Khusus admin: kelola data. Didaftarkan sebelum /pegawai/{user} agar "tambah" tidak dianggap id.
    Route::middleware('role:admin')->group(function () {
        Route::get('pegawai/impor', [EmployeeImportController::class, 'create'])->name('employees.import');
        Route::get('pegawai/impor/template', [EmployeeImportController::class, 'template'])->name('employees.import.template');
        Route::post('pegawai/impor', [EmployeeImportController::class, 'preview'])->name('employees.import.preview');
        Route::post('pegawai/impor/simpan', [EmployeeImportController::class, 'store'])->name('employees.import.store');

        Route::get('pegawai/tambah', [EmployeeController::class, 'create'])->name('employees.create');
        Route::post('pegawai', [EmployeeController::class, 'store'])->name('employees.store');
        Route::get('pegawai/{user}/ubah', [EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('pegawai/{user}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::delete('pegawai/{user}', [EmployeeController::class, 'destroy'])->name('employees.destroy');

        Route::get('pegawai/{user}/koreksi/{date}', [AttendanceCorrectionController::class, 'edit'])->name('corrections.edit');
        Route::put('pegawai/{user}/koreksi/{date}', [AttendanceCorrectionController::class, 'update'])->name('corrections.update');

        Route::get('hari-libur', [HolidayController::class, 'index'])->name('holidays.index');
        Route::post('hari-libur', [HolidayController::class, 'store'])->name('holidays.store');
        Route::post('hari-libur/perbarui', [HolidayController::class, 'sync'])->name('holidays.sync');
        Route::patch('hari-libur/{holiday}/masuk', [HolidayController::class, 'toggle'])->name('holidays.toggle');
        Route::delete('hari-libur/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');

        Route::get('pengaturan', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('pengaturan', [SettingController::class, 'update'])->name('settings.update');
    });

    // Pimpinan (kepala sekolah, wakil, bendahara) & admin: memantau, hanya melihat.
    Route::middleware('role:admin,pimpinan')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('rekap', [ReportController::class, 'monthly'])->name('reports.monthly');
        Route::get('rekap/unduh', [ReportController::class, 'export'])->name('reports.export');

        Route::get('pengajuan', [LeaveRequestController::class, 'monitor'])->name('leaves.monitor');

        Route::get('ekspor', [ExportController::class, 'create'])->name('exports.create');
        Route::get('ekspor/unduh', [ExportController::class, 'download'])->name('exports.download');

        Route::get('pegawai', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('pegawai/{user}', [EmployeeController::class, 'show'])->name('employees.show');
    });
});
