<?php

namespace App\Http\Controllers;

use App\Exceptions\ImportException;
use App\Services\EmployeeImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
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

    private function xlsxTemplate(): BinaryFileResponse
    {
        $columns = EmployeeImporter::COLUMNS;
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';

        $header = new Style(fontBold: true, fontColor: 'FFFFFF', backgroundColor: '1F5FBF');
        $text = new Style(format: '@');
        $bold = new Style(fontBold: true);
        $wrap = new Style(shouldWrapText: true);

        $writer = new Writer;
        $writer->openToFile($path);

        // Lembar 1: tempat mengisi data.
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Data guru & staf');
        $sheet->setSheetView(new SheetView(freezeRow: 2));
        $widths = ['name' => 34, 'username' => 18, 'password' => 14, 'employee_type' => 10, 'position' => 26, 'nip' => 22, 'phone' => 16, 'role' => 12, 'must_attend' => 15, 'work_start' => 11, 'work_end' => 11];
        $index = 1;
        foreach (array_keys($columns) as $key) {
            $sheet->setColumnWidth($widths[$key], $index++);
        }

        $writer->addRow(Row::fromValuesWithStyle(array_column($columns, 0), $header));

        // Baris kosong yang sudah berformat Teks di kolom NIP, HP, dsb. OpenSpout tidak menulis
        // baris yang semua selnya kosong, jadi kolom nama diberi teks kosong.
        for ($i = 0; $i < self::TEMPLATE_ROWS; $i++) {
            $writer->addRow(new Row(array_map(fn (string $key) => match (true) {
                $key === 'name' => new StringCell('', $text),
                in_array($key, self::TEXT_COLUMNS, true) => new EmptyCell(null, $text),
                default => new EmptyCell(null, null),
            }, array_keys($columns))));
        }

        // Lembar 2: petunjuk & contoh.
        $guide = $writer->addNewSheetAndMakeItCurrent();
        $guide->setName('Petunjuk');
        $guide->setColumnWidth(20, 1);
        $guide->setColumnWidth(90, 2);
        $writer->addRow(Row::fromValuesWithStyle(['Cara mengisi'], new Style(fontBold: true, fontSize: 14)));
        foreach ([
            '1. Isi satu baris untuk setiap guru/staf di lembar "Data guru & staf". Jangan mengubah baris judul kolom.',
            '2. Hanya kolom "nama" yang wajib. Username & kata sandi yang kosong dibuat otomatis dan ditampilkan setelah impor.',
            '3. Kolom NIP dan no_hp sudah berformat Teks. Bila menyalin dari file lain, tempel dengan "Paste Values" agar formatnya tetap Teks.',
            '4. Simpan file (.xlsx), lalu unggah di menu Guru & Staf → Impor Excel. Data diperiksa dan ditampilkan dulu sebelum disimpan.',
            '5. Mengimpor ulang dengan username/NIP yang sama memperbarui data akun tersebut; sel kosong tidak mengubah datanya.',
        ] as $step) {
            $writer->addRow(Row::fromValues([$step]));
        }
        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValuesWithStyle(['Kolom', 'Keterangan'], $bold));
        foreach ($columns as [$title, $description]) {
            $writer->addRow(Row::fromValuesWithStyles([$title, $description], [1 => $wrap]));
        }
        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValuesWithStyle(['Contoh (jangan disalin ke lembar data)'], $bold));
        $writer->addRow(Row::fromValuesWithStyle(array_column($columns, 0), $bold));
        foreach ([
            ['Drs. Bambang Supriyadi, M.Pd.', 'kepsek', '', 'guru', 'Kepala Sekolah', '196805121994031004', '081211112222', 'pimpinan', 'ya', '', ''],
            ['Siti Aminah, S.Pd.', '', '', 'guru', 'Guru Kelas 1', '198001012005012001', '081234567890', '', '', '', ''],
            ['Joko Susilo', '', 'rahasia123', 'tendik', 'Staf Tata Usaha', '', '081298765432', '', 'ya', '07:30', '15:30'],
        ] as $example) {
            $writer->addRow(Row::fromValuesWithStyle($example, $text));
        }

        $writer->close();

        return response()->download($path, 'template-impor-guru-staf.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    private function csvTemplate(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_column(EmployeeImporter::COLUMNS, 0), ';', '"', '');
            fclose($out);
        }, 'template-impor-guru-staf.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
