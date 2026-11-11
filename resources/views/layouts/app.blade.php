<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head')
    <title>@yield('title', 'Dashboard') · {{ $schoolSettings->schoolName() }}</title>
</head>
<body>
<a class="skip-link" href="#main">Lewati ke konten utama</a>

@php
    $user = auth()->user();
    $isAdmin = $user->isAdmin();
    $nav = [
        'Harian' => [
            ['route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'home', 'label' => 'Dashboard'],
            ['route' => 'attendances.index', 'match' => 'attendances.*', 'icon' => 'clipboard', 'label' => 'Presensi Harian'],
            ['route' => 'reports.monthly', 'match' => 'reports.*', 'icon' => 'calendar', 'label' => 'Rekap Bulanan'],
            ['route' => 'scans.index', 'match' => 'scans.*', 'icon' => 'list', 'label' => 'Log Tap Kartu'],
        ],
        'Data Sekolah' => [
            ['route' => 'students.index', 'match' => 'students.*', 'icon' => 'users', 'label' => 'Siswa'],
            ['route' => 'classes.index', 'match' => 'classes.*', 'icon' => 'school', 'label' => 'Kelas', 'admin' => true],
            ['route' => 'holidays.index', 'match' => 'holidays.*', 'icon' => 'calendar-off', 'label' => 'Hari Libur', 'admin' => true],
        ],
        'Sistem' => [
            ['route' => 'devices.index', 'match' => 'devices.*', 'icon' => 'wifi', 'label' => 'Pembaca Wi-Fi', 'admin' => true],
            ['route' => 'users.index', 'match' => 'users.*', 'icon' => 'user-cog', 'label' => 'Pengguna', 'admin' => true],
            ['route' => 'settings.edit', 'match' => 'settings.*', 'icon' => 'sliders', 'label' => 'Pengaturan', 'admin' => true],
        ],
    ];
@endphp

<div class="shell">
    <aside class="sidebar" id="sidebar" aria-label="Menu utama">
        <a class="brand" href="{{ route('dashboard') }}">
            @include('partials.brand-mark')
            <span class="brand__text">
                <strong>{{ $schoolSettings->schoolName() }}</strong>
                <span>Presensi kartu RFID</span>
            </span>
        </a>

        <nav class="nav">
            @foreach ($nav as $group => $items)
                @php $items = array_filter($items, fn ($item) => $isAdmin || empty($item['admin'])); @endphp
                @if ($items)
                    <p class="nav__group" id="nav-{{ \Illuminate\Support\Str::slug($group) }}">{{ $group }}</p>
                    <ul aria-labelledby="nav-{{ \Illuminate\Support\Str::slug($group) }}">
                        @foreach ($items as $item)
                            @php $active = request()->routeIs($item['match']); @endphp
                            <li>
                                <a href="{{ route($item['route']) }}" class="nav__link @if ($active) is-active @endif" @if ($active) aria-current="page" @endif>
                                    <x-icon :name="$item['icon']" /> {{ $item['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endforeach
        </nav>

        <div class="sidebar__user">
            <span class="sidebar__avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
            <span class="sidebar__who">
                <strong>{{ $user->name }}</strong>
                <span>{{ $user->role->label() }}</span>
            </span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn--ghost btn--icon" title="Keluar">
                    <x-icon name="logout" :size="18" /><span class="sr-only">Keluar</span>
                </button>
            </form>
        </div>
    </aside>

    <div class="main-col">
        <header class="topbar">
            <button type="button" class="btn btn--ghost btn--icon topbar__menu" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false">
                <x-icon name="menu" /><span class="sr-only">Buka menu</span>
            </button>

            <div class="reader-tools" role="group" aria-label="Pembaca kartu">
                <span class="reader-status" data-reader-status>
                    <span class="reader-status__dot" aria-hidden="true"></span>
                    <span data-reader-label>Siap menerima kartu</span>
                </span>
                <button type="button" class="btn btn--ghost btn--sm" data-sound-toggle aria-pressed="true">
                    <span data-when="on"><x-icon name="volume" :size="18" /> <span class="topbar__label">Suara aktif</span></span>
                    <span data-when="off"><x-icon name="volume-off" :size="18" /> <span class="topbar__label">Suara mati</span></span>
                </button>
            </div>

            <div class="a11y-tools" role="group" aria-label="Pengaturan tampilan">
                <button type="button" class="btn btn--ghost btn--sm" data-text-size="down" title="Perkecil teks">A−<span class="sr-only"> Perkecil teks</span></button>
                <button type="button" class="btn btn--ghost btn--sm" data-text-size="reset" title="Ukuran teks normal">A<span class="sr-only"> Ukuran teks normal</span></button>
                <button type="button" class="btn btn--ghost btn--sm" data-text-size="up" title="Perbesar teks">A+<span class="sr-only"> Perbesar teks</span></button>
                <button type="button" class="btn btn--ghost btn--sm" data-contrast-toggle aria-pressed="false">
                    <x-icon name="contrast" :size="18" /> <span class="topbar__label">Kontras tinggi</span>
                </button>
            </div>
        </header>

        <main id="main" class="content" tabindex="-1">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>
<div class="sidebar-backdrop" data-sidebar-backdrop hidden></div>

{{-- Hasil tap kartu: muncul di atas halaman apa pun yang sedang dibuka guru. --}}
<div class="tap-overlay" data-tap-overlay hidden>
    <section class="tap-dialog" data-tap-card aria-labelledby="tap-headline" aria-describedby="tap-message">
        <header class="tap-dialog__status">
            <span class="tap-dialog__icon" aria-hidden="true">
                <span data-icon="success"><x-icon name="check" :size="28" /></span>
                <span data-icon="warning"><x-icon name="clock" :size="28" /></span>
                <span data-icon="checkout"><x-icon name="door" :size="28" /></span>
                <span data-icon="info"><x-icon name="info" :size="28" /></span>
                <span data-icon="error"><x-icon name="x" :size="28" /></span>
            </span>
            <span class="tap-dialog__headline" id="tap-headline" data-tap-headline></span>
            <span class="tap-dialog__time" data-tap-time></span>
        </header>
        <div class="tap-dialog__body">
            <div class="tap-dialog__photo" data-tap-photo></div>
            <div class="tap-dialog__who">
                <p class="tap-dialog__name" data-tap-name></p>
                <p class="tap-dialog__meta" data-tap-class></p>
            </div>
        </div>
        <footer class="tap-dialog__foot">
            <p class="tap-dialog__message" id="tap-message" data-tap-message></p>
            <button type="button" class="btn btn--secondary btn--sm" data-tap-close>Tutup <kbd>Esc</kbd></button>
        </footer>
    </section>
</div>
<div class="sr-only" aria-live="assertive" aria-atomic="true" data-tap-announcer></div>

<script>
    window.Presensi = {
        tapUrl: @json(route('tap.store')),
        heartbeatUrl: @json(route('heartbeat')),
        loginUrl: @json(route('login')),
    };
</script>
<script src="{{ asset('js/card-reader.js') }}?v={{ filemtime(public_path('js/card-reader.js')) }}" defer></script>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
