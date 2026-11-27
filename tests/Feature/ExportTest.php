<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveType;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\AttendanceService;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;
use ZipArchive;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    private const FORMULA = '=HYPERLINK("http://contoh.test","klik")';

    private User $kepsek;

    private User $siti;

    protected function setUp(): void
    {
        parent::setUp();

        // Rabu 11 November 2026 pukul 09.00. Rentang uji: Senin 2 – Rabu 11 November.
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(9, 0));
        $this->setUpSchool();
        $this->kepsek = User::factory()->pimpinan()->create(['must_attend' => false]);
        $this->siti = User::factory()->create(['name' => 'Siti Aminah, S.Pd.', 'nip' => '198001012005012001', 'position' => 'Guru Kelas 1']);

        Holiday::create(['date' => '2026-11-06', 'name' => 'Libur contoh']);
        $this->record($this->siti, '2026-11-09', ['check_in_at' => '2026-11-09 06:50', 'check_out_at' => '2026-11-09 13:05', 'check_in_distance' => 20, 'check_out_distance' => 25]);
        $this->record($this->siti, '2026-11-10', ['status' => AttendanceStatus::Terlambat, 'check_in_at' => '2026-11-10 07:40', 'check_out_at' => '2026-11-10 12:30', 'late_minutes' => 40, 'early_minutes' => 30]);
        $this->record($this->siti, '2026-11-04', ['status' => AttendanceStatus::Alpa, 'check_in_at' => null, 'source' => Attendance::SOURCE_SYSTEM]);
        $this->record($this->siti, '2026-11-03', ['status' => AttendanceStatus::DinasLuar, 'check_in_at' => null, 'source' => Attendance::SOURCE_ADMIN, 'note' => 'Rapat di dinas']);
        $leave = LeaveRequest::factory()->for($this->siti)->create(['type' => LeaveType::Sakit, 'start_date' => '2026-11-07', 'end_date' => '2026-11-07', 'reason' => self::FORMULA]);
        app(AttendanceService::class)->recordLeave($leave);
    }

    private function record(User $user, string $date, array $attributes): void
    {
        Attendance::factory()->for($user)->create(['date' => $date, 'check_in_distance' => null, 'check_in_accuracy' => null, ...$attributes]);
    }

    private function download(string $url): TestResponse
    {
        return $this->get($url)->assertOk();
    }

    /** @return array<string, list<list<mixed>>> lembar => baris */
    private function readXlsx(TestResponse $response): array
    {
        $reader = new Reader;
        $reader->open($response->baseResponse->getFile()->getPathname());
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
            }
        }
        $reader->close();

        return $sheets;
    }

    /** Baris-baris di bawah judul tabel yang sel pertamanya $first. */
    private function tableAfter(array $rows, string $first): array
    {
        $index = collect($rows)->search(fn ($row) => ($row[0] ?? null) === $first);
        $this->assertNotFalse($index, "Tabel \"{$first}\" tidak ditemukan");

        return array_slice($rows, $index + 1);
    }

    public function test_one_person_to_excel(): void
    {
        $response = $this->actingAs($this->kepsek)
            ->download("/ekspor/unduh?pegawai={$this->siti->id}&dari=2026-11-02&sampai=2026-11-11")
            ->assertDownload('presensi-siti-aminah-spd-2026-11-02_2026-11-11.xlsx');
        $sheets = $this->readXlsx($response);

        $this->assertSame(['Presensi', 'Pengajuan izin'], array_keys($sheets));
        $rows = $sheets['Presensi'];
        $this->assertSame(['Nama', 'Siti Aminah, S.Pd.'], $rows[1]);
        $this->assertSame(['NIP', '198001012005012001'], $rows[2]);
        $this->assertSame(['Periode', '02 November 2026 – 11 November 2026'], $rows[4]);

        // Hari sekolah 2,3,4,5,7,9,10,11 (6 libur, 8 Minggu): hadir 1, terlambat 1, sakit 1, dinas luar 1, alpa 1, tanpa catatan 2.
        $summary = $this->tableAfter($rows, 'Hari sekolah')[0];
        $this->assertSame([8, 1, 1, 0, 1, 0, 1, 1, 2, 40, 1, 25], $summary);

        $days = collect($this->tableAfter($rows, 'Tanggal'))
            ->keyBy(fn ($row) => $row[0] instanceof DateTimeInterface ? $row[0]->format('Y-m-d') : $row[0]);
        $this->assertCount(10, $days, 'Setiap tanggal dalam rentang, termasuk libur');
        $this->assertSame(['Senin', 'Tidak ada catatan'], array_slice($days['2026-11-02'], 1, 2));
        $this->assertSame('Koreksi admin: Rapat di dinas', $days['2026-11-03'][9]);
        $this->assertSame(['Libur', 'Libur contoh'], [$days['2026-11-06'][2], $days['2026-11-06'][9]]);
        $this->assertSame(self::FORMULA, $days['2026-11-07'][9], 'Alasan izin ditulis apa adanya sebagai teks');
        $this->assertSame('Bukan hari sekolah', $days['2026-11-08'][2]);
        $this->assertSame(['Hadir', '06:50', '13:05'], array_slice($days['2026-11-09'], 2, 3));
        $this->assertSame([20, 25], array_slice($days['2026-11-09'], 7, 2));
        $this->assertSame(['Terlambat', '07:40', '12:30', 40, 30], array_slice($days['2026-11-10'], 2, 5));
        $this->assertSame('Terlambat 40 menit · Pulang awal 30 menit', $days['2026-11-10'][9]);
        $this->assertSame('Belum presensi', $days['2026-11-11'][2]);

        [$header, $leave] = $sheets['Pengajuan izin'];
        $this->assertSame('Jenis', $header[0]);
        $this->assertSame(['Sakit', '2026-11-07'], [$leave[0], $leave[1]->format('Y-m-d')]);
        $this->assertSame(self::FORMULA, $leave[4]);

        // Tidak ada rumus di file: teks berawalan "=" tidak dijalankan Excel.
        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        foreach (['xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml'] as $sheet) {
            $this->assertStringNotContainsString('<f>', (string) $zip->getFromName($sheet));
        }
        $zip->close();
    }

    public function test_one_person_to_csv(): void
    {
        $csv = $this->actingAs($this->kepsek)
            ->download("/ekspor/unduh?pegawai={$this->siti->id}&dari=2026-11-02&sampai=2026-11-11&format=csv")
            ->assertDownload('presensi-siti-aminah-spd-2026-11-02_2026-11-11.csv')
            ->streamedContent();

        $lines = array_map(fn ($line) => str_getcsv($line, ';', '"', ''), explode("\n", trim(ltrim($csv, "\xEF\xBB\xBF"))));
        $this->assertSame(['Tanggal', 'Hari', 'Status'], array_slice($lines[0], 0, 3));
        $this->assertCount(11, $lines);
        $sick = collect($lines)->firstWhere(0, '2026-11-07');
        $this->assertSame('Sakit', $sick[2]);
        $this->assertSame("'".self::FORMULA, $sick[9], 'Di CSV diberi tanda petik agar tidak dibaca sebagai rumus');
    }

    public function test_everyone_summary_and_daily_rows(): void
    {
        $budi = User::factory()->tendik()->create(['name' => 'Budi Santoso']);
        $this->record($budi, '2026-11-09', ['check_in_at' => '2026-11-09 07:00']);
        $moved = User::factory()->inactive()->create(['name' => 'Eko Pindahan']);
        $this->record($moved, '2026-11-03', ['check_in_at' => '2026-11-03 06:55']);
        User::factory()->inactive()->create(['name' => 'Fani Lama']);

        $response = $this->actingAs($this->kepsek)
            ->download('/ekspor/unduh?dari=2026-11-02&sampai=2026-11-11')
            ->assertDownload('rekap-presensi-2026-11-02_2026-11-11.xlsx');
        $sheets = $this->readXlsx($response);

        $this->assertSame(['Ringkasan', 'Rincian harian'], array_keys($sheets));
        $summary = collect($this->tableAfter($sheets['Ringkasan'], 'No'))->keyBy(1);
        $this->assertSame(['Budi Santoso', 'Eko Pindahan', 'Siti Aminah, S.Pd.'], $summary->keys()->all(), 'Termasuk yang sudah nonaktif tetapi punya data di rentang ini');
        $this->assertSame([8, 1, 1], [$summary['Siti Aminah, S.Pd.'][5], $summary['Siti Aminah, S.Pd.'][6], $summary['Siti Aminah, S.Pd.'][7]]);

        $daily = collect($sheets['Rincian harian'])->skip(1);
        $this->assertSame(8, $daily->where(0, 'Siti Aminah, S.Pd.')->count(), 'Hanya hari sekolah atau yang ada catatannya');

        $tendik = $this->download('/ekspor/unduh?dari=2026-11-02&sampai=2026-11-11&jenis=tendik&format=csv')->streamedContent();
        $this->assertStringContainsString('Budi Santoso', $tendik);
        $this->assertStringNotContainsString('Siti Aminah', $tendik);
        $this->assertStringStartsWith("\xEF\xBB\xBFNo;Nama;NIP;Jabatan;Jenis;\"Hari sekolah\"", $tendik);
    }

    public function test_range_is_validated(): void
    {
        $this->actingAs($this->kepsek)->from('/ekspor');

        $this->get('/ekspor/unduh?dari=2026-11-11&sampai=2026-11-01')
            ->assertRedirect('/ekspor')->assertSessionHasErrors(['sampai' => 'Tanggal akhir tidak boleh sebelum tanggal mulai.']);
        $this->get('/ekspor/unduh?dari=2025-01-01&sampai=2026-11-11')
            ->assertSessionHasErrors(['sampai' => 'Rentang paling panjang 1 tahun (366 hari).']);
        $this->get('/ekspor/unduh?sampai=2026-11-11')->assertSessionHasErrors('dari');
        $this->get('/ekspor/unduh?pegawai=99999&dari=2026-11-01&sampai=2026-11-11')->assertSessionHasErrors('pegawai');
        $this->get('/ekspor/unduh?dari=2026-11-01&sampai=2026-11-11&format=pdf')->assertSessionHasErrors('format');
        $this->get('/ekspor/unduh?dari=2025-11-11&sampai=2026-11-11')->assertOk();
    }

    public function test_staff_can_only_export_their_own_history(): void
    {
        $other = User::factory()->create(['name' => 'Budi Santoso']);
        $this->actingAs($this->siti);

        $this->get('/ekspor')->assertForbidden();
        $this->get("/ekspor/unduh?pegawai={$other->id}&dari=2026-11-02&sampai=2026-11-11")->assertForbidden();

        $this->get('/riwayat')->assertOk()->assertSee('Unduh riwayat')->assertSee('Semester ini');
        $csv = $this->download("/riwayat/unduh?pegawai={$other->id}&dari=2026-11-02&sampai=2026-11-11&format=csv")
            ->assertDownload('presensi-siti-aminah-spd-2026-11-02_2026-11-11.csv')
            ->streamedContent();
        $this->assertStringContainsString('Terlambat 40 menit', $csv);

        $this->from('/riwayat')->get('/riwayat/unduh?dari=2026-11-11&sampai=2026-11-01')->assertRedirect('/riwayat')->assertSessionHasErrors('sampai');
    }

    public function test_export_pages_and_shortcuts(): void
    {
        $this->actingAs($this->kepsek);

        $this->get('/ekspor')->assertOk()
            ->assertSee('Ekspor data presensi')
            ->assertSee('Semua guru & staf (ringkasan per orang)')
            ->assertSee('data-range-from="2026-07-01"', false) // semester ganjil
            ->assertSee('value="2026-11-01"', false)
            ->assertSee('value="2026-11-11"', false);
        $this->get("/ekspor?pegawai={$this->siti->id}")->assertSee('<option value="'.$this->siti->id.'" selected>', false);

        $this->get("/pegawai/{$this->siti->id}")->assertSee('/ekspor?pegawai='.$this->siti->id, false);
        $this->get('/rekap')->assertSee('Ekspor rentang tanggal');
        $this->get('/dashboard')->assertSee('Ekspor Data');
    }
}
