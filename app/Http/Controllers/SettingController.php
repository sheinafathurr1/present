<?php

namespace App\Http\Controllers;

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
            'late_after' => ['required', 'date_format:H:i'],
            'check_out_start' => ['required', 'date_format:H:i', 'after:late_after'],
            'tap_cooldown_seconds' => ['required', 'integer', 'min:0', 'max:3600'],
            'school_days' => ['required', 'array', 'min:1'],
            'school_days.*' => ['integer', 'between:1,7'],
        ], [
            'check_out_start.after' => 'Jam mulai pulang harus setelah batas jam masuk.',
            'school_days.required' => 'Pilih minimal satu hari sekolah.',
        ]);

        $data['school_days'] = array_values(array_map('intval', $data['school_days']));
        $data['tap_cooldown_seconds'] = (int) $data['tap_cooldown_seconds'];

        $settings->update($data);

        return redirect()->route('settings.edit')->with('success', 'Pengaturan disimpan.');
    }
}
