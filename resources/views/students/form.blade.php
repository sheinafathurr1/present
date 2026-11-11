@extends('layouts.app')

@php $editing = $student->exists; @endphp
@section('title', $editing ? 'Ubah '.$student->name : 'Tambah Siswa')

@section('content')
<div class="page-head">
    <div>
        <a href="{{ $editing ? route('students.show', $student) : route('students.index') }}" class="back-link"><x-icon name="arrow-left" :size="18" /> Kembali</a>
        <h1>{{ $editing ? 'Ubah data siswa' : 'Tambah siswa' }}</h1>
    </div>
</div>

<form method="POST" action="{{ $editing ? route('students.update', $student) : route('students.store') }}" enctype="multipart/form-data" class="form-layout" novalidate>
    @csrf
    @if ($editing) @method('PUT') @endif

    <section class="card" aria-labelledby="sec-kartu">
        <h2 id="sec-kartu"><x-icon name="card" /> Kartu RFID</h2>
        <div class="rfid-capture" data-rfid-capture>
            <x-input name="rfid_uid" label="Nomor kartu (UID)" :value="$student->rfid_uid" autocomplete="off" inputmode="text"
                     hint="Tempelkan kartu siswa ke pembaca USB di PC ini — nomor terisi otomatis (selama halaman ini terbuka, kartu tidak dicatat sebagai presensi). Kosongkan bila siswa belum punya kartu."
                     data-rfid-input class="input input--mono input--lg" />
            <p class="rfid-capture__status" data-rfid-status aria-live="polite"></p>
        </div>

        @if ($unknownScans->isNotEmpty())
            <div class="callout callout--info">
                <x-icon name="info" />
                <div>
                    <p><strong>Kartu yang baru ditempel tetapi belum terdaftar:</strong></p>
                    <ul class="chip-list">
                        @foreach ($unknownScans as $scan)
                            <li>
                                <button type="button" class="chip" data-fill-uid="{{ $scan->uid }}">
                                    <code>{{ $scan->uid }}</code>
                                    <span class="muted">{{ $scan->scanned_at->diffForHumans() }}{{ $scan->sourceName() ? ' · '.$scan->sourceName() : '' }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                    <p class="hint">Pilih salah satu untuk memakainya, misalnya kartu yang tadi ditempel di PC guru lain atau di pembaca Wi-Fi.</p>
                </div>
            </div>
        @endif
    </section>

    <section class="card" aria-labelledby="sec-identitas">
        <h2 id="sec-identitas"><x-icon name="user" /> Identitas</h2>
        <div class="form-grid">
            <x-input name="name" label="Nama lengkap" :value="$student->name" required autocomplete="off" />
            <x-input name="nickname" label="Nama panggilan" :value="$student->nickname" hint="Dipakai untuk menyapa siswa (tulisan & suara) saat tap kartu." />
            <x-input name="nis" label="NIS" :value="$student->nis" required />
            <x-input name="nisn" label="NISN" :value="$student->nisn" />
            <x-select name="gender" label="Jenis kelamin" :value="$student->gender" placeholder="— Pilih —" :options="['L' => 'Laki-laki', 'P' => 'Perempuan']" />
            <x-select name="school_class_id" label="Kelas" :value="$student->school_class_id" placeholder="— Belum ada kelas —"
                      :options="$classes->mapWithKeys(fn ($c) => [$c->id => $c->name.($c->level ? ' ('.$c->level.')' : '')])->all()" />
        </div>
    </section>

    <section class="card" aria-labelledby="sec-kebutuhan">
        <h2 id="sec-kebutuhan"><x-icon name="eye" /> Kebutuhan khusus & cara memberi tahu saat tap</h2>
        <div class="form-grid">
            <x-select name="disability_type" label="Jenis kekhususan" :value="$student->disability_type" placeholder="— Tidak diisi —"
                      :options="collect($disabilityTypes)->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()" data-disability-select />
            <x-select name="feedback_mode" label="Saat kartu ditempel" :value="$student->feedback_mode ?? 'both'" required
                      :options="collect($feedbackModes)->mapWithKeys(fn ($m) => [$m->value => $m->label()])->all()"
                      hint="Tunanetra: utamakan suara. Tunarungu: utamakan tampilan." data-feedback-select />
        </div>
        <x-textarea name="notes" label="Catatan pendamping" :value="$student->notes" hint="Mis. perlu dibantu menempelkan kartu, alergi, atau kontak darurat lain." />
    </section>

    <section class="card" aria-labelledby="sec-wali">
        <h2 id="sec-wali"><x-icon name="users" /> Orang tua / wali</h2>
        <div class="form-grid">
            <x-input name="guardian_name" label="Nama orang tua/wali" :value="$student->guardian_name" />
            <x-input name="guardian_phone" label="No. HP/WhatsApp" type="tel" :value="$student->guardian_phone" placeholder="08xxxxxxxxxx" />
        </div>
    </section>

    <section class="card" aria-labelledby="sec-foto">
        <h2 id="sec-foto">Foto & status</h2>
        <div class="photo-field">
            @if ($student->photoUrl())
                <img src="{{ $student->photoUrl() }}" alt="Foto {{ $student->name }} saat ini" class="avatar avatar--xl">
            @endif
            <div>
                <x-input name="photo" label="Foto siswa" type="file" accept="image/*" hint="Ditampilkan besar saat tap kartu agar siswa mengenali dirinya. Maks. 2 MB." />
                @if ($student->photoUrl())
                    <label class="check"><input type="checkbox" name="remove_photo" value="1"> <span>Hapus foto</span></label>
                @endif
            </div>
        </div>
        <label class="check check--lg">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $student->is_active ?? true))>
            <span><strong>Siswa aktif</strong><br><span class="muted">Siswa nonaktif (lulus/pindah) tidak bisa presensi dan tidak dihitung.</span></span>
        </label>
    </section>

    <div class="form-actions">
        <button type="submit" class="btn btn--primary btn--lg"><x-icon name="check" /> Simpan</button>
        <a href="{{ $editing ? route('students.show', $student) : route('students.index') }}" class="btn btn--ghost btn--lg">Batal</a>
    </div>
</form>

@if ($editing)
    <form method="POST" action="{{ route('students.destroy', $student) }}" class="danger-zone card"
          data-confirm="Hapus {{ $student->name }} beserta seluruh riwayat presensinya? Tindakan ini tidak bisa dibatalkan. Untuk siswa lulus/pindah, cukup nonaktifkan.">
        @csrf
        @method('DELETE')
        <div>
            <h2>Hapus siswa</h2>
            <p class="muted">Riwayat presensi ikut terhapus. Untuk siswa yang lulus atau pindah, sebaiknya cukup dinonaktifkan.</p>
        </div>
        <button type="submit" class="btn btn--danger"><x-icon name="trash" /> Hapus siswa</button>
    </form>
@endif
@endsection
