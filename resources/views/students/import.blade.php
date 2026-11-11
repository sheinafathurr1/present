@extends('layouts.app')

@section('title', 'Impor Siswa')

@section('content')
<div class="page-head">
    <div>
        <a href="{{ route('students.index') }}" class="back-link"><x-icon name="arrow-left" :size="18" /> Data siswa</a>
        <h1>Impor data siswa</h1>
        <p class="lead">Masukkan banyak siswa sekaligus dari file Excel.</p>
    </div>
</div>

<div class="grid-2">
    <section class="card" aria-labelledby="langkah">
        <h2 id="langkah">Langkah-langkah</h2>
        <ol class="steps">
            <li><a href="{{ route('students.import.template') }}" class="btn btn--secondary btn--sm"><x-icon name="download" :size="16" /> Unduh template</a> lalu buka di Excel.</li>
            <li>Isi satu baris untuk satu siswa. Kolom <strong>nis</strong> dan <strong>nama</strong> wajib diisi.</li>
            <li>Simpan dengan <em>File → Save As → CSV UTF-8</em>.</li>
            <li>Unggah file tersebut di formulir ini.</li>
        </ol>
        <p class="hint">Siswa yang NIS-nya sudah ada akan diperbarui. Kelas yang belum ada akan dibuat otomatis.</p>
    </section>

    <section class="card" aria-labelledby="kolom">
        <h2 id="kolom">Kolom yang dikenali</h2>
        <dl class="details details--compact">
            <div><dt><code>nis</code>, <code>nama</code></dt><dd>Wajib</dd></div>
            <div><dt><code>nama_panggilan</code></dt><dd>Untuk sapaan di kiosk</dd></div>
            <div><dt><code>jenis_kelamin</code></dt><dd>L atau P</dd></div>
            <div><dt><code>kelas</code>, <code>jenjang</code></dt><dd>Mis. "Kelas 1A", "SDLB"</dd></div>
            <div><dt><code>jenis_disabilitas</code></dt><dd>Kode A, B, C, C1, D, E, G, Q</dd></div>
            <div><dt><code>uid_kartu</code></dt><dd>Nomor kartu RFID (boleh kosong)</dd></div>
            <div><dt><code>nama_wali</code>, <code>telepon_wali</code></dt><dd>Kontak orang tua</dd></div>
        </dl>
    </section>
</div>

<form method="POST" action="{{ route('students.import.store') }}" enctype="multipart/form-data" class="card">
    @csrf
    <x-input name="file" label="File CSV" type="file" accept=".csv,text/csv" required hint="Maksimal 5 MB. Pemisah koma (,) atau titik koma (;) sama-sama bisa." />
    <button type="submit" class="btn btn--primary btn--lg"><x-icon name="upload" /> Impor sekarang</button>
</form>
@endsection
