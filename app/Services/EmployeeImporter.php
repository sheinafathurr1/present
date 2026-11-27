<?php

namespace App\Services;

use App\Enums\EmployeeType;
use App\Enums\UserRole;
use App\Exceptions\ImportException;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

/**
 * Impor data guru & staf dari Excel (.xlsx) atau CSV.
 *
 *  - Baris judul kolom dicari otomatis (boleh ada baris judul laporan di atasnya,
 *    seperti file ekspor Dapodik). Kolom yang tidak dikenal diabaikan.
 *  - Akun dicocokkan lewat username; bila username kosong, lewat NIP, lalu nama yang
 *    sama persis. Yang belum ada dibuat baru, yang sudah ada diperbarui (sel kosong
 *    tidak mengubah data).
 *  - Username & kata sandi yang kosong dibuat otomatis untuk akun baru.
 *  - Semua baris diperiksa dulu. Bila ada satu saja yang salah, tidak ada yang disimpan.
 */
class EmployeeImporter
{
    public const MAX_ROWS = 500;

    /**
     * Kolom yang dikenali: kunci => [judul di template, keterangan, nama lain, petunjuk singkat, contoh].
     */
    public const COLUMNS = [
        'name' => ['nama', 'Wajib. Nama lengkap beserta gelar.', ['nama lengkap', 'nama pegawai', 'nama ptk', 'nama guru'],
            'WAJIB diisi. Nama lengkap + gelar', 'Siti Aminah, S.Pd.'],
        'username' => ['username', 'Untuk login: huruf kecil tanpa spasi, mis. siti.aminah. Kosongkan untuk dibuat otomatis dari nama.', ['user', 'nama pengguna', 'user name'],
            'Boleh kosong (dibuat otomatis). Huruf kecil, tanpa spasi', 'siti.aminah'],
        'password' => ['kata_sandi', 'Minimal 8 karakter. Kosongkan untuk dibuat otomatis (ditampilkan setelah impor).', ['kata sandi', 'password', 'sandi'],
            'Boleh kosong (dibuat otomatis). Min. 8 karakter', 'rahasia123'],
        'employee_type' => ['jenis', 'guru atau tendik. Kosong: ditebak dari jabatan (mengandung "guru"/"kepala sekolah" = guru).', ['jenis pegawai', 'jenis ptk', 'kategori', 'tipe'],
            'Isi: guru atau tendik', 'guru'],
        'position' => ['jabatan', 'Mis. Guru Kelas 3, Guru PJOK, Staf Tata Usaha, Penjaga Sekolah.', ['tugas', 'jabatan ptk'],
            'Mis. Guru Kelas 3, Staf Tata Usaha', 'Guru Kelas 1'],
        'nip' => ['nip', 'Opsional. Ketik sebagai teks agar 18 digitnya tidak berubah.', ['no nip', 'nomor nip'],
            'Boleh kosong. 18 digit tanpa spasi', '198001012005012001'],
        'phone' => ['no_hp', 'Opsional, mis. 081234567890.', ['hp', 'no hp', 'nomor hp', 'telepon', 'no telepon', 'no telp', 'nomor telepon', 'whatsapp', 'wa', 'no wa'],
            'Boleh kosong. Diawali 08', '081234567890'],
        'role' => ['peran', 'pegawai (bawaan), pimpinan (kepala sekolah, wakil, bendahara), atau admin.', ['role', 'hak akses'],
            'Isi: pegawai, pimpinan, atau admin. Kosong = pegawai', 'pegawai'],
        'must_attend' => ['wajib_presensi', 'ya (bawaan) atau tidak.', ['wajib presensi', 'presensi'],
            'Isi: ya atau tidak. Kosong = ya', 'ya'],
        'work_start' => ['jam_masuk', 'Opsional, jam masuk khusus orang ini, mis. 07:30. Kosong: jadwal umum.', ['jam masuk'],
            'Boleh kosong (jadwal umum). Mis. 07:30', '07:30'],
        'work_end' => ['jam_pulang', 'Opsional, jam pulang khusus, mis. 15:30. Isi bersama jam_masuk.', ['jam pulang'],
            'Boleh kosong. Isi bersama jam_masuk', '15:30'],
    ];

    /** Kolom yang diperiksa agar angka panjang tidak rusak karena format Angka di Excel. */
    private const LONG_NUMBER_COLUMNS = ['nip', 'phone'];

    public function __construct(private ?User $actor = null) {}

    /* ---------- Membaca file ---------- */

    /**
     * @return array{columns: list<string>, rows: list<array{line: int, values: array<string, mixed>}>}
     *
     * @throws ImportException
     */
    public function read(string $path, string $extension): array
    {
        $lines = match (strtolower($extension)) {
            'xlsx' => $this->readXlsx($path),
            'csv', 'txt' => $this->readCsv($path),
            default => throw new ImportException('Format file tidak didukung. Gunakan Excel (.xlsx) atau CSV.'),
        };

        // Cari baris judul kolom di 15 baris pertama.
        $headerLine = null;
        $map = [];
        foreach (array_slice($lines, 0, 15, true) as $line => $cells) {
            $map = $this->mapHeader($cells);
            if (isset($map['name'])) {
                $headerLine = $line;
                break;
            }
        }

        if ($headerLine === null) {
            throw new ImportException('Kolom "nama" tidak ditemukan. Baris judul kolom harus berisi minimal "nama" — silakan pakai template.');
        }

        $rows = [];
        foreach ($lines as $line => $cells) {
            if ($line <= $headerLine) {
                continue;
            }

            $values = [];
            foreach ($map as $key => $index) {
                $values[$key] = $cells[$index] ?? null;
            }

            if (collect($values)->every(fn ($value) => $value === null || (is_string($value) && trim($value) === ''))) {
                continue; // baris kosong
            }

            $rows[] = ['line' => $line, 'values' => $values];

            if (count($rows) > self::MAX_ROWS) {
                throw new ImportException('File berisi lebih dari '.self::MAX_ROWS.' baris data. Pecah menjadi beberapa file.');
            }
        }

        if ($rows === []) {
            throw new ImportException('Tidak ada data di bawah baris judul kolom.');
        }

        return ['columns' => array_keys($map), 'rows' => $rows];
    }

    /** @return array<int, list<mixed>> nomor baris (mulai 1) => sel */
    private function readXlsx(string $path): array
    {
        $reader = new XlsxReader(new XlsxOptions(SHOULD_PRESERVE_EMPTY_ROWS: true));
        $lines = [];

        try {
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                $line = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    $lines[++$line] = $row->toArray();
                    if ($line > self::MAX_ROWS + 50) {
                        break;
                    }
                }
                break; // hanya lembar pertama
            }
        } catch (Throwable) {
            throw new ImportException('File Excel tidak bisa dibaca. Buka di Excel lalu simpan ulang sebagai "Excel Workbook (.xlsx)" atau "CSV UTF-8".');
        } finally {
            $reader->close();
        }

        return $lines;
    }

    /** @return array<int, list<mixed>> */
    private function readCsv(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        if (! mb_check_encoding($content, 'UTF-8')) {
            // CSV biasa dari Excel di Windows memakai Windows-1252.
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        // Pemisah kolom: yang menghasilkan kolom terbanyak di baris-baris awal (baris petunjuk
        // di atas judul kolom boleh mengandung koma, jadi baris pertama saja tidak cukup).
        $sample = array_slice(preg_split('/\r\n|\n|\r/', $content) ?: [], 0, 15);
        $delimiter = ';';
        $most = 0;
        foreach ([';', ',', "\t"] as $candidate) {
            $fields = max(array_map(fn (string $line) => count(str_getcsv($line, $candidate, '"', '')), $sample ?: ['']));
            if ($fields > $most) {
                [$delimiter, $most] = [$candidate, $fields];
            }
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $lines = [];
        $line = 0;
        while (($cells = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $lines[++$line] = $cells === [null] ? [] : $cells;
            if ($line > self::MAX_ROWS + 50) {
                break;
            }
        }
        fclose($handle);

        return $lines;
    }

    /** @return array<string, int> kunci kolom => indeks sel */
    private function mapHeader(array $cells): array
    {
        $lookup = [];
        foreach (self::COLUMNS as $key => [$title, , $aliases]) {
            foreach ([$title, ...$aliases] as $name) {
                $lookup[self::normalizeHeader($name)] = $key;
            }
        }

        $map = [];
        foreach ($cells as $index => $cell) {
            if (! is_string($cell)) {
                continue;
            }
            $key = $lookup[self::normalizeHeader($cell)] ?? null;
            if ($key !== null && ! isset($map[$key])) {
                $map[$key] = $index;
            }
        }

        // Ekspor Dapodik: "Jenis PTK" sekaligus menjadi jabatan bila tidak ada kolom jabatan.
        if (! isset($map['position']) && isset($map['employee_type'])) {
            $header = self::normalizeHeader((string) $cells[$map['employee_type']]);
            if ($header === 'jenis ptk') {
                $map['position'] = $map['employee_type'];
            }
        }

        return $map;
    }

    private static function normalizeHeader(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        return trim($value);
    }

    /* ---------- Memeriksa baris ---------- */

    /**
     * Periksa semua baris dan tentukan tindakan untuk masing-masing.
     *
     * @param  array{columns: list<string>, rows: list<array{line: int, values: array<string, mixed>}>}  $file
     * @return array{rows: list<array<string, mixed>>, created: int, updated: int, invalid: int}
     */
    public function analyze(array $file): array
    {
        $usernames = []; // username => baris
        $nips = [];      // nip => baris
        $matched = [];   // id akun => baris
        $results = [];

        foreach ($file['rows'] as $row) {
            $results[] = $this->analyzeRow($row['line'], $row['values'], $usernames, $nips, $matched);
        }

        return [
            'rows' => $results,
            'created' => count(array_filter($results, fn ($r) => $r['errors'] === [] && $r['action'] === 'baru')),
            'updated' => count(array_filter($results, fn ($r) => $r['errors'] === [] && $r['action'] === 'perbarui')),
            'invalid' => count(array_filter($results, fn ($r) => $r['errors'] !== [])),
        ];
    }

    /**
     * @param  array<string, int>  $usernames
     * @param  array<string, int>  $nips
     * @param  array<int, int>  $matched
     * @return array<string, mixed>
     */
    private function analyzeRow(int $line, array $raw, array &$usernames, array &$nips, array &$matched): array
    {
        $errors = [];

        // Angka panjang yang terlanjur rusak karena format Angka di Excel.
        foreach (self::LONG_NUMBER_COLUMNS as $key) {
            if (self::looksLikeBrokenNumber($raw[$key] ?? null)) {
                $label = $key === 'nip' ? 'NIP' : 'Nomor HP';
                $errors[] = "{$label} terbaca sebagai angka sehingga digitnya berubah. Ubah format kolom menjadi Teks lalu ketik ulang.";
            }
        }

        $data = [
            'name' => self::text($raw['name'] ?? null, collapse: true),
            'username' => ($u = self::text($raw['username'] ?? null)) === null ? null : mb_strtolower($u),
            'password' => self::text($raw['password'] ?? null),
            'position' => self::text($raw['position'] ?? null, collapse: true),
            'nip' => self::digitsOnly(self::text($raw['nip'] ?? null)),
            'phone' => self::phone($raw['phone'] ?? null),
            'work_start' => self::time($raw['work_start'] ?? null),
            'work_end' => self::time($raw['work_end'] ?? null),
        ];

        $role = self::text($raw['role'] ?? null);
        $roleValue = $role === null ? null : self::role($role);
        if ($role !== null && $roleValue === null) {
            $errors[] = "Peran \"{$role}\" tidak dikenal. Isi pegawai, pimpinan, atau admin.";
        }
        $attend = self::text($raw['must_attend'] ?? null);
        $attendValue = $attend === null ? null : self::yesNo($attend);
        if ($attend !== null && $attendValue === null) {
            $errors[] = "Wajib presensi \"{$attend}\" tidak dikenal. Isi ya atau tidak.";
        }
        foreach (['work_start' => 'Jam masuk', 'work_end' => 'Jam pulang'] as $key => $label) {
            if (self::text($raw[$key] ?? null) !== null && $data[$key] === null) {
                $errors[] = "{$label} harus berformat jam, mis. 07:30.";
            }
        }

        // Cocokkan dengan akun yang sudah ada: username, lalu NIP, lalu nama yang sama persis
        // (agar file yang sama bisa diimpor ulang setelah diperbaiki tanpa membuat akun ganda).
        $existing = null;
        if ($data['username'] !== null) {
            $existing = User::where('username', $data['username'])->first();
        } else {
            if ($data['nip'] !== null) {
                $existing = User::where('nip', $data['nip'])->first();
            }
            if (! $existing && $data['name'] !== null) {
                $sameName = User::whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])->get()
                    ->filter(fn (User $user) => $data['nip'] === null || $user->nip === null || $user->nip === $data['nip']);
                if ($sameName->count() === 1) {
                    $existing = $sameName->first();
                } elseif ($sameName->count() > 1) {
                    $errors[] = "Ada {$sameName->count()} akun bernama {$data['name']} (".$sameName->pluck('username')->join(', ').'). Isi kolom username untuk memilih akun yang dimaksud.';
                }
            }
        }

        // Akun lama: jenis hanya diubah bila kolom jenis diisi. Akun baru: boleh ditebak dari jabatan.
        $type = self::employeeType(self::text($raw['employee_type'] ?? null), $existing ? null : $data['position']);

        $generated = ['username' => false, 'password' => false];
        if ($existing && $data['username'] === null) {
            $data['username'] = $existing->username;
        }
        if (! $existing && $data['username'] === null && $data['name'] !== null) {
            $data['username'] = $this->makeUsername($data['name'], $usernames);
            $generated['username'] = true;
        }
        if (! $existing && $data['password'] === null) {
            $data['password'] = self::makePassword();
            $generated['password'] = true;
        }

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            // Username selalu terisi (atau dibuat) bila nama ada; baris tanpa nama cukup diberi tahu "Nama kosong".
            'username' => ['required_with:name', 'nullable', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/'],
            'password' => ['nullable', 'string', Password::min(8)],
            'position' => ['nullable', 'string', 'max:100'],
            'nip' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9]+$/'],
            'work_start' => ['nullable', 'required_with:work_end'],
            'work_end' => ['nullable', 'required_with:work_start', 'after:work_start'],
        ], [
            'name.required' => 'Nama kosong.',
            'username.regex' => 'Username hanya boleh huruf kecil, angka, titik, garis bawah, atau tanda minus (tanpa spasi).',
            'phone.regex' => 'Nomor HP hanya berisi angka.',
            'work_end.after' => 'Jam pulang harus setelah jam masuk.',
        ]);
        $errors = [...$errors, ...$validator->errors()->all()];

        // Bentrok di dalam file atau dengan akun lain.
        if ($data['username'] !== null) {
            if (isset($usernames[$data['username']]) && $usernames[$data['username']] !== $line) {
                $errors[] = "Username {$data['username']} sudah dipakai di baris {$usernames[$data['username']]}.";
            } else {
                $usernames[$data['username']] = $line;
            }
        }
        if ($data['nip'] !== null) {
            if (isset($nips[$data['nip']])) {
                $errors[] = "NIP {$data['nip']} sudah dipakai di baris {$nips[$data['nip']]}.";
            } else {
                $nips[$data['nip']] = $line;
                $owner = User::where('nip', $data['nip'])->when($existing, fn ($q) => $q->whereKeyNot($existing->id))->first();
                if ($owner) {
                    $errors[] = "NIP {$data['nip']} sudah dipakai oleh {$owner->name} (username {$owner->username}).";
                }
            }
        }
        if ($existing) {
            if (isset($matched[$existing->id])) {
                $errors[] = "Akun {$existing->username} sudah diperbarui oleh baris {$matched[$existing->id]}.";
            }
            $matched[$existing->id] = $line;

            if ($this->actor && $existing->is($this->actor) && $roleValue !== null && $roleValue !== UserRole::Admin) {
                $errors[] = 'Anda tidak bisa mencabut peran admin Anda sendiri.';
            }
        }

        // Nilai akhir yang akan disimpan.
        $attributes = array_filter([
            'name' => $data['name'],
            'username' => $data['username'],
            'position' => $data['position'],
            'nip' => $data['nip'],
            'phone' => $data['phone'],
            'work_start' => $data['work_start'],
            'work_end' => $data['work_end'],
            'employee_type' => $type,
            'role' => $roleValue,
            'must_attend' => $attendValue,
        ], fn ($value) => $value !== null);

        if (! $existing) {
            $attributes += ['employee_type' => EmployeeType::Guru, 'role' => UserRole::Pegawai, 'must_attend' => true];
        }

        return [
            'line' => $line,
            'action' => $existing ? 'perbarui' : 'baru',
            'user_id' => $existing?->id,
            'attributes' => $attributes,
            'password' => $data['password'],
            'generated' => $generated,
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /* ---------- Menyimpan ---------- */

    /**
     * Simpan hasil pemeriksaan. Hanya dipanggil bila tidak ada baris yang salah.
     *
     * @param  array{rows: list<array<string, mixed>>}  $analysis
     * @return list<array{name: string, username: string, password: ?string, action: string, generated: array{username: bool, password: bool}, position: ?string, type: string}>
     */
    public function import(array $analysis): array
    {
        if (collect($analysis['rows'])->contains(fn ($row) => $row['errors'] !== [])) {
            throw new ImportException('Masih ada baris yang perlu diperbaiki.');
        }

        return DB::transaction(function () use ($analysis) {
            $accounts = [];

            foreach ($analysis['rows'] as $row) {
                $attributes = $row['attributes'];
                if ($row['password'] !== null) {
                    $attributes['password'] = $row['password'];
                }

                if ($row['action'] === 'perbarui') {
                    $user = User::findOrFail($row['user_id']);
                    $user->update($attributes);
                } else {
                    $user = User::create($attributes + ['is_active' => true]);
                }

                $accounts[] = [
                    'name' => $user->name,
                    'username' => $user->username,
                    'password' => $row['password'],
                    'action' => $row['action'],
                    'generated' => $row['generated'],
                    'position' => $user->position,
                    'type' => $user->employee_type->label(),
                ];
            }

            return $accounts;
        });
    }

    /* ---------- Pembantu ---------- */

    private static function text(mixed $value, bool $collapse = false): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('H:i');
        } elseif (is_bool($value)) {
            $value = $value ? 'ya' : 'tidak';
        } elseif (is_float($value) && floor($value) === $value && abs($value) < 1e15) {
            $value = sprintf('%.0f', $value);
        } elseif (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        if ($collapse) {
            $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        }

        return $value === '' ? null : $value;
    }

    /** Angka ≥ 16 digit dari Excel (sel berformat Angka) sudah kehilangan digit terakhirnya. */
    private static function looksLikeBrokenNumber(mixed $value): bool
    {
        if (is_float($value) || is_int($value)) {
            return abs($value) >= 1e15;
        }

        return is_string($value) && (bool) preg_match('/^\s*\d([.,]\d+)?E\+?\d+\s*$/i', $value);
    }

    private static function digitsOnly(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $value);

        return $digits === '' ? null : $digits;
    }

    private static function phone(mixed $value): ?string
    {
        if ((is_int($value) || is_float($value)) && $value > 0 && $value < 1e15) {
            // Sel berformat Angka membuang angka 0 di depan: 81234567890 → 081234567890.
            $digits = sprintf('%.0f', $value);

            return str_starts_with($digits, '8') ? '0'.$digits : $digits;
        }

        $text = self::text($value);
        if ($text === null) {
            return null;
        }
        $text = preg_replace('/[\s().-]/', '', $text);

        return $text === '' ? null : $text;
    }

    private static function time(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i');
        }
        if ((is_float($value) || is_int($value)) && $value >= 0 && $value < 1) {
            // Jam di Excel disimpan sebagai pecahan hari.
            $minutes = (int) round($value * 1440);

            return sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60);
        }

        $text = self::text($value);
        if ($text === null || ! preg_match('/^(\d{1,2})[:.](\d{2})(?:[:.]\d{2})?$/', $text, $m) || (int) $m[1] > 23 || (int) $m[2] > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $m[1], $m[2]);
    }

    private static function employeeType(?string $type, ?string $position): ?EmployeeType
    {
        $text = mb_strtolower((string) ($type ?? $position));
        if ($text === '') {
            return null;
        }

        return preg_match('/guru|kepala sekolah|wakil kepala|wali kelas/', $text)
            ? EmployeeType::Guru
            : EmployeeType::Tendik;
    }

    private static function role(string $value): ?UserRole
    {
        $text = mb_strtolower(trim($value));

        return match (true) {
            in_array($text, ['pegawai', 'guru', 'staf', 'staff', 'tendik', 'guru/staf', 'guru / staf'], true) => UserRole::Pegawai,
            (bool) preg_match('/pimpinan|kepala|wakil|bendahara/', $text) => UserRole::Pimpinan,
            in_array($text, ['admin', 'administrator', 'operator'], true) => UserRole::Admin,
            default => null,
        };
    }

    private static function yesNo(string $value): ?bool
    {
        $text = mb_strtolower(trim($value));

        return match (true) {
            in_array($text, ['ya', 'y', 'yes', '1', 'true', 'wajib', 'iya'], true) => true,
            in_array($text, ['tidak', 't', 'no', 'n', '0', 'false', 'bukan', 'tdk'], true) => false,
            default => null,
        };
    }

    /**
     * Username dari nama: dua kata pertama tanpa gelar, mis. "Drs. Ahmad Fauzi, M.Pd." → ahmad.fauzi.
     *
     * @param  array<string, int>  $taken
     */
    private function makeUsername(string $name, array $taken): string
    {
        $clean = preg_replace('/,.*$/u', '', $name) ?? $name;
        $clean = preg_replace('/^((drs?|dra|dr|h|hj|ir|prof|ust|ustadz|ustazah|bapak|ibu|bpk|bu|pak)\.?\s+)+/i', '', trim($clean)) ?? $clean;
        $words = array_values(array_filter(array_map(
            fn (string $word) => preg_replace('/[^a-z0-9]/', '', mb_strtolower(Str::ascii($word))),
            preg_split('/\s+/', $clean) ?: [],
        )));

        $base = substr(implode('.', array_slice($words, 0, 2)), 0, 40);
        if (strlen($base) < 3) {
            $base = str_pad($base ?: 'guru', 3, '0');
        }

        $candidate = $base;
        for ($n = 2; isset($taken[$candidate]) || User::where('username', $candidate)->exists(); $n++) {
            $candidate = $base.$n;
        }

        return $candidate;
    }

    /** Kata sandi mudah diketik di HP: 4 huruf + 4 angka, tanpa huruf/angka yang mirip. */
    public static function makePassword(): string
    {
        $letters = 'abcdefghjkmnpqrstuvwxyz';
        $digits = '23456789';
        $password = '';
        for ($i = 0; $i < 4; $i++) {
            $password .= $letters[random_int(0, strlen($letters) - 1)];
        }
        for ($i = 0; $i < 4; $i++) {
            $password .= $digits[random_int(0, strlen($digits) - 1)];
        }

        return $password;
    }
}
