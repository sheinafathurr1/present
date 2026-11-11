@props(['name', 'label', 'options' => [], 'value' => null, 'hint' => null, 'required' => false, 'placeholder' => null, 'id' => null])
@php
    $id = $id ?? 'f-'.str_replace(['[', ']', '.'], '-', $name);
    $error = $errors->first($name);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
    $selected = (string) old($name, $value instanceof \BackedEnum ? $value->value : $value);
@endphp
<div class="field @if ($error) has-error @endif">
    <label for="{{ $id }}">{{ $label }} @if ($required)<span class="req" aria-hidden="true">*</span><span class="sr-only">(wajib)</span>@endif</label>
    <select id="{{ $id }}" name="{{ $name }}" @if ($required) required @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->merge(['class' => 'input']) }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($hint)<p class="hint" id="{{ $id }}-hint">{{ $hint }}</p>@endif
    @if ($error)<p class="error" id="{{ $id }}-error"><x-icon name="alert" :size="16" /> {{ $error }}</p>@endif
</div>
