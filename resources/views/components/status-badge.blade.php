@props(['status' => null])
@if ($status)
    <span {{ $attributes->merge(['class' => 'badge badge--'.$status->tone()]) }}>
        <span class="badge__code" aria-hidden="true">{{ $status->code() }}</span>{{ $status->label() }}
    </span>
@else
    <span {{ $attributes->merge(['class' => 'badge badge--muted']) }}>
        <span class="badge__code" aria-hidden="true">?</span>Belum presensi
    </span>
@endif
