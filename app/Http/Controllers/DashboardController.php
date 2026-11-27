<?php

namespace App\Http\Controllers;

use App\Enums\EmployeeType;
use App\Services\AttendanceService;
use App\Support\DailySummary;
use App\Support\SchoolSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pantauan kehadiran hari ini untuk kepala sekolah, wakil, bendahara, dan admin.
 */
class DashboardController extends Controller
{
    public function index(Request $request, SchoolSettings $settings, AttendanceService $service): View
    {
        $today = CarbonImmutable::today();
        $type = EmployeeType::tryFrom((string) $request->query('jenis'));

        return view('dashboard.index', [
            'today' => $today,
            'type' => $type,
            'summary' => DailySummary::for($today, $type),
            'trend' => DailySummary::trend($today, $settings),
            'dayOff' => $service->dayOffReason($today),
            'hasLocation' => $settings->hasLocation(),
            'markingTime' => config('presensi.absent_marking_time'),
        ]);
    }
}
