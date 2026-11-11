@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false, 'id' => null])
@php
    $id = $id ?? 'f-'.str_replace(['[', ']', '.'], '-', $name);
    $error = $errors->first($name);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
@endphp
<div class="field @if ($error) has-error @endif">
    <label for="{{ $id }}">{{ $label }} @if ($required)<span class="req" aria-hidden="true">*</span><span class="sr-only">(wajib)</span>@endif</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
           @if ($type !== 'file' && $type !== 'password') value="{{ old($name, $value) }}" @endif
           @if ($required) required @endif
           @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
           @if ($error) aria-invalid="true" @endif
           {{ $attributes->merge(['class' => 'input']) }}>
    @if ($hint)<p class="hint" id="{{ $id }}-hint">{{ $hint }}</p>@endif
    @if ($error)<p class="error" id="{{ $id }}-error"><x-icon name="alert" :size="16" /> {{ $error }}</p>@endif
</div>
