@extends('layouts.app')

@section('title', 'Guru & Staf')

@section('content')
@php $isAdmin = auth()->user()->isAdmin(); @endphp
<div class="page-head">
    <div>
        <h1>Guru & Staf</h1>
        <p class="lead">{{ $counts['guru'] }} guru · {{ $counts['tendik'] }} staf tendik aktif</p>
    </div>
    @if ($isAdmin)
        <div class="actions">
            <a href="{{ route('employees.create') }}" class="btn btn--primary"><x-icon name="plus" :size="18" /> Tambah</a>
        </div>
    @endif
</div>

<form method="GET" class="card filters" role="search">
    <div class="field field--grow">
        <label for="f-cari">Cari</label>
        <input id="f-cari" class="input" type="search" name="cari" value="{{ $search }}" placeholder="Nama, username, NIP, atau jabatan">
    </div>
    <x-select name="jenis" label="Jenis" :options="['guru' => 'Guru', 'tendik' => 'Staf tendik']" :value="$type?->value" placeholder="Semua" data-autosubmit />
    <x-select name="status" label="Status akun" :options="['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif', 'semua' => 'Semua']" :value="$state" data-autosubmit />
    <div class="filters__actions"><button type="submit" class="btn btn--secondary"><x-icon name="search" :size="18" /> Cari</button></div>
</form>

<section class="card">
    @if ($employees->isEmpty())
        <p class="empty">Tidak ada yang cocok.</p>
    @else
        <div class="table-wrap">
            <table class="table table--stack">
                <thead>
                <tr>
                    <th scope="col">Nama</th>
                    <th scope="col">Username</th>
                    <th scope="col">Jenis</th>
                    <th scope="col">Hari ini</th>
                    @if ($isAdmin)<th scope="col"><span class="sr-only">Aksi</span></th>@endif
                </tr>
                </thead>
                <tbody>
                @foreach ($employees as $employee)
                    @php $attendance = $employee->attendances->first(); @endphp
                    <tr>
                        <th scope="row">
                            <a href="{{ route('employees.show', $employee) }}">{{ $employee->name }}</a>
                            <span class="muted">{{ $employee->position ?: '—' }}@if ($employee->nip) · NIP {{ $employee->nip }}@endif</span>
                        </th>
                        <td data-label="Username"><code>{{ $employee->username }}</code></td>
                        <td data-label="Jenis">
                            {{ $employee->employee_type->label() }}
                            @if ($employee->role !== \App\Enums\UserRole::Pegawai)<span class="badge badge--info">{{ $employee->role->label() }}</span>@endif
                        </td>
                        <td data-label="Hari ini">
                            @if (! $employee->is_active)
                                <span class="badge badge--muted">Nonaktif</span>
                            @elseif (! $employee->must_attend)
                                <span class="muted">Tidak wajib presensi</span>
                            @else
                                <x-status-badge :status="$attendance?->status" />
                            @endif
                        </td>
                        @if ($isAdmin)
                            <td class="row-actions">
                                <a class="btn btn--ghost btn--sm" href="{{ route('employees.edit', $employee) }}"><x-icon name="edit" :size="16" /> Ubah<span class="sr-only"> {{ $employee->name }}</span></a>
                            </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $employees->links() }}
    @endif
</section>
@endsection
