@extends('layouts.app')

@section('title', $student->name)

@section('content')
<div class="page-head">
    <div class="person person--lg">
        <x-avatar :student="$student" :size="72" />
        <div>
            <a href="{{ route('students.index') }}" class="back-link"><x-icon name="arrow-left" :size="18" /> Data siswa</a>
            <h1>{{ $student->name }}</h1>
            <p class="lead">{{ $student->schoolClass?->name ?? 'Belum ada kelas' }} · NIS {{ $student->nis }}
                @unless ($student->is_active)<span class="badge badge--muted">Nonaktif</span>@endunless
            </p>
        </div>
    </div>
    @if (auth()->user()->isAdmin())
        <div class="actions">
            <a href="{{ route('students.edit', $student) }}" class="btn btn--primary"><x-icon name="edit" /> Ubah data</a>
        </div>
    @endif
</div>

<div class="grid-2">
    <section class="card" aria-labelledby="profil">
        <h2 id="profil">Profil</h2>
        <dl class="details">
            <div><dt>Nama panggilan</dt><dd>{{ $student->nickname ?: $student->callName() }}</dd></div>
            <div><dt>Jenis kelamin</dt><dd>{{ ['L' => 'Laki-laki', 'P' => 'Perempuan'][$student->gender] ?? '—' }}</dd></div>
            <div><dt>NISN</dt><dd>{{ $student->nisn ?: '—' }}</dd></div>
            <div><dt>Wali kelas</dt><dd>{{ $student->schoolClass?->homeroomTeacher?->name ?? '—' }}</dd></div>
            <div><dt>Orang tua/wali</dt><dd>{{ $student->guardian_name ?: '—' }}
                @if ($student->guardian_phone)<br><a href="tel:{{ preg_replace('/[^0-9+]/', '', $student->guardian_phone) }}">{{ $student->guardian_phone }}</a>@endif</dd></div>
        </dl>
    </section>

    <section class="card" aria-labelledby="aksesibilitas">
        <h2 id="aksesibilitas">Kartu & kebutuhan khusus</h2>
        <dl class="details">
            <div><dt>Kartu RFID</dt><dd>
                @if ($student->rfid_uid)<code>{{ $student->rfid_uid }}</code>@else<span class="badge badge--muted">Belum terdaftar</span>@endif
            </dd></div>
            <div><dt>Kekhususan</dt><dd>{{ $student->disability_type?->label() ?? '—' }}</dd></div>
            <div><dt>Saat tap kartu</dt><dd>{{ $student->feedback_mode->label() }}</dd></div>
            <div><dt>Catatan</dt><dd>{!! $student->notes ? nl2br(e($student->notes)) : '—' !!}</dd></div>
        </dl>
    </section>
</div>

<section class="card" aria-labelledby="riwayat">
    <div class="card__head">
        <h2 id="riwayat">Riwayat presensi</h2>
        <a href="{{ route('reports.monthly', ['kelas' => $student->school_class_id]) }}">Rekap bulanan kelas →</a>
    </div>
    @if ($attendances->isEmpty())
        <p class="empty">Belum ada riwayat presensi.</p>
    @else
        <div class="table-wrap">
            <table class="table table--stack">
                <caption class="sr-only">Riwayat presensi {{ $student->name }}</caption>
                <thead>
                <tr>
                    <th scope="col">Tanggal</th>
                    <th scope="col">Status</th>
                    <th scope="col">Masuk</th>
                    <th scope="col">Pulang</th>
                    <th scope="col">Keterangan</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($attendances as $attendance)
                    <tr>
                        <th scope="row" data-label="Tanggal">{{ \Carbon\Carbon::parse($attendance->date)->translatedFormat('D, d M Y') }}</th>
                        <td data-label="Status"><x-status-badge :status="$attendance->status" /></td>
                        <td data-label="Masuk">{{ $attendance->check_in_at?->format('H.i') ?? '—' }}
                            @if ($attendance->checkInDevice)<span class="muted small">{{ $attendance->checkInDevice->name }}</span>@endif</td>
                        <td data-label="Pulang">{{ $attendance->check_out_at?->format('H.i') ?? '—' }}
                            @if ($attendance->checkOutDevice)<span class="muted small">{{ $attendance->checkOutDevice->name }}</span>@endif</td>
                        <td data-label="Keterangan">
                            {{ $attendance->note ?: '' }}
                            <span class="muted small">{{ $attendance->sourceLabel() }}{{ $attendance->recorder ? ' · '.$attendance->recorder->name : '' }}</span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $attendances->links() }}
    @endif
</section>
@endsection
