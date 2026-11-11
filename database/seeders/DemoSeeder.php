<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\DisabilityType;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Device;
use App\Models\Holiday;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Support\SchoolSettings;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk mencoba aplikasi. Jangan dijalankan di server produksi.
 *
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(SchoolSettings $settings): void
    {
        $this->call(DatabaseSeeder::class);

        $guru = User::firstOrCreate(['email' => 'guru@sekolah.test'], [
            'name' => 'Ibu Sari Rahayu', 'password' => 'password', 'role' => UserRole::Guru,
        ]);
        User::firstOrCreate(['email' => 'kiosk@sekolah.test'], [
            'name' => 'Kiosk Gerbang', 'password' => 'password', 'role' => UserRole::Kiosk,
        ]);

        $classes = collect([
            ['name' => 'Kelas 1 SDLB-A', 'level' => 'SDLB', 'type' => DisabilityType::Tunanetra, 'check_out_start' => '10:30'],
            ['name' => 'Kelas 3 SDLB-B', 'level' => 'SDLB', 'type' => DisabilityType::Tunarungu, 'check_out_start' => '11:00'],
            ['name' => 'Kelas 8 SMPLB-C', 'level' => 'SMPLB', 'type' => DisabilityType::TunagrahitaRingan, 'check_out_start' => null],
            ['name' => 'Kelas 11 SMALB-D', 'level' => 'SMALB', 'type' => DisabilityType::Tunadaksa, 'check_out_start' => null],
        ])->map(function (array $data) use ($guru) {
            $class = SchoolClass::firstOrCreate(['name' => $data['name']], [
                'level' => $data['level'],
                'homeroom_teacher_id' => $guru->id,
                'check_out_start' => $data['check_out_start'],
            ]);

            if (! $class->students()->exists()) {
                Student::factory()->count(6)->create([
                    'school_class_id' => $class->id,
                    'disability_type' => $data['type'],
                    'feedback_mode' => $data['type']->suggestedFeedback(),
                ]);
            }

            return $class;
        });

        Student::factory()->withoutCard()->create(['school_class_id' => $classes->first()->id, 'disability_type' => DisabilityType::Autis]);

        Device::firstOrCreate(['name' => 'Pintu Asrama'], ['type' => Device::TYPE_NETWORK, 'location' => 'Gedung asrama']);
        Holiday::firstOrCreate(['date' => now()->startOfMonth()->addDays(16)->toDateString()], ['name' => 'Contoh hari libur']);

        // Riwayat presensi 3 minggu terakhir (tanpa hari ini).
        $device = Device::where('type', Device::TYPE_USB)->first();
        $students = Student::active()->whereNotNull('rfid_uid')->get();

        for ($date = today()->subDays(21); $date->lt(today()); $date->addDay()) {
            if (! $settings->isSchoolDay($date)) {
                continue;
            }

            foreach ($students as $student) {
                $roll = random_int(1, 100);
                $status = match (true) {
                    $roll <= 78 => AttendanceStatus::Hadir,
                    $roll <= 88 => AttendanceStatus::Terlambat,
                    $roll <= 93 => AttendanceStatus::Sakit,
                    $roll <= 97 => AttendanceStatus::Izin,
                    default => AttendanceStatus::Alpa,
                };

                $present = $status->isPresent();
                $checkIn = $date->copy()->setTime(6, random_int(20, 59));
                if ($status === AttendanceStatus::Terlambat) {
                    $checkIn = $date->copy()->setTime(7, random_int(16, 50));
                }

                Attendance::firstOrCreate(['student_id' => $student->id, 'date' => $date->toDateString()], [
                    'status' => $status,
                    'check_in_at' => $present ? $checkIn : null,
                    'check_out_at' => $present ? $date->copy()->setTime(12, random_int(0, 40)) : null,
                    'check_in_device_id' => $present ? $device?->id : null,
                    'check_out_device_id' => $present ? $device?->id : null,
                    'source' => $present ? Attendance::SOURCE_RFID : ($status === AttendanceStatus::Alpa ? Attendance::SOURCE_SYSTEM : Attendance::SOURCE_MANUAL),
                    'note' => match ($status) {
                        AttendanceStatus::Sakit => 'Demam, surat dari orang tua',
                        AttendanceStatus::Izin => 'Kontrol ke dokter',
                        default => null,
                    },
                    'recorded_by' => $present ? null : $guru->id,
                ]);
            }
        }

        $this->command?->info('Data contoh dibuat. Akun guru & kiosk: guru@sekolah.test / kiosk@sekolah.test (sandi: password).');
    }
}
