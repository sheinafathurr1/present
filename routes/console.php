<?php

use Illuminate\Support\Facades\Schedule;

/*
| Jadwal otomatis. Agar berjalan, server perlu memanggil
| `php artisan schedule:run` setiap menit (lihat README).
*/

// Tandai Alpa guru/staf yang tidak presensi dan tidak mengajukan izin.
Schedule::command('presensi:tandai-alpa')
    ->dailyAt(config('presensi.absent_marking_time', '16:00'))
    ->withoutOverlapping();

// Perbarui libur nasional & cuti bersama (tahun ini dan tahun depan) tiap Senin pagi.
Schedule::command('presensi:sinkron-libur')->weeklyOn(1, '05:30');
