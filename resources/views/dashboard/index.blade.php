@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    $t = $summary->totals;
    $segments = [
        ['key' => 'hadir', 'stat' => 'hadir', 'label' => 'Tepat waktu'],
        ['key' => 'terlambat', 'stat' => 'terlambat', 'label' => 'Terlambat'],
        ['key' => 'izin', 'stat' => 'izin', 'label' => 'Izin'],
        ['key' => 'sakit', 'stat' => 'sakit', 'label' => 'Sakit'],
        ['key' => 'alpa', 'stat' => 'alpa', 'label' => 'Alpa'],
        ['key' => 'belum', 'stat' => 'unrecorded', 'label' => 'Belum tercatat'],
    ];
@endphp

<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <p class="lead">{{ $today->translatedFormat('l, d F Y') }}
            @unless ($isSchoolDay)<span class="badge badge--muted">Bukan hari sekolah</span>@endunless
        </p>
    </div>
    <div class="actions">
        <a href="{{ route('attendances.index') }}" class="btn btn--secondary"><x-icon name="clipboard" :size="18" /> Presensi harian</a>
    </div>
</div>

<div id="live-dashboard" data-live-url="{{ route('dashboard.live') }}">
    <div class="dash-top">
        <section class="card summary" aria-labelledby="summary-title">
            <h2 id="summary-title" class="card__title">Kehadiran hari ini</h2>
            <div class="summary__figure">
                <p class="summary__number">
                    <span data-stat="present">{{ $t['present'] }}</span><span class="summary__total">/<span data-stat="total">{{ $t['total'] }}</span></span>
                </p>
                <p class="summary__caption">
                    siswa hadir · <strong><span data-stat="percentage">{{ $summary->percentage() }}</span>%</strong>
                </p>
            </div>

            <div class="statusbar" aria-hidden="true">
                @foreach ($segments as $segment)
                    <span class="statusbar__seg statusbar__seg--{{ $segment['key'] }} @if ($t[$segment['stat']] === 0) is-empty @endif"
                          data-seg="{{ $segment['stat'] }}" style="flex-grow: {{ $t[$segment['stat']] }}"></span>
                @endforeach
            </div>

            <dl class="breakdown">
                @foreach ($segments as $segment)
                    <div>
                        <dt><span class="swatch swatch--{{ $segment['key'] }}" aria-hidden="true"></span>{{ $segment['label'] }}</dt>
                        <dd data-stat="{{ $segment['stat'] }}">{{ $t[$segment['stat']] }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="card" aria-labelledby="trend-title">
            <h2 id="trend-title" class="card__title">7 hari terakhir</h2>
            <ol class="trend">
                @foreach ($trend as $day)
                    <li class="trend__day @unless ($day['school_day']) is-off @endunless @if ($day['today']) is-today @endif">
                        <span class="trend__value" @if ($day['today']) data-stat="percentage-label" @endif>{{ $day['school_day'] ? $day['percentage'].'%' : '–' }}</span>
                        <span class="trend__track" aria-hidden="true">
                            <span class="trend__fill" @if ($day['today']) data-trend-today @endif style="height: {{ $day['school_day'] ? max(2, $day['percentage']) : 0 }}%"></span>
                        </span>
                        <span class="trend__label">
                            <span aria-hidden="true">{{ $day['label'] }}</span>
                            <span class="sr-only">{{ $day['date']->translatedFormat('l, d F') }}: {{ $day['school_day'] ? $day['count'].' siswa hadir' : 'bukan hari sekolah' }}</span>
                        </span>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>

    <div class="dash-bottom">
        <section class="card" aria-labelledby="classes-title">
            <div class="card__head">
                <h2 id="classes-title" class="card__title">Per kelas</h2>
                <a href="{{ route('attendances.index', ['status' => 'belum']) }}" class="link-quiet">Lihat yang belum presensi</a>
            </div>
            @if ($summary->classes->isEmpty())
                <p class="empty">Belum ada kelas berisi siswa.
                    @if (auth()->user()->isAdmin())<a href="{{ route('students.create') }}">Tambah siswa</a>@endif
                </p>
            @else
                <div class="table-wrap">
                    <table class="table table--stack table--classes">
                        <caption class="sr-only">Kehadiran per kelas hari ini</caption>
                        <thead>
                        <tr>
                            <th scope="col">Kelas</th>
                            <th scope="col">Hadir</th>
                            <th scope="col" class="num">Terlambat</th>
                            <th scope="col" class="num">Izin/Sakit</th>
                            <th scope="col" class="num">Belum</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($summary->classes as $row)
                            @php $pct = $row['total'] > 0 ? (int) round($row['present'] / $row['total'] * 100) : 0; @endphp
                            <tr data-class-row="{{ $row['class']->id }}">
                                <th scope="row" data-label="Kelas">
                                    <a href="{{ route('attendances.index', ['kelas' => $row['class']->id]) }}">{{ $row['class']->name }}</a>
                                    @if ($row['class']->level)<span class="muted">{{ $row['class']->level }}</span>@endif
                                </th>
                                <td data-label="Hadir">
                                    <span class="progress">
                                        <span class="progress__bar" aria-hidden="true"><span data-class-bar style="width: {{ $pct }}%"></span></span>
                                        <span class="progress__text"><span data-class-stat="present">{{ $row['present'] }}</span>/<span data-class-stat="total">{{ $row['total'] }}</span></span>
                                    </span>
                                </td>
                                <td data-label="Terlambat" class="num" data-class-stat="terlambat">{{ $row['terlambat'] }}</td>
                                <td data-label="Izin/Sakit" class="num" data-class-stat="izin_sakit">{{ $row['izin'] + $row['sakit'] }}</td>
                                <td data-label="Belum" class="num" data-class-stat="unrecorded">{{ $row['unrecorded'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div>
            <section class="card" aria-labelledby="feed-title">
                <div class="card__head">
                    <h2 id="feed-title" class="card__title">Tap terakhir</h2>
                    <span class="live-indicator" data-live-status><span class="dot dot--live" aria-hidden="true"></span> Langsung</span>
                </div>
                <ul class="feed" data-feed aria-live="polite" aria-relevant="additions">
                    @forelse ($recentScans as $scan)
                        <li class="feed__item" data-scan-id="{{ $scan->id }}">
                            <span class="feed__time">{{ $scan->scanned_at->format('H.i') }}</span>
                            <span class="feed__who">
                                <strong>{{ $scan->student?->name ?? 'Kartu '.$scan->uid }}</strong>
                                <span>{{ collect([$scan->student?->schoolClass?->name, $scan->sourceName()])->filter()->implode(' · ') }}</span>
                            </span>
                            <span class="badge badge--{{ $scan->result->tone() }}">{{ $scan->result->label() }}</span>
                        </li>
                    @empty
                        <li class="feed__empty" data-feed-empty>Belum ada kartu yang ditempel hari ini.</li>
                    @endforelse
                </ul>
                <div class="card__foot"><a href="{{ route('scans.index') }}" class="link-quiet">Semua log tap</a></div>
            </section>

            @if ($unregisteredCount > 0)
                <div class="callout">
                    <x-icon name="card" :size="20" />
                    <p><strong>{{ $unregisteredCount }} siswa</strong> belum punya kartu terdaftar.
                        <a href="{{ route('students.index', ['kartu' => 'belum']) }}">Lihat daftar</a></p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
