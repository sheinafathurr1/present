<?php

namespace App\Http\Controllers;

use App\Enums\EmployeeType;
use App\Models\User;
use App\Services\AttendanceExporter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor data presensi per guru/staf (atau semua orang) untuk rentang tanggal pilihan.
 */
class ExportController extends Controller
{
    public function create(Request $request): View
    {
        return view('exports.create', [
            'employees' => User::query()
                ->where(fn ($q) => $q->where('must_attend', true)->orWhereHas('attendances'))
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(['id', 'name', 'position', 'is_active']),
            'selected' => $request->integer('pegawai') ?: null,
            'type' => EmployeeType::tryFrom((string) $request->query('jenis')),
            'presets' => self::presets(),
        ]);
    }

    /** Pimpinan & admin: satu orang atau semua orang. */
    public function download(Request $request, AttendanceExporter $exporter): BinaryFileResponse|StreamedResponse
    {
        $request->validate([
            'pegawai' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'jenis' => ['nullable', Rule::enum(EmployeeType::class)],
        ], [], ['pegawai' => 'guru/staf']);
        [$from, $to, $format] = $this->range($request);

        if ($id = $request->integer('pegawai')) {
            return $exporter->person(User::findOrFail($id), $from, $to, $format);
        }

        $type = EmployeeType::tryFrom((string) $request->input('jenis'));
        $users = User::query()
            ->where(fn ($q) => $q->attending()->orWhereHas('attendances', fn ($a) => $a->whereBetween('date', [$from->toDateString(), $to->toDateString()])))
            ->when($type, fn ($q) => $q->where('employee_type', $type))
            ->orderBy('name')
            ->get();

        return $exporter->everyone($users, $from, $to, $format, $type ? mb_strtolower($type->label()) : null);
    }

    /** Guru/staf: riwayat sendiri. */
    public function own(Request $request, AttendanceExporter $exporter): BinaryFileResponse|StreamedResponse
    {
        [$from, $to, $format] = $this->range($request);

        return $exporter->person($request->user(), $from, $to, $format);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string} */
    private function range(Request $request): array
    {
        $data = $request->validate([
            'dari' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01'],
            'sampai' => ['required', 'date_format:Y-m-d', 'after_or_equal:dari', 'before_or_equal:2100-12-31'],
            'format' => ['nullable', Rule::in(['xlsx', 'csv'])],
        ], [
            'sampai.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal mulai.',
        ], ['dari' => 'tanggal mulai', 'sampai' => 'tanggal akhir']);

        $from = CarbonImmutable::parse($data['dari'])->startOfDay();
        $to = CarbonImmutable::parse($data['sampai'])->startOfDay();

        if ($from->diffInDays($to) + 1 > AttendanceExporter::MAX_DAYS) {
            throw ValidationException::withMessages(['sampai' => 'Rentang paling panjang 1 tahun ('.AttendanceExporter::MAX_DAYS.' hari).']);
        }

        return [$from, $to, $data['format'] ?? 'xlsx'];
    }

    /**
     * Pilihan rentang cepat.
     *
     * @return list<array{label: string, from: string, to: string}>
     */
    public static function presets(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $semesterStart = $today->month >= 7 ? $today->setDate($today->year, 7, 1) : $today->setDate($today->year, 1, 1);
        $lastMonth = $today->subMonthNoOverflow();

        return [
            ['label' => 'Bulan ini', 'from' => $today->startOfMonth()->toDateString(), 'to' => $today->toDateString()],
            ['label' => 'Bulan lalu', 'from' => $lastMonth->startOfMonth()->toDateString(), 'to' => $lastMonth->endOfMonth()->toDateString()],
            ['label' => '3 bulan terakhir', 'from' => $today->subMonthsNoOverflow(2)->startOfMonth()->toDateString(), 'to' => $today->toDateString()],
            ['label' => 'Semester ini', 'from' => $semesterStart->toDateString(), 'to' => $today->toDateString()],
            ['label' => 'Tahun ini', 'from' => $today->startOfYear()->toDateString(), 'to' => $today->toDateString()],
        ];
    }
}
