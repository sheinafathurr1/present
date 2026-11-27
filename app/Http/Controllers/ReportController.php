<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeType;
use App\Support\MonthlyReport;
use App\Support\SchoolSettings;
use App\Support\Spreadsheet;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rekap kehadiran bulanan guru & staf.
 */
class ReportController extends Controller
{
    public function monthly(Request $request, SchoolSettings $settings): View
    {
        $month = HistoryController::month($request);
        $type = EmployeeType::tryFrom((string) $request->query('jenis'));

        return view('reports.monthly', [
            'report' => MonthlyReport::build($month, $settings, $type),
            'month' => $month,
            'type' => $type,
            'statuses' => AttendanceStatus::cases(),
        ]);
    }

    public function export(Request $request, SchoolSettings $settings): StreamedResponse
    {
        $month = HistoryController::month($request);
        $type = EmployeeType::tryFrom((string) $request->query('jenis'));
        $report = MonthlyReport::build($month, $settings, $type);
        $statuses = AttendanceStatus::cases();

        $filename = sprintf('rekap-presensi-%s%s.csv', $month->format('Y-m'), $type ? '-'.$type->value : '');

        return response()->streamDownload(function () use ($report, $statuses) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8.

            $header = ['Nama', 'NIP', 'Jabatan', 'Jenis'];
            foreach ($report['days'] as $day) {
                $header[] = $day['day'];
            }
            foreach ($statuses as $status) {
                $header[] = $status->label();
            }
            array_push($header, 'Tanpa keterangan', 'Total terlambat (menit)', 'Pulang awal (kali)', 'Kehadiran (%)');
            fputcsv($out, $header, ';');

            foreach ($report['rows'] as $row) {
                $user = $row['user'];
                $line = [Spreadsheet::csvSafe($user->name), $user->nip, Spreadsheet::csvSafe($user->position), $user->employee_type->label()];

                foreach ($report['days'] as $day) {
                    $attendance = $row['cells'][$day['date']];
                    $line[] = match (true) {
                        $attendance !== null => $attendance->status->code(),
                        ! $day['school'] => 'L',
                        $day['future'] || $day['today'] => '',
                        default => '-',
                    };
                }
                foreach ($statuses as $status) {
                    $line[] = $row['totals'][$status->value];
                }
                array_push($line, $row['missing'], $row['late_minutes'], $row['early_count'], $row['percentage']);
                fputcsv($out, $line, ';');
            }

            fputcsv($out, [], ';');
            fputcsv($out, ['Keterangan: H Hadir, T Terlambat, I Izin, S Sakit, C Cuti, D Dinas luar, A Alpa, L Libur, - tidak ada catatan'], ';');
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
