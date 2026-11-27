@extends('layouts.app')

@section('title', 'Rekap Bulanan')

@section('content')
@php
    $query = array_filter(['bulan' => $month->format('Y-m'), 'jenis' => $type?->value]);
    $title = 'Rekap kehadiran '.($type ? mb_strtolower($type->label()) : 'guru & staf').' · '.$month->translatedFormat('F Y');
@endphp
<div class="page-head">
    <div>
        <h1>Rekap Bulanan</h1>
        <p class="lead">{{ $report['school_day_count'] }} hari sekolah di {{ $month->translatedFormat('F Y') }} · {{ count($report['rows']) }} orang</p>
    </div>
    <div class="actions no-print">
        <a class="btn btn--secondary" href="{{ route('reports.export', $query) }}"><x-icon name="download" :size="18" /> Unduh bulan ini (CSV)</a>
        <a class="btn btn--secondary" href="{{ route('exports.create', array_filter(['jenis' => $type?->value])) }}"><x-icon name="calendar" :size="18" /> Ekspor rentang tanggal</a>
        <button type="button" class="btn btn--secondary" data-print><x-icon name="printer" :size="18" /> Cetak</button>
    </div>
</div>

<div class="toolbar no-print">
    @include('partials.month-nav')
    @include('partials.type-tabs')
</div>

<section class="card" aria-labelledby="judul-rekap">
    <h2 id="judul-rekap" class="print-only">{{ $schoolSettings->schoolName() }} — {{ $title }}</h2>
    <ul class="legend" aria-label="Keterangan kode">
        @foreach ($statuses as $status)
            <li><span class="cell-code tone-{{ $status->tone() }}" aria-hidden="true">{{ $status->code() }}</span> {{ $status->label() }}</li>
        @endforeach
        <li><span class="cell-code tone-missing" aria-hidden="true">–</span> Tidak ada catatan</li>
        <li><span class="cell-code tone-off" aria-hidden="true"></span> Libur / bukan hari sekolah</li>
    </ul>

    @if ($report['rows'] === [])
        <p class="empty">Belum ada guru atau staf yang wajib presensi.</p>
    @else
        <div class="table-wrap table-wrap--matrix" tabindex="0" role="region" aria-label="Tabel rekap, bisa digulir ke samping">
            <table class="table table--matrix">
                <caption class="sr-only">{{ $title }}</caption>
                <thead>
                <tr>
                    <th scope="col" class="sticky-col">Nama</th>
                    @foreach ($report['days'] as $day)
                        <th scope="col" class="day @unless ($day['school']) is-off @endunless @if ($day['today']) is-today @endif"
                            title="{{ $day['label'] }}{{ $day['holiday'] ? ' — '.$day['holiday'] : '' }}">
                            <span aria-hidden="true">{{ $day['day'] }}<small>{{ $day['weekday'] }}</small></span>
                            <span class="sr-only">{{ $day['label'] }}</span>
                        </th>
                    @endforeach
                    @foreach ($statuses as $status)
                        <th scope="col" class="num total" title="{{ $status->label() }}"><abbr title="{{ $status->label() }}">{{ $status->code() }}</abbr></th>
                    @endforeach
                    <th scope="col" class="num total" title="Tanpa catatan">–</th>
                    <th scope="col" class="num total">Telat<small>menit</small></th>
                    <th scope="col" class="num total">Pulang awal<small>kali</small></th>
                    <th scope="col" class="num total">% Hadir</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($report['rows'] as $row)
                    <tr>
                        <th scope="row" class="sticky-col">
                            <a href="{{ route('employees.show', ['user' => $row['user'], 'bulan' => $month->format('Y-m')]) }}">{{ $row['user']->name }}</a>
                            <span class="muted small">{{ $row['user']->subtitle() }}</span>
                        </th>
                        @foreach ($report['days'] as $day)
                            @php $attendance = $row['cells'][$day['date']]; @endphp
                            @if ($attendance)
                                @php
                                    $tip = $attendance->status->label()
                                        .($attendance->check_in_at ? ' · '.$attendance->check_in_at->format('H:i').'–'.($attendance->check_out_at?->format('H:i') ?? '…') : '')
                                        .($attendance->late_minutes ? ' · telat '.$attendance->late_minutes.' mnt' : '')
                                        .($attendance->early_minutes ? ' · pulang awal '.$attendance->early_minutes.' mnt' : '');
                                @endphp
                                <td class="cell tone-{{ $attendance->status->tone() }} @if ($attendance->early_minutes) has-early @endif" title="{{ $tip }}">
                                    {{ $attendance->status->code() }}<span class="sr-only"> ({{ $tip }})</span>
                                </td>
                            @elseif (! $day['school'])
                                <td class="is-off"><span class="sr-only">Libur</span></td>
                            @elseif ($day['future'] || $day['today'])
                                <td></td>
                            @else
                                <td class="cell tone-missing" title="Tidak ada catatan">–</td>
                            @endif
                        @endforeach
                        @foreach ($statuses as $status)
                            <td class="num total">{{ $row['totals'][$status->value] }}</td>
                        @endforeach
                        <td class="num total">{{ $row['missing'] }}</td>
                        <td class="num total">{{ $row['late_minutes'] }}</td>
                        <td class="num total">{{ $row['early_count'] }}</td>
                        <td class="num total"><strong>{{ $row['school_days'] ? $row['percentage'].'%' : '—' }}</strong></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="hint table-note">Persentase hadir = (Hadir + Terlambat) ÷ hari sekolah sampai hari ini. Kotak bergaris bawah menandai pulang awal.</p>
    @endif
</section>
@endsection
