@extends('layouts.app')

@section('title', 'Log Tap Kartu')

@section('content')
<div class="page-head">
    <div>
        <h1>Log Tap Kartu</h1>
        <p class="lead">Semua kartu yang ditempel di pembaca, termasuk kartu yang belum terdaftar.</p>
    </div>
</div>

<form method="GET" class="filters card" aria-label="Saring log">
    <div class="field">
        <label for="f-tanggal">Tanggal</label>
        <input id="f-tanggal" class="input" type="date" name="tanggal" value="{{ $filters['tanggal'] ?? '' }}">
    </div>
    <div class="field">
        <label for="f-hasil">Hasil</label>
        <select id="f-hasil" name="hasil" class="input">
            <option value="">Semua hasil</option>
            @foreach ($results as $result)
                <option value="{{ $result->value }}" @selected(($filters['hasil'] ?? null) === $result->value)>{{ $result->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="f-perangkat">Perangkat</label>
        <select id="f-perangkat" name="perangkat" class="input">
            <option value="">Semua perangkat</option>
            @foreach ($devices as $device)
                <option value="{{ $device->id }}" @selected(($filters['perangkat'] ?? null) == $device->id)>{{ $device->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="filters__actions">
        <button class="btn btn--primary" type="submit">Tampilkan</button>
    </div>
</form>

<section class="card">
    @if ($scans->isEmpty())
        <p class="empty">Belum ada data tap kartu.</p>
    @else
        <div class="table-wrap">
            <table class="table table--stack">
                <caption class="sr-only">Log tap kartu</caption>
                <thead>
                <tr>
                    <th scope="col">Waktu</th>
                    <th scope="col">Kartu</th>
                    <th scope="col">Siswa</th>
                    <th scope="col">Hasil</th>
                    <th scope="col">Perangkat</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($scans as $scan)
                    <tr>
                        <td data-label="Waktu">{{ $scan->scanned_at->translatedFormat('d M Y, H.i.s') }}</td>
                        <td data-label="Kartu"><code>{{ $scan->uid }}</code></td>
                        <td data-label="Siswa">
                            @if ($scan->student)
                                <a href="{{ route('students.show', $scan->student) }}">{{ $scan->student->name }}</a>
                                <span class="muted small">{{ $scan->student->schoolClass?->name }}</span>
                            @elseif (auth()->user()->isAdmin())
                                <a class="btn btn--secondary btn--sm" href="{{ route('students.create', ['uid' => $scan->uid]) }}"><x-icon name="plus" :size="16" /> Daftarkan kartu</a>
                            @else
                                <span class="muted">Belum terdaftar</span>
                            @endif
                        </td>
                        <td data-label="Hasil"><span class="badge badge--{{ $scan->result->tone() }}">{{ $scan->result->label() }}</span></td>
                        <td data-label="Perangkat">{{ $scan->device?->name ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $scans->links() }}
    @endif
</section>
@endsection
