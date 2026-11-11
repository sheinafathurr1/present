<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <title>Presensi · {{ $device->name }} · {{ $schoolSettings->schoolName() }}</title>
    <link rel="stylesheet" href="{{ asset('css/kiosk.css') }}?v={{ filemtime(public_path('css/kiosk.css')) }}">
</head>
<body>
<div class="kiosk" id="kiosk" data-state="idle"
     data-tap-url="{{ route('kiosk.tap', $device) }}"
     data-stats-url="{{ route('kiosk.stats', $device) }}"
     data-login-url="{{ route('login') }}"
     data-server-time="{{ now()->getTimestampMs() }}"
     data-timezone="{{ config('app.timezone') }}">

    <header class="k-top">
        <div class="k-school">
            <strong>{{ $schoolSettings->schoolName() }}</strong>
            <span>{{ $device->name }}{{ $device->location ? ' · '.$device->location : '' }}</span>
        </div>
        <div class="k-clock" aria-hidden="true">
            <span class="k-clock__time" data-clock-time>{{ now()->format('H.i') }}</span>
            <span class="k-clock__date" data-clock-date>{{ now()->translatedFormat('l, d F Y') }}</span>
        </div>
        <div class="k-count" aria-label="Jumlah siswa hadir hari ini">
            <span class="k-count__value"><span data-stat-present>{{ $stats['present'] }}</span><small>/<span data-stat-total>{{ $stats['total'] }}</span></small></span>
            <span class="k-count__label">siswa hadir</span>
        </div>
    </header>

    {{-- Layar menunggu --}}
    <main class="k-idle" data-idle>
        <div class="k-tap-art" aria-hidden="true">
            <svg viewBox="0 0 240 200" width="240" height="200">
                <rect class="k-art-reader" x="40" y="110" width="160" height="70" rx="14"/>
                <circle class="k-art-led" cx="176" cy="132" r="6"/>
                <g class="k-art-card">
                    <rect x="62" y="20" width="116" height="74" rx="10"/>
                    <path d="M80 72h30"/>
                    <path d="M132 46a12 12 0 0 1 0 20M142 39a22 22 0 0 1 0 34"/>
                </g>
            </svg>
        </div>
        <h1 class="k-instruction">{{ $schoolSettings->kioskMessage() }}</h1>
        <p class="k-hint">Tunggu sampai terdengar bunyi dan layar berubah warna</p>
    </main>

    {{-- Layar hasil tap --}}
    <section class="k-result" data-result hidden aria-hidden="true">
        <div class="k-result__visual">
            <div class="k-result__icon" data-result-icon>
                <span data-icon="success"><x-icon name="check" :size="120" /></span>
                <span data-icon="warning"><x-icon name="clock" :size="120" /></span>
                <span data-icon="checkout"><x-icon name="door" :size="120" /></span>
                <span data-icon="info"><x-icon name="info" :size="120" /></span>
                <span data-icon="error"><x-icon name="x" :size="120" /></span>
            </div>
            <div class="k-result__photo" data-result-photo></div>
        </div>
        <div class="k-result__text">
            <p class="k-result__headline" data-result-headline></p>
            <p class="k-result__name" data-result-name></p>
            <p class="k-result__class" data-result-class></p>
            <p class="k-result__message" data-result-message></p>
        </div>
    </section>

    <footer class="k-bottom">
        <ol class="k-recent" data-recent aria-label="Tap terakhir"></ol>
        <div class="k-controls">
            <button type="button" class="k-btn" data-sound-toggle aria-pressed="true">
                <span data-when="on"><x-icon name="volume" /> Suara aktif</span>
                <span data-when="off"><x-icon name="volume-off" /> Suara mati</span>
            </button>
            <button type="button" class="k-btn" data-fullscreen><x-icon name="maximize" /> Layar penuh</button>
            <a class="k-btn" href="{{ route('kiosk.index') }}"><x-icon name="arrow-left" /> Ganti</a>
        </div>
    </footer>

    <div class="k-banner" data-offline hidden role="alert">
        <x-icon name="wifi" /> Koneksi ke server terputus. Presensi belum bisa dicatat — hubungi operator.
    </div>

    {{-- Browser hanya mengizinkan suara setelah ada klik pertama. --}}
    <div class="k-start" data-start>
        <div class="k-start__box">
            <x-icon name="volume" :size="56" />
            <h2>Siap memulai presensi</h2>
            <p>Klik tombol di bawah agar suara dan layar penuh bisa aktif.</p>
            <button type="button" class="k-start__btn" data-start-button>Mulai presensi</button>
            <p class="k-start__note">Pembaca kartu USB bekerja seperti keyboard: tidak perlu mengklik apa pun setelah ini.</p>
        </div>
    </div>

    <div class="sr-only" aria-live="assertive" aria-atomic="true" data-announcer></div>
</div>

<script src="{{ asset('js/kiosk.js') }}?v={{ filemtime(public_path('js/kiosk.js')) }}"></script>
</body>
</html>
