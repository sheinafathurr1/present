@extends('layouts.app')

@section('title', 'Ekspor Data')

@section('content')
<div class="page-head">
    <div>
        <h1>Ekspor data presensi</h1>
        <p class="lead">Unduh data satu guru/staf atau semua orang untuk rentang tanggal yang Anda pilih.</p>
    </div>
</div>

<form method="GET" action="{{ route('exports.download') }}" class="card form-narrow" data-export-form novalidate>
    <x-select name="pegawai" label="Guru/staf" :value="old('pegawai', $selected)" placeholder="Semua guru & staf (ringkasan per orang)"
              :options="$employees->mapWithKeys(fn ($e) => [$e->id => $e->name.($e->position ? ' — '.$e->position : '').($e->is_active ? '' : ' (nonaktif)')])->all()"
              data-export-person />

    <div data-for-everyone>
        <x-select name="jenis" label="Jenis" :value="old('jenis', $type?->value)" placeholder="Guru & staf tendik"
                  :options="['guru' => 'Guru saja', 'tendik' => 'Staf tendik saja']" />
    </div>

    @include('partials.date-range')
    @include('partials.export-format')

    <div class="export-note" aria-live="polite">
        <p class="hint" data-note-person hidden><x-icon name="info" :size="16" /> Berisi ringkasan, setiap tanggal dalam rentang (termasuk libur), jam masuk & pulang, keterlambatan, jarak dari sekolah, dan pengajuan izin.</p>
        <p class="hint" data-note-everyone><x-icon name="info" :size="16" /> Berisi ringkasan per orang (jumlah hadir, terlambat, izin, sakit, cuti, dinas luar, alpa, total menit terlambat, persentase) dan rincian harian.</p>
    </div>

    <button type="submit" class="btn btn--primary btn--lg"><x-icon name="download" :size="18" /> Unduh</button>
</form>
@endsection
