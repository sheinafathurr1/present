@extends('layouts.app')

@section('title', 'Hari Libur')

@section('content')
@php
    $typeClass = [
        \App\Models\Holiday::TYPE_NATIONAL => 'national',
        \App\Models\Holiday::TYPE_JOINT => 'joint',
        \App\Models\Holiday::TYPE_SCHOOL => 'school',
    ];
@endphp

<div class="page-head">
    <div>
        <h1>Hari Libur {{ $year }}</h1>
        <p class="lead">{{ $offCount }} hari libur · siswa tidak ditandai alpa dan hari ini tidak dihitung di rekap.</p>
    </div>
    <div class="actions">
        <nav class="year-nav" aria-label="Pilih tahun">
            <a class="btn btn--secondary btn--icon" href="{{ route('holidays.index', ['tahun' => $year - 1]) }}" title="{{ $year - 1 }}">‹<span class="sr-only"> Tahun {{ $year - 1 }}</span></a>
            <span class="year-nav__label">{{ $year }}</span>
            <a class="btn btn--secondary btn--icon" href="{{ route('holidays.index', ['tahun' => $year + 1]) }}" title="{{ $year + 1 }}">›<span class="sr-only"> Tahun {{ $year + 1 }}</span></a>
        </nav>
        <form method="POST" action="{{ route('holidays.sync') }}">
            @csrf
            <input type="hidden" name="tahun" value="{{ $year }}">
            <button type="submit" class="btn btn--secondary"><x-icon name="refresh" :size="18" /> Perbarui libur nasional</button>
        </form>
    </div>
</div>

@if (! $hasNational || $sync['source'] === \App\Services\NationalHolidaySync::SOURCE_BUILT_IN)
    <div class="callout callout--top">
        <x-icon name="info" :size="20" />
        <p>
            @if ($hasNational)
                Server belum bisa mengambil kalender libur dari internet, jadi baru libur bertanggal tetap (Tahun Baru, Hari Buruh, Pancasila, 17 Agustus, Natal, dan libur Paskah) yang terisi.
            @else
                Libur nasional {{ $year }} belum terisi.
            @endif
            Klik <strong>Perbarui libur nasional</strong> saat server terhubung internet untuk melengkapi Idul Fitri, Imlek, Nyepi, Waisak, dan cuti bersama.
        </p>
    </div>
@elseif ($sync['at'])
    <p class="sync-note">
        <x-icon name="check" :size="16" />
        <span>Libur nasional & cuti bersama diperbarui otomatis tiap Senin · terakhir {{ $sync['at']->diffForHumans() }}.
            Cuti bersama {{ $jointOff ? 'dihitung libur' : 'tetap masuk sekolah' }} (<a href="{{ route('settings.edit') }}">ubah</a>).</span>
    </p>
@endif

<ul class="cal-legend" aria-label="Keterangan warna">
    <li><span class="cal-swatch cal-swatch--national" aria-hidden="true"></span> Libur nasional</li>
    <li><span class="cal-swatch cal-swatch--joint" aria-hidden="true"></span> Cuti bersama</li>
    <li><span class="cal-swatch cal-swatch--school" aria-hidden="true"></span> Libur sekolah</li>
    <li><span class="cal-swatch cal-swatch--cancelled" aria-hidden="true"></span> Tetap masuk sekolah</li>
    <li><span class="cal-swatch cal-swatch--weekend" aria-hidden="true"></span> Bukan hari sekolah</li>
</ul>

<section class="cal-grid" aria-label="Kalender {{ $year }}" data-cal-grid>
    @foreach ($months as $month)
        <div class="cal-month" @if ($month['current']) data-cal-current @endif>
            <div class="cal-month__head">
                <h2>{{ $month['label'] }}</h2>
                @if ($month['count'] > 0)<span class="muted small">{{ $month['count'] }} libur</span>@endif
            </div>
            <table class="cal">
                <caption class="sr-only">{{ $month['label'] }} {{ $year }}</caption>
                <thead>
                <tr>
                    @foreach ($weekdays as $short => $full)
                        <th scope="col" abbr="{{ $full }}">{{ $short }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach ($month['weeks'] as $week)
                    <tr>
                        @foreach ($week as $cell)
                            <td>
                                @if ($cell)
                                    @php
                                        $holiday = $cell['holiday'];
                                        $classes = ['cal-day'];
                                        if ($holiday) {
                                            $classes[] = 'is-'.$typeClass[$holiday->type];
                                            if ($holiday->is_school_day) { $classes[] = 'is-cancelled'; }
                                            if ($holiday->is_tentative) { $classes[] = 'is-tentative'; }
                                        }
                                        if ($cell['weekend']) { $classes[] = 'is-weekend'; }
                                        if ($cell['today']) { $classes[] = 'is-today'; }
                                        $label = $cell['label'];
                                        if ($holiday) {
                                            $label .= ': '.$holiday->name.' ('.$holiday->typeLabel().($holiday->is_tentative ? ', belum pasti' : '').($holiday->is_school_day ? ', tetap masuk sekolah' : '').')';
                                        } elseif ($cell['weekend']) {
                                            $label .= ': bukan hari sekolah';
                                        }
                                    @endphp
                                    <button type="button" class="{{ implode(' ', $classes) }}" data-cal-day="{{ $cell['date'] }}"
                                            @if ($holiday) data-holiday @endif aria-label="{{ $label }}" title="{{ $holiday?->name }}">
                                        {{ $cell['day'] }}
                                    </button>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</section>
<p class="hint cal-hint">Pilih tanggal kosong untuk mengisi formulir libur sekolah (klik tanggal kedua untuk rentang), atau tanggal berwarna untuk melihat keterangannya.<span class="cal-hint__swipe"> Geser kalender ke samping untuk melihat bulan lain.</span></p>

<div class="grid-2 grid-2--sidebar holiday-bottom">
    <form method="POST" action="{{ route('holidays.store') }}" class="card" id="form-libur" novalidate>
        @csrf
        <h2 class="card__title">Tambah libur sekolah</h2>
        <x-input name="name" label="Keterangan" required placeholder="mis. Libur semester ganjil" />
        <x-input name="date" label="Tanggal mulai" type="date" required />
        <x-input name="end_date" label="Sampai tanggal" type="date" hint="Isi bila libur lebih dari satu hari." />
        <button type="submit" class="btn btn--primary btn--block"><x-icon name="plus" :size="18" /> Simpan</button>
    </form>

    <section class="card" aria-labelledby="daftar-libur">
        <h2 id="daftar-libur" class="card__title">Daftar libur {{ $year }}</h2>
        @if ($holidays->isEmpty())
            <p class="empty">Belum ada hari libur untuk tahun {{ $year }}.</p>
        @else
            <ul class="holiday-list">
                @foreach ($holidays as $holiday)
                    @php $date = \Carbon\Carbon::parse($holiday->date); @endphp
                    <li id="libur-{{ $holiday->date }}" class="@if ($holiday->is_school_day) is-cancelled @endif">
                        <span class="holiday-list__date">
                            <span class="cal-swatch cal-swatch--{{ $holiday->is_school_day ? 'cancelled' : $typeClass[$holiday->type] }}" aria-hidden="true"></span>
                            {{ $date->translatedFormat('D, d M') }}
                        </span>
                        <span class="holiday-list__name">
                            {{ $holiday->name }}
                            <span class="holiday-list__tags">
                                <span class="muted small">{{ $holiday->typeLabel() }}</span>
                                @if ($holiday->is_tentative)<span class="badge badge--warning">Belum pasti</span>@endif
                                @if ($holiday->is_school_day)<span class="badge badge--muted">Tetap masuk sekolah</span>@endif
                            </span>
                        </span>
                        <span class="holiday-list__actions">
                            @if ($holiday->is_auto)
                                <form method="POST" action="{{ route('holidays.toggle', $holiday) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn--ghost btn--sm">
                                        {{ $holiday->is_school_day ? 'Jadikan libur' : 'Tetap masuk sekolah' }}<span class="sr-only"> pada {{ $date->translatedFormat('d F') }}</span>
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('holidays.destroy', $holiday) }}" data-confirm="Hapus libur {{ $holiday->name }} ({{ $date->translatedFormat('d F Y') }})?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn--ghost btn--sm"><x-icon name="trash" :size="16" /> Hapus<span class="sr-only"> {{ $holiday->name }}</span></button>
                                </form>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
