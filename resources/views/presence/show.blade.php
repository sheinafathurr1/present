@extends('layouts.app')

@section('title', 'Presensi')

@section('content')
@php
    $now = now();
    $mode = match (true) {
        ! $user->attends() => 'exempt',
        $attendance?->check_out_at !== null => 'done',
        $attendance?->check_in_at !== null => 'checkout',
        $attendance !== null && $attendance->status->isExcused() => 'leave',
        $dayOff !== null => 'off',
        default => 'checkin',
    };
    $hour = (int) $now->format('G');
    $greeting = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));
    $needsLocation = in_array($mode, ['checkin', 'checkout'], true);
@endphp

<div class="presence">
    <header class="presence__head">
        <p class="muted">{{ $greeting }},</p>
        <h1>{{ $user->name }}</h1>
    </header>

    <section class="card today" aria-label="Presensi hari ini">
        <div class="today__time">
            <p class="today__date">{{ $now->translatedFormat('l, d F Y') }}</p>
            <p class="today__clock"><time data-clock data-server-time="{{ $now->getTimestampMs() }}" data-tz="{{ config('app.timezone') }}">{{ $now->format('H:i') }}</time></p>
            <p class="today__schedule">Jam kerja {{ $workStart }}–{{ $workEnd }} · toleransi {{ $tolerance }} menit</p>
        </div>

        <dl class="today__slots">
            <div class="slot @if ($attendance?->check_in_at) is-done @endif">
                <dt>Masuk</dt>
                <dd class="slot__time">{{ $attendance?->check_in_at?->format('H:i') ?? '—' }}</dd>
                @if ($attendance?->check_in_at)
                    <dd class="slot__meta">
                        <x-status-badge :status="$attendance->status" />
                        @if ($attendance->late_minutes > 0)<span>{{ \App\Models\Attendance::minutes($attendance->late_minutes) }}</span>@endif
                    </dd>
                @endif
            </div>
            <div class="slot @if ($attendance?->check_out_at) is-done @endif">
                <dt>Pulang</dt>
                <dd class="slot__time">{{ $attendance?->check_out_at?->format('H:i') ?? '—' }}</dd>
                @if ($attendance?->check_out_at)
                    <dd class="slot__meta">
                        @if ($attendance->early_minutes > 0)
                            <span class="badge badge--warning">Pulang awal {{ \App\Models\Attendance::minutes($attendance->early_minutes) }}</span>
                        @else
                            <span class="badge badge--success">Tepat waktu</span>
                        @endif
                    </dd>
                @endif
            </div>
        </dl>
    </section>

    @switch($mode)
        @case('exempt')
            <div class="callout callout--info"><x-icon name="info" /><p>Akun Anda tidak diwajibkan presensi.</p></div>
            @break

        @case('off')
            <div class="state-card">
                <x-icon name="calendar-off" :size="28" />
                <div>
                    <h2>Tidak ada presensi hari ini</h2>
                    <p>{{ $dayOff }} Selamat beristirahat.</p>
                </div>
            </div>
            @break

        @case('leave')
            <div class="state-card state-card--{{ $attendance->status->tone() }}">
                <x-icon name="file" :size="28" />
                <div>
                    <h2>Hari ini Anda tercatat {{ $attendance->status->label() }}</h2>
                    <p>
                        @if ($attendance->leaveRequest)
                            {{ $attendance->leaveRequest->reason }} ({{ $attendance->leaveRequest->periodLabel() }}).
                        @endif
                        Ternyata masuk? <a href="{{ route('leaves.index') }}">Batalkan pengajuannya</a> lalu presensi seperti biasa.
                    </p>
                </div>
            </div>
            @break

        @case('done')
            <div class="state-card state-card--hadir">
                <x-icon name="check" :size="28" />
                <div>
                    <h2>Presensi hari ini lengkap</h2>
                    <p>Terima kasih. Sampai jumpa di hari sekolah berikutnya.</p>
                </div>
            </div>
            @break
    @endswitch

    @if ($needsLocation)
        @if (! $settings->hasLocation())
            <div class="alert alert--warning" role="status">
                <x-icon name="alert" />
                <div>
                    Lokasi sekolah belum diatur, jadi presensi belum bisa dilakukan.
                    @if ($user->isAdmin())
                        <a href="{{ route('settings.edit') }}#lokasi">Atur lokasi sekolah</a>.
                    @else
                        Hubungi admin sekolah.
                    @endif
                </div>
            </div>
        @else
            <section class="card geo" aria-labelledby="geo-title" data-geo
                     data-lat="{{ $settings->schoolLat() }}" data-lng="{{ $settings->schoolLng() }}"
                     data-radius="{{ $settings->radius() }}" data-max-accuracy="{{ $settings->maxAccuracy() }}">
                <svg class="geo__radar" viewBox="-100 -100 200 200" aria-hidden="true" focusable="false">
                    <circle class="geo__ring" r="94"/>
                    <circle class="geo__area" r="62"/>
                    <circle class="geo__accuracy" data-geo-accuracy r="0" cx="0" cy="0"/>
                    <path class="geo__school" d="M0-9 9 0 0 9-9 0z"/>
                    <circle class="geo__me" data-geo-me r="7" cx="0" cy="0" hidden/>
                </svg>
                <div class="geo__body" aria-live="polite">
                    <p class="geo__state" data-geo-state data-tone="pending">
                        <span class="geo__dot" aria-hidden="true"></span>
                        <strong id="geo-title" data-geo-title>Mencari lokasi Anda…</strong>
                    </p>
                    <p class="geo__detail" data-geo-detail>Pastikan lokasi/GPS HP menyala, lalu izinkan browser membaca lokasi.</p>
                    <button type="button" class="btn btn--secondary btn--sm" data-geo-retry hidden><x-icon name="refresh" :size="16" /> Coba lagi</button>
                </div>
                <noscript><p class="geo__detail">Aktifkan JavaScript di browser agar lokasi bisa dibaca.</p></noscript>
            </section>

            @php
                $isCheckIn = $mode === 'checkin';
            @endphp
            <form method="POST" action="{{ route($isCheckIn ? 'presence.check-in' : 'presence.check-out') }}" class="presence__form"
                  data-presence-form data-mode="{{ $mode }}"
                  @unless ($isCheckIn) data-work-end="{{ $workEnd }}" @endunless>
                @csrf
                <input type="hidden" name="lat" data-geo-field="lat">
                <input type="hidden" name="lng" data-geo-field="lng">
                <input type="hidden" name="accuracy" data-geo-field="accuracy">
                <button type="submit" class="btn btn--primary btn--xl btn--block" data-presence-submit disabled>
                    <x-icon :name="$isCheckIn ? 'login' : 'logout'" :size="22" />
                    <span data-presence-label>{{ $isCheckIn ? 'Presensi Masuk' : 'Presensi Pulang' }}</span>
                </button>
                <p class="hint presence__hint" data-presence-hint>Tombol aktif setelah lokasi Anda terbaca di area sekolah.</p>
            </form>
        @endif
    @endif

    @if ($mode === 'checkin')
        <a class="link-card" href="{{ route('leaves.create') }}">
            <x-icon name="file" />
            <span><strong>Tidak masuk hari ini?</strong><span>Ajukan izin, sakit, cuti, atau dinas luar.</span></span>
            <x-icon name="chevron-right" />
        </a>
    @endif

    @if ($recent->isNotEmpty())
        <section class="card" aria-labelledby="recent-title">
            <div class="card__head">
                <h2 id="recent-title" class="card__title">Beberapa hari terakhir</h2>
                <a class="link-quiet" href="{{ route('history.index') }}">Lihat riwayat</a>
            </div>
            <ul class="day-list">
                @foreach ($recent as $item)
                    @php $date = \Carbon\Carbon::parse($item->date); @endphp
                    <li class="day-list__item">
                        <span class="day-list__date"><strong>{{ $date->translatedFormat('D') }}</strong> {{ $date->translatedFormat('d M') }}</span>
                        <x-status-badge :status="$item->status" />
                        <span class="day-list__times">
                            @if ($item->check_in_at)
                                {{ $item->check_in_at->format('H:i') }}–{{ $item->check_out_at?->format('H:i') ?? '…' }}
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/presence.js') }}?v={{ filemtime(public_path('js/presence.js')) }}" defer></script>
@endpush
