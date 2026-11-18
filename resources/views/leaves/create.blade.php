@extends('layouts.app')

@section('title', 'Ajukan Izin')

@section('content')
<a href="{{ route('leaves.index') }}" class="back-link"><x-icon name="arrow-left" :size="16" /> Izin & Cuti</a>
<div class="page-head">
    <div>
        <h1>Ajukan tidak masuk</h1>
        <p class="lead">Langsung tercatat untuk hari sekolah di tanggal yang dipilih.</p>
    </div>
</div>

<form method="POST" action="{{ route('leaves.store') }}" enctype="multipart/form-data" class="card form-narrow" data-leave-form novalidate>
    @csrf

    <fieldset class="choice-group @error('type') has-error @enderror">
        <legend>Jenis <span class="req" aria-hidden="true">*</span><span class="sr-only">(wajib)</span></legend>
        <div class="choice-grid">
            @foreach ($types as $leaveType)
                <label class="choice">
                    <input type="radio" name="type" value="{{ $leaveType->value }}" @checked(old('type', 'izin') === $leaveType->value) required>
                    <span>
                        <strong><span class="swatch swatch--{{ $leaveType->status()->tone() }}" aria-hidden="true"></span>{{ $leaveType->label() }}</strong>
                        <span class="muted">{{ $leaveType->hint() }}</span>
                    </span>
                </label>
            @endforeach
        </div>
        @error('type')<p class="error"><x-icon name="alert" :size="16" /> {{ $message }}</p>@enderror
    </fieldset>

    <div class="form-grid">
        <x-input name="start_date" label="Tanggal mulai" type="date" required :value="$today" min="{{ $minDate }}" max="{{ $maxDate }}" />
        <x-input name="end_date" label="Sampai tanggal" type="date" min="{{ $minDate }}" max="{{ $maxDate }}" hint="Kosongkan bila hanya satu hari." />
    </div>

    <x-textarea name="reason" label="Keterangan" required rows="3" placeholder="mis. Demam, istirahat sesuai saran dokter" />

    <div class="field @error('attachment') has-error @enderror">
        <label for="f-attachment">Lampiran <span class="muted">(opsional)</span></label>
        <input id="f-attachment" name="attachment" type="file" class="input" accept="image/*,application/pdf"
               aria-describedby="f-attachment-hint @error('attachment') f-attachment-error @enderror">
        <p class="hint" id="f-attachment-hint">Foto surat dokter, surat tugas, atau dokumen lain (JPG, PNG, atau PDF, maks. {{ round($maxKb / 1024) }} MB). <span data-file-status></span></p>
        @error('attachment')<p class="error" id="f-attachment-error"><x-icon name="alert" :size="16" /> {{ $message }}</p>@enderror
    </div>

    <p class="hint form-note"><x-icon name="info" :size="16" /> Hari libur dan akhir pekan dilewati otomatis. Hari yang sudah Anda presensi masuk tidak diubah.</p>

    <button type="submit" class="btn btn--primary btn--lg btn--block">Kirim pengajuan</button>
</form>
@endsection
