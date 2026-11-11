<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi teknis presensi
|--------------------------------------------------------------------------
|
| Pengaturan yang sering diubah pihak sekolah (jam masuk, batas terlambat,
| jam pulang, hari sekolah, dan lain-lain) disimpan di database dan dapat
| diubah lewat menu "Pengaturan" di dashboard. File ini hanya berisi nilai
| teknis yang biasanya diatur sekali oleh admin server melalui file .env.
|
*/

return [

    // Jam scheduler menandai siswa yang belum presensi sebagai "Alpa".
    // Membutuhkan `php artisan schedule:run` yang dipanggil tiap menit
    // (cron di Linux atau Task Scheduler di Windows).
    'absent_marking_time' => env('PRESENSI_ABSENT_MARKING_TIME', '16:00'),

    // Lama log scan kartu disimpan sebelum dihapus otomatis (hari).
    'scan_retention_days' => (int) env('PRESENSI_SCAN_RETENTION_DAYS', 180),

    // Batas permintaan per menit untuk tiap perangkat pembaca kartu.
    'device_rate_limit' => (int) env('PRESENSI_DEVICE_RATE_LIMIT', 120),

    // Nilai bawaan pengaturan sekolah (dipakai sebelum diubah di dashboard).
    'defaults' => [
        'school_name' => env('APP_NAME', 'Presensi SLB'),
        'late_after' => '07:15',
        'check_out_start' => '12:00',
        'tap_cooldown_seconds' => 60,
        'school_days' => [1, 2, 3, 4, 5, 6],
    ],

];
