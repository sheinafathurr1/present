<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Support\SchoolSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public const DAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    public function edit(SchoolSettings $settings): View
    {
        return view('settings.edit', ['settings' => $settings, 'days' => self::DAYS]);
    }

    public function update(Request $request, SchoolSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:150'],
            'school_lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:school_lng'],
            'school_lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:school_lat'],
            'radius_m' => ['required', 'integer', 'min:20', 'max:2000'],
            'max_accuracy_m' => ['required', 'integer', 'min:10', 'max:500'],
            'guru_start' => ['required', 'date_format:H:i'],
            'guru_end' => ['required', 'date_format:H:i', 'after:guru_start'],
            'tendik_start' => ['required', 'date_format:H:i'],
            'tendik_end' => ['required', 'date_format:H:i', 'after:tendik_start'],
            'late_tolerance_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'check_in_opens' => ['required', 'date_format:H:i'],
            'school_days' => ['required', 'array', 'min:1'],
            'school_days.*' => ['integer', 'between:1,7'],
            'joint_holidays_off' => ['nullable', 'boolean'],
        ], [
            'guru_end.after' => 'Jam pulang guru harus setelah jam masuk.',
            'tendik_end.after' => 'Jam pulang staf tendik harus setelah jam masuk.',
            'school_days.required' => 'Pilih minimal satu hari sekolah.',
        ]);

        $data['school_lat'] = isset($data['school_lat']) ? round((float) $data['school_lat'], 7) : null;
        $data['school_lng'] = isset($data['school_lng']) ? round((float) $data['school_lng'], 7) : null;
        foreach (['radius_m', 'max_accuracy_m', 'late_tolerance_minutes'] as $key) {
            $data[$key] = (int) $data[$key];
        }
        $data['school_days'] = array_values(array_map('intval', $data['school_days']));
        sort($data['school_days']);
        $data['joint_holidays_off'] = $request->boolean('joint_holidays_off');

        // Terapkan pilihan cuti bersama ke semua cuti bersama yang diambil otomatis.
        if ($data['joint_holidays_off'] !== $settings->jointHolidaysAreOff()) {
            Holiday::where('type', Holiday::TYPE_JOINT)->where('is_auto', true)
                ->update(['is_school_day' => ! $data['joint_holidays_off']]);
        }

        $settings->update($data);

        return redirect()->route('settings.edit')->with('success', 'Pengaturan disimpan.');
    }
}
