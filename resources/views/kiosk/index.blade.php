@extends('layouts.guest')

@section('title', 'Pilih Layar Presensi')

@section('content')
<div class="kiosk-picker">
    <div class="login__brand">
        <span class="brand__mark brand__mark--lg" aria-hidden="true"><x-icon name="monitor" :size="36" /></span>
        <h1>Layar Presensi</h1>
        <p>Pilih titik presensi untuk komputer ini. Pastikan pembaca kartu USB sudah tercolok.</p>
    </div>

    @if ($devices->isEmpty())
        <div class="card">
            <p>Belum ada perangkat pembaca USB yang aktif.</p>
            @if (auth()->user()->isAdmin())
                <p><a href="{{ route('devices.create') }}" class="btn btn--primary"><x-icon name="plus" /> Tambah perangkat</a></p>
            @else
                <p class="muted">Minta admin menambahkan perangkat di menu Perangkat Pembaca.</p>
            @endif
        </div>
    @else
        <ul class="kiosk-picker__list">
            @foreach ($devices as $device)
                <li>
                    <a href="{{ route('kiosk.show', $device) }}" class="card kiosk-picker__item">
                        <x-icon name="monitor" :size="28" />
                        <span>
                            <strong>{{ $device->name }}</strong>
                            <span class="muted">{{ $device->location ?: 'Pembaca USB' }}</span>
                        </span>
                        <span aria-hidden="true">→</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="kiosk-picker__foot">
        @unless (auth()->user()->role === \App\Enums\UserRole::Kiosk)
            <a href="{{ route('dashboard') }}" class="btn btn--ghost"><x-icon name="arrow-left" /> Kembali ke dashboard</a>
        @endunless
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn--ghost"><x-icon name="logout" /> Keluar</button>
        </form>
    </div>
</div>
@endsection
