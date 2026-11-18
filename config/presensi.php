<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi teknis presensi
|--------------------------------------------------------------------------
|
| Pengaturan yang sering diubah pihak sekolah (lokasi & radius sekolah, jam
| kerja guru dan staf, toleransi terlambat, hari sekolah) disimpan di
| database dan diubah lewat menu "Pengaturan". File ini hanya berisi nilai
| teknis yang biasanya diatur sekali oleh admin server melalui file .env.
|
*/

return [

    // Jam scheduler menandai guru/staf yang belum presensi sebagai "Alpa".
    // Membutuhkan `php artisan schedule:run` yang dipanggil tiap menit (cron).
    'absent_marking_time' => env('PRESENSI_ABSENT_MARKING_TIME', '16:00'),

    // Sumber libur nasional & cuti bersama Indonesia (format iCalendar/.ics).
    // Diperbarui otomatis tiap minggu dan bisa juga lewat tombol di menu Hari Libur.
    'holiday_calendar_url' => env(
        'PRESENSI_HOLIDAY_ICS_URL',
        'https://calendar.google.com/calendar/ical/id.indonesian%23holiday%40group.v.calendar.google.com/public/basic.ics',
    ),

    // Pengajuan izin/sakit/cuti/dinas luar dari HP.
    'leave' => [
        // Paling lama berapa hari ke belakang boleh diajukan (mis. sakit kemarin baru sempat lapor).
        'backdate_days' => (int) env('PRESENSI_LEAVE_BACKDATE_DAYS', 7),
        // Paling jauh berapa hari ke depan boleh diajukan.
        'advance_days' => (int) env('PRESENSI_LEAVE_ADVANCE_DAYS', 90),
        // Panjang maksimal satu pengajuan (hari kalender).
        'max_days' => (int) env('PRESENSI_LEAVE_MAX_DAYS', 90),
        // Ukuran maksimal lampiran (KB). Foto dari HP diperkecil otomatis sebelum diunggah.
        'attachment_max_kb' => (int) env('PRESENSI_LEAVE_ATTACHMENT_MAX_KB', 5120),
    ],

    // Nilai bawaan pengaturan sekolah (dipakai sebelum diubah di dashboard).
    'defaults' => [
        'school_name' => env('APP_NAME', 'Presensi Sekolah'),

        // Titik pusat sekolah & radius presensi. Diisi admin di menu Pengaturan.
        'school_lat' => null,
        'school_lng' => null,
        'radius_m' => 100,
        // Bacaan GPS yang lebih kasar dari ini ditolak (meter).
        'max_accuracy_m' => 100,

        // Jam kerja bawaan; bisa diganti per orang di data pegawai.
        'guru_start' => '07:00',
        'guru_end' => '13:00',
        'tendik_start' => '07:00',
        'tendik_end' => '15:00',
        // Datang sampai sekian menit setelah jam masuk masih dihitung "Hadir".
        'late_tolerance_minutes' => 15,
        // Presensi masuk baru bisa dilakukan mulai jam ini.
        'check_in_opens' => '05:00',

        'school_days' => [1, 2, 3, 4, 5, 6],
        'joint_holidays_off' => true,
    ],

];
