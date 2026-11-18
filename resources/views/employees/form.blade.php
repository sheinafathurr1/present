@extends('layouts.app')

@php $editing = $employee->exists; @endphp
@section('title', $editing ? 'Ubah '.$employee->name : 'Tambah Guru/Staf')

@section('content')
<a href="{{ $editing ? route('employees.show', $employee) : route('employees.index') }}" class="back-link"><x-icon name="arrow-left" :size="16" /> {{ $editing ? $employee->name : 'Guru & Staf' }}</a>
<div class="page-head">
    <div>
        <h1>{{ $editing ? 'Ubah data' : 'Tambah guru/staf' }}</h1>
        <p class="lead">Setiap orang masuk dengan username & kata sandinya sendiri, lalu presensi dari HP masing-masing.</p>
    </div>
</div>

<form method="POST" action="{{ $editing ? route('employees.update', $employee) : route('employees.store') }}" class="form-layout" novalidate>
    @csrf
    @if ($editing) @method('PUT') @endif

    <section class="card">
        <h2 class="card__title">Identitas</h2>
        <x-input name="name" label="Nama lengkap" required :value="$employee->name" placeholder="mis. Siti Aminah, S.Pd." />
        <div class="form-grid">
            <x-select name="employee_type" label="Jenis" required :value="$employee->employee_type?->value ?? 'guru'"
                      :options="collect(\App\Enums\EmployeeType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()" />
            <x-input name="position" label="Jabatan" :value="$employee->position" list="jabatan" placeholder="mis. Guru Kelas 3" />
            <x-input name="nip" label="NIP" :value="$employee->nip" inputmode="numeric" hint="Opsional." />
            <x-input name="phone" label="Nomor HP" type="tel" :value="$employee->phone" hint="Opsional." />
        </div>
        <datalist id="jabatan">
            @foreach (['Kepala Sekolah', 'Wakil Kepala Sekolah', 'Bendahara', 'Guru Kelas 1', 'Guru Kelas 2', 'Guru Kelas 3', 'Guru Kelas 4', 'Guru Kelas 5', 'Guru Kelas 6', 'Guru PJOK', 'Guru Pendidikan Agama', 'Guru Bahasa Inggris', 'Operator Sekolah', 'Staf Tata Usaha', 'Pustakawan', 'Penjaga Sekolah'] as $option)
                <option value="{{ $option }}"></option>
            @endforeach
        </datalist>
    </section>

    <section class="card">
        <h2 class="card__title">Akun</h2>
        <div class="form-grid">
            <x-input name="username" label="Username" required :value="$employee->username" autocapitalize="none" autocorrect="off" spellcheck="false" autocomplete="off"
                     hint="Huruf kecil tanpa spasi, mis. siti.aminah" />
            <x-input name="password" :label="$editing ? 'Kata sandi baru' : 'Kata sandi'" type="text" :required="! $editing" autocomplete="new-password" autocapitalize="none" spellcheck="false"
                     :hint="$editing ? 'Kosongkan bila tidak diganti. Minimal 8 karakter.' : 'Minimal 8 karakter. Sampaikan secara pribadi; pemilik akun bisa menggantinya sendiri.'" />
        </div>

        <fieldset class="choice-group @error('role') has-error @enderror">
            <legend>Peran <span class="req" aria-hidden="true">*</span><span class="sr-only">(wajib)</span></legend>
            @foreach (\App\Enums\UserRole::cases() as $role)
                <label class="choice">
                    <input type="radio" name="role" value="{{ $role->value }}" @checked(old('role', $employee->role?->value ?? 'pegawai') === $role->value)>
                    <span><strong>{{ $role->label() }}</strong><span class="muted">{{ $role->description() }}</span></span>
                </label>
            @endforeach
            @error('role')<p class="error"><x-icon name="alert" :size="16" /> {{ $message }}</p>@enderror
        </fieldset>

        @if ($editing)
            <label class="check check--lg">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $employee->is_active))>
                <span><strong>Akun aktif</strong><br><span class="muted">Matikan bila sudah tidak bertugas di sekolah. Riwayatnya tetap tersimpan.</span></span>
            </label>
        @endif
    </section>

    <section class="card">
        <h2 class="card__title">Presensi</h2>
        <label class="check check--lg">
            <input type="hidden" name="must_attend" value="0">
            <input type="checkbox" name="must_attend" value="1" @checked(old('must_attend', $employee->exists ? $employee->must_attend : true))>
            <span><strong>Wajib presensi dari HP</strong><br><span class="muted">Matikan untuk akun operator yang tidak perlu dihitung kehadirannya.</span></span>
        </label>
        <div class="form-grid">
            <x-input name="work_start" label="Jam masuk khusus" type="time" :value="$employee->work_start" />
            <x-input name="work_end" label="Jam pulang khusus" type="time" :value="$employee->work_end" />
        </div>
        <p class="hint">Kosongkan untuk memakai jadwal umum: guru {{ $settings->get('guru_start') }}–{{ $settings->get('guru_end') }},
            staf tendik {{ $settings->get('tendik_start') }}–{{ $settings->get('tendik_end') }}.</p>
    </section>

    <div class="form-actions">
        <button type="submit" class="btn btn--primary btn--lg">{{ $editing ? 'Simpan perubahan' : 'Buat akun' }}</button>
        <a href="{{ $editing ? route('employees.show', $employee) : route('employees.index') }}" class="btn btn--ghost btn--lg">Batal</a>
    </div>
</form>

@if ($editing && ! $employee->is(auth()->user()))
    <section class="card danger-zone">
        <div>
            <h2 class="card__title">Hapus akun</h2>
            <p class="muted">Hanya untuk akun yang salah dibuat dan belum punya riwayat. Bila sudah pernah presensi, nonaktifkan saja.</p>
        </div>
        <form method="POST" action="{{ route('employees.destroy', $employee) }}" data-confirm="Hapus akun {{ $employee->name }}?">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn--danger"><x-icon name="trash" :size="18" /> Hapus</button>
        </form>
    </section>
@endif
@endsection
