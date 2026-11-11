@extends('layouts.app')

@section('title', 'Kelas')

@section('content')
<div class="page-head">
    <div>
        <h1>Kelas</h1>
        <p class="lead">Setiap kelas boleh punya jam masuk/pulang sendiri, mis. SDLB pulang lebih awal.</p>
    </div>
    <div class="actions"><a href="{{ route('classes.create') }}" class="btn btn--primary"><x-icon name="plus" /> Tambah kelas</a></div>
</div>

<section class="card">
    @if ($classes->isEmpty())
        <p class="empty">Belum ada kelas. <a href="{{ route('classes.create') }}">Tambah kelas pertama</a>.</p>
    @else
        <div class="table-wrap">
            <table class="table table--stack">
                <caption class="sr-only">Daftar kelas</caption>
                <thead>
                <tr>
                    <th scope="col">Kelas</th>
                    <th scope="col">Jenjang</th>
                    <th scope="col">Wali kelas</th>
                    <th scope="col" class="num">Siswa aktif</th>
                    <th scope="col">Jadwal</th>
                    <th scope="col"><span class="sr-only">Aksi</span></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($classes as $class)
                    <tr>
                        <th scope="row" data-label="Kelas">{{ $class->name }}</th>
                        <td data-label="Jenjang">{{ $class->level ?? '—' }}</td>
                        <td data-label="Wali kelas">{{ $class->homeroomTeacher?->name ?? '—' }}</td>
                        <td data-label="Siswa aktif" class="num"><a href="{{ route('students.index', ['kelas' => $class->id]) }}">{{ $class->students_count }}</a></td>
                        <td data-label="Jadwal">
                            @if ($class->late_after || $class->check_out_start)
                                Masuk ≤ {{ $class->late_after ?? 'ikut sekolah' }}, pulang ≥ {{ $class->check_out_start ?? 'ikut sekolah' }}
                            @else
                                <span class="muted">Ikut pengaturan sekolah</span>
                            @endif
                        </td>
                        <td data-label="Aksi" class="row-actions">
                            <a href="{{ route('classes.edit', $class) }}" class="btn btn--ghost btn--sm"><x-icon name="edit" :size="16" /> Ubah<span class="sr-only"> {{ $class->name }}</span></a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
