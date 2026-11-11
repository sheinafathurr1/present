@extends('layouts.app')

@section('title', 'Data Siswa')

@section('content')
@php $isAdmin = auth()->user()->isAdmin(); @endphp
<div class="page-head">
    <div>
        <h1>Data Siswa</h1>
        <p class="lead">{{ $students->total() }} siswa ditemukan</p>
    </div>
    @if ($isAdmin)
        <div class="actions">
            <a href="{{ route('students.import') }}" class="btn btn--secondary"><x-icon name="upload" /> Impor dari Excel</a>
            <a href="{{ route('students.create') }}" class="btn btn--primary"><x-icon name="plus" /> Tambah siswa</a>
        </div>
    @endif
</div>

<form method="GET" class="filters card" role="search" aria-label="Cari siswa">
    <div class="field field--grow">
        <label for="f-q">Cari</label>
        <input id="f-q" class="input" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nama, NIS, atau nomor kartu">
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
        <label for="f-kartu">Kartu</label>
        <select id="f-kartu" name="kartu" class="input">
            <option value="">Semua</option>
            <option value="ada" @selected(($filters['kartu'] ?? null) === 'ada')>Sudah punya kartu</option>
            <option value="belum" @selected(($filters['kartu'] ?? null) === 'belum')>Belum punya kartu</option>
        </select>
    </div>
    <div class="field">
        <label for="f-status">Status</label>
        <select id="f-status" name="status" class="input">
            <option value="aktif" @selected(($filters['status'] ?? 'aktif') === 'aktif')>Aktif</option>
            <option value="nonaktif" @selected(($filters['status'] ?? null) === 'nonaktif')>Nonaktif</option>
        </select>
    </div>
    <div class="filters__actions">
        <button class="btn btn--primary" type="submit"><x-icon name="search" /> Cari</button>
    </div>
</form>

<section class="card">
    @if ($students->isEmpty())
        <div class="empty">
            <p>Belum ada data siswa yang sesuai.</p>
            @if ($isAdmin)
                <p><a href="{{ route('students.create') }}" class="btn btn--primary"><x-icon name="plus" /> Tambah siswa pertama</a></p>
            @endif
        </div>
    @else
        <div class="table-wrap">
            <table class="table table--stack">
                <caption class="sr-only">Daftar siswa</caption>
                <thead>
                <tr>
                    <th scope="col">Siswa</th>
                    <th scope="col">Kelas</th>
                    <th scope="col">Kekhususan</th>
                    <th scope="col">Kartu RFID</th>
                    @if ($isAdmin)<th scope="col"><span class="sr-only">Aksi</span></th>@endif
                </tr>
                </thead>
                <tbody>
                @foreach ($students as $student)
                    <tr>
                        <th scope="row" data-label="Siswa">
                            <span class="person">
                                <x-avatar :student="$student" :size="36" />
                                <span>
                                    <a href="{{ route('students.show', $student) }}">{{ $student->name }}</a>
                                    <span class="muted">NIS {{ $student->nis }}{{ $student->nickname ? ' · "'.$student->nickname.'"' : '' }}</span>
                                </span>
                            </span>
                        </th>
                        <td data-label="Kelas">{{ $student->schoolClass?->name ?? '—' }}</td>
                        <td data-label="Kekhususan">{{ $student->disability_type?->label() ?? '—' }}</td>
                        <td data-label="Kartu RFID">
                            @if ($student->rfid_uid)
                                <span class="badge badge--success"><x-icon name="card" :size="16" /> <code>{{ $student->rfid_uid }}</code></span>
                            @else
                                <span class="badge badge--muted">Belum terdaftar</span>
                            @endif
                        </td>
                        @if ($isAdmin)
                            <td data-label="Aksi" class="row-actions">
                                <a href="{{ route('students.edit', $student) }}" class="btn btn--ghost btn--sm"><x-icon name="edit" :size="16" /> Ubah<span class="sr-only"> {{ $student->name }}</span></a>
                            </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $students->links() }}
    @endif
</section>
@endsection
