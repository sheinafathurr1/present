<?php

namespace Tests\Feature;

use App\Enums\EmployeeType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class EmployeeImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'nama;username;kata_sandi;jenis;jabatan;nip;no_hp;peran;wajib_presensi;jam_masuk;jam_pulang';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->admin = User::factory()->admin()->create(['username' => 'admin']);
        $this->actingAs($this->admin);
    }

    private function csv(string $content, string $name = 'guru.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    /** @param  list<list<mixed>>  $rows */
    private function xlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return new UploadedFile($path, 'guru.xlsx', null, null, true);
    }

    private function upload(UploadedFile $file): TestResponse
    {
        return $this->post('/pegawai/impor', ['file' => $file]);
    }

    private function confirm(): TestResponse
    {
        return $this->post('/pegawai/impor/simpan', ['token' => session('employee_import')['token'] ?? '']);
    }

    private function passwordFor(TestResponse $response, string $username): string
    {
        preg_match('#<code>'.preg_quote($username, '#').'</code></td>\s*<td data-label="Kata sandi"><code>([^<]+)</code>#', $response->getContent(), $m);
        $this->assertNotEmpty($m, "Kata sandi {$username} tidak tampil");

        return $m[1];
    }

    public function test_templates_can_be_downloaded(): void
    {
        $this->get('/pegawai/impor')->assertOk()->assertSee('Template Excel')->assertSee('wajib_presensi');
        $this->get('/pegawai')->assertSee('Impor Excel');

        $response = $this->get('/pegawai/impor/template')->assertOk()->assertDownload('template-impor-guru-staf.xlsx');
        $reader = new Reader;
        $reader->open($response->baseResponse->getFile()->getPathname());
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()] = $row->toArray();
                break;
            }
        }
        $reader->close();

        $this->assertSame(['Data guru & staf', 'Petunjuk'], array_keys($sheets));
        $this->assertSame(explode(';', self::HEADER), $sheets['Data guru & staf']);

        // Template yang belum diisi: baris kosong berformat Teks tidak dianggap data.
        $this->upload(new UploadedFile($response->baseResponse->getFile()->getPathname(), 'template.xlsx', null, null, true))
            ->assertSessionHas('error', 'Tidak ada data di bawah baris judul kolom.');

        $csv = $this->get('/pegawai/impor/template?format=csv')->assertDownload('template-impor-guru-staf.csv')->streamedContent();
        $this->assertSame("\xEF\xBB\xBF".self::HEADER, trim($csv));
    }

    public function test_csv_import_creates_accounts_with_generated_credentials(): void
    {
        $file = $this->csv("\xEF\xBB\xBF".self::HEADER."\n"
            ."Siti Aminah, S.Pd.;;;guru;Guru Kelas 1;19800101 200501 2 001;0812-3456-7890;;;;\n"
            ."Drs. Bambang Supriyadi, M.Pd.;kepsek;rahasia123;;Kepala Sekolah;;;pimpinan;ya;;\n"
            .";;;;;;;;;;\n"
            ."Joko Susilo;;;;Staf Tata Usaha;;;;tidak;07.30;15:30\n");

        $this->upload($file)
            ->assertOk()
            ->assertSee('Periksa data')
            ->assertSee('siti.aminah')
            ->assertSee('joko.susilo')
            ->assertSee('dibuat otomatis')
            ->assertSee('Simpan 3 akun');
        $this->assertSame(1, User::count(), 'Belum ada yang disimpan saat pratinjau');

        $result = $this->confirm()->assertOk()->assertSee('3 akun baru dibuat')->assertSee('Cetak kartu akun');

        $siti = User::where('username', 'siti.aminah')->sole();
        $this->assertSame('Siti Aminah, S.Pd.', $siti->name);
        $this->assertSame(EmployeeType::Guru, $siti->employee_type);
        $this->assertSame(UserRole::Pegawai, $siti->role);
        $this->assertSame('198001012005012001', $siti->nip);
        $this->assertSame('081234567890', $siti->phone);
        $this->assertTrue($siti->must_attend);

        $kepsek = User::where('username', 'kepsek')->sole();
        $this->assertSame(UserRole::Pimpinan, $kepsek->role);
        $this->assertSame(EmployeeType::Guru, $kepsek->employee_type, 'Ditebak dari jabatan Kepala Sekolah');
        $this->assertTrue(Hash::check('rahasia123', $kepsek->password));

        $joko = User::where('username', 'joko.susilo')->sole();
        $this->assertSame(EmployeeType::Tendik, $joko->employee_type);
        $this->assertFalse($joko->must_attend);
        $this->assertSame(['07:30', '15:30'], [$joko->work_start, $joko->work_end]);

        $this->assertSame([], Storage::disk('local')->files('impor-pegawai'), 'File sementara dihapus');

        // Kata sandi otomatis ditampilkan sekali dan bisa dipakai masuk.
        $password = $this->passwordFor($result, 'siti.aminah');
        $this->assertMatchesRegularExpression('/^[a-z]{4}[2-9]{4}$/', $password);
        $this->post('/logout');
        $this->post('/login', ['username' => 'siti.aminah', 'password' => $password])->assertRedirect('/presensi');
    }

    public function test_xlsx_with_title_rows_and_dapodik_columns(): void
    {
        $file = $this->xlsx([
            ['Daftar Guru dan Tenaga Kependidikan'],
            ['SD Negeri Contoh 01'],
            [],
            ['No', 'Nama', 'NUPTK', 'JK', 'NIP', 'Jenis PTK', 'HP'],
            [1, 'Ani Lestari, S.Pd.', '1234567890123456', 'P', '198501012010012001', 'Guru Kelas', 81311112222],
            [2, 'Udin Sedunia', '', 'L', '', 'Penjaga Sekolah', ''],
        ]);

        $this->upload($file)->assertOk()->assertSee('ani.lestari')->assertSee('Simpan 2 akun');
        $this->confirm()->assertOk();

        $ani = User::where('username', 'ani.lestari')->sole();
        $this->assertSame(EmployeeType::Guru, $ani->employee_type);
        $this->assertSame('Guru Kelas', $ani->position);
        $this->assertSame('198501012010012001', $ani->nip);
        $this->assertSame('081311112222', $ani->phone, 'Angka 0 di depan yang hilang dikembalikan');

        $udin = User::where('username', 'udin.sedunia')->sole();
        $this->assertSame(EmployeeType::Tendik, $udin->employee_type);
        $this->assertSame('Penjaga Sekolah', $udin->position);
    }

    public function test_excel_numbers_and_times_are_read_correctly(): void
    {
        $header = explode(';', self::HEADER);

        // NIP 18 digit yang diketik di sel berformat Angka sudah rusak: harus ditolak.
        $this->upload($this->xlsx([
            $header,
            ['Ani Lestari', '', '', 'guru', '', 1.98501012010012E+17, '', '', '', '', ''],
        ]))->assertOk()
            ->assertSee('NIP terbaca sebagai angka')
            ->assertSee('Ada 1 baris yang perlu diperbaiki')
            ->assertDontSee('Simpan 1 akun');
        $this->confirm()->assertRedirect('/pegawai/impor');
        $this->assertSame(1, User::count());

        // Jam dari Excel berupa pecahan hari.
        $this->upload($this->xlsx([
            $header,
            ['Ani Lestari', '', '', 'guru', '', '', 81234567890, '', '', 0.3125, '13.30'],
        ]))->assertOk();
        $this->confirm()->assertOk();

        $ani = User::where('username', 'ani.lestari')->sole();
        $this->assertSame('081234567890', $ani->phone);
        $this->assertSame(['07:30', '13:30'], [$ani->work_start, $ani->work_end]);
    }

    public function test_any_problem_blocks_the_whole_import(): void
    {
        User::factory()->create(['name' => 'Rina', 'username' => 'rina', 'nip' => '197001011995012001']);

        $this->upload($this->csv(self::HEADER."\n"
            ."Ani;ani;;;;;;;;;\n"
            ."Ani Kedua;ani;;;;;;;;;\n"
            ."Budi;;abc;;;;;;;;\n"
            .";;;;Guru Kelas 3;;;;;;\n"
            ."Cici;;;;;;;raja;;;\n"
            ."Dodi;;;;;;;;mungkin;25:00;\n"
            ."Eko;eko;;;;197001011995012001;;;;;\n"
            ."Fajar Nugroho;Fajar N;;;;;;;;;\n"))
            ->assertOk()
            ->assertSee('Ada 7 baris yang perlu diperbaiki')
            ->assertSee('Username ani sudah dipakai di baris 2.')
            ->assertSee('Kata sandi minimal 8 karakter.')
            ->assertDontSee('Username wajib diisi')
            ->assertSee('Nama kosong.')
            ->assertSee('Peran &quot;raja&quot; tidak dikenal', false)
            ->assertSee('Wajib presensi &quot;mungkin&quot; tidak dikenal', false)
            ->assertSee('Jam masuk harus berformat jam')
            ->assertSee('NIP 197001011995012001 sudah dipakai oleh Rina (username rina).')
            ->assertSee('Username hanya boleh huruf kecil')
            ->assertDontSee('name="token"', false);

        $this->confirm()->assertRedirect('/pegawai/impor');
        $this->assertSame(2, User::count());
    }

    public function test_existing_accounts_are_updated_without_clearing_data(): void
    {
        $guru = User::factory()->create(['username' => 'guru', 'name' => 'Siti', 'position' => 'Guru Kelas 1', 'phone' => '0811', 'nip' => '198001012005012001']);
        $byNip = User::factory()->tendik()->create(['username' => 'joko', 'nip' => '197501012000031002', 'position' => 'Staf TU']);
        $other = User::factory()->create(['username' => 'dewi']);

        $result = $this->upload($this->csv(self::HEADER."\n"
            ."Siti Aminah, S.Pd.;guru;;;;;081299998888;;;;\n"
            ."Joko Susilo;;;;Kepala Tata Usaha;197501012000031002;;;;;\n"
            ."Dewi Lestari;dewi;barubaru1;;;;;;;;\n"))
            ->assertOk()
            ->assertSee('Simpan 3 akun')
            ->assertSee('kata sandi diganti');
        $result = $this->confirm()->assertOk()->assertSee('0 akun baru dibuat')->assertSee('3 akun diperbarui');

        $guru->refresh();
        $this->assertSame('Siti Aminah, S.Pd.', $guru->name);
        $this->assertSame('081299998888', $guru->phone);
        $this->assertSame('Guru Kelas 1', $guru->position, 'Sel kosong tidak menghapus data');
        $this->assertSame(EmployeeType::Guru, $guru->employee_type);
        $this->assertTrue(Hash::check('password', $guru->password), 'Kata sandi tidak berubah bila kosong');

        $byNip->refresh();
        $this->assertSame('Joko Susilo', $byNip->name);
        $this->assertSame('Kepala Tata Usaha', $byNip->position);
        $this->assertSame(EmployeeType::Tendik, $byNip->employee_type);

        $this->assertTrue(Hash::check('barubaru1', $other->fresh()->password));
        $this->assertSame('barubaru1', $this->passwordFor($result, 'dewi'));
        $this->assertSame(4, User::count());
    }

    public function test_importing_the_same_file_again_does_not_duplicate_accounts(): void
    {
        $file = self::HEADER."\n"
            ."Siti Aminah, S.Pd.;;;guru;Guru Kelas 1;;;;;;\n"
            ."Joko Susilo;;;;Staf TU;197501012000031002;;;;;\n";

        $this->upload($this->csv($file));
        $this->confirm()->assertSee('2 akun baru dibuat');

        // Diperbaiki lalu diimpor ulang: dicocokkan lewat nama & NIP, tidak membuat akun baru.
        $this->upload($this->csv(str_replace('Guru Kelas 1', 'Guru Kelas 2', $file)))->assertSee('Simpan 2 akun')->assertSee('<dt>Akun baru</dt><dd>0</dd>', false);
        $this->confirm()->assertSee('0 akun baru dibuat')->assertSee('2 akun diperbarui');

        $this->assertSame(3, User::count());
        $this->assertSame('Guru Kelas 2', User::where('username', 'siti.aminah')->value('position'));
    }

    public function test_ambiguous_names_need_a_username(): void
    {
        User::factory()->create(['name' => 'Ahmad Fauzi', 'username' => 'ahmad1']);
        User::factory()->create(['name' => 'Ahmad Fauzi', 'username' => 'ahmad2']);

        $this->upload($this->csv(self::HEADER."\nAhmad Fauzi;;;;Guru PJOK;;;;;;\n"))
            ->assertSee('Ada 2 akun bernama Ahmad Fauzi (ahmad1, ahmad2). Isi kolom username untuk memilih akun yang dimaksud.');
    }

    public function test_generated_usernames_stay_unique(): void
    {
        User::factory()->create(['name' => 'Siti Aminah', 'username' => 'siti.aminah', 'nip' => '198001012005012001']);

        // Orang lain bernama sama (NIP berbeda) tetap dibuat sebagai akun baru dengan username lain.
        $this->upload($this->csv(self::HEADER."\n"
            ."Hj. Siti Aminah, S.Pd.;;;;;;;;;;\n"
            ."Siti Aminah;;;;;198702022010012002;;;;;\n"
            ."Al;;;;;;;;;;\n"))->assertOk();
        $this->confirm()->assertOk();

        $this->assertTrue(User::where('username', 'siti.aminah2')->exists());
        $this->assertTrue(User::where('username', 'siti.aminah3')->exists());
        $this->assertTrue(User::where('username', 'al0')->exists());
    }

    public function test_windows_csv_with_commas(): void
    {
        $this->upload($this->csv("nama,jabatan\r\nJos\xe9 Rizal,Guru Kelas 2\r\n"))->assertOk()->assertSee('José Rizal');
        $this->confirm()->assertOk();

        $this->assertSame('José Rizal', User::where('username', 'jose.rizal')->value('name'));
    }

    public function test_admin_cannot_remove_own_admin_role(): void
    {
        $this->upload($this->csv(self::HEADER."\nOperator;admin;;;;;;pegawai;;;\n"))
            ->assertSee('Anda tidak bisa mencabut peran admin Anda sendiri.');

        $this->assertTrue($this->admin->fresh()->isAdmin());
    }

    public function test_wrong_files_are_explained(): void
    {
        $this->post('/pegawai/impor', [])->assertSessionHasErrors('file');
        $this->upload(UploadedFile::fake()->create('guru.xls', 10))->assertSessionHasErrors('file');

        $this->upload($this->csv("nomor;alamat\n1;Jl. Merdeka\n"))
            ->assertRedirect('/pegawai/impor')
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Kolom "nama" tidak ditemukan'));

        $this->upload(UploadedFile::fake()->createWithContent('rusak.xlsx', 'bukan file excel'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'tidak bisa dibaca'));

        $this->upload($this->csv(self::HEADER."\n"))
            ->assertSessionHas('error', 'Tidak ada data di bawah baris judul kolom.');

        $this->assertSame([], Storage::disk('local')->files('impor-pegawai'));
    }

    public function test_confirming_twice_or_without_preview_is_refused(): void
    {
        $this->post('/pegawai/impor/simpan', ['token' => 'palsu'])->assertRedirect('/pegawai/impor')->assertSessionHas('error');

        $this->upload($this->csv(self::HEADER."\nAni;;;;;;;;;;\n"));
        $token = session('employee_import')['token'];
        $this->post('/pegawai/impor/simpan', ['token' => $token])->assertOk();
        $this->post('/pegawai/impor/simpan', ['token' => $token])->assertRedirect('/pegawai/impor');

        $this->assertSame(1, User::where('name', 'Ani')->count());
    }

    public function test_only_admin_can_import(): void
    {
        $this->actingAs(User::factory()->pimpinan()->create());

        $this->get('/pegawai/impor')->assertForbidden();
        $this->get('/pegawai/impor/template')->assertForbidden();
        $this->upload($this->csv(self::HEADER."\nAni;;;;;;;;;;\n"))->assertForbidden();
        $this->post('/pegawai/impor/simpan')->assertForbidden();
    }
}
