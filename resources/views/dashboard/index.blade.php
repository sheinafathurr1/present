@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <p class="lead">{{ $today->translatedFormat('l, d F Y') }}
            @unless ($isSchoolDay)<span class="badge badge--muted">Bukan hari sekolah</span>@endunless
        </p>
    </div>
    <div class="actions">
        <a href="{{ route('attendances.index') }}" class="btn btn--secondary"><x-icon name="clipboard" /> Presensi hari ini</a>
    </div>
</div>

@php $t = $summary->totals; @endphp
<section aria-labelledby="ringkasan" data-live-url="{{ route('dashboard.live') }}" id="live-dashboard">
    <h2 id="ringkasan" class="sr-only">Ringkasan hari ini</h2>
    <div class="stat-grid">
        <div class="stat stat--primary">
            <span class="stat__label">Hadir</span>
            <span class="stat__value"><span data-stat="present">{{ $t['present'] }}</span><small> / <span data-stat="total">{{ $t['total'] }}</span></small></span>
            <span class="stat__meta"><span data-stat="percentage">{{ $summary->percentage() }}</span>% siswa aktif</span>
            <div class="meter" aria-hidden="true"><span data-meter style="width: {{ $summary->percentage() }}%"></span></div>
        </div>
        <div class="stat">
            <span class="stat__label"><span class="dot dot--warning" aria-hidden="true"></span>Terlambat</span>
            <span class="stat__value" data-stat="terlambat">{{ $t['terlambat'] }}</span>
            <span class="stat__meta">sudah termasuk hadir</span>
        </div>
        <div class="stat">
            <span class="stat__label"><span class="dot dot--info" aria-hidden="true"></span>Izin / Sakit</span>
            <span class="stat__value"><span data-stat="izin">{{ $t['izin'] }}</span> / <span data-stat="sakit">{{ $t['sakit'] }}</span></span>
            <span class="stat__meta">dicatat guru</span>
        </div>
        <div class="stat">
            <span class="stat__label"><span class="dot dot--danger" aria-hidden="true"></span>Belum presensi</span>
            <span class="stat__value" data-stat="unrecorded">{{ $t['unrecorded'] }}</span>
            <span class="stat__meta"><span data-stat="alpa">{{ $t['alpa'] }}</span> sudah ditandai alpa</span>
        </div>
    </div>

    <div class="grid-2">
        <section class="card" aria-labelledby="tap-terbaru">
            <div class="card__head">
                <h2 id="tap-terbaru">Tap kartu terbaru</h2>
                <span class="live-indicator" data-live-status><span class="dot dot--success" aria-hidden="true"></span> Diperbarui otomatis</span>
            </div>
            <ul class="feed" data-feed aria-live="polite" aria-relevant="additions">
                @forelse ($recentScans as $scan)
                    <li class="feed__item" data-scan-id="{{ $scan->id }}">
                        <span class="feed__time">{{ $scan->scanned_at->format('H.i') }}</span>
                        <span class="feed__who">
                            <strong>{{ $scan->student?->name ?? 'Kartu '.$scan->uid }}</strong>
                            <span>{{ $scan->student?->schoolClass?->name }}{{ $scan->device ? ' · '.$scan->device->name : '' }}</span>
                        </span>
                        <span class="badge badge--{{ $scan->result->tone() }}">{{ $scan->result->label() }}</span>
                    </li>
                @empty
                    <li class="feed__empty" data-feed-empty>Belum ada kartu yang ditempel hari ini.</li>
                @endforelse
            </ul>
            <div class="card__foot"><a href="{{ route('scans.index') }}">Lihat semua log tap →</a></div>
        </section>

        <section class="card" aria-labelledby="tren">
            <div class="card__head"><h2 id="tren">Kehadiran 7 hari terakhir</h2></div>
            <ol class="bars">
                @foreach ($trend as $day)
                    <li class="bars__item @unless ($day['school_day']) is-off @endunless">
                        <span class="bars__value">{{ $day['school_day'] ? $day['percentage'].'%' : 'Libur' }}</span>
                        <span class="bars__track" aria-hidden="true"><span style="height: {{ $day['school_day'] ? max(2, $day['percentage']) : 0 }}%"></span></span>
                        <span class="bars__label">
                            <span aria-hidden="true">{{ $day['label'] }}</span>
                            <span class="sr-only">{{ $day['date']->translatedFormat('l, d F') }}: {{ $day['school_day'] ? $day['count'].' siswa hadir' : 'bukan hari sekolah' }}</span>
                        </span>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>
</section>

<div class="grid-2">
    <section class="card" aria-labelledby="per-kelas">
        <div class="card__head"><h2 id="per-kelas">Per kelas hari ini</h2></div>
        @if ($summary->classes->isEmpty())
            <p class="empty">Belum ada kelas berisi siswa. <a href="{{ route('students.index') }}">Tambahkan data siswa</a>.</p>
        @else
            <div class="table-wrap">
                <table class="table">
                    <caption class="sr-only">Jumlah kehadiran per kelas</caption>
                    <thead>
                    <tr>
                        <th scope="col">Kelas</th>
                        <th scope="col" class="num">Hadir</th>
                        <th scope="col" class="num">Terlambat</th>
                        <th scope="col" class="num">Izin/Sakit</th>
                        <th scope="col" class="num">Belum</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($summary->classes as $row)
                        <tr>
                            <th scope="row">
                                <a href="{{ route('attendances.index', ['kelas' => $row['class']->id]) }}">{{ $row['class']->name }}</a>
                                @if ($row['class']->level)<span class="muted">· {{ $row['class']->level }}</span>@endif
                            </th>
                            <td class="num">{{ $row['present'] }} / {{ $row['total'] }}</td>
                            <td class="num">{{ $row['terlambat'] }}</td>
                            <td class="num">{{ $row['izin'] + $row['sakit'] }}</td>
                            <td class="num">@if ($row['unrecorded'] > 0)<strong class="text-danger">{{ $row['unrecorded'] }}</strong>@else 0 @endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="card" aria-labelledby="perangkat">
        <div class="card__head"><h2 id="perangkat">Perangkat pembaca</h2></div>
        @if ($devices->isEmpty())
            <p class="empty">Belum ada perangkat.
                @if (auth()->user()->isAdmin())<a href="{{ route('devices.create') }}">Tambah perangkat pembaca</a>.@endif
            </p>
        @else
            <ul class="device-list">
                @foreach ($devices as $device)
                    <li>
                        <x-icon :name="$device->type === 'network' ? 'wifi' : 'monitor'" />
                        <span>
                            <strong>{{ $device->name }}</strong>
                            <span class="muted">{{ $device->location ?: $device->typeLabel() }}</span>
                        </span>
                        @if (! $device->is_active)
                            <span class="badge badge--muted">Nonaktif</span>
                        @elseif ($device->isOnline())
                            <span class="badge badge--success"><span class="dot dot--success" aria-hidden="true"></span>Aktif</span>
                        @else
                            <span class="badge badge--muted" title="Terakhir terhubung {{ $device->last_seen_at?->diffForHumans() ?? 'belum pernah' }}">Tidak terhubung</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
        @if ($unregisteredCount > 0)
            <div class="callout">
                <x-icon name="card" />
                <p><strong>{{ $unregisteredCount }} siswa</strong> belum memiliki kartu terdaftar.
                    <a href="{{ route('students.index', ['kartu' => 'belum']) }}">Lihat daftarnya</a></p>
            </div>
        @endif
    </section>
</div>
@endsection
