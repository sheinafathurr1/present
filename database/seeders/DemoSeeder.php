<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\DisabilityType;
use App\Enums\FeedbackMode;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Device;
use App\Models\Holiday;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\SchoolSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Data uji coba lengkap. Otomatis dijalankan oleh `php artisan db:seed` bila
 * APP_ENV=local. Jangan dijalankan di server sekolah yang sudah dipakai.
 *
 * Isi:
 *  - 1 admin + 4 guru (wali kelas), semua berkata sandi "password"
 *  - 4 kelas (SDLB, SMPLB, SMALB) dengan jadwal berbeda
 *  - 25 siswa: berbagai kebutuhan khusus & mode umpan balik, 2 tanpa kartu, 1 nonaktif
 *  - Nomor kartu pendek (1001, 1002, ...) agar mudah diketik saat uji coba tanpa alat
 *  - Riwayat presensi sejak awal bulan lalu (hadir, terlambat, izin, sakit, alpa)
 *  - Tap hari ini lewat aturan presensi yang sebenarnya, termasuk kartu tak dikenal
 *  - 2 hari libur contoh dan 1 pembaca Wi-Fi (token ditampilkan)
 *
 * Ulangi dari awal: php artisan migrate:fresh --seed
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'password';

    /** Kartu tak dikenal yang "ditempel" pagi ini, untuk mencoba alur pendaftaran kartu. */
    public const UNKNOWN_CARD = '9001';

    private const CLASSES = [
        [
            'name' => 'Kelas 1 SDLB-A', 'level' => 'SDLB', 'late_after' => '07:30', 'check_out_start' => '10:30',
            'teacher' => ['Ibu Sari Rahayu', 'guru@sekolah.test'], 'type' => DisabilityType::Tunanetra,
            'students' => [
                ['Aditya Pratama', 'Adit', 'L', 'rajin'],
                ['Nadia Putri Maharani', 'Nadia', 'P', 'biasa'],
                ['Rizky Ramadhan', 'Rizky', 'L', 'telat'],
                ['Salsabila Azzahra', 'Salsa', 'P', 'biasa'],
                ['Fajar Nugroho', 'Fajar', 'L', 'sakit'],
                ['Keisha Ayu Lestari', 'Keisha', 'P', 'biasa', 'autis_tanpa_kartu'],
            ],
        ],
        [
            'name' => 'Kelas 3 SDLB-B', 'level' => 'SDLB', 'late_after' => null, 'check_out_start' => '11:00',
            'teacher' => ['Ibu Dewi Anggraini', 'dewi@sekolah.test'], 'type' => DisabilityType::Tunarungu,
            'students' => [
                ['Bagas Saputra', 'Bagas', 'L', 'rajin'],
                ['Citra Ayu Wulandari', 'Citra', 'P', 'biasa'],
                ['Dimas Prasetyo', 'Dimas', 'L', 'telat'],
                ['Intan Permatasari', 'Intan', 'P', 'rajin'],
                ['Galih Wicaksono', 'Galih', 'L', 'biasa', 'ganda'],
                ['Putri Anjani', 'Putri', 'P', 'biasa', 'tanpa_kartu'],
            ],
        ],
        [
            'name' => 'Kelas 8 SMPLB-C', 'level' => 'SMPLB', 'late_after' => null, 'check_out_start' => null,
            'teacher' => ['Bapak Agus Santoso', 'agus@sekolah.test'], 'type' => DisabilityType::TunagrahitaRingan,
            'students' => [
                ['Yusuf Hidayat', 'Yusuf', 'L', 'biasa'],
                ['Annisa Rahmadani', 'Annisa', 'P', 'rajin'],
                ['Bayu Firmansyah', 'Bayu', 'L', 'telat'],
                ['Dinda Kusuma', 'Dinda', 'P', 'biasa', 'sedang'],
                ['Rafi Maulana', 'Rafi', 'L', 'sakit'],
                ['Siti Aisyah', 'Aisyah', 'P', 'rajin'],
            ],
        ],
        [
            'name' => 'Kelas 11 SMALB-D', 'level' => 'SMALB', 'late_after' => null, 'check_out_start' => null,
            'teacher' => ['Ibu Rina Marlina', 'rina@sekolah.test'], 'type' => DisabilityType::Tunadaksa,
            'students' => [
                ['Muhammad Iqbal', 'Iqbal', 'L', 'biasa'],
                ['Zahra Amelia', 'Zahra', 'P', 'rajin'],
                ['Arif Setiawan', 'Arif', 'L', 'telat'],
                ['Laila Fitriani', 'Laila', 'P', 'biasa'],
                ['Hendra Kurniawan', 'Hendra', 'L', 'biasa'],
                ['Wulan Sari Dewi', 'Wulan', 'P', 'rajin'],
                ['Taufik Hidayatullah', 'Taufik', 'L', 'biasa', 'lulus'],
            ],
        ],
    ];

    /** Peluang status per "kebiasaan" siswa (hadir, terlambat, izin, sakit, alpa), total 100. */
    private const PROFILES = [
        'rajin' => [95, 2, 2, 1, 0],
        'biasa' => [84, 7, 4, 3, 2],
        'telat' => [66, 28, 2, 2, 2],
        'sakit' => [78, 5, 3, 12, 2],
    ];

    public function run(SchoolSettings $settings, AttendanceService $attendance): void
    {
        if (Student::exists()) {
            $this->command?->warn('Data siswa sudah ada — data uji coba tidak ditambahkan lagi.');
            $this->command?->line('  Untuk mengulang dari awal: php artisan migrate:fresh --seed');

            return;
        }

        mt_srand(2026);
        fake()->seed(2026);

        DatabaseSeeder::ensureAdmin(self::PASSWORD, $this->command);

        $today = CarbonImmutable::today();
        $this->seedHolidays($today);

        [$classes, $students] = $this->seedClassesAndStudents();
        $this->seedHistory($students, $classes, $settings, $today);
        $this->seedToday($students, $classes, $settings, $attendance, $today);
        $token = $this->seedDevice();

        $this->report($students, $token);
    }

    private function seedHolidays(CarbonImmutable $today): void
    {
        // Satu hari libur di bulan lalu dan satu di bulan depan.
        $lastMonth = $today->subMonthNoOverflow()->startOfMonth()->addDays(14);
        while (! $lastMonth->isWednesday()) {
            $lastMonth = $lastMonth->addDay();
        }

        $nextMonth = $today->addMonthNoOverflow()->startOfMonth();
        while (! $nextMonth->isMonday()) {
            $nextMonth = $nextMonth->addDay();
        }

        Holiday::create(['date' => $lastMonth->toDateString(), 'name' => 'Rapat guru (contoh)']);
        Holiday::create(['date' => $nextMonth->toDateString(), 'name' => 'Libur contoh']);
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function seedClassesAndStudents(): array
    {
        $classes = [];
        $students = [];
        $nis = 2024001;
        $card = 1001;

        foreach (self::CLASSES as $data) {
            [$teacherName, $teacherEmail] = $data['teacher'];

            $teacher = User::create([
                'name' => $teacherName,
                'email' => $teacherEmail,
                'password' => self::PASSWORD,
                'role' => UserRole::Guru,
            ]);

            $class = SchoolClass::create([
                'name' => $data['name'],
                'level' => $data['level'],
                'homeroom_teacher_id' => $teacher->id,
                'late_after' => $data['late_after'],
                'check_out_start' => $data['check_out_start'],
            ]);

            $classes[$class->id] = ['model' => $class, 'teacher' => $teacher];

            foreach ($data['students'] as $row) {
                [$name, $nickname, $gender, $profile] = $row;
                $flag = $row[4] ?? null;

                $type = match ($flag) {
                    'autis_tanpa_kartu' => DisabilityType::Autis,
                    'ganda' => DisabilityType::Ganda,
                    'sedang' => DisabilityType::TunagrahitaSedang,
                    default => $data['type'],
                };

                $hasCard = ! in_array($flag, ['autis_tanpa_kartu', 'tanpa_kartu', 'lulus'], true);

                $student = Student::create([
                    'nis' => (string) $nis++,
                    'nisn' => '00'.fake()->numerify('########'),
                    'name' => $name,
                    'nickname' => $nickname,
                    'gender' => $gender,
                    'school_class_id' => $class->id,
                    'disability_type' => $type,
                    'feedback_mode' => $flag === 'autis_tanpa_kartu' ? FeedbackMode::Visual : $type->suggestedFeedback(),
                    'rfid_uid' => $hasCard ? (string) $card++ : null,
                    'guardian_name' => fake()->firstName($gender === 'L' ? 'female' : 'male').' '.fake()->lastName(),
                    'guardian_phone' => '08'.fake()->numerify('##########'),
                    'notes' => $this->noteFor($type, $flag),
                    'is_active' => $flag !== 'lulus',
                ]);

                $students[] = ['model' => $student, 'profile' => $profile, 'class_id' => $class->id];
            }
        }

        return [$classes, $students];
    }

    private function noteFor(DisabilityType $type, ?string $flag): ?string
    {
        return match (true) {
            $flag === 'lulus' => 'Sudah lulus — data disimpan sebagai arsip.',
            $flag === 'autis_tanpa_kartu' => 'Mudah kaget dengan suara keras. Kartu sedang dipesan.',
            $flag === 'tanpa_kartu' => 'Kartu hilang, menunggu kartu pengganti.',
            $type === DisabilityType::Tunanetra => 'Dipandu pendamping ke meja guru saat tap kartu.',
            $type === DisabilityType::Tunadaksa => 'Pengguna kursi roda; pembaca kartu diletakkan di tepi meja.',
            default => null,
        };
    }

    /**
     * Riwayat dari awal bulan lalu sampai kemarin, dimasukkan sekaligus agar cepat.
     *
     * @param  list<array<string, mixed>>  $students
     * @param  array<int, array<string, mixed>>  $classes
     */
    private function seedHistory(array $students, array $classes, SchoolSettings $settings, CarbonImmutable $today): void
    {
        $rows = [];
        $now = now();

        for ($date = $today->subMonthNoOverflow()->startOfMonth(); $date->lt($today); $date = $date->addDay()) {
            if (! $settings->isSchoolDay($date)) {
                continue;
            }

            foreach ($students as $entry) {
                /** @var Student $student */
                $student = $entry['model'];

                if (! $student->is_active) {
                    continue;
                }

                $class = $classes[$entry['class_id']];
                $status = $this->pickStatus($entry['profile']);
                $isPresent = $status->isPresent();

                $lateAfter = $settings->lateAfter($class['model']);
                $checkOutStart = $settings->checkOutStart($class['model']);

                $checkIn = null;
                $checkOut = null;
                if ($isPresent) {
                    $checkIn = $status === AttendanceStatus::Terlambat
                        ? $date->setTimeFromTimeString($lateAfter)->addMinutes(mt_rand(1, 40))
                        : $date->setTime(6, 20)->addMinutes(mt_rand(0, 50));
                    $checkOut = $date->setTimeFromTimeString($checkOutStart)->addMinutes(mt_rand(0, 35));
                }

                $manual = in_array($status, [AttendanceStatus::Izin, AttendanceStatus::Sakit], true) || $student->rfid_uid === null;

                $rows[] = [
                    'student_id' => $student->id,
                    'date' => $date->toDateString(),
                    'status' => $status->value,
                    'check_in_at' => $checkIn?->toDateTimeString(),
                    'check_out_at' => $checkOut?->toDateTimeString(),
                    'source' => $status === AttendanceStatus::Alpa
                        ? Attendance::SOURCE_SYSTEM
                        : ($manual ? Attendance::SOURCE_MANUAL : Attendance::SOURCE_RFID),
                    'note' => match ($status) {
                        AttendanceStatus::Sakit => fake()->randomElement(['Demam', 'Batuk pilek', 'Sakit perut', 'Kontrol ke dokter']),
                        AttendanceStatus::Izin => fake()->randomElement(['Acara keluarga', 'Terapi wicara', 'Urusan keluarga']),
                        default => null,
                    },
                    'recorded_by' => $manual || $status === AttendanceStatus::Alpa ? $class['teacher']->id : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            Attendance::insert($chunk);
        }
    }

    private function pickStatus(string $profile): AttendanceStatus
    {
        [$hadir, $terlambat, $izin, $sakit] = self::PROFILES[$profile];
        $roll = mt_rand(1, 100);

        return match (true) {
            $roll <= $hadir => AttendanceStatus::Hadir,
            $roll <= $hadir + $terlambat => AttendanceStatus::Terlambat,
            $roll <= $hadir + $terlambat + $izin => AttendanceStatus::Izin,
            $roll <= $hadir + $terlambat + $izin + $sakit => AttendanceStatus::Sakit,
            default => AttendanceStatus::Alpa,
        };
    }

    /**
     * Pagi ini: sebagian siswa sudah menempelkan kartu di PC wali kelasnya, lewat
     * aturan presensi yang sebenarnya. Dua siswa per kelas sengaja dibiarkan
     * belum tap agar bisa dicoba sendiri.
     *
     * @param  list<array<string, mixed>>  $students
     * @param  array<int, array<string, mixed>>  $classes
     * @return list<int> id siswa yang sudah tap hari ini
     */
    private function seedToday(array $students, array $classes, SchoolSettings $settings, AttendanceService $attendance, CarbonImmutable $today): array
    {
        if (! $settings->isSchoolDay($today)) {
            return [];
        }

        $now = now();
        $tapped = [];
        $byClass = collect($students)->groupBy('class_id');

        foreach ($byClass as $classId => $entries) {
            $teacher = $classes[$classId]['teacher'];
            $withCard = $entries->filter(fn ($entry) => $entry['model']->rfid_uid && $entry['model']->is_active)->values();

            // Dua siswa terakhir yang punya kartu dibiarkan belum tap.
            $toTap = $withCard->slice(0, max(0, $withCard->count() - 2));

            foreach ($toTap as $index => $entry) {
                // Siswa terakhir yang tap di tiap kelas datang agak siang (bisa tercatat terlambat).
                $at = $index === $toTap->count() - 1
                    ? $today->setTime(7, 20)->addMinutes(mt_rand(0, 20))
                    : $today->setTime(6, 25)->addMinutes(($index * 13 + mt_rand(0, 9)) % 50);

                if ($at->gt($now)) {
                    continue;
                }

                $attendance->handleTap($entry['model']->rfid_uid, null, $at, $teacher);
                $tapped[] = $entry['model']->id;

                // Satu siswa menempelkan kartunya dua kali → tercatat "Tempel ulang".
                if ($index === 0 && $at->addSeconds(20)->lte($now)) {
                    $attendance->handleTap($entry['model']->rfid_uid, null, $at->addSeconds(20), $teacher);
                }
            }
        }

        $firstTeacher = reset($classes)['teacher'];
        $unknownAt = $today->setTime(7, 5);
        if ($unknownAt->lte($now)) {
            $attendance->handleTap(self::UNKNOWN_CARD, null, $unknownAt, $firstTeacher);
        }

        // Satu siswa izin sakit hari ini, dicatat wali kelasnya.
        $sick = collect($students)->first(fn ($entry) => $entry['profile'] === 'sakit' && ! in_array($entry['model']->id, $tapped, true));
        if ($sick) {
            $attendance->recordManual($sick['model'], $today->toDateString(), AttendanceStatus::Sakit, 'Demam, kabar dari orang tua', $classes[$sick['class_id']]['teacher']);
        }

        return $tapped;
    }

    private function seedDevice(): string
    {
        return Device::create(['name' => 'Pintu Asrama', 'location' => 'Gedung asrama (contoh)'])->issueToken();
    }

    /**
     * @param  list<array<string, mixed>>  $students
     */
    private function report(array $students, string $token): void
    {
        if (! $this->command) {
            return;
        }

        $this->command->getOutput()->writeln('');
        $this->command->info('Data uji coba dibuat. Semua akun memakai kata sandi: '.self::PASSWORD);

        $this->command->table(['Peran', 'Nama', 'Email'], array_merge(
            [['Admin', 'Administrator', env('ADMIN_EMAIL', 'admin@sekolah.test')]],
            array_map(fn (array $class) => ['Guru', $class['teacher'][0], $class['teacher'][1]], self::CLASSES),
        ));

        $recordedToday = Attendance::where('date', today()->toDateString())->pluck('student_id')->all();

        $this->command->info('Kartu yang BELUM tercatat hari ini — ketik nomornya lalu Enter di dashboard untuk mencoba:');
        $this->command->table(['No. kartu', 'Siswa', 'Kelas', 'Saat tap'], collect($students)
            ->filter(fn ($entry) => $entry['model']->rfid_uid && $entry['model']->is_active
                && ! in_array($entry['model']->id, $recordedToday, true))
            ->map(fn ($entry) => [
                $entry['model']->rfid_uid,
                $entry['model']->name,
                $entry['model']->schoolClass->name,
                $entry['model']->feedback_mode->label(),
            ])
            ->values()
            ->all());

        $this->command->line('  Kartu tak dikenal untuk mencoba pendaftaran kartu: '.self::UNKNOWN_CARD);
        $this->command->line('  Token pembaca Wi-Fi "Pintu Asrama" (untuk uji API): '.$token);
        $this->command->getOutput()->writeln('');
    }
}
