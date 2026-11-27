<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\User;
use App\Support\MonthlyReport;
use App\Support\SchoolSettings;
use App\Support\Spreadsheet;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\Common\Entity\Sheet;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor presensi untuk rentang tanggal tertentu, ke Excel (.xlsx) atau CSV.
 *
 *  - Satu orang: ringkasan, rincian setiap tanggal, dan daftar pengajuan izin.
 *  - Semua orang: ringkasan per orang dan rincian harian per orang.
 */
class AttendanceExporter
{
    public const MAX_DAYS = 366;

    private const DAY_HEADER = ['Tanggal', 'Hari', 'Status', 'Masuk', 'Pulang', 'Terlambat (menit)', 'Pulang awal (menit)', 'Jarak masuk (m)', 'Jarak pulang (m)', 'Keterangan'];

    public function __construct(private SchoolSettings $settings) {}

    /* ---------- Satu orang ---------- */

    public function person(User $user, CarbonImmutable $from, CarbonImmutable $to, string $format): BinaryFileResponse|StreamedResponse
    {
        $report = MonthlyReport::period($from, $to, $this->settings, collect([$user]));
        $row = $report['rows'][0];
        $days = array_values(array_filter($report['days'], fn (array $day) => ! $day['future']));
        $leaves = $user->leaveRequests()->overlapping($from->toDateString(), $to->toDateString())->orderBy('start_date')->get();

        $name = sprintf('presensi-%s-%s_%s', Str::slug($user->name) ?: 'pegawai', $from->toDateString(), $to->toDateString());

        if ($format === 'csv') {
            return $this->csv($name, array_merge(
                [self::DAY_HEADER],
                array_map(fn (array $day) => $this->dayValues($day, $row['cells'][$day['date']], dateAsText: true), $days),
            ));
        }

        return $this->xlsx($name, function (Writer $writer, array $s) use ($user, $from, $to, $row, $days, $leaves) {
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Presensi');
            $this->widths($sheet, [14, 11, 18, 9, 9, 12, 12, 11, 11, 60]);

            $writer->addRow(Spreadsheet::row(['Laporan presensi guru & staf'], $s['title']));
            foreach ([
                ['Nama', $user->name],
                ['NIP', $user->nip ?: '—'],
                ['Jabatan', trim(($user->position ? $user->position.' · ' : '').$user->employee_type->label())],
                ['Periode', $this->periodLabel($from, $to)],
                ['Sekolah', $this->settings->schoolName()],
            ] as $info) {
                $writer->addRow(Spreadsheet::row($info, styles: [$s['label']]));
            }

            $writer->addRow(Spreadsheet::row(['']));
            $writer->addRow(Spreadsheet::row(['Ringkasan'], $s['bold']));
            $writer->addRow(Spreadsheet::row(self::summaryHeader(), $s['header']));
            $writer->addRow(Spreadsheet::row(self::summaryValues($row)));

            $writer->addRow(Spreadsheet::row(['']));
            $writer->addRow(Spreadsheet::row(['Rincian per hari'], $s['bold']));
            $writer->addRow(Spreadsheet::row(self::DAY_HEADER, $s['header']));
            foreach ($days as $day) {
                $attendance = $row['cells'][$day['date']];
                $writer->addRow(Spreadsheet::row(
                    $this->dayValues($day, $attendance),
                    $day['school'] || $attendance ? null : $s['muted'],
                    [0 => $day['school'] || $attendance ? $s['date'] : $s['mutedDate']],
                ));
            }

            // Lembar 2: pengajuan izin dalam periode.
            $sheet = $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName('Pengajuan izin');
            $this->widths($sheet, [12, 14, 14, 10, 60, 18, 10]);
            $writer->addRow(Spreadsheet::row(['Jenis', 'Mulai', 'Selesai', 'Hari kalender', 'Keterangan', 'Diajukan', 'Lampiran'], $s['header']));
            foreach ($leaves as $leave) {
                $writer->addRow(Spreadsheet::row([
                    $leave->type->label(),
                    CarbonImmutable::parse($leave->start_date),
                    CarbonImmutable::parse($leave->end_date),
                    $leave->calendarDays(),
                    $leave->reason,
                    $leave->created_at->toImmutable(),
                    $leave->attachment_path ? 'Ada' : '',
                ], styles: [1 => $s['date'], 2 => $s['date'], 5 => $s['dateTime']]));
            }
            if ($leaves->isEmpty()) {
                $writer->addRow(Spreadsheet::row(['Tidak ada pengajuan pada periode ini.'], $s['muted']));
            }
        });
    }

    /* ---------- Semua orang ---------- */

    /** @param  Collection<int, User>  $users */
    public function everyone(Collection $users, CarbonImmutable $from, CarbonImmutable $to, string $format, ?string $groupLabel = null): BinaryFileResponse|StreamedResponse
    {
        $report = MonthlyReport::period($from, $to, $this->settings, $users);
        $name = sprintf('rekap-presensi-%s_%s', $from->toDateString(), $to->toDateString());
        $summaryHeader = ['No', 'Nama', 'NIP', 'Jabatan', 'Jenis', ...self::summaryHeader()];
        $summaryRow = fn (array $row, int $index) => [
            $index + 1, $row['user']->name, $row['user']->nip, $row['user']->position, $row['user']->employee_type->label(),
            ...self::summaryValues($row),
        ];

        if ($format === 'csv') {
            return $this->csv($name, array_merge(
                [$summaryHeader],
                array_map($summaryRow, $report['rows'], array_keys($report['rows'])),
            ));
        }

        return $this->xlsx($name, function (Writer $writer, array $s) use ($report, $from, $to, $groupLabel, $summaryHeader, $summaryRow) {
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Ringkasan');
            $sheet->setSheetView(new SheetView(freezeRow: 6));
            $this->widths($sheet, [5, 32, 21, 24, 12, 12, 8, 10, 7, 7, 7, 10, 7, 15, 16, 16, 13]);

            $writer->addRow(Spreadsheet::row(['Rekap presensi '.($groupLabel ?? 'guru & staf')], $s['title']));
            $writer->addRow(Spreadsheet::row(['Periode', $this->periodLabel($from, $to)], styles: [$s['label']]));
            $writer->addRow(Spreadsheet::row(['Sekolah', $this->settings->schoolName()], styles: [$s['label']]));
            $writer->addRow(Spreadsheet::row(['Hari sekolah', count(array_filter($report['days'], fn ($day) => $day['school'] && ! $day['future']))], styles: [$s['label']]));
            $writer->addRow(Spreadsheet::row(['']));
            $writer->addRow(Spreadsheet::row($summaryHeader, $s['header']));
            foreach ($report['rows'] as $index => $row) {
                $writer->addRow(Spreadsheet::row($summaryRow($row, $index)));
            }

            $sheet = $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName('Rincian harian');
            $sheet->setSheetView(new SheetView(freezeRow: 2));
            $this->widths($sheet, [32, 21, ...[14, 11, 18, 9, 9, 12, 12, 11, 11, 60]]);
            $writer->addRow(Spreadsheet::row(['Nama', 'NIP', ...self::DAY_HEADER], $s['header']));
            foreach ($report['rows'] as $row) {
                foreach ($report['days'] as $day) {
                    $attendance = $row['cells'][$day['date']];
                    if ($day['future'] || (! $day['school'] && ! $attendance)) {
                        continue;
                    }
                    $writer->addRow(Spreadsheet::row(
                        [$row['user']->name, $row['user']->nip, ...$this->dayValues($day, $attendance)],
                        styles: [2 => $s['date']],
                    ));
                }
            }
        });
    }

    /* ---------- Isi baris ---------- */

    /** @return list<string> */
    private static function summaryHeader(): array
    {
        return [
            'Hari sekolah',
            ...array_map(fn (AttendanceStatus $status) => $status->label(), AttendanceStatus::cases()),
            'Tanpa keterangan', 'Total terlambat (menit)', 'Pulang awal (kali)', 'Kehadiran (%)',
        ];
    }

    /** @return list<int> */
    private static function summaryValues(array $row): array
    {
        return [
            $row['school_days'],
            ...array_map(fn (AttendanceStatus $status) => $row['totals'][$status->value], AttendanceStatus::cases()),
            $row['missing'], $row['late_minutes'], $row['early_count'], $row['percentage'],
        ];
    }

    /** @return list<mixed> */
    private function dayValues(array $day, ?Attendance $attendance, bool $dateAsText = false): array
    {
        $date = CarbonImmutable::parse($day['date']);

        $status = match (true) {
            $attendance !== null => $attendance->status->label(),
            $day['holiday'] !== null => 'Libur',
            ! $day['school'] => 'Bukan hari sekolah',
            $day['today'] => 'Belum presensi',
            default => 'Tidak ada catatan',
        };

        $notes = [];
        if ($day['holiday']) {
            $notes[] = $day['holiday'];
        }
        if ($attendance) {
            array_push($notes, ...$attendance->remarks($day['today']));
            if ($attendance->leaveRequest) {
                $notes[] = $attendance->leaveRequest->reason;
            }
            if ($attendance->source === Attendance::SOURCE_ADMIN) {
                $notes[] = 'Koreksi admin: '.$attendance->note;
            } elseif ($attendance->source === Attendance::SOURCE_SYSTEM) {
                $notes[] = 'Tidak presensi & tidak mengajukan izin';
            }
        }

        $values = [
            $dateAsText ? $date->toDateString() : $date,
            $date->translatedFormat('l'),
            $status,
            $attendance?->check_in_at?->format('H:i'),
            $attendance?->check_out_at?->format('H:i'),
            $attendance?->late_minutes ?: null,
            $attendance?->early_minutes ?: null,
            $attendance?->check_in_distance,
            $attendance?->check_out_distance,
            implode(' · ', $notes),
        ];

        return $dateAsText ? array_map(fn ($value) => Spreadsheet::csvSafe($value), $values) : $values;
    }

    private function periodLabel(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return $from->translatedFormat('d F Y').' – '.$to->translatedFormat('d F Y');
    }

    /* ---------- Penulisan file ---------- */

    /** @param  callable(Writer, array<string, Style>): void  $fill */
    private function xlsx(string $name, callable $fill): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'eks').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);

        $fill($writer, [
            'title' => new Style(fontBold: true, fontSize: 14),
            'bold' => new Style(fontBold: true),
            'label' => new Style(fontColor: '5A6372'),
            'header' => new Style(fontBold: true, fontColor: 'FFFFFF', backgroundColor: '1F5FBF', shouldWrapText: true),
            'muted' => new Style(fontColor: '8A93A3'),
            'date' => new Style(format: 'dd/mm/yyyy'),
            'mutedDate' => new Style(fontColor: '8A93A3', format: 'dd/mm/yyyy'),
            'dateTime' => new Style(format: 'dd/mm/yyyy hh:mm'),
        ]);

        $writer->close();

        return response()->download($path, $name.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /** @param  list<list<mixed>>  $rows */
    private function csv(string $name, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8.
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($value) => Spreadsheet::csvSafe($value), $row), ';', '"', '');
            }
            fclose($out);
        }, $name.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @param  list<float|int>  $widths */
    private function widths(Sheet $sheet, array $widths): void
    {
        foreach ($widths as $index => $width) {
            $sheet->setColumnWidth($width, $index + 1);
        }
    }
}
