@extends('layouts.app')

@section('title', 'Pengajuan Izin')

@section('content')
<div class="page-head">
    <div>
        <h1>Pengajuan Izin</h1>
        <p class="lead">Izin, sakit, cuti, dan dinas luar guru & staf pada {{ $month->translatedFormat('F Y') }}.</p>
    </div>
    @include('partials.month-nav')
</div>

<form method="GET" class="card filters" role="search">
    <input type="hidden" name="bulan" value="{{ $month->format('Y-m') }}">
    <x-select name="jenis" label="Jenis" :options="collect($types)->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()" :value="$type?->value" placeholder="Semua jenis" data-autosubmit />
    <x-select name="pegawai" label="Guru/staf" :options="$employees->pluck('name', 'id')->all()" :value="request('pegawai')" placeholder="Semua orang" data-autosubmit />
    <noscript><button type="submit" class="btn btn--secondary">Terapkan</button></noscript>
</form>

<section class="card">
    @if ($leaves->isEmpty())
        <p class="empty">Tidak ada pengajuan pada bulan ini.</p>
    @else
        <div class="table-wrap">
            <table class="table table--stack">
                <thead>
                <tr>
                    <th scope="col">Nama</th>
                    <th scope="col">Jenis</th>
                    <th scope="col">Tanggal</th>
                    <th scope="col">Keterangan</th>
                    <th scope="col">Diajukan</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($leaves as $leave)
                    <tr>
                        <th scope="row">
                            <a href="{{ route('employees.show', ['user' => $leave->user, 'bulan' => $month->format('Y-m')]) }}">{{ $leave->user->name }}</a>
                            <span class="muted">{{ $leave->user->subtitle() }}</span>
                        </th>
                        <td data-label="Jenis"><x-status-badge :status="$leave->type->status()" /></td>
                        <td data-label="Tanggal">{{ $leave->periodLabel() }}<span class="muted">{{ $leave->calendarDays() }} hari kalender</span></td>
                        <td data-label="Keterangan">
                            {{ $leave->reason }}
                            @if ($leave->attachment_path)
                                <a class="attachment-link" href="{{ route('leaves.attachment', $leave) }}" target="_blank" rel="noopener"><x-icon name="paperclip" :size="16" /> Lampiran</a>
                            @endif
                        </td>
                        <td data-label="Diajukan">{{ $leave->created_at->translatedFormat('d M, H:i') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $leaves->links() }}
    @endif
</section>
@endsection
