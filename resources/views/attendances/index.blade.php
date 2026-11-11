@extends('layouts.app')

@section('title', 'Presensi Harian')

@section('content')
@php
    $dateString = $date->toDateString();
    $isToday = $date->isToday();
    $statusOptions = collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all();
@endphp
<div class="page-head">
    <div>
        <h1>Presensi Harian</h1>
        <p class="lead">{{ $date->translatedFormat('l, d F Y') }}
            @if ($isToday)<span class="badge badge--info">Hari ini</span>@endif
            @unless ($isSchoolDay)<span class="badge badge--muted">Bukan hari sekolah</span>@endunless
        </p>
    </div>
    <div class="actions">
        <a class="btn btn--secondary" href="{{ route('attendances.index', array_merge($filters, ['tanggal' => $date->subDay()->toDateString()])) }}">‹ Hari sebelumnya</a>
        @unless ($isToday)
            <a class="btn btn--secondary" href="{{ route('attendances.index', array_merge($filters, ['tanggal' => $date->addDay()->toDateString()])) }}">Hari berikutnya ›</a>
        @endunless
    </div>
</div>

<form method="GET" class="filters card" role="search" aria-label="Saring daftar presensi">
    <div class="field">
        <label for="f-tanggal">Tanggal</label>
        <input id="f-tanggal" class="input" type="date" name="tanggal" value="{{ $dateString }}" max="{{ today()->toDateString() }}">
    </div>
    <div class="field">
        <label for="f-kelas">Kelas</label>
        <select id="f-kelas" name="kelas" class="input">
            <option value="">Semua kelas</option>
            @foreach ($classes as $class)
                <option value="{{ $class->id }}" @selected(($filters['kelas'] ?? null) == $class->id)>{{ $class->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="f-status">Status</label>
        <select id="f-status" name="status" class="input">
            <option value="">Semua status</option>
            <option value="belum" @selected(($filters['status'] ?? null) === 'belum')>Belum presensi</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="f-q">Cari siswa</label>
        <input id="f-q" class="input" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nama atau NIS">
    </div>
    <div class="filters__actions">
        <button class="btn btn--primary" type="submit"><x-icon name="search" /> Tampilkan</button>
    </div>
</form>

<section class="card" aria-labelledby="daftar-presensi">
    <div class="card__head">
        <h2 id="daftar-presensi">{{ $students->count() }} siswa</h2>
        @if ($isSchoolDay && $date->lte(today()))
            <form method="POST" action="{{ route('attendances.mark-absent') }}"
                  data-confirm="Tandai semua siswa yang belum presensi pada {{ $date->translatedFormat('d F Y') }} sebagai Alpa?">
                @csrf
                <input type="hidden" name="date" value="{{ $dateString }}">
                <button type="submit" class="btn btn--secondary btn--sm">Tandai yang belum presensi sebagai Alpa</button>
            </form>
        @endif
    </div>

    @if ($students->isEmpty())
        <p class="empty">Tidak ada siswa yang sesuai saringan.</p>
    @else
        <div class="table-wrap">
            <table class="table table--stack">
                <caption class="sr-only">Daftar presensi siswa tanggal {{ $date->translatedFormat('d F Y') }}</caption>
                <thead>
                <tr>
                    <th scope="col">Siswa</th>
                    <th scope="col">Status</th>
                    <th scope="col">Masuk</th>
                    <th scope="col">Pulang</th>
                    <th scope="col">Ubah status</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($students as $student)
                    @php $record = $student->today; @endphp
                    <tr id="siswa-{{ $student->id }}">
                        <th scope="row" data-label="Siswa">
                            <span class="person">
                                <x-avatar :student="$student" :size="36" />
                                <span>
                                    <a href="{{ route('students.show', $student) }}">{{ $student->name }}</a>
                                    <span class="muted">{{ $student->schoolClass?->name ?? 'Tanpa kelas' }} · NIS {{ $student->nis }}</span>
                                </span>
                            </span>
                        </th>
                        <td data-label="Status">
                            <x-status-badge :status="$record?->status" />
                            @if ($record?->note)<span class="note">“{{ $record->note }}”</span>@endif
                            @if ($record && $record->source !== 'rfid')
                                <span class="muted small">{{ $record->sourceLabel() }}{{ $record->recorder ? ' · '.$record->recorder->name : '' }}</span>
                            @endif
                        </td>
                        <td data-label="Masuk">{{ $record?->check_in_at?->format('H.i') ?? '—' }}</td>
                        <td data-label="Pulang">{{ $record?->check_out_at?->format('H.i') ?? '—' }}</td>
                        <td data-label="Ubah status">
                            <form method="POST" action="{{ route('attendances.store') }}" class="inline-form">
                                @csrf
                                <input type="hidden" name="student_id" value="{{ $student->id }}">
                                <input type="hidden" name="date" value="{{ $dateString }}">
                                <label class="sr-only" for="st-{{ $student->id }}">Status {{ $student->name }}</label>
                                <select id="st-{{ $student->id }}" name="status" class="input input--sm" required>
                                    @unless ($record)<option value="" selected disabled>Pilih status…</option>@endunless
                                    @foreach ($statusOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($record?->status?->value === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <label class="sr-only" for="nt-{{ $student->id }}">Catatan untuk {{ $student->name }}</label>
                                <input id="nt-{{ $student->id }}" name="note" class="input input--sm" value="{{ $record?->note }}" placeholder="Catatan (opsional)" maxlength="255">
                                <button type="submit" class="btn btn--secondary btn--sm">Simpan</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
