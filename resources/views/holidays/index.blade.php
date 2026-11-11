@extends('layouts.app')

@section('title', 'Hari Libur')

@section('content')
<div class="page-head">
    <div>
        <h1>Hari libur</h1>
        <p class="lead">Pada hari libur siswa tidak ditandai alpa dan tidak dihitung di rekap.</p>
    </div>
    <div class="actions">
        <a class="btn btn--secondary" href="{{ route('holidays.index', ['tahun' => $year - 1]) }}">‹ {{ $year - 1 }}</a>
        <a class="btn btn--secondary" href="{{ route('holidays.index', ['tahun' => $year + 1]) }}">{{ $year + 1 }} ›</a>
    </div>
</div>

<div class="grid-2 grid-2--sidebar">
    <form method="POST" action="{{ route('holidays.store') }}" class="card" novalidate>
        @csrf
        <h2>Tambah hari libur</h2>
        <x-input name="name" label="Keterangan" required placeholder="mis. Libur semester" />
        <x-input name="date" label="Tanggal mulai" type="date" required />
        <x-input name="end_date" label="Sampai tanggal" type="date" hint="Isi bila libur lebih dari satu hari." />
        <button type="submit" class="btn btn--primary btn--block"><x-icon name="plus" /> Simpan</button>
    </form>

    <section class="card" aria-labelledby="daftar-libur">
        <h2 id="daftar-libur">Daftar libur {{ $year }}</h2>
        @if ($holidays->isEmpty())
            <p class="empty">Belum ada hari libur untuk tahun {{ $year }}.</p>
        @else
            <ul class="holiday-list">
                @foreach ($holidays as $holiday)
                    <li>
                        <span class="holiday-list__date">{{ \Carbon\Carbon::parse($holiday->date)->translatedFormat('l, d F Y') }}</span>
                        <span class="holiday-list__name">{{ $holiday->name }}</span>
                        <form method="POST" action="{{ route('holidays.destroy', $holiday) }}" data-confirm="Hapus hari libur {{ $holiday->name }}?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn--ghost btn--sm"><x-icon name="trash" :size="16" /> Hapus<span class="sr-only"> {{ $holiday->name }}</span></button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
