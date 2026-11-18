@extends('layouts.app')

@section('title', 'Akun')

@section('content')
<div class="profile-head">
    <span class="avatar avatar--lg" aria-hidden="true">{{ $user->initials() }}</span>
    <div class="profile-head__info">
        <h1>{{ $user->name }}</h1>
        <p>{{ $user->subtitle() }} · {{ $user->role->label() }}</p>
    </div>
</div>

<div class="grid-2">
    <section class="card" aria-labelledby="data-saya">
        <h2 id="data-saya" class="card__title">Data saya</h2>
        <dl class="details details--stacked">
            <div><dt>Username</dt><dd><code>{{ $user->username }}</code></dd></div>
            @if ($user->nip)<div><dt>NIP</dt><dd>{{ $user->nip }}</dd></div>@endif
            <div><dt>Jenis</dt><dd>{{ $user->employee_type->label() }}</dd></div>
            @if ($user->attends())
                <div><dt>Jam kerja</dt><dd>{{ $schoolSettings->workStartFor($user) }}–{{ $schoolSettings->workEndFor($user) }}</dd></div>
            @endif
        </dl>
        <p class="hint">Data yang salah? Minta admin sekolah memperbaikinya.</p>
    </section>

    <form method="POST" action="{{ route('account.password') }}" class="card" novalidate>
        @csrf
        @method('PUT')
        <h2 class="card__title"><x-icon name="key" /> Ganti kata sandi</h2>
        <x-input name="current_password" label="Kata sandi saat ini" type="password" required autocomplete="current-password" />
        <x-input name="password" label="Kata sandi baru" type="password" required autocomplete="new-password" hint="Minimal 8 karakter." />
        <x-input name="password_confirmation" label="Ulangi kata sandi baru" type="password" required autocomplete="new-password" />
        <button type="submit" class="btn btn--primary">Simpan kata sandi</button>
    </form>
</div>

<form method="POST" action="{{ route('logout') }}" class="logout-form">
    @csrf
    <button type="submit" class="btn btn--secondary btn--block"><x-icon name="logout" :size="18" /> Keluar</button>
</form>
@endsection
