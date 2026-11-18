@extends('layouts.app')

@section('title', 'Riwayat Saya')

@section('content')
<div class="page-head">
    <div>
        <h1>Riwayat Saya</h1>
        <p class="lead">Kehadiran Anda per bulan.</p>
    </div>
    @include('partials.month-nav')
</div>

<section class="card">
    @include('partials.month-stats')
</section>

@if ($leaves->isNotEmpty())
    <section class="card" aria-labelledby="izin-bulan-ini">
        <div class="card__head">
            <h2 id="izin-bulan-ini" class="card__title">Pengajuan bulan ini</h2>
            <a class="link-quiet" href="{{ route('leaves.index') }}">Kelola</a>
        </div>
        <ul class="plain-list">
            @foreach ($leaves as $leave)
                <li><x-status-badge :status="$leave->type->status()" /> {{ $leave->periodLabel() }} <span class="muted">· {{ $leave->reason }}</span></li>
            @endforeach
        </ul>
    </section>
@endif

<section class="card" aria-labelledby="hari-hari">
    <h2 id="hari-hari" class="card__title">Per hari</h2>
    @include('partials.day-table')
</section>
@endsection
