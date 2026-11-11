@extends('layouts.app')

@section('title', 'Perangkat Pembaca')

@section('content')
<div class="page-head">
    <div>
        <h1>Perangkat pembaca kartu</h1>
        <p class="lead">Daftarkan setiap titik presensi, misalnya "Gerbang Utama" atau "Pintu Asrama".</p>
    </div>
    <div class="actions"><a href="{{ route('devices.create') }}" class="btn btn--primary"><x-icon name="plus" /> Tambah perangkat</a></div>
</div>

@if ($newToken = session('new_token'))
    <section class="card token-card" aria-labelledby="token-baru">
        <h2 id="token-baru"><x-icon name="key" /> Token untuk {{ $newToken['device'] }}</h2>
        <p><strong>Salin dan simpan sekarang.</strong> Token ini hanya ditampilkan satu kali.</p>
        <div class="copy-field">
            <label class="sr-only" for="token-value">Token perangkat</label>
            <input id="token-value" class="input input--mono" value="{{ $newToken['token'] }}" readonly>
            <button type="button" class="btn btn--secondary" data-copy="#token-value">Salin</button>
        </div>
        @if ($newToken['type'] === 'network')
            <p class="hint">Masukkan token ini ke program ESP32 (<code>DEVICE_TOKEN</code>). Alamat API: <code>{{ route('api.taps.store') }}</code></p>
        @else
            <p class="hint">Pembaca USB tidak memerlukan token: cukup buka layar presensi di komputer yang tersambung ke pembaca.</p>
        @endif
    </section>
@endif

<section class="card">
    @if ($devices->isEmpty())
        <p class="empty">Belum ada perangkat. <a href="{{ route('devices.create') }}">Tambah perangkat pertama</a>.</p>
    @else
        <div class="table-wrap">
            <table class="table table--stack">
                <caption class="sr-only">Daftar perangkat</caption>
                <thead>
                <tr>
                    <th scope="col">Perangkat</th>
                    <th scope="col">Jenis</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="num">Tap hari ini</th>
                    <th scope="col"><span class="sr-only">Aksi</span></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($devices as $device)
                    <tr>
                        <th scope="row" data-label="Perangkat">{{ $device->name }}<span class="muted">{{ $device->location }}</span></th>
                        <td data-label="Jenis"><x-icon :name="$device->type === 'network' ? 'wifi' : 'monitor'" :size="16" /> {{ $device->typeLabel() }}</td>
                        <td data-label="Status">
                            @if (! $device->is_active)
                                <span class="badge badge--muted">Nonaktif</span>
                            @elseif ($device->isOnline())
                                <span class="badge badge--success">Terhubung</span>
                            @else
                                <span class="badge badge--muted">Terakhir: {{ $device->last_seen_at?->diffForHumans() ?? 'belum pernah' }}</span>
                            @endif
                        </td>
                        <td data-label="Tap hari ini" class="num">{{ $device->scans_count }}</td>
                        <td data-label="Aksi" class="row-actions">
                            @if ($device->type === 'usb' && $device->is_active)
                                <a href="{{ route('kiosk.show', $device) }}" class="btn btn--secondary btn--sm"><x-icon name="monitor" :size="16" /> Buka kiosk</a>
                            @endif
                            @if ($device->type === 'network')
                                <form method="POST" action="{{ route('devices.token', $device) }}" data-confirm="Buat token baru untuk {{ $device->name }}? Token lama langsung tidak berlaku.">
                                    @csrf
                                    <button type="submit" class="btn btn--ghost btn--sm"><x-icon name="key" :size="16" /> Token baru</button>
                                </form>
                            @endif
                            <a href="{{ route('devices.edit', $device) }}" class="btn btn--ghost btn--sm"><x-icon name="edit" :size="16" /> Ubah<span class="sr-only"> {{ $device->name }}</span></a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
