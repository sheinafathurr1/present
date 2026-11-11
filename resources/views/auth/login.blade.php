@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
<div class="login">
    <div class="login__brand">
        <span class="brand__mark brand__mark--lg" aria-hidden="true"><x-icon name="card" :size="36" /></span>
        <h1>{{ $schoolSettings->schoolName() }}</h1>
        <p>Sistem Presensi Kartu RFID</p>
    </div>

    <form method="POST" action="{{ route('login') }}" class="card login__card" novalidate>
        @csrf
        <h2>Masuk ke akun</h2>

        @include('partials.flash')

        <x-input name="email" label="Email" type="email" required autofocus autocomplete="username" />
        <x-input name="password" label="Kata sandi" type="password" required autocomplete="current-password" />

        <label class="check">
            <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
            <span>Ingat saya di komputer ini</span>
        </label>

        <button type="submit" class="btn btn--primary btn--block btn--lg">Masuk</button>

        <p class="hint login__hint">Lupa kata sandi? Hubungi admin sekolah.</p>
    </form>
</div>
@endsection
