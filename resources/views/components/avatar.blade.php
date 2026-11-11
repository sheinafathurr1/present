@props(['student', 'size' => 40])
@if ($student->photoUrl())
    <img src="{{ $student->photoUrl() }}" alt="" width="{{ $size }}" height="{{ $size }}" {{ $attributes->merge(['class' => 'avatar']) }}>
@else
    <span aria-hidden="true" style="--size: {{ $size }}px" {{ $attributes->merge(['class' => 'avatar avatar--initials']) }}>{{ $student->initials() }}</span>
@endif
