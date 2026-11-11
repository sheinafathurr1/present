@extends('layouts.app')

@php $editing = $schoolClass->exists; @endphp
@section('title', $editing ? 'Ubah Kelas' : 'Tambah Kelas')

@section('content')
<div class="page-head">
    <div>
        <a href="{{ route('classes.index') }}" class="back-link"><x-icon name="arrow-left" :size="18" /> Kelas</a>
        <h1>{{ $editing ? 'Ubah kelas '.$schoolClass->name : 'Tambah kelas' }}</h1>
    </div>
</div>

<form method="POST" action="{{ $editing ? route('classes.update', $schoolClass) : route('classes.store') }}" class="form-layout" novalidate>
    @csrf
    @if ($editing) @method('PUT') @endif
    <section class="card">
        <div class="form-grid">
            <x-input name="name" label="Nama kelas" :value="$schoolClass->name" required placeholder="mis. Kelas 3 B" />
            <x-select name="level" label="Jenjang" :value="$schoolClass->level" placeholder="— Pilih —" :options="array_combine($levels, $levels)" />
            <x-select name="homeroom_teacher_id" label="Wali kelas" :value="$schoolClass->homeroom_teacher_id" placeholder="— Belum ditentukan —"
                      :options="$teachers->pluck('name', 'id')->all()" />
        </div>
    </section>
    <section class="card" aria-labelledby="jadwal">
        <h2 id="jadwal"><x-icon name="clock" /> Jadwal khusus (opsional)</h2>
        <p class="hint">Kosongkan untuk mengikuti jadwal di menu Pengaturan.</p>
        <div class="form-grid">
            <x-input name="late_after" label="Batas jam masuk (lewat = terlambat)" type="time" :value="$schoolClass->late_after" />
            <x-input name="check_out_start" label="Presensi pulang mulai" type="time" :value="$schoolClass->check_out_start" />
        </div>
    </section>
    <div class="form-actions">
        <button type="submit" class="btn btn--primary btn--lg"><x-icon name="check" /> Simpan</button>
        <a href="{{ route('classes.index') }}" class="btn btn--ghost btn--lg">Batal</a>
    </div>
</form>

@if ($editing)
    <form method="POST" action="{{ route('classes.destroy', $schoolClass) }}" class="danger-zone card" data-confirm="Hapus kelas {{ $schoolClass->name }}?">
        @csrf
        @method('DELETE')
        <div>
            <h2>Hapus kelas</h2>
            <p class="muted">Hanya bisa dihapus bila tidak ada siswa di dalamnya.</p>
        </div>
        <button type="submit" class="btn btn--danger"><x-icon name="trash" /> Hapus kelas</button>
    </form>
@endif
@endsection
