{{-- Pindah bulan: ‹ November 2026 ›. Parameter lain di URL dipertahankan. --}}
@php
    $prev = $month->subMonth()->format('Y-m');
    $next = $month->addMonth()->format('Y-m');
    $isCurrent = $month->format('Y-m') === now()->format('Y-m');
@endphp
<nav class="month-nav" aria-label="Pilih bulan">
    <a class="btn btn--secondary btn--icon" href="{{ request()->fullUrlWithQuery(['bulan' => $prev, 'page' => null]) }}" title="Bulan sebelumnya">
        <x-icon name="chevron-left" :size="18" /><span class="sr-only">{{ $month->subMonth()->translatedFormat('F Y') }}</span>
    </a>
    <span class="month-nav__label">{{ $month->translatedFormat('F Y') }}</span>
    @if ($isCurrent)
        <span class="btn btn--secondary btn--icon is-disabled" aria-disabled="true"><x-icon name="chevron-right" :size="18" /></span>
    @else
        <a class="btn btn--secondary btn--icon" href="{{ request()->fullUrlWithQuery(['bulan' => $next, 'page' => null]) }}" title="Bulan berikutnya">
            <x-icon name="chevron-right" :size="18" /><span class="sr-only">{{ $month->addMonth()->translatedFormat('F Y') }}</span>
        </a>
    @endif
</nav>
