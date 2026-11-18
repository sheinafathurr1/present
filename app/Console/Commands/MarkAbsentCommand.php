<?php

namespace App\Console\Commands;

use App\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('presensi:tandai-alpa {--tanggal= : Tanggal (Y-m-d), bawaan hari ini}')]
#[Description('Tandai guru & staf yang belum presensi atau mengajukan izin sebagai Alpa (dilewati pada hari libur)')]
class MarkAbsentCommand extends Command
{
    public function handle(AttendanceService $service): int
    {
        $date = CarbonImmutable::parse($this->option('tanggal') ?: today()->toDateString());
        $count = $service->markAbsent($date);

        if ($count === null) {
            $this->components->info("{$date->translatedFormat('l, d F Y')} bukan hari sekolah. Tidak ada yang ditandai.");

            return self::SUCCESS;
        }

        $this->components->info("{$count} guru/staf ditandai Alpa untuk {$date->translatedFormat('l, d F Y')}.");

        return self::SUCCESS;
    }
}
