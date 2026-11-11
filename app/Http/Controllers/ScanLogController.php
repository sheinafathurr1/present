<?php

namespace App\Http\Controllers;

use App\Enums\ScanResult;
use App\Models\Device;
use App\Models\RfidScan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ScanLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'hasil' => ['nullable', Rule::enum(ScanResult::class)],
            'perangkat' => ['nullable', 'integer'],
        ]);

        $scans = RfidScan::with(['student.schoolClass', 'device', 'user'])
            ->when($filters['tanggal'] ?? null, fn ($query, $date) => $query
                ->whereBetween('scanned_at', [$date.' 00:00:00', $date.' 23:59:59']))
            ->when($filters['hasil'] ?? null, fn ($query, $result) => $query->where('result', $result))
            ->when($filters['perangkat'] ?? null, fn ($query, $device) => $query->where('device_id', $device))
            ->latest('scanned_at')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('scans.index', [
            'scans' => $scans,
            'filters' => $filters,
            'devices' => Device::orderBy('name')->get(),
            'results' => ScanResult::cases(),
        ]);
    }
}
