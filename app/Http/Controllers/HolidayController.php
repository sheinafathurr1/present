<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HolidayController extends Controller
{
    public function index(Request $request): View
    {
        $year = (int) $request->integer('tahun', now()->year);

        return view('holidays.index', [
            'year' => $year,
            'holidays' => Holiday::whereBetween('date', ["{$year}-01-01", "{$year}-12-31"])->orderBy('date')->get(),
        ]);
    }

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
            Holiday::updateOrCreate(['date' => $date->toDateString()], ['name' => $data['name']]);
        }

        return redirect()->route('holidays.index', ['tahun' => $start->year])
            ->with('success', "Hari libur \"{$data['name']}\" disimpan.");
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return back()->with('success', "Hari libur \"{$holiday->name}\" dihapus.");
    }
}
