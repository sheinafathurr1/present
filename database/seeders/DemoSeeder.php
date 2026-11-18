<?php

namespace Database\Seeders;

use App\Enums\EmployeeType;
use App\Enums\LeaveType;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\Geo;
use App\Support\Location;
use App\Support\SchoolSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Data uji coba lengkap. Otomatis dijalankan oleh `php artisan db:seed` bila
 * APP_ENV=local. Jangan dijalankan di server sekolah yang sudah dipakai.
 *
 * Isi:
 *  - Admin (operator), kepala sekolah, wakil, bendahara, 9 guru, 3 staf tendik,
 *    dan 1 akun nonaktif. Semua berkata sandi "password".
 *  - Titik sekolah contoh (Jakarta Pusat) dengan radius 100 m.
 *  - Riwayat presensi GPS sejak awal bulan lalu: hadir, terlambat, pulang awal,
 *    lupa presensi pulang, dan alpa — dibuat lewat aturan presensi yang sebenarnya.
 *  - Pengajuan sakit, izin, cuti, dan dinas luar (termasuk hari ini & yang akan datang).
 *  - Presensi hari ini sesuai jam saat seeder dijalankan.
 *
 * Ulangi dari awal: php artisan migrate:fresh --seed
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public const SCHOOL_LAT = -6.2;

    public const SCHOOL_LNG = 106.816666;

    /** username, nama, jenis, jabatan, peran, kebiasaan */
    private const STAFF = [
        ['kepsek', 'Drs. Bambang Supriyadi, M.Pd.', 'guru', 'Kepala Sekolah', 'pimpinan', 'rajin'],
        ['wakasek', 'Sri Wahyuni, S.Pd.', 'guru', 'Wakil Kepala Sekolah', 'pimpinan', 'rajin'],
        ['bendahara', 'Rina Marlina, S.E.', 'tendik', 'Bendahara', 'pimpinan', 'biasa'],
        ['guru', 'Siti Aminah, S.Pd.', 'guru', 'Guru Kelas 1', 'pegawai', 'biasa'],
        ['ahmad.fauzi', 'Ahmad Fauzi, S.Pd.', 'guru', 'Guru Kelas 2', 'pegawai', 'telat'],
        ['dewi.lestari', 'Dewi Lestari, S.Pd.', 'guru', 'Guru Kelas 3', 'pegawai', 'rajin'],
        ['hendra.gunawan', 'Hendra Gunawan, S.Pd.', 'guru', 'Guru Kelas 4', 'pegawai', 'biasa'],
        ['yusuf.maulana', 'Yusuf Maulana, S.Pd.', 'guru', 'Guru Kelas 5', 'pegawai', 'pulang_awal'],
        ['ratna.sari', 'Ratna Sari, S.Pd.', 'guru', 'Guru Kelas 6', 'pegawai', 'biasa'],
        ['nur.hidayah', 'Nur Hidayah, S.Pd.I.', 'guru', 'Guru Pendidikan Agama', 'pegawai', 'biasa'],
        ['agus.setiawan', 'Agus Setiawan, S.Pd.', 'guru', 'Guru PJOK', 'pegawai', 'telat'],
        ['maya.anggraini', 'Maya Anggraini, S.Pd.', 'guru', 'Guru Bahasa Inggris', 'pegawai', 'biasa'],
        ['tendik', 'Joko Susilo', 'tendik', 'Staf Tata Usaha', 'pegawai', 'biasa'],
        ['lina.kurniasih', 'Lina Kurniasih', 'tendik', 'Pustakawan', 'pegawai', 'rajin'],
        ['slamet', 'Slamet Riyadi', 'tendik', 'Penjaga Sekolah', 'pegawai', 'rajin'],
        ['budi.hartono', 'Budi Hartono, S.Pd.', 'guru', 'Guru Kelas 4 (sudah pindah tugas)', 'pegawai', 'nonaktif'],
    ];

    /** Yang sengaja belum presensi hari ini, untuk dilihat di dashboard. */
    private const NOT_YET_TODAY = ['yusuf.maulana', 'maya.anggraini'];

    /** @var array<string, User> */
    private array $users = [];

    private SchoolSettings $settings;

    private AttendanceService $service;

    public function run(SchoolSettings $settings, AttendanceService $service): void
    {
        if (User::where('username', 'kepsek')->exists()) {
            $this->command?->warn('Data uji coba sudah ada — tidak ditambahkan lagi.');
            $this->command?->line('  Untuk mengulang dari awal: php artisan migrate:fresh --seed');

            return;
        }

        $this->settings = $settings;
        $this->service = $service;
        mt_srand(2026);

        DatabaseSeeder::ensureAdmin(self::PASSWORD, $this->command);

        if (! $settings->hasLocation()) {
            $settings->update([
                'school_name' => 'SD Negeri Contoh 01',
                'school_lat' => self::SCHOOL_LAT,
                'school_lng' => self::SCHOOL_LNG,
                'radius_m' => 100,
            ]);
        }

        $today = CarbonImmutable::today();
        $from = $today->subMonthNoOverflow()->startOfMonth();

        $this->seedHoliday($today);
        $this->seedUsers($from);
        $this->seedLeaves($from, $today);
        $this->seedHistory($from, $today);
        $this->seedToday($today);

        $this->report();
    }

    private function seedHoliday(CarbonImmutable $today): void
    {
        $date = $today->subMonthNoOverflow()->startOfMonth()->addDays(14);
        while (! $date->isWednesday()) {
            $date = $date->addDay();
        }

        Holiday::firstOrCreate(['date' => $date->toDateString()], ['name' => 'Libur sekolah (contoh)']);
    }

    private function seedUsers(CarbonImmutable $from): void
    {
        $nip = 198001012005011001;

        foreach (self::STAFF as [$username, $name, $type, $position, $role, $profile]) {
            $user = User::create([
                'name' => $name,
                'username' => $username,
                'password' => self::PASSWORD,
                'role' => UserRole::from($role),
                'employee_type' => EmployeeType::from($type),
                'position' => $position,
                'nip' => $type === 'guru' ? (string) $nip++ : null,
                'phone' => '0812'.mt_rand(10000000, 99999999),
                'is_active' => $profile !== 'nonaktif',
            ]);
            // Seolah sudah terdaftar sebelum riwayat dimulai.
            $user->forceFill(['created_at' => $from->subDays(30), 'updated_at' => $from->subDays(30)])->saveQuietly();

            $this->users[$username] = $user;
        }

        // Penjaga sekolah punya jam kerja khusus.
        $this->users['slamet']->update(['work_start' => '06:00', 'work_end' => '14:00']);
    }

    private function seedLeaves(CarbonImmutable $from, CarbonImmutable $today): void
    {
        $pastDays = $this->service->schoolDaysBetween($from, $today->subDay());
        $at = fn (int $index) => CarbonImmutable::parse($pastDays[min(count($pastDays) - 1, max(0, $index))]);

        // Sakit 3 hari di bulan lalu, diajukan pagi hari pertama.
        $start = $at(7);
        $this->leave('nur.hidayah', LeaveType::Sakit, $start, $this->nthSchoolDayFrom($start, 2), 'Demam berdarah, rawat jalan sesuai surat dokter.', $start->setTime(6, 20));

        // Izin 1 hari.
        $day = $at(12);
        $this->leave('maya.anggraini', LeaveType::Izin, $day, $day, 'Menghadiri wisuda adik di Bandung.', $day->subDays(3)->setTime(19, 5));

        // Cuti 2 hari, diajukan jauh hari.
        $start = $at(17);
        $this->leave('dewi.lestari', LeaveType::Cuti, $start, $this->nthSchoolDayFrom($start, 1), 'Cuti tahunan, mengurus keperluan keluarga.', $start->subDays(10)->setTime(15, 30));

        // Dinas luar 2 hari, diajukan seminggu sebelumnya.
        $start = $at(count($pastDays) - 6);
        $this->leave('hendra.gunawan', LeaveType::DinasLuar, $start, $this->nthSchoolDayFrom($start, 1), 'Pelatihan implementasi Kurikulum Merdeka di Dinas Pendidikan.', $start->subWeek()->setTime(13, 40));

        // Hari ini: satu sakit, satu dinas luar.
        if ($this->settings->isSchoolDay($today)) {
            $this->leave('ahmad.fauzi', LeaveType::Sakit, $today, $today, 'Flu dan demam, istirahat di rumah.', $today->setTime(5, 45));
            $this->leave('agus.setiawan', LeaveType::DinasLuar, $today, $today, 'Mendampingi siswa lomba O2SN tingkat kecamatan.', $today->subDays(2)->setTime(14, 10));
        }

        // Cuti yang akan datang.
        $start = $today->next(CarbonImmutable::MONDAY);
        $this->leave('ratna.sari', LeaveType::Cuti, $start, $this->nthSchoolDayFrom($start, 2), 'Cuti tahunan, acara pernikahan keluarga di luar kota.', $today->subDay()->setTime(20, 15));
    }

    private function leave(string $username, LeaveType $type, CarbonImmutable $start, CarbonImmutable $end, string $reason, CarbonImmutable $submittedAt): void
    {
        $leave = LeaveRequest::create([
            'user_id' => $this->users[$username]->id,
            'type' => $type,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'reason' => $reason,
        ]);
        $leave->forceFill(['created_at' => $submittedAt, 'updated_at' => $submittedAt])->saveQuietly();

        $this->service->recordLeave($leave);
    }

    private function nthSchoolDayFrom(CarbonImmutable $start, int $extra): CarbonImmutable
    {
        $date = $start;
        while ($extra > 0) {
            $date = $date->addDay();
            if ($this->settings->isSchoolDay($date)) {
                $extra--;
            }
        }

        return $date;
    }

    private function seedHistory(CarbonImmutable $from, CarbonImmutable $today): void
    {
        foreach ($this->service->schoolDaysBetween($from, $today->subDay()) as $date) {
            $day = CarbonImmutable::parse($date);
            $recorded = Attendance::where('date', $date)->pluck('user_id')->all();

            foreach ($this->attending() as $username => $user) {
                if (in_array($user->id, $recorded, true)) {
                    continue; // sudah tercatat dari pengajuan izin
                }

                $profile = $this->profile($username);

                if (mt_rand(1, 1000) <= (['rajin' => 3, 'telat' => 25][$profile] ?? 10)) {
                    continue; // tidak datang & tidak izin → nanti ditandai Alpa
                }

                $arrival = $this->arrival($user, $profile, $day);
                $this->checkIn($user, $arrival);

                if (mt_rand(1, 100) <= 3) {
                    continue; // lupa presensi pulang
                }

                $this->checkOut($user, $this->departure($user, $profile, $day));
            }

            $this->service->markAbsent($day);
        }
    }

    private function seedToday(CarbonImmutable $today): void
    {
        if (! $this->settings->isSchoolDay($today)) {
            return;
        }

        $now = now()->toImmutable();
        $recorded = Attendance::where('date', $today->toDateString())->pluck('user_id')->all();

        foreach ($this->attending() as $username => $user) {
            if (in_array($username, self::NOT_YET_TODAY, true) || in_array($user->id, $recorded, true)) {
                continue;
            }

            $profile = $this->profile($username);
            $arrival = $this->arrival($user, $profile, $today);
            if ($arrival->gt($now)) {
                continue;
            }
            $this->checkIn($user, $arrival);

            $departure = $this->departure($user, $profile, $today);
            if ($departure->lte($now)) {
                $this->checkOut($user, $departure);
            }
        }
    }

    /** @return array<string, User> */
    private function attending(): array
    {
        return array_filter($this->users, fn (User $user) => $user->attends());
    }

    private function profile(string $username): string
    {
        foreach (self::STAFF as $row) {
            if ($row[0] === $username) {
                return $row[5];
            }
        }

        return 'biasa';
    }

    private function arrival(User $user, string $profile, CarbonImmutable $day): CarbonImmutable
    {
        $start = $day->setTimeFromTimeString($this->settings->workStartFor($user));
        $tolerance = $this->settings->lateTolerance();
        $lateChance = ['rajin' => 2, 'telat' => 35][$profile] ?? 8;

        $minutes = mt_rand(1, 100) <= $lateChance
            ? $tolerance + mt_rand(1, 55)              // terlambat
            : mt_rand($profile === 'rajin' ? -50 : -35, $profile === 'rajin' ? -5 : $tolerance);

        return $start->addMinutes($minutes)->addSeconds(mt_rand(0, 59));
    }

    private function departure(User $user, string $profile, CarbonImmutable $day): CarbonImmutable
    {
        $end = $day->setTimeFromTimeString($this->settings->workEndFor($user));
        $earlyChance = $profile === 'pulang_awal' ? 30 : 4;

        $minutes = mt_rand(1, 100) <= $earlyChance ? -mt_rand(15, 90) : mt_rand(0, 70);

        return $end->addMinutes($minutes)->addSeconds(mt_rand(0, 59));
    }

    private function checkIn(User $user, CarbonImmutable $at): void
    {
        $this->service->checkIn($user, $this->nearSchool(), $at);
    }

    private function checkOut(User $user, CarbonImmutable $at): void
    {
        $this->service->checkOut($user, $this->nearSchool(), $at);
    }

    /** Lokasi acak di dalam area sekolah, seperti bacaan GPS HP. */
    private function nearSchool(): Location
    {
        $distance = mt_rand(2, 70);
        $bearing = deg2rad(mt_rand(0, 359));
        [$lat, $lng] = Geo::offset(
            $this->settings->schoolLat(), $this->settings->schoolLng(),
            $distance * cos($bearing), $distance * sin($bearing),
        );

        return new Location($lat, $lng, mt_rand(5, 40));
    }

    private function report(): void
    {
        $this->command?->getOutput()->writeln('');
        $this->command?->info('Data uji coba siap. Semua akun berkata sandi "'.self::PASSWORD.'":');
        $this->command?->table(['Username', 'Nama', 'Peran'], [
            ['admin', 'Operator Sekolah', 'Admin — kelola data & pengaturan'],
            ['kepsek', $this->users['kepsek']->name, 'Pimpinan — pantau (hanya melihat)'],
            ['wakasek', $this->users['wakasek']->name, 'Pimpinan'],
            ['bendahara', $this->users['bendahara']->name, 'Pimpinan'],
            ['guru', $this->users['guru']->name, 'Guru — presensi dari HP'],
            ['tendik', $this->users['tendik']->name, 'Staf tendik — presensi dari HP'],
        ]);
        $this->command?->line(sprintf('  Titik sekolah contoh: %s, %s (radius 100 m). Ubah di menu Pengaturan.', self::SCHOOL_LAT, self::SCHOOL_LNG));
        $this->command?->line('  Untuk mencoba presensi dari laptop, atur lokasi palsu di DevTools browser (Sensors → Location).');
    }
}
