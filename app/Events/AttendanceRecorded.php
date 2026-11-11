<?php

namespace App\Events;

use App\Enums\ScanResult;
use App\Models\Attendance;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dipicu setiap kali presensi masuk/pulang tercatat dari kartu RFID.
 *
 * Belum ada listener bawaan. Event ini titik sambung untuk fitur tambahan,
 * misalnya mengirim notifikasi WhatsApp ke orang tua (lihat README).
 */
class AttendanceRecorded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Attendance $attendance,
        public ScanResult $result,
    ) {}
}
