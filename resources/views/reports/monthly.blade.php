@extends('layouts.app')

@section('title', 'Rekap Bulanan')

@section('content')
@php
    $query = array_filter(['bulan' => $report->month->format('Y-m'), 'kelas' => $report->class?->id]);
@endphp
<div class="page-head">
    <div>
        <h1>Rekap Bulanan</h1>
        <p class="lead">{{ $report->class?->name ?? 'Semua kelas' }} · {{ $report->month->translatedFormat('F Y') }} ·
            {{ $report->schoolDaysElapsed }} hari sekolah berjalan</p>
    </div>
    <div class="actions no-print">
        <a class="btn btn--secondary" href="{{ route('reports.export', $query) }}"><x-icon name="download" /> Unduh Excel (CSV)</a>
        <button type="button" class="btn btn--secondary" data-print><x-icon name="printer" /> Cetak</button>
    </div>
</div>

<form method="GET" class="filters card no-print" aria-label="Pilih bulan dan kelas">
    <div class="field">
        <label for="f-bulan">Bulan</label>
        <input id="f-bulan" class="input" type="month" name="bulan" value="{{ $report->month->format('Y-m') }}">
    </div>
    <div class="field">
        <label for="f-kelas">Kelas</label>
        <select id="f-kelas" name="kelas" class="input">
            <option value="">Semua kelas</option>
            @foreach ($classes as $class)
                <option value="{{ $class->id }}" @selected($report->class?->id === $class->id)>{{ $class->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="filters__actions">
        <button class="btn btn--primary" type="submit">Tampilkan</button>
    </div>
</form>

<section class="card" aria-labelledby="judul-rekap">
    <h2 id="judul-rekap" class="print-only">{{ $report->title() }}</h2>
    <ul class="legend" aria-label="Keterangan kode">
        @foreach ($legend as $code => $label)
            <li><span class="cell-code tone-{{ ['H' => 'hadir', 'T' => 'terlambat', 'I' => 'izin', 'S' => 'sakit', 'A' => 'alpa'][$code] ?? 'missing' }}" aria-hidden="true">{{ $code }}</span> {{ $label }}</li>
        @endforeach
        <li><span class="cell-code tone-off" aria-hidden="true"></span> Libur / bukan hari sekolah</li>
    </ul>

    @if ($report->rows->isEmpty())
        <p class="empty">Belum ada siswa di kelas ini.</p>
    @else
        <div class="table-wrap table-wrap--matrix" tabindex="0" role="region" aria-label="Tabel rekap, bisa digulir ke samping">
            <table class="table table--matrix">
                <caption class="sr-only">{{ $report->title() }}</caption>
                <thead>
                <tr>
                    <th scope="col" class="sticky-col">Nama siswa</th>
                    @foreach ($report->days as $day)
                        <th scope="col" class="day @unless ($day['school_day']) is-off @endunless @if ($day['today']) is-today @endif" title="{{ $day['date']->translatedFormat('l, d F') }}{{ $day['holiday'] ? ' — '.$day['holiday'] : '' }}">
                            <span aria-hidden="true">{{ $day['day'] }}<small>{{ mb_substr($day['date']->translatedFormat('D'), 0, 2) }}</small></span>
                            <span class="sr-only">{{ $day['date']->translatedFormat('l d') }}</span>
                        </th>
                    @endforeach
                    <th scope="col" class="num total">H</th>
                    <th scope="col" class="num total">T</th>
                    <th scope="col" class="num total">I</th>
                    <th scope="col" class="num total">S</th>
                    <th scope="col" class="num total">A</th>
                    <th scope="col" class="num total">% Hadir</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($report->rows as $row)
                    <tr>
                        <th scope="row" class="sticky-col">
                            <a href="{{ route('students.show', $row['student']) }}">{{ $row['student']->name }}</a>
                            @unless ($report->class)<span class="muted small">{{ $row['student']->schoolClass?->name }}</span>@endunless
                        </th>
                        @foreach ($row['cells'] as $index => $cell)
                            <td class="cell tone-{{ $cell['tone'] }}" @if ($cell['title']) title="{{ $cell['title'] }}" @endif>
                                {{ $cell['code'] }}@if ($cell['title'] && $cell['code'] !== '')<span class="sr-only"> ({{ $cell['title'] }})</span>@endif
                            </td>
                        @endforeach
                        @foreach ($row['counts'] as $count)
                            <td class="num total">{{ $count }}</td>
                        @endforeach
                        <td class="num total"><strong>{{ $row['percentage'] === null ? '—' : $row['percentage'].'%' }}</strong></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
