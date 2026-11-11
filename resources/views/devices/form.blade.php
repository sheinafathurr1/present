@extends('layouts.app')

@php $editing = $device->exists; @endphp
@section('title', $editing ? 'Ubah Perangkat' : 'Tambah Perangkat')

@section('content')
<div class="page-head">
    <div>
        <a href="{{ route('devices.index') }}" class="back-link"><x-icon name="arrow-left" :size="18" /> Perangkat</a>
        <h1>{{ $editing ? 'Ubah '.$device->name : 'Tambah perangkat pembaca' }}</h1>
    </div>
</div>

<form method="POST" action="{{ $editing ? route('devices.update', $device) : route('devices.store') }}" class="form-layout" novalidate>
    @csrf
    @if ($editing) @method('PUT') @endif
    <section class="card">
        <div class="form-grid">
            <x-input name="name" label="Nama perangkat" :value="$device->name" required placeholder="mis. Gerbang Utama" />
            <x-input name="location" label="Lokasi" :value="$device->location" placeholder="mis. Lobi depan, dekat pos satpam" />
        </div>

        <fieldset class="choice-group">
            <legend>Jenis pembaca</legend>
            @php $type = old('type', $device->type ?? 'usb'); @endphp
            <label class="choice">
                <input type="radio" name="type" value="usb" @checked($type === 'usb')>
                <span>
                    <strong><x-icon name="monitor" /> Pembaca USB + layar kiosk</strong>
                    <span class="muted">Pembaca RFID dicolok ke komputer/mini PC, lalu layar presensi dibuka di browser. Paling mudah & murah.</span>
                </span>
            </label>
            <label class="choice">
                <input type="radio" name="type" value="network" @checked($type === 'network')>
                <span>
                    <strong><x-icon name="wifi" /> Pembaca jaringan (ESP32/ESP8266)</strong>
                    <span class="muted">Alat mandiri dengan Wi-Fi yang mengirim data ke server memakai token. Contoh program ada di folder <code>hardware/</code>.</span>
                </span>
            </label>
        </fieldset>

        <label class="check check--lg">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $device->is_active ?? true))>
            <span><strong>Perangkat aktif</strong><br><span class="muted">Perangkat nonaktif tidak bisa mencatat presensi.</span></span>
        </label>
    </section>
    <div class="form-actions">
        <button type="submit" class="btn btn--primary btn--lg"><x-icon name="check" /> Simpan</button>
        <a href="{{ route('devices.index') }}" class="btn btn--ghost btn--lg">Batal</a>
    </div>
</form>

@if ($editing)
    <form method="POST" action="{{ route('devices.destroy', $device) }}" class="danger-zone card" data-confirm="Hapus perangkat {{ $device->name }}? Riwayat presensi tetap tersimpan.">
        @csrf
        @method('DELETE')
        <div>
            <h2>Hapus perangkat</h2>
            <p class="muted">Riwayat presensi yang sudah tercatat tidak ikut terhapus.</p>
        </div>
        <button type="submit" class="btn btn--danger"><x-icon name="trash" /> Hapus perangkat</button>
    </form>
@endif
@endsection
