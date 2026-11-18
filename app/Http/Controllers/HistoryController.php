<?php

namespace App\Http\Controllers;

use App\Support\MonthlyReport;
use App\Support\SchoolSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Riwayat presensi milik sendiri, per bulan.
 */
class HistoryController extends Controller
{
    public function index(Request $request, SchoolSettings $settings): View
    {
        $month = self::month($request);
        $user = $request->user();
        $report = MonthlyReport::build($month, $settings, users: collect([$user]));

        return view('history.index', [
            'month' => $month,
            'days' => $report['days'],
            'row' => $report['rows'][0],
            'user' => $user,
            'leaves' => $user->leaveRequests()
                ->overlapping($month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString())
                ->orderBy('start_date')
                ->get(),
        ]);
    }

    /** Bulan dari ?bulan=YYYY-MM (bawaan bulan ini). */
    public static function month(Request $request): CarbonImmutable
    {
        $value = (string) $request->query('bulan', '');

        if (preg_match('/^(\d{4})-(\d{2})$/', $value, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12 && (int) $m[1] >= 2000 && (int) $m[1] <= 2100) {
            return CarbonImmutable::create((int) $m[1], (int) $m[2], 1)->startOfDay();
        }

        return CarbonImmutable::today()->startOfMonth();
    }
}
