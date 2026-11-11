<?php

use Illuminate\Support\Facades\Schedule;

/*
| Jadwal otomatis. Agar berjalan, server perlu memanggil
| `php artisan schedule:run` setiap menit (lihat README).
*/

Schedule::command('presensi:tandai-alpa')
    ->dailyAt(config('presensi.absent_marking_time', '16:00'))
    ->withoutOverlapping();

// Hapus log scan kartu yang lebih lama dari PRESENSI_SCAN_RETENTION_DAYS.
Schedule::command('model:prune')->dailyAt('01:00');
