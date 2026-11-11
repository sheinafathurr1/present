@extends('layouts.app')

@php $editing = $device->exists; @endphp
@section('title', $editing ? 'Ubah Pembaca Wi-Fi' : 'Tambah Pembaca Wi-Fi')

@section('content')
<div class="page-head">
    <div>
        <a href="{{ route('devices.index') }}" class="back-link"><x-icon name="arrow-left" :size="18" /> Pembaca Wi-Fi</a>
        <h1>{{ $editing ? 'Ubah '.$device->name : 'Tambah pembaca Wi-Fi' }}</h1>
        <p class="lead">Alat mandiri ESP32/ESP8266 yang mengirim tap lewat jaringan. Contoh program ada di folder <code>hardware/</code>.</p>
    </div>
</div>

<form method="POST" action="{{ $editing ? route('devices.update', $device) : route('devices.store') }}" class="form-layout" novalidate>
    @csrf
    @if ($editing) @method('PUT') @endif
    <section class="card">
        <div class="form-grid">
            <x-input name="name" label="Nama pembaca" :value="$device->name" required placeholder="mis. Pintu Asrama" />
            <x-input name="location" label="Lokasi" :value="$device->location" placeholder="mis. Lobi depan, dekat pos satpam" />
        </div>

        <label class="check check--lg">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $device->is_active ?? true))>
            <span><strong>Pembaca aktif</strong><br><span class="muted">Pembaca nonaktif tidak bisa mencatat presensi.</span></span>
        </label>
    </section>
    <div class="form-actions">
        <button type="submit" class="btn btn--primary btn--lg"><x-icon name="check" /> Simpan</button>
        <a href="{{ route('devices.index') }}" class="btn btn--ghost btn--lg">Batal</a>
    </div>
</form>

@if ($editing)
    <form method="POST" action="{{ route('devices.destroy', $device) }}" class="danger-zone card" data-confirm="Hapus pembaca {{ $device->name }}? Riwayat presensi tetap tersimpan.">
        @csrf
        @method('DELETE')
        <div>
            <h2>Hapus pembaca</h2>
            <p class="muted">Riwayat presensi yang sudah tercatat tidak ikut terhapus.</p>
        </div>
        <button type="submit" class="btn btn--danger"><x-icon name="trash" /> Hapus pembaca</button>
    </form>
@endif
@endsection
