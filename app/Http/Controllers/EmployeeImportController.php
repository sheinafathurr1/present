<?php

namespace App\Http\Controllers;

use App\Exceptions\ImportException;
use App\Services\EmployeeImporter;
use App\Support\Spreadsheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Comment\Comment;
use OpenSpout\Common\Entity\Comment\TextRun;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Impor akun guru & staf dari Excel/CSV (admin).
 *
 * Alurnya dua langkah: unggah → pratinjau (semua baris diperiksa) → simpan.
 * File disimpan sementara di folder privat selama pratinjau, lalu dihapus.
 */
class EmployeeImportController extends Controller
{
    private const DISK = 'local';

    private const FOLDER = 'impor-pegawai';

    private const SESSION_KEY = 'employee_import';

    /** Kolom yang diformat Teks di template agar angka & username tidak diubah Excel. */
    private const TEXT_COLUMNS = ['username', 'password', 'nip', 'phone', 'work_start', 'work_end'];

    /** Banyak baris kosong berformat yang disiapkan di template. */
    private const TEMPLATE_ROWS = 300;

    public function create(): View
    {
        return view('employees.import', ['columns' => EmployeeImporter::COLUMNS]);
    }

    public function template(Request $request): BinaryFileResponse|StreamedResponse
    {
        return $request->query('format') === 'csv' ? $this->csvTemplate() : $this->xlsxTemplate();
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx,csv,txt', 'max:2048'],
        ], [
            'file.required' => 'Pilih file Excel (.xlsx) atau CSV.',
            'file.extensions' => 'Gunakan file Excel (.xlsx) atau CSV. File .xls lama: buka di Excel lalu simpan sebagai "Excel Workbook (.xlsx)".',
            'file.max' => 'File paling besar 2 MB.',
        ], ['file' => 'file']);

        $this->forgetPending($request);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $token = Str::random(40);
        $path = $file->storeAs(self::FOLDER, $token.'.'.$extension, self::DISK);

        $importer = $this->importer($request);

        try {
            $analysis = $importer->analyze($importer->read(Storage::disk(self::DISK)->path($path), $extension));
        } catch (ImportException $e) {
            Storage::disk(self::DISK)->delete($path);

            return redirect()->route('employees.import')->with('error', $e->getMessage());
        }

        $request->session()->put(self::SESSION_KEY, ['token' => $token, 'path' => $path, 'name' => $file->getClientOriginalName()]);

        return view('employees.import-preview', [
            'analysis' => $analysis,
            'token' => $token,
            'fileName' => $file->getClientOriginalName(),
        ]);
    }

    public function store(Request $request): View|RedirectResponse
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        if (! $pending || ! hash_equals($pending['token'], (string) $request->input('token'))
            || ! Storage::disk(self::DISK)->exists($pending['path'])) {
            return redirect()->route('employees.import')
                ->with('error', 'Data impor sudah kedaluwarsa atau sudah disimpan. Silakan unggah ulang file-nya.');
        }

        $importer = $this->importer($request);

        try {
            // Diperiksa ulang: data bisa berubah sejak pratinjau.
            $analysis = $importer->analyze($importer->read(Storage::disk(self::DISK)->path($pending['path']), pathinfo($pending['path'], PATHINFO_EXTENSION)));
            if ($analysis['invalid'] > 0) {
                throw new ImportException('Data berubah sejak pratinjau dan sekarang ada baris yang perlu diperbaiki. Silakan unggah ulang.');
            }
            $accounts = $importer->import($analysis);
        } catch (ImportException $e) {
            return redirect()->route('employees.import')->with('error', $e->getMessage());
        } finally {
            $this->forgetPending($request);
        }

        // Ditampilkan langsung (bukan redirect) agar kata sandi tidak disimpan di sesi.
        return view('employees.import-result', [
            'accounts' => $accounts,
            'created' => array_values(array_filter($accounts, fn ($a) => $a['action'] === 'baru')),
            'updated' => array_values(array_filter($accounts, fn ($a) => $a['action'] === 'perbarui')),
        ]);
    }

    private function importer(Request $request): EmployeeImporter
    {
        return new EmployeeImporter($request->user());
    }

    /** Hapus file pratinjau sebelumnya, juga sisa file lama yang terbengkalai. */
    private function forgetPending(Request $request): void
    {
        $disk = Storage::disk(self::DISK);

        if ($pending = $request->session()->pull(self::SESSION_KEY)) {
            $disk->delete($pending['path']);
        }

        foreach ($disk->files(self::FOLDER) as $file) {
            if ($disk->lastModified($file) < now()->subDay()->getTimestamp()) {
                $disk->delete($file);
            }
        }
    }

    /** Langkah singkat yang ditulis di atas baris judul kolom (Excel & CSV). */
    private const GUIDE = [
        'CARA MENGISI: satu baris untuk setiap guru/staf, mulai tepat di bawah baris judul kolom (baris biru). Jangan mengubah judul kolom.',
        'Hanya kolom "nama" yang wajib. Username & kata sandi yang kosong dibuat otomatis dan ditampilkan setelah impor. Baris petunjuk ini boleh dibiarkan.',
    ];

    private function xlsxTemplate(): BinaryFileResponse
    {
        $columns = EmployeeImporter::COLUMNS;
        $keys = array_keys($columns);
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';

        $title = new Style(fontBold: true, fontSize: 14);
        $guide = new Style(fontColor: '344054');
        $hint = new Style(fontItalic: true, fontSize: 10, fontColor: '5A6372', backgroundColor: 'EEF2F8', shouldWrapText: true, cellVerticalAlignment: CellVerticalAlignment::TOP);
        $hintRequired = new Style(fontItalic: true, fontBold: true, fontSize: 10, fontColor: 'B42318', backgroundColor: 'FDECEA', shouldWrapText: true, cellVerticalAlignment: CellVerticalAlignment::TOP);
        $header = new Style(fontBold: true, fontColor: 'FFFFFF', backgroundColor: '1F5FBF');
        $headerRequired = new Style(fontBold: true, fontColor: 'FFFFFF', backgroundColor: '0B3D91');
        $text = new Style(format: '@');
        $bold = new Style(fontBold: true);
        $wrap = new Style(shouldWrapText: true, cellVerticalAlignment: CellVerticalAlignment::TOP);

        $writer = new Writer;
        $writer->openToFile($path);

        // Lembar 1: petunjuk singkat (baris 1–3), judul kolom (baris 4), lalu tempat mengisi data.
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Data guru & staf');
        $sheet->setSheetView(new SheetView(freezeRow: 5));
        $widths = ['name' => 34, 'username' => 20, 'password' => 18, 'employee_type' => 14, 'position' => 24, 'nip' => 22, 'phone' => 16, 'role' => 20, 'must_attend' => 16, 'work_start' => 17, 'work_end' => 17];
        foreach ($keys as $index => $key) {
            $sheet->setColumnWidth($widths[$key], $index + 1);
        }

        $writer->addRow(Spreadsheet::row([self::GUIDE[0]], $title));
        $writer->addRow(Spreadsheet::row([self::GUIDE[1].' Arahkan kursor ke judul kolom untuk penjelasan; contoh ada di lembar "Petunjuk".'], $guide));
        $writer->addRow(Spreadsheet::row(
            array_column($columns, 3),
            $hint,
            [0 => $hintRequired],
            height: 42,
        ));
        $writer->addRow(new Row(array_map(fn (string $key) => new StringCell(
            $columns[$key][0],
            $key === 'name' ? $headerRequired : $header,
            new Comment(width: '220pt', height: '80pt', textRuns: [
                new TextRun($columns[$key][0], bold: true),
                new TextRun("\n".$columns[$key][1]."\nContoh: ".$columns[$key][4]),
            ]),
        ), $keys)));

        // Baris kosong yang sudah berformat Teks di kolom NIP, HP, dsb. OpenSpout tidak menulis
        // baris yang semua selnya kosong, jadi kolom nama diberi teks kosong.
        for ($i = 0; $i < self::TEMPLATE_ROWS; $i++) {
            $writer->addRow(new Row(array_map(fn (string $key) => match (true) {
                $key === 'name' => new StringCell('', $text),
                in_array($key, self::TEXT_COLUMNS, true) => new EmptyCell(null, $text),
                default => new EmptyCell(null, null),
            }, $keys)));
        }

        // Lembar 2: petunjuk lengkap & contoh.
        $guideSheet = $writer->addNewSheetAndMakeItCurrent();
        $guideSheet->setName('Petunjuk');
        foreach ([18, 10, 70, 30] as $index => $width) {
            $guideSheet->setColumnWidth($width, $index + 1);
        }
        $writer->addRow(Spreadsheet::row(['Cara mengisi template impor guru & staf'], $title));
        foreach ([
            '1. Buka lembar "Data guru & staf". Isi satu baris untuk setiap guru/staf, mulai baris 5 (tepat di bawah judul kolom yang berwarna biru).',
            '2. Hanya kolom "nama" yang wajib. Kolom lain boleh dikosongkan.',
            '3. Username & kata sandi yang kosong dibuat otomatis. Setelah impor, daftarnya ditampilkan sekali dan bisa dicetak sebagai kartu akun.',
            '4. Kolom username, kata_sandi, nip, no_hp, jam_masuk, dan jam_pulang sudah berformat Teks agar NIP 18 digit dan angka 0 di depan nomor HP tidak berubah.',
            '   Bila menyalin dari file lain, tempel dengan klik kanan → "Paste Values" (Tempel Nilai) agar formatnya tetap Teks.',
            '5. Simpan file (.xlsx), lalu unggah di menu Guru & Staf → Impor Excel. Data diperiksa dan ditampilkan dulu; belum ada yang tersimpan sebelum Anda menekan Simpan.',
            '6. Mengimpor ulang file yang sama aman: akun dicocokkan lewat username, NIP, atau nama, lalu diperbarui (sel kosong tidak menghapus data).',
        ] as $step) {
            $writer->addRow(Spreadsheet::row([$step]));
        }
        $writer->addRow(Spreadsheet::row(['']));
        $writer->addRow(Spreadsheet::row(['Kolom', 'Wajib?', 'Keterangan', 'Contoh isian'], $bold));
        foreach ($columns as $key => [$columnTitle, $description, , , $example]) {
            $writer->addRow(Spreadsheet::row([$columnTitle, $key === 'name' ? 'Wajib' : 'Boleh kosong', $description, $example], styles: [2 => $wrap, 3 => $text]));
        }
        $writer->addRow(Spreadsheet::row(['']));
        $writer->addRow(Spreadsheet::row(['Contoh baris lengkap (jangan disalin ke lembar data)'], $bold));
        $writer->addRow(Spreadsheet::row(array_column($columns, 0), $bold));
        foreach ([
            ['Drs. Bambang Supriyadi, M.Pd.', 'kepsek', '', 'guru', 'Kepala Sekolah', '196805121994031004', '081211112222', 'pimpinan', 'ya', '', ''],
            ['Siti Aminah, S.Pd.', '', '', 'guru', 'Guru Kelas 1', '198001012005012001', '081234567890', '', '', '', ''],
            ['Joko Susilo', '', 'rahasia123', 'tendik', 'Staf Tata Usaha', '', '081298765432', '', 'ya', '07:30', '15:30'],
        ] as $example) {
            $writer->addRow(Spreadsheet::row($example, $text));
        }

        $writer->close();

        return response()->download($path, 'template-impor-guru-staf.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    private function csvTemplate(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $columns = EmployeeImporter::COLUMNS;
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [self::GUIDE[0]], ';', '"', '');
            fputcsv($out, [self::GUIDE[1].' Untuk NIP 18 digit lebih aman memakai template Excel; bila mengisi CSV ini di Excel, ubah format kolom nip & no_hp menjadi Teks sebelum mengetik.'], ';', '"', '');
            fputcsv($out, array_column($columns, 3), ';', '"', '');
            fputcsv($out, array_column($columns, 0), ';', '"', '');
            fclose($out);
        }, 'template-impor-guru-staf.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
