{{-- Saring guru / staf tendik. --}}
<nav class="segmented" aria-label="Saring jenis pegawai">
    @foreach (['' => 'Semua', 'guru' => 'Guru', 'tendik' => 'Staf tendik'] as $value => $text)
        @php $active = ($type?->value ?? '') === $value; @endphp
        <a href="{{ request()->fullUrlWithQuery(['jenis' => $value ?: null, 'page' => null]) }}" class="segmented__item @if ($active) is-active @endif" @if ($active) aria-current="true" @endif>{{ $text }}</a>
    @endforeach
</nav>
