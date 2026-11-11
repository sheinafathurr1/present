<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function index(): View
    {
        return view('devices.index', [
            'devices' => Device::withCount(['scans' => fn ($query) => $query->where('scanned_at', '>=', today())])
                ->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('devices.form', ['device' => new Device]);
    }

    public function store(Request $request): RedirectResponse
    {
        $device = Device::create($this->validated($request));
        $token = $device->issueToken();

        return redirect()->route('devices.index')
            ->with('success', "Perangkat {$device->name} ditambahkan.")
            ->with('new_token', ['device' => $device->name, 'token' => $token]);
    }

    public function edit(Device $device): View
    {
        return view('devices.form', compact('device'));
    }

    public function update(Request $request, Device $device): RedirectResponse
    {
        $device->update($this->validated($request));

        return redirect()->route('devices.index')->with('success', "Perangkat {$device->name} diperbarui.");
    }

    public function destroy(Device $device): RedirectResponse
    {
        $device->delete();

        return redirect()->route('devices.index')->with('success', "Perangkat {$device->name} dihapus.");
    }

    public function regenerateToken(Device $device): RedirectResponse
    {
        $token = $device->issueToken();

        return redirect()->route('devices.index')
            ->with('success', "Token baru untuk {$device->name} dibuat. Token lama sudah tidak berlaku.")
            ->with('new_token', ['device' => $device->name, 'token' => $token]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
