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
            ['route' => 'devices.index', 'match' => 'devices.*', 'icon' => 'reader', 'label' => 'Perangkat Pembaca', 'admin' => true],
            ['route' => 'users.index', 'match' => 'users.*', 'icon' => 'user-cog', 'label' => 'Pengguna', 'admin' => true],
            ['route' => 'settings.edit', 'match' => 'settings.*', 'icon' => 'sliders', 'label' => 'Pengaturan', 'admin' => true],
        ],
    ];
@endphp

<div class="shell">
    <aside class="sidebar" id="sidebar" aria-label="Menu utama">
        <div class="brand">
            <span class="brand__mark" aria-hidden="true"><x-icon name="card" :size="22" /></span>
            <span class="brand__text">
                <strong>{{ $schoolSettings->schoolName() }}</strong>
                <span>Presensi Kartu RFID</span>
            </span>
        </div>

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

        <a href="{{ route('kiosk.index') }}" class="btn btn--primary btn--block sidebar__kiosk">
            <x-icon name="monitor" /> Buka Layar Presensi
        </a>
    </aside>

    <div class="main-col">
        <header class="topbar">
            <button type="button" class="btn btn--ghost btn--icon topbar__menu" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false">
                <x-icon name="menu" /><span class="sr-only">Buka menu</span>
            </button>

            <div class="a11y-tools" role="group" aria-label="Pengaturan tampilan">
                <span class="a11y-tools__label" aria-hidden="true"><x-icon name="text-size" :size="18" /></span>
                <button type="button" class="btn btn--ghost btn--sm" data-text-size="down" title="Perkecil teks">A−<span class="sr-only"> Perkecil teks</span></button>
                <button type="button" class="btn btn--ghost btn--sm" data-text-size="reset" title="Ukuran teks normal">A<span class="sr-only"> Ukuran teks normal</span></button>
                <button type="button" class="btn btn--ghost btn--sm" data-text-size="up" title="Perbesar teks">A+<span class="sr-only"> Perbesar teks</span></button>
                <button type="button" class="btn btn--ghost btn--sm" data-contrast-toggle aria-pressed="false">
                    <x-icon name="contrast" :size="18" /> <span>Kontras tinggi</span>
                </button>
            </div>

            <div class="topbar__user">
                <span class="topbar__name">
                    <strong>{{ $user->name }}</strong>
                    <span>{{ $user->role->label() }}</span>
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn--secondary btn--sm"><x-icon name="logout" :size="18" /> Keluar</button>
                </form>
            </div>
        </header>

        <main id="main" class="content" tabindex="-1">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>
<div class="sidebar-backdrop" data-sidebar-backdrop hidden></div>

<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
