<!DOCTYPE html>
<html lang="id">
<head>
    @include('partials.head')
    <title>@yield('title', 'Presensi') · {{ $schoolSettings->schoolName() }}</title>
</head>
<body>
<a class="skip-link" href="#main">Lewati ke konten utama</a>

@php
    $user = auth()->user();
    $monitor = $user->canMonitor();
    $attends = $user->attends();

    // Menu lengkap (sidebar di layar lebar, laci "Menu" di HP).
    $nav = [];
    if ($monitor) {
        $nav['Pantau'] = [
            ['route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'home', 'label' => 'Kehadiran Hari Ini'],
            ['route' => 'reports.monthly', 'match' => 'reports.*', 'icon' => 'chart', 'label' => 'Rekap Bulanan'],
            ['route' => 'leaves.monitor', 'match' => 'leaves.monitor', 'icon' => 'file', 'label' => 'Pengajuan Izin'],
            ['route' => 'employees.index', 'match' => ['employees.*', 'corrections.*'], 'icon' => 'users', 'label' => 'Guru & Staf'],
            ['route' => 'exports.create', 'match' => 'exports.*', 'icon' => 'download', 'label' => 'Ekspor Data'],
        ];
    }
    $mine = [];
    if ($attends) {
        $mine[] = ['route' => 'presence.show', 'match' => 'presence.*', 'icon' => 'pin', 'label' => 'Presensi'];
        $mine[] = ['route' => 'history.index', 'match' => 'history.*', 'icon' => 'calendar', 'label' => 'Riwayat Saya'];
        $mine[] = ['route' => 'leaves.index', 'match' => ['leaves.index', 'leaves.create'], 'icon' => 'file', 'label' => $monitor ? 'Izin Saya' : 'Izin & Cuti'];
    }
    $mine[] = ['route' => 'account.edit', 'match' => 'account.*', 'icon' => 'user', 'label' => 'Akun'];
    $nav[$monitor ? 'Saya' : 'Menu'] = $mine;
    if ($user->isAdmin()) {
        $nav['Kelola'] = [
            ['route' => 'holidays.index', 'match' => 'holidays.*', 'icon' => 'calendar-off', 'label' => 'Hari Libur'],
            ['route' => 'settings.edit', 'match' => 'settings.*', 'icon' => 'sliders', 'label' => 'Pengaturan'],
        ];
    }

    // Navigasi bawah di HP: paling banyak 5 tombol.
    if (! $monitor) {
        $tabs = [
            ['route' => 'presence.show', 'match' => 'presence.*', 'icon' => 'pin', 'label' => 'Presensi'],
            ['route' => 'history.index', 'match' => 'history.*', 'icon' => 'calendar', 'label' => 'Riwayat'],
            ['route' => 'leaves.index', 'match' => 'leaves.*', 'icon' => 'file', 'label' => 'Izin'],
            ['route' => 'account.edit', 'match' => 'account.*', 'icon' => 'user', 'label' => 'Akun'],
        ];
    } else {
        $tabs = [['route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'home', 'label' => 'Hari ini']];
        if ($attends) {
            $tabs[] = ['route' => 'presence.show', 'match' => 'presence.*', 'icon' => 'pin', 'label' => 'Presensi'];
        }
        $tabs[] = ['route' => 'reports.monthly', 'match' => 'reports.*', 'icon' => 'chart', 'label' => 'Rekap'];
        $tabs[] = ['route' => 'employees.index', 'match' => ['employees.*', 'corrections.*'], 'icon' => 'users', 'label' => 'Guru & Staf'];
        if (! $attends) {
            $tabs[] = ['route' => 'leaves.monitor', 'match' => 'leaves.monitor', 'icon' => 'file', 'label' => 'Izin'];
        }
    }
    $hasDrawer = $monitor;
@endphp

<div class="shell">
    <aside class="sidebar" id="sidebar" aria-label="Menu utama">
        <a class="brand" href="{{ $user->homeRoute() }}">
            @include('partials.brand-mark')
            <span class="brand__text">
                <strong>{{ $schoolSettings->schoolName() }}</strong>
                <span>Presensi guru & staf</span>
            </span>
        </a>

        <nav class="nav">
            @foreach ($nav as $group => $items)
                <p class="nav__group" id="nav-{{ \Illuminate\Support\Str::slug($group) }}">{{ $group }}</p>
                <ul aria-labelledby="nav-{{ \Illuminate\Support\Str::slug($group) }}">
                    @foreach ($items as $item)
                        @php $active = request()->routeIs(...(array) $item['match']); @endphp
                        <li>
                            <a href="{{ route($item['route']) }}" class="nav__link @if ($active) is-active @endif" @if ($active) aria-current="page" @endif>
                                <x-icon :name="$item['icon']" /> {{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </nav>

        <div class="sidebar__user">
            <span class="avatar" aria-hidden="true">{{ $user->initials() }}</span>
            <span class="sidebar__who">
                <strong>{{ $user->name }}</strong>
                <span>{{ $user->subtitle() }}</span>
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
            <a class="brand brand--compact" href="{{ $user->homeRoute() }}">
                @include('partials.brand-mark', ['size' => 30])
                <span class="brand__text"><strong>{{ $schoolSettings->schoolName() }}</strong></span>
            </a>
            <a class="topbar__avatar" href="{{ route('account.edit') }}" title="Akun: {{ $user->name }}">
                <span class="avatar avatar--sm" aria-hidden="true">{{ $user->initials() }}</span><span class="sr-only">Akun {{ $user->name }}</span>
            </a>
        </header>

        <main id="main" class="content" tabindex="-1">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>

<nav class="tabbar" aria-label="Navigasi utama">
    @foreach ($tabs as $tab)
        @php $active = request()->routeIs(...(array) $tab['match']); @endphp
        <a href="{{ route($tab['route']) }}" class="tabbar__item @if ($active) is-active @endif" @if ($active) aria-current="page" @endif>
            <x-icon :name="$tab['icon']" :size="22" /><span>{{ $tab['label'] }}</span>
        </a>
    @endforeach
    @if ($hasDrawer)
        <button type="button" class="tabbar__item" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false">
            <x-icon name="grid" :size="22" /><span>Menu</span>
        </button>
    @endif
</nav>
<div class="sidebar-backdrop" data-sidebar-backdrop hidden></div>

<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
