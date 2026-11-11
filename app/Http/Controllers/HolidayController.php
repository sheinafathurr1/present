<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Services\NationalHolidaySync;
use App\Support\SchoolSettings;
use App\Support\YearCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class HolidayController extends Controller
{
    public function index(Request $request, SchoolSettings $settings): View
    {
        $year = max(2000, min(2100, (int) $request->integer('tahun', now()->year)));
        $holidays = Holiday::inYear($year)->orderBy('date')->get();
        $syncedAt = $settings->get('holidays_synced_at');

        return view('holidays.index', [
            'year' => $year,
            'holidays' => $holidays,
            'months' => YearCalendar::build($year, $holidays->keyBy('date'), $settings),
            'weekdays' => YearCalendar::WEEKDAYS,
            'offCount' => $holidays->where('is_school_day', false)->count(),
            'hasNational' => $holidays->where('is_auto', true)->isNotEmpty(),
            'sync' => [
                'at' => $syncedAt ? Carbon::parse($syncedAt) : null,
                'source' => $settings->get('holidays_source'),
                'error' => $settings->get('holidays_error'),
            ],
            'jointOff' => $settings->jointHolidaysAreOff(),
        ]);
    }

    /** Tambah libur sekolah (satu hari atau rentang tanggal). */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
            'name' => ['required', 'string', 'max:150'],
        ]);

        $start = CarbonImmutable::parse($data['date']);
        $end = isset($data['end_date']) ? CarbonImmutable::parse($data['end_date']) : $start;

        if ($start->diffInDays($end) > 60) {
            return back()->withInput()->with('error', 'Rentang libur maksimal 60 hari sekali simpan.');
        }

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            Holiday::updateOrCreate(['date' => $date->toDateString()], [
                'name' => $data['name'],
                'type' => Holiday::TYPE_SCHOOL,
                'is_auto' => false,
                'is_tentative' => false,
                'is_school_day' => false,
            ]);
        }

        return redirect()->route('holidays.index', ['tahun' => $start->year])
            ->with('success', "Libur sekolah \"{$data['name']}\" disimpan.");
    }

    /** Libur nasional/cuti bersama: tandai tetap masuk sekolah, atau kembalikan jadi libur. */
    public function toggle(Holiday $holiday): RedirectResponse
    {
        $holiday->update(['is_school_day' => ! $holiday->is_school_day]);

        $date = Carbon::parse($holiday->date)->translatedFormat('d F Y');

        return redirect()->route('holidays.index', ['tahun' => substr($holiday->date, 0, 4)])
            ->with('success', $holiday->is_school_day
                ? "{$date} ditandai tetap masuk sekolah."
                : "{$date} kembali menjadi hari libur.");
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        if ($holiday->is_auto) {
            return back()->with('error', 'Libur nasional tidak dihapus. Pilih "Tetap masuk sekolah" bila sekolah tidak libur.');
        }

        $holiday->delete();

        return back()->with('success', "Libur \"{$holiday->name}\" dihapus.");
    }

    /** Ambil ulang libur nasional & cuti bersama dari internet. */
    public function sync(Request $request, NationalHolidaySync $sync): RedirectResponse
    {
        $year = (int) $request->integer('tahun', now()->year);
        $result = $sync->sync([$year, $year + 1]);

        if ($result['source'] === NationalHolidaySync::SOURCE_INTERNET) {
            return redirect()->route('holidays.index', ['tahun' => $year])->with('success', sprintf(
                'Libur nasional diperbarui: %d baru, %d diubah, %d dihapus.',
                $result['added'], $result['updated'], $result['removed'],
            ));
        }

        return redirect()->route('holidays.index', ['tahun' => $year])->with('error',
            'Tidak bisa mengambil libur nasional dari internet. Libur bertanggal tetap tetap tersedia; coba lagi saat server terhubung internet.');
    }
}
