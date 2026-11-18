@extends('layouts.app')

@section('title', 'Izin & Cuti')

@section('content')
<div class="page-head">
    <div>
        <h1>Izin & Cuti</h1>
        <p class="lead">Pengajuan langsung tercatat tanpa menunggu persetujuan. Pimpinan sekolah dapat melihatnya.</p>
    </div>
    <div class="actions">
        <a href="{{ route('leaves.create') }}" class="btn btn--primary"><x-icon name="plus" :size="18" /> Ajukan</a>
    </div>
</div>

@if ($leaves->isEmpty())
    <div class="card empty-state">
        <x-icon name="file" :size="32" />
        <p>Belum ada pengajuan.</p>
        <a href="{{ route('leaves.create') }}" class="btn btn--secondary">Ajukan izin, sakit, cuti, atau dinas luar</a>
    </div>
@else
    <ul class="leave-list">
        @foreach ($leaves as $leave)
            @php
                $ongoing = $leave->isOngoing($today);
                $past = $leave->isPast($today);
                $schoolDays = $leave->attendances_count;
            @endphp
            <li class="card leave">
                <div class="leave__head">
                    <x-status-badge :status="$leave->type->status()" />
                    @if ($ongoing)<span class="badge badge--info">Sedang berlangsung</span>@endif
                    @if (! $past && ! $ongoing)<span class="badge badge--muted">Akan datang</span>@endif
                </div>
                <p class="leave__period">{{ $leave->periodLabel() }}</p>
                <p class="leave__meta">{{ $schoolDays }} hari sekolah · diajukan {{ $leave->created_at->translatedFormat('d M Y, H:i') }}</p>
                <p class="leave__reason">{{ $leave->reason }}</p>
                <div class="leave__actions">
                    @if ($leave->attachment_path)
                        <a class="btn btn--ghost btn--sm" href="{{ route('leaves.attachment', $leave) }}" target="_blank" rel="noopener">
                            <x-icon name="paperclip" :size="16" /> Lampiran
                        </a>
                    @endif
                    @if ($leave->canBeCancelledBy(auth()->user()))
                        <form method="POST" action="{{ route('leaves.destroy', $leave) }}"
                              data-confirm="{{ $ongoing && $leave->start_date < $today->toDateString()
                                  ? 'Akhiri '.$leave->type->label().' mulai hari ini? Hari sebelumnya tetap tercatat.'
                                  : 'Batalkan '.$leave->type->label().' '.$leave->periodLabel().'?' }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn--ghost btn--sm text-danger">
                                <x-icon name="x" :size="16" />
                                {{ $ongoing && $leave->start_date < $today->toDateString() ? 'Akhiri mulai hari ini' : 'Batalkan' }}
                            </button>
                        </form>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
    {{ $leaves->links() }}
@endif
@endsection
