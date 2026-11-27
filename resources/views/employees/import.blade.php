@extends('layouts.app')

@section('title', 'Impor Guru & Staf')

@section('content')
<a href="{{ route('employees.index') }}" class="back-link"><x-icon name="arrow-left" :size="16" /> Guru & Staf</a>
<div class="page-head">
    <div>
        <h1>Impor guru & staf</h1>
        <p class="lead">Buat banyak akun sekaligus dari file Excel atau CSV, tanpa mengisi satu per satu.</p>
    </div>
</div>

<div class="form-layout">
    <section class="card import-step" aria-labelledby="langkah-1">
        <span class="import-step__number" aria-hidden="true">1</span>
        <div>
            <h2 id="langkah-1" class="card__title">Siapkan file</h2>
            <p>Unduh template, isi satu baris untuk setiap guru/staf, lalu simpan. Hanya kolom <strong>nama</strong> yang wajib;
                username & kata sandi yang kosong dibuat otomatis.</p>
            <ul class="guide-list">
                <li>Template Excel sudah berisi <strong>petunjuk di atas setiap kolom</strong>, penjelasan saat kursor diarahkan ke judul kolom, dan lembar <em>Petunjuk</em> berisi contoh.</li>
                <li>Isi mulai tepat di bawah judul kolom yang berwarna biru. Baris petunjuk di atasnya boleh dibiarkan.</li>
                <li>Pakai template <strong>Excel</strong> bila ada NIP: kolomnya sudah berformat Teks sehingga 18 digitnya tidak berubah.</li>
            </ul>
            <div class="actions">
                <a href="{{ route('employees.import.template') }}" class="btn btn--secondary"><x-icon name="sheet" :size="18" /> Template Excel (.xlsx)</a>
                <a href="{{ route('employees.import.template', ['format' => 'csv']) }}" class="btn btn--ghost"><x-icon name="download" :size="18" /> Template CSV</a>
            </div>
            <p class="hint">Sudah punya daftar guru di file lain, mis. ekspor Dapodik? Bisa langsung diunggah selama ada kolom <strong>Nama</strong>;
                kolom lain yang dikenali ikut terbaca dan sisanya diabaikan.</p>
        </div>
    </section>

    <form method="POST" action="{{ route('employees.import.preview') }}" enctype="multipart/form-data" class="card import-step" novalidate>
        @csrf
        <span class="import-step__number" aria-hidden="true">2</span>
        <div>
            <h2 class="card__title">Unggah & periksa</h2>
            <div class="field @error('file') has-error @enderror">
                <label for="f-file">File Excel (.xlsx) atau CSV <span class="req" aria-hidden="true">*</span><span class="sr-only">(wajib)</span></label>
                <input id="f-file" name="file" type="file" class="input" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required
                       aria-describedby="f-file-hint @error('file') f-file-error @enderror">
                <p class="hint" id="f-file-hint">Maksimal {{ \App\Services\EmployeeImporter::MAX_ROWS }} baris, 2 MB. Data ditampilkan dulu untuk diperiksa; belum ada yang disimpan.</p>
                @error('file')<p class="error" id="f-file-error"><x-icon name="alert" :size="16" /> {{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn btn--primary"><x-icon name="upload" :size="18" /> Periksa data</button>
        </div>
    </form>

    <section class="card" aria-labelledby="kolom">
        <h2 id="kolom" class="card__title">Kolom yang dikenali</h2>
        <div class="table-wrap">
            <table class="table table--stack table--columns">
                <thead><tr><th scope="col">Kolom</th><th scope="col">Keterangan</th><th scope="col">Contoh isian</th><th scope="col">Nama lain yang juga dikenali</th></tr></thead>
                <tbody>
                @foreach ($columns as $key => [$title, $description, $aliases, , $example])
                    <tr>
                        <th scope="row"><code>{{ $title }}</code>@if ($key === 'name') <span class="badge badge--danger">Wajib</span>@endif</th>
                        <td data-label="Keterangan">{{ $description }}</td>
                        <td data-label="Contoh"><code>{{ $example }}</code></td>
                        <td data-label="Nama lain" class="muted small">{{ implode(', ', $aliases) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="hint table-note">Akun dicocokkan lewat username; bila kosong, lewat NIP, lalu nama yang sama persis. Jadi file yang sama aman diimpor ulang setelah diperbaiki: akun yang sudah ada diperbarui (sel kosong tidak mengubah datanya), bukan dibuat dobel.</p>
    </section>
</div>
@endsection
