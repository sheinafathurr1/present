@extends('layouts.app')

@php $editing = $user->exists; @endphp
@section('title', $editing ? 'Ubah Pengguna' : 'Tambah Pengguna')

@section('content')
<div class="page-head">
    <div>
        <a href="{{ route('users.index') }}" class="back-link"><x-icon name="arrow-left" :size="18" /> Pengguna</a>
        <h1>{{ $editing ? 'Ubah akun '.$user->name : 'Tambah pengguna' }}</h1>
    </div>
</div>

<form method="POST" action="{{ $editing ? route('users.update', $user) : route('users.store') }}" class="form-layout" novalidate>
    @csrf
    @if ($editing) @method('PUT') @endif
    <section class="card">
        <div class="form-grid">
            <x-input name="name" label="Nama" :value="$user->name" required />
            <x-input name="email" label="Email (untuk login)" type="email" :value="$user->email" required autocomplete="off" />
        </div>

        <fieldset class="choice-group">
            <legend>Peran</legend>
            @php $role = old('role', $user->role?->value); @endphp
            @foreach ($roles as $option)
                <label class="choice">
                    <input type="radio" name="role" value="{{ $option->value }}" @checked($role === $option->value)>
                    <span><strong>{{ $option->label() }}</strong><span class="muted">{{ $option->description() }}</span></span>
                </label>
            @endforeach
        </fieldset>

        <div class="form-grid">
            <x-input name="password" label="{{ $editing ? 'Kata sandi baru' : 'Kata sandi' }}" type="password" :required="! $editing"
                     autocomplete="new-password" hint="{{ $editing ? 'Kosongkan bila tidak diganti. ' : '' }}Minimal 8 karakter." />
            <x-input name="password_confirmation" label="Ulangi kata sandi" type="password" :required="! $editing" autocomplete="new-password" />
        </div>
    </section>
    <div class="form-actions">
        <button type="submit" class="btn btn--primary btn--lg"><x-icon name="check" /> Simpan</button>
        <a href="{{ route('users.index') }}" class="btn btn--ghost btn--lg">Batal</a>
    </div>
</form>

@if ($editing && ! $user->is(auth()->user()))
    <form method="POST" action="{{ route('users.destroy', $user) }}" class="danger-zone card" data-confirm="Hapus akun {{ $user->name }}?">
        @csrf
        @method('DELETE')
        <div>
            <h2>Hapus akun</h2>
            <p class="muted">Data presensi yang pernah dicatat akun ini tetap tersimpan.</p>
        </div>
        <button type="submit" class="btn btn--danger"><x-icon name="trash" /> Hapus akun</button>
    </form>
@endif
@endsection
