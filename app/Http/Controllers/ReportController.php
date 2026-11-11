<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Support\MonthlyReport;
use App\Support\SchoolSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function monthly(Request $request, SchoolSettings $settings): View
    {
        [$report, $classes] = $this->buildReport($request, $settings);

        return view('reports.monthly', [
            'report' => $report,
            'classes' => $classes,
            'legend' => MonthlyReport::legend(),
        ]);
    }

    /**
     * Unduh rekap sebagai CSV yang bisa langsung dibuka di Excel.
     * Memakai pemisah titik koma (;) karena Excel berbahasa Indonesia
     * memakai koma sebagai pemisah desimal.
     */
    public function export(Request $request, SchoolSettings $settings): StreamedResponse
    {
        [$report] = $this->buildReport($request, $settings);

        $filename = Str::slug($report->title()).'.csv';

        return response()->streamDownload(function () use ($report, $settings) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8 dengan benar.

            fputcsv($out, [$settings->schoolName()], ';');
            fputcsv($out, [$report->title()], ';');
            fputcsv($out, [], ';');

            fputcsv($out, [
                'No', 'NIS', 'Nama', 'Kelas',
                ...array_map(fn ($day) => (string) $day['day'], $report->days),
                'H', 'T', 'I', 'S', 'A', '% Hadir',
            ], ';');

            foreach ($report->rows as $index => $row) {
                fputcsv($out, [
                    $index + 1,
                    $row['student']->nis,
                    $row['student']->name,
                    $row['student']->schoolClass?->name,
                    ...array_map(fn ($cell) => $cell['code'], $row['cells']),
                    ...array_values($row['counts']),
                    $row['percentage'] === null ? '' : $row['percentage'].'%',
                ], ';');
            }

            fputcsv($out, [], ';');
            fputcsv($out, ['Keterangan: H = Hadir, T = Terlambat, I = Izin, S = Sakit, A = Alpa, – = tidak ada catatan (dihitung alpa)'], ';');
            fputcsv($out, ['Hari sekolah yang sudah berjalan: '.$report->schoolDaysElapsed], ';');

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: MonthlyReport, 1: Collection<int, SchoolClass>} */
    private function buildReport(Request $request, SchoolSettings $settings): array
    {
        $filters = $request->validate([
            'bulan' => ['nullable', 'date_format:Y-m'],
            'kelas' => ['nullable', 'integer'],
        ]);

        $classes = SchoolClass::orderBy('level')->orderBy('name')->get();
        $class = isset($filters['kelas']) ? $classes->firstWhere('id', (int) $filters['kelas']) : null;
        $month = CarbonImmutable::createFromFormat('!Y-m', $filters['bulan'] ?? now()->format('Y-m'));

        return [new MonthlyReport($month, $class, $settings), $classes];
    }
}
