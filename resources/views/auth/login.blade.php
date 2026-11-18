@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
<div class="login">
    <div class="login__brand">
        @include('partials.brand-mark', ['size' => 48])
        <div>
            <h1>{{ $schoolSettings->schoolName() }}</h1>
            <p>Presensi guru & staf</p>
        </div>
    </div>

    <form method="POST" action="{{ route('login') }}" class="card login__card" novalidate>
        @csrf
        <h2>Masuk</h2>
        <p>Gunakan username dan kata sandi dari admin sekolah.</p>

        @include('partials.flash')

        <x-input name="username" label="Username" required autofocus autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" />
        <x-input name="password" label="Kata sandi" type="password" required autocomplete="current-password" />

        <label class="check">
            <input type="checkbox" name="remember" value="1" @checked(old('remember', true))>
            <span>Ingat saya di perangkat ini</span>
        </label>

        <button type="submit" class="btn btn--primary btn--block btn--lg">Masuk</button>
    </form>

    <p class="hint login__hint">Lupa kata sandi? Minta admin sekolah mengaturnya ulang.</p>
</div>
@endsection
