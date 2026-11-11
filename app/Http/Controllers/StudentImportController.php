<?php

namespace App\Http\Controllers;

use App\Enums\DisabilityType;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Impor data siswa dari file CSV (bisa disimpan dari Excel: "CSV UTF-8").
 * Siswa dengan NIS yang sudah ada akan diperbarui, yang belum ada ditambahkan.
 */
class StudentImportController extends Controller
{
    public const COLUMNS = [
        'nis', 'nama', 'nama_panggilan', 'jenis_kelamin', 'kelas', 'jenjang',
        'jenis_disabilitas', 'uid_kartu', 'nama_wali', 'telepon_wali',
    ];

    public function create(): View
    {
        return view('students.import', ['columns' => self::COLUMNS]);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::COLUMNS, ';');
            fputcsv($out, ['2024001', 'Budi Santoso', 'Budi', 'L', 'Kelas 1A', 'SDLB', 'A', '', 'Siti Aminah', '081234567890'], ';');
            fputcsv($out, ['2024002', 'Rina Wulandari', 'Rina', 'P', 'Kelas 1A', 'SDLB', 'B', '0004283792', 'Agus Salim', '081298765432'], ';');
            fclose($out);
        }, 'template-impor-siswa.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $firstLine = (string) fgets($handle);
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
        rewind($handle);

        $header = fgetcsv($handle, null, $delimiter, '"', '');
        $header = array_map(
            fn ($column) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $column))),
            $header ?: [],
        );

        if (! in_array('nis', $header, true) || ! in_array('nama', $header, true)) {
            fclose($handle);

            return back()->with('error', 'Baris pertama file harus berisi judul kolom, minimal "nis" dan "nama". Silakan pakai template.');
        }

        $created = 0;
        $updated = 0;
        $errors = [];
        $line = 1;

        DB::transaction(function () use ($handle, $delimiter, $header, &$created, &$updated, &$errors, &$line) {
            while (($values = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
                $line++;

                if ($values === [null] || count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }

                $row = array_combine($header, array_pad(array_slice($values, 0, count($header)), count($header), null));
                $row = array_map(fn ($value) => trim((string) $value), $row);

                if (($row['nis'] ?? '') === '' || ($row['nama'] ?? '') === '') {
                    $errors[] = "Baris {$line}: NIS dan nama wajib diisi.";

                    continue;
                }

                $uid = Student::normalizeUid($row['uid_kartu'] ?? null);

                if ($uid && Student::where('rfid_uid', $uid)->where('nis', '!=', $row['nis'])->exists()) {
                    $errors[] = "Baris {$line}: kartu {$uid} sudah dipakai siswa lain, kartu tidak disimpan.";
                    $uid = null;
                }

                $class = null;

                if (($row['kelas'] ?? '') !== '') {
                    $class = SchoolClass::firstOrCreate(
                        ['name' => $row['kelas']],
                        ['level' => in_array(strtoupper($row['jenjang'] ?? ''), SchoolClass::LEVELS, true) ? strtoupper($row['jenjang']) : null],
                    );
                }

                $gender = strtoupper(substr($row['jenis_kelamin'] ?? '', 0, 1));
                $disability = DisabilityType::fromInput($row['jenis_disabilitas'] ?? null);

                $student = Student::firstOrNew(['nis' => $row['nis']]);
                $student->fill(array_filter([
                    'name' => $row['nama'],
                    'nickname' => $row['nama_panggilan'] ?? null,
                    'gender' => in_array($gender, ['L', 'P'], true) ? $gender : null,
                    'school_class_id' => $class?->id,
                    'disability_type' => $disability,
                    'guardian_name' => $row['nama_wali'] ?? null,
                    'guardian_phone' => $row['telepon_wali'] ?? null,
                    'rfid_uid' => $uid,
                ], fn ($value) => $value !== null && $value !== ''));

                if (! $student->exists && $disability) {
                    $student->feedback_mode = $disability->suggestedFeedback();
                }

                $student->exists ? $updated++ : $created++;
                $student->save();
            }
        });

        fclose($handle);

        return redirect()->route('students.index')
            ->with('success', "Impor selesai: {$created} siswa baru, {$updated} siswa diperbarui.")
            ->with('import_errors', $errors);
    }
}
