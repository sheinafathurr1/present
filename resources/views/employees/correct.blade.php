@extends('layouts.app')

@section('title', 'Koreksi Presensi')

@section('content')
<a href="{{ route('employees.show', ['user' => $employee, 'bulan' => $day->format('Y-m')]) }}" class="back-link"><x-icon name="arrow-left" :size="16" /> {{ $employee->name }}</a>
<div class="page-head">
    <div>
        <h1>Koreksi presensi</h1>
        <p class="lead">{{ $employee->name }} · {{ $day->translatedFormat('l, d F Y') }} · jam kerja {{ $workStart }}–{{ $workEnd }}</p>
    </div>
</div>

@unless ($isSchoolDay)
    <div class="callout callout--info callout--top"><x-icon name="info" /><p>Tanggal ini bukan hari sekolah. Koreksi tetap bisa disimpan bila memang ada kegiatan.</p></div>
@endunless

@if ($attendance)
    <section class="card form-narrow">
        <h2 class="card__title">Tercatat saat ini</h2>
        <dl class="details details--stacked">
            <div><dt>Status</dt><dd><x-status-badge :status="$attendance->status" /></dd></div>
            <div><dt>Masuk</dt><dd>{{ $attendance->check_in_at?->format('H:i') ?? '—' }}@if ($attendance->check_in_distance !== null) <span class="muted">· {{ $attendance->check_in_distance }} m dari sekolah, akurasi ± {{ $attendance->check_in_accuracy }} m</span>@endif</dd></div>
            <div><dt>Pulang</dt><dd>{{ $attendance->check_out_at?->format('H:i') ?? '—' }}@if ($attendance->check_out_distance !== null) <span class="muted">· {{ $attendance->check_out_distance }} m dari sekolah</span>@endif</dd></div>
            <div><dt>Sumber</dt><dd>{{ $attendance->sourceLabel() }}@if ($attendance->note) · {{ $attendance->note }}@endif</dd></div>
        </dl>
    </section>
@endif

<form method="POST" action="{{ route('corrections.update', [$employee, $day->toDateString()]) }}" class="card form-narrow" data-correction-form novalidate>
    @csrf
    @method('PUT')
    <h2 class="card__title">Ubah menjadi</h2>

    <fieldset class="choice-group @error('status') has-error @enderror">
        <legend>Status <span class="req" aria-hidden="true">*</span><span class="sr-only">(wajib)</span></legend>
        <div class="choice-grid choice-grid--compact">
            @foreach ($statuses as $status)
                <label class="choice">
                    <input type="radio" name="status" value="{{ $status->value }}" @checked(old('status', $attendance?->status->value ?? 'hadir') === $status->value)>
                    <span><strong><span class="swatch swatch--{{ $status->tone() }}" aria-hidden="true"></span>{{ $status->label() }}</strong></span>
                </label>
            @endforeach
        </div>
        @error('status')<p class="error"><x-icon name="alert" :size="16" /> {{ $message }}</p>@enderror
    </fieldset>

    <div class="form-grid" data-times>
        <x-input name="check_in" label="Jam masuk" type="time" :value="$attendance?->check_in_at?->format('H:i')" hint="Terlambat dihitung otomatis dari jam ini bila memilih Terlambat." />
        <x-input name="check_out" label="Jam pulang" type="time" :value="$attendance?->check_out_at?->format('H:i')" hint="Kosongkan bila tidak presensi pulang." />
    </div>

    <x-input name="note" label="Alasan koreksi" required placeholder="mis. HP rusak, sudah konfirmasi ke kepala sekolah" />

    <button type="submit" class="btn btn--primary btn--lg">Simpan koreksi</button>
</form>
@endsection
