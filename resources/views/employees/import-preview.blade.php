@extends('layouts.app')

@section('title', 'Periksa Impor')

@section('content')
@php
    $rows = collect($analysis['rows']);
    if ($analysis['invalid'] > 0) {
        $rows = $rows->sortBy(fn ($row) => [$row['errors'] === [] ? 1 : 0, $row['line']])->values();
    }
    $total = $rows->count();
@endphp
<a href="{{ route('employees.import') }}" class="back-link"><x-icon name="arrow-left" :size="16" /> Impor guru & staf</a>
<div class="page-head">
    <div>
        <h1>Periksa data</h1>
        <p class="lead">{{ $fileName }} · {{ $total }} baris</p>
    </div>
</div>

<dl class="import-summary">
    <div class="import-summary__item is-new"><dt>Akun baru</dt><dd>{{ $analysis['created'] }}</dd></div>
    <div class="import-summary__item is-update"><dt>Diperbarui</dt><dd>{{ $analysis['updated'] }}</dd></div>
    <div class="import-summary__item @if ($analysis['invalid'] > 0) is-error @endif"><dt>Perlu diperbaiki</dt><dd>{{ $analysis['invalid'] }}</dd></div>
</dl>

@if ($analysis['invalid'] > 0)
    <div class="alert alert--error" role="alert">
        <x-icon name="alert" />
        <div>
            <strong>Ada {{ $analysis['invalid'] }} baris yang perlu diperbaiki.</strong>
            Perbaiki di file (nomor baris sesuai Excel), lalu unggah ulang. Belum ada data yang disimpan.
        </div>
    </div>
    <div class="form-actions">
        <a href="{{ route('employees.import') }}" class="btn btn--primary"><x-icon name="upload" :size="18" /> Unggah ulang</a>
    </div>
@else
    <form method="POST" action="{{ route('employees.import.store') }}" class="card import-confirm">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <p>Semua baris sudah benar. Kata sandi yang dibuat otomatis akan ditampilkan setelah disimpan.</p>
        <div class="actions">
            <button type="submit" class="btn btn--primary btn--lg"><x-icon name="check" :size="18" /> Simpan {{ $total }} akun</button>
            <a href="{{ route('employees.import') }}" class="btn btn--ghost btn--lg">Batal</a>
        </div>
    </form>
@endif

<section class="card" aria-labelledby="isi-file">
    <h2 id="isi-file" class="card__title">Isi file</h2>
    <div class="table-wrap">
        <table class="table table--stack table--import">
            <thead>
            <tr>
                <th scope="col" class="num">Baris</th>
                <th scope="col">Nama</th>
                <th scope="col">Username</th>
                <th scope="col">Jenis & jabatan</th>
                <th scope="col">NIP / HP</th>
                <th scope="col">Peran</th>
                <th scope="col">Hasil</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($rows as $row)
                @php $a = $row['attributes']; @endphp
                <tr @if ($row['errors']) class="has-error" @endif>
                    <td data-label="Baris" class="num"><span>{{ $row['line'] }}</span></td>
                    <th scope="row">{{ $a['name'] ?? '—' }}</th>
                    <td data-label="Username">
                        @isset($a['username'])<code>{{ $a['username'] }}</code>@endisset
                        @if ($row['generated']['username'])<span class="muted small">dibuat otomatis</span>@endif
                    </td>
                    <td data-label="Jenis & jabatan">
                        <span @unless (isset($a['employee_type'])) class="muted" @endunless>{{ isset($a['employee_type']) ? $a['employee_type']->label() : 'tetap' }}</span>
                        @isset($a['position'])<span class="muted">{{ $a['position'] }}</span>@endisset
                    </td>
                    <td data-label="NIP / HP">
                        @isset($a['nip'])<span>{{ $a['nip'] }}</span>@endisset
                        @isset($a['phone'])<span class="muted">{{ $a['phone'] }}</span>@endisset
                    </td>
                    <td data-label="Peran">
                        <span @unless (isset($a['role'])) class="muted" @endunless>{{ isset($a['role']) ? $a['role']->label() : 'tetap' }}</span>
                        @if (($a['must_attend'] ?? true) === false)<span class="muted small">tidak wajib presensi</span>@endif
                        @isset($a['work_start'])<span class="muted small">jam {{ $a['work_start'] }}–{{ $a['work_end'] ?? '?' }}</span>@endisset
                    </td>
                    <td data-label="Hasil">
                        @if ($row['errors'])
                            <ul class="error-list">
                                @foreach ($row['errors'] as $message)<li>{{ $message }}</li>@endforeach
                            </ul>
                        @elseif ($row['action'] === 'baru')
                            <span class="badge badge--success">Akun baru</span>
                        @else
                            <span class="badge badge--info">Diperbarui</span>
                            @if ($row['password'])<span class="muted small">kata sandi diganti</span>@endif
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
