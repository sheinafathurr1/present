@extends('layouts.app')

@section('title', 'Pembaca Wi-Fi')

@section('content')
<div class="page-head">
    <div>
        <h1>Pembaca kartu Wi-Fi (opsional)</h1>
        <p class="lead">Hanya untuk alat mandiri ESP32/ESP8266, misalnya di pintu asrama.</p>
    </div>
    <div class="actions"><a href="{{ route('devices.create') }}" class="btn btn--primary"><x-icon name="plus" /> Tambah pembaca Wi-Fi</a></div>
</div>

<div class="callout callout--info callout--top">
    <x-icon name="info" />
    <p><strong>Pembaca USB di PC guru tidak perlu didaftarkan di sini.</strong>
        Cukup colokkan pembaca ke PC, login sebagai guru, lalu siswa bisa langsung menempelkan kartu
        selama halaman dashboard terbuka.</p>
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
        <p class="hint">Masukkan token ini ke program ESP32 (<code>DEVICE_TOKEN</code>). Alamat API: <code>{{ route('api.taps.store') }}</code></p>
    </section>
@endif

<section class="card">
    @if ($devices->isEmpty())
        <p class="empty">Belum ada pembaca Wi-Fi. Bagian ini boleh dilewati bila sekolah hanya memakai pembaca USB di PC guru.</p>
    @else
        <div class="table-wrap">
            <table class="table table--stack">
                <caption class="sr-only">Daftar pembaca Wi-Fi</caption>
                <thead>
                <tr>
                    <th scope="col">Pembaca</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="num">Tap hari ini</th>
                    <th scope="col"><span class="sr-only">Aksi</span></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($devices as $device)
                    <tr>
                        <th scope="row" data-label="Pembaca">{{ $device->name }}<span class="muted">{{ $device->location }}</span></th>
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
                            <form method="POST" action="{{ route('devices.token', $device) }}" data-confirm="Buat token baru untuk {{ $device->name }}? Token lama langsung tidak berlaku.">
                                @csrf
                                <button type="submit" class="btn btn--ghost btn--sm"><x-icon name="key" :size="16" /> Token baru</button>
                            </form>
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
