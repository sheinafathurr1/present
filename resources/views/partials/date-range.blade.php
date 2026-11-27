{{-- Rentang tanggal "dari – sampai" dengan pilihan cepat. $presets dari ExportController::presets(). --}}
@php $presets ??= \App\Http\Controllers\ExportController::presets(); @endphp
<fieldset class="date-range" data-date-range>
    <legend>Rentang tanggal <span class="req" aria-hidden="true">*</span><span class="sr-only">(wajib)</span></legend>
    <div class="date-range__presets" role="group" aria-label="Pilihan cepat">
        @foreach ($presets as $preset)
            <button type="button" class="chip-btn" data-range-from="{{ $preset['from'] }}" data-range-to="{{ $preset['to'] }}"
                    aria-pressed="{{ old('dari', $presets[0]['from']) === $preset['from'] && old('sampai', $presets[0]['to']) === $preset['to'] ? 'true' : 'false' }}">{{ $preset['label'] }}</button>
        @endforeach
    </div>
    <div class="form-grid">
        <x-input name="dari" :id="($idPrefix ?? 'f').'-dari'" label="Dari tanggal" type="date" required :value="old('dari', $presets[0]['from'])" />
        <x-input name="sampai" :id="($idPrefix ?? 'f').'-sampai'" label="Sampai tanggal" type="date" required :value="old('sampai', $presets[0]['to'])" />
    </div>
</fieldset>
