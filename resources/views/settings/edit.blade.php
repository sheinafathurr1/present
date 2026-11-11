@extends('layouts.app')

@section('title', 'Pengaturan')

@section('content')
<div class="page-head">
    <div>
        <h1>Pengaturan sekolah</h1>
        <p class="lead">Berlaku untuk semua kelas, kecuali kelas yang punya jadwal khusus.</p>
    </div>
</div>

<form method="POST" action="{{ route('settings.update') }}" class="form-layout" novalidate>
    @csrf
    @method('PUT')

    <section class="card">
        <h2>Identitas</h2>
        <x-input name="school_name" label="Nama sekolah" :value="$settings->schoolName()" required />
        <x-input name="kiosk_message" label="Pesan di layar kiosk" :value="$settings->kioskMessage()" required hint="Ditampilkan besar di layar saat menunggu kartu ditempel." />
    </section>

    <section class="card" aria-labelledby="jam">
        <h2 id="jam"><x-icon name="clock" /> Jam presensi</h2>
        <div class="form-grid">
            <x-input name="late_after" label="Batas jam masuk" type="time" :value="$settings->lateAfter()" required
                     hint="Tap setelah menit ini dicatat Terlambat." />
            <x-input name="check_out_start" label="Presensi pulang mulai" type="time" :value="$settings->checkOutStart()" required
                     hint="Tap kedua mulai jam ini dicatat sebagai pulang." />
            <x-input name="tap_cooldown_seconds" label="Jeda tap ganda (detik)" type="number" min="0" max="3600" :value="$settings->cooldownSeconds()" required
                     hint="Kartu yang ditempel lagi dalam jeda ini diabaikan, agar tidak tercatat ganda." />
        </div>
    </section>

    <section class="card">
        <fieldset class="day-picker">
            <legend><h2>Hari sekolah</h2></legend>
            <p class="hint">Hanya pada hari ini siswa bisa dicatat Terlambat atau Alpa. Tanggal merah diatur di menu Hari Libur.</p>
            @php $selectedDays = old('school_days', $settings->schoolDays()); @endphp
            <div class="day-picker__options">
                @foreach ($days as $number => $label)
                    <label class="check check--pill">
                        <input type="checkbox" name="school_days[]" value="{{ $number }}" @checked(in_array($number, array_map('intval', (array) $selectedDays), true))>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            @error('school_days')<p class="error"><x-icon name="alert" :size="16" /> {{ $message }}</p>@enderror
        </fieldset>
    </section>

    <div class="form-actions">
        <button type="submit" class="btn btn--primary btn--lg"><x-icon name="check" /> Simpan pengaturan</button>
    </div>
</form>
@endsection
