<?php

namespace App\Console\Commands;

use App\Services\NationalHolidaySync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('presensi:sinkron-libur {--tahun=* : Tahun yang diperbarui, bawaan tahun ini dan tahun depan}')]
#[Description('Ambil libur nasional & cuti bersama Indonesia (butuh internet; tanpa internet memakai data bawaan)')]
class SyncHolidaysCommand extends Command
{
    public function handle(NationalHolidaySync $sync): int
    {
        $years = array_map('intval', $this->option('tahun')) ?: [now()->year, now()->year + 1];
        $result = $sync->sync($years);

        if ($result['source'] === NationalHolidaySync::SOURCE_INTERNET) {
            $this->components->info(sprintf(
                'Libur nasional %s diperbarui dari internet: %d baru, %d diubah, %d dihapus.',
                implode(' & ', $years), $result['added'], $result['updated'], $result['removed'],
            ));
        } else {
            $this->components->warn('Tidak bisa mengambil kalender libur dari internet: '.$result['error']);
            $this->components->info(sprintf('Memakai data bawaan (libur bertanggal tetap): %d ditambahkan.', $result['added']));
        }

        return self::SUCCESS;
    }
}
