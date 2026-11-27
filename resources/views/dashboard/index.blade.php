@extends('layouts.app')

@section('title', 'Kehadiran Hari Ini')

@section('content')
@php
    $totals = $summary['totals'];
    $segments = [
        'hadir' => 'Hadir', 'terlambat' => 'Terlambat', 'izin' => 'Izin', 'sakit' => 'Sakit',
        'cuti' => 'Cuti', 'dinas_luar' => 'Dinas luar', 'alpa' => 'Alpa', 'belum' => 'Belum presensi',
    ];
@endphp

<div class="page-head" data-auto-refresh="120">
    <div>
        <h1>Kehadiran Hari Ini</h1>
        <p class="lead">{{ $today->translatedFormat('l, d F Y') }} · diperbarui {{ now()->format('H:i') }}</p>
    </div>
    @include('partials.type-tabs')
</div>

@if (! $hasLocation)
    <div class="alert alert--warning" role="status">
        <x-icon name="alert" />
        <div>Lokasi sekolah belum diatur, jadi guru & staf belum bisa presensi.
            @if (auth()->user()->isAdmin())<a href="{{ route('settings.edit') }}#lokasi">Atur sekarang</a>.@else Minta admin mengaturnya.@endif
        </div>
    </div>
@endif

@if ($dayOff)
    <div class="callout callout--info callout--top"><x-icon name="calendar-off" /><p>{{ $dayOff }} Guru & staf tidak perlu presensi.</p></div>
@endif

<div class="dash-top">
    <section class="card summary" aria-labelledby="ringkasan">
        <h2 id="ringkasan" class="sr-only">Ringkasan</h2>
        <div class="summary__figure">
            <p class="summary__number">{{ $summary['present'] }}<span class="summary__total">/{{ $summary['total'] }}</span></p>
            <p class="summary__caption"><strong>{{ $summary['percentage'] }}%</strong> sudah hadir
                @if ($totals['terlambat'] > 0) · {{ $totals['terlambat'] }} terlambat @endif
            </p>
        </div>

        <div class="statusbar" aria-hidden="true">
            @foreach ($segments as $key => $text)
                <span class="statusbar__seg statusbar__seg--{{ $key }} @if ($totals[$key] === 0) is-empty @endif" style="flex-grow: {{ $totals[$key] }}"></span>
            @endforeach
        </div>

        <dl class="breakdown">
            @foreach ($segments as $key => $text)
                <div>
                    <dt><span class="swatch swatch--{{ $key }}" aria-hidden="true"></span>{{ $text }}</dt>
                    <dd>{{ $totals[$key] }}</dd>
                </div>
            @endforeach
        </dl>
        @if (! $dayOff && $totals['belum'] > 0)
            <p class="hint summary__hint">Yang belum presensi dan tidak mengajukan izin ditandai Alpa otomatis pukul {{ $markingTime }}.</p>
        @endif
    </section>

    <section class="card" aria-labelledby="tren">
        <h2 id="tren" class="card__title">Kehadiran 7 hari sekolah terakhir</h2>
        @if ($trend === [])
            <p class="empty">Belum ada data.</p>
        @else
            <ol class="trend">
                @foreach ($trend as $day)
                    <li class="trend__day @if ($day['today']) is-today @endif">
                        <span class="trend__value">{{ $day['percentage'] }}%</span>
                        <span class="trend__track" aria-hidden="true"><span class="trend__fill" style="height: {{ max(2, $day['percentage']) }}%"></span></span>
                        <span class="trend__label"><span aria-hidden="true">{{ $day['label'] }}</span><span class="sr-only">{{ $day['full'] }}</span></span>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
</div>

<section class="card" aria-labelledby="daftar">
    <div class="card__head">
        <h2 id="daftar" class="card__title">Guru & staf ({{ $summary['total'] }})</h2>
        <a class="link-quiet" href="{{ route('reports.monthly', array_filter(['jenis' => $type?->value])) }}">Rekap bulan ini</a>
    </div>

    @if ($summary['rows']->isEmpty())
        <p class="empty">Belum ada guru atau staf yang wajib presensi.</p>
    @else
        <div class="table-wrap">
            <table class="table table--stack table--grid">
                <thead>
                <tr>
                    <th scope="col">Nama</th>
                    <th scope="col">Status</th>
                    <th scope="col">Masuk</th>
                    <th scope="col">Pulang</th>
                    <th scope="col">Keterangan</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($summary['rows'] as $row)
                    @php $attendance = $row['attendance']; @endphp
                    <tr>
                        <th scope="row">
                            <a href="{{ route('employees.show', $row['user']) }}">{{ $row['user']->name }}</a>
                            <span class="muted">{{ $row['user']->subtitle() }}</span>
                        </th>
                        <td data-label="Status"><x-status-badge :status="$attendance?->status" /></td>
                        <td data-label="Masuk">
                            {{ $attendance?->check_in_at?->format('H:i') ?? '—' }}
                            @if ($attendance?->check_in_distance !== null)<span class="muted nowrap">{{ $attendance->check_in_distance }} m dari sekolah</span>@endif
                        </td>
                        <td data-label="Pulang">{{ $attendance?->check_out_at?->format('H:i') ?? '—' }}</td>
                        <td data-label="Keterangan">
                            @if ($attendance)
                                @php $remarks = $attendance->remarks(true); @endphp
                                @if ($remarks)<span>{{ implode(' · ', $remarks) }}</span>@endif
                                @if ($attendance->leaveRequest)
                                    <span class="note">{{ $attendance->leaveRequest->reason }} · {{ $attendance->leaveRequest->periodLabel() }}</span>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
