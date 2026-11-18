@extends('layouts.app')

@section('title', $employee->name)

@section('content')
@php $isAdmin = auth()->user()->isAdmin(); @endphp
<a href="{{ route('employees.index') }}" class="back-link"><x-icon name="arrow-left" :size="16" /> Guru & Staf</a>

<div class="profile-head">
    <span class="avatar avatar--lg" aria-hidden="true">{{ $employee->initials() }}</span>
    <div class="profile-head__info">
        <h1>{{ $employee->name }}</h1>
        <p>
            {{ $employee->position ?: $employee->employee_type->label() }}
            @if ($employee->nip) · NIP {{ $employee->nip }}@endif
            @unless ($employee->is_active) · <span class="badge badge--muted">Nonaktif</span>@endunless
        </p>
    </div>
    @if ($isAdmin)
        <div class="actions">
            <a href="{{ route('employees.edit', $employee) }}" class="btn btn--secondary"><x-icon name="edit" :size="18" /> Ubah data</a>
        </div>
    @endif
</div>

<div class="grid-2 grid-2--sidebar">
    <section class="card" aria-labelledby="data-akun">
        <h2 id="data-akun" class="card__title">Data</h2>
        <dl class="details details--stacked">
            <div><dt>Username</dt><dd><code>{{ $employee->username }}</code></dd></div>
            <div><dt>Jenis</dt><dd>{{ $employee->employee_type->label() }}</dd></div>
            <div><dt>Peran</dt><dd>{{ $employee->role->label() }}</dd></div>
            <div><dt>Jam kerja</dt><dd>
                @if ($employee->must_attend)
                    {{ $workStart }}–{{ $workEnd }}
                    <span class="muted small">{{ $employee->work_start ? 'jadwal khusus' : 'jadwal '.mb_strtolower($employee->employee_type->label()) }}</span>
                @else
                    Tidak wajib presensi
                @endif
            </dd></div>
            @if ($employee->phone)<div><dt>Nomor HP</dt><dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $employee->phone) }}">{{ $employee->phone }}</a></dd></div>@endif
        </dl>
    </section>

    <section class="card" aria-labelledby="ringkasan-bulan">
        <div class="card__head">
            <h2 id="ringkasan-bulan" class="card__title">Ringkasan bulan</h2>
            @include('partials.month-nav')
        </div>
        @include('partials.month-stats')
    </section>
</div>

@if ($leaves->isNotEmpty())
    <section class="card" aria-labelledby="izin-bulan">
        <h2 id="izin-bulan" class="card__title">Pengajuan bulan ini</h2>
        <ul class="plain-list">
            @foreach ($leaves as $leave)
                <li>
                    <x-status-badge :status="$leave->type->status()" /> <strong>{{ $leave->periodLabel() }}</strong>
                    <span class="muted">· {{ $leave->reason }} · diajukan {{ $leave->created_at->translatedFormat('d M, H:i') }}</span>
                    @if ($leave->attachment_path)
                        <a class="attachment-link" href="{{ route('leaves.attachment', $leave) }}" target="_blank" rel="noopener"><x-icon name="paperclip" :size="16" /> Lampiran</a>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endif

<section class="card" aria-labelledby="per-hari">
    <h2 id="per-hari" class="card__title">Per hari</h2>
    @if ($employee->must_attend)
        @include('partials.day-table', ['correctFor' => $isAdmin ? $employee : null, 'showLocation' => true])
    @else
        <p class="empty">{{ $employee->name }} tidak diwajibkan presensi.</p>
    @endif
</section>
@endsection
