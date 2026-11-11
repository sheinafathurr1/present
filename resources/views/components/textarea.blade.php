@props(['name', 'label', 'value' => null, 'hint' => null, 'required' => false, 'id' => null, 'rows' => 3])
@php
    $id = $id ?? 'f-'.$name;
    $error = $errors->first($name);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
@endphp
<div class="field @if ($error) has-error @endif">
    <label for="{{ $id }}">{{ $label }} @if ($required)<span class="req" aria-hidden="true">*</span><span class="sr-only">(wajib)</span>@endif</label>
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @if ($required) required @endif
              @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
              @if ($error) aria-invalid="true" @endif
              {{ $attributes->merge(['class' => 'input']) }}>{{ old($name, $value) }}</textarea>
    @if ($hint)<p class="hint" id="{{ $id }}-hint">{{ $hint }}</p>@endif
    @if ($error)<p class="error" id="{{ $id }}-error"><x-icon name="alert" :size="16" /> {{ $error }}</p>@endif
</div>
