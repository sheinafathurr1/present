@extends('layouts.app')

@section('title', 'Pengaturan')

@section('content')
<div class="page-head">
    <div>
        <h1>Pengaturan sekolah</h1>
        <p class="lead">Berlaku untuk semua guru & staf, kecuali yang diberi jam kerja khusus.</p>
    </div>
</div>

<form method="POST" action="{{ route('settings.update') }}" class="form-layout" novalidate>
    @csrf
    @method('PUT')

    <section class="card">
        <h2 class="card__title">Identitas</h2>
        <x-input name="school_name" label="Nama sekolah" :value="$settings->schoolName()" required />
    </section>

    <section class="card" id="lokasi" aria-labelledby="judul-lokasi">
        <h2 id="judul-lokasi" class="card__title"><x-icon name="pin" /> Lokasi presensi</h2>
        <p class="hint card__intro">Guru & staf hanya bisa presensi bila HP-nya berada dalam radius ini dari titik sekolah.
            Cara termudah: buka halaman ini dari HP saat berdiri di tengah sekolah, lalu ketuk <strong>Gunakan lokasi saya sekarang</strong>.</p>

        @unless ($settings->hasLocation())
            <div class="alert alert--warning" role="status"><x-icon name="alert" /><div>Lokasi belum diatur. Presensi belum bisa dilakukan sampai titik sekolah diisi.</div></div>
        @endunless

        <div class="locate">
            <button type="button" class="btn btn--secondary" data-locate><x-icon name="crosshair" :size="18" /> Gunakan lokasi saya sekarang</button>
            <p class="hint" data-locate-status aria-live="polite"></p>
        </div>

        <div class="form-grid">
            <x-input name="school_lat" label="Garis lintang (latitude)" :value="$settings->schoolLat()" inputmode="decimal" placeholder="mis. -6.2000000" />
            <x-input name="school_lng" label="Garis bujur (longitude)" :value="$settings->schoolLng()" inputmode="decimal" placeholder="mis. 106.8166660" />
        </div>
        <p class="hint locate__map"><a href="#" data-map-link target="_blank" rel="noopener" hidden><x-icon name="external" :size="14" /> Periksa titik di Google Maps</a>
            Bisa juga disalin dari Google Maps: tekan lama titik sekolah, lalu salin angka koordinatnya.</p>

        <div class="form-grid">
            <x-input name="radius_m" label="Radius presensi (meter)" type="number" min="20" max="2000" :value="$settings->radius()" required
                     hint="Umumnya 50–150 m, tergantung luas sekolah." />
            <x-input name="max_accuracy_m" label="Batas akurasi GPS (meter)" type="number" min="10" max="500" :value="$settings->maxAccuracy()" required
                     hint="Bacaan GPS yang lebih kasar dari ini ditolak. Di dalam gedung akurasi HP biasanya 10–50 m." />
        </div>
    </section>

    <section class="card" aria-labelledby="jam">
        <h2 id="jam" class="card__title"><x-icon name="clock" /> Jam kerja</h2>
        <div class="form-grid">
            <x-input name="guru_start" label="Guru: jam masuk" type="time" :value="$settings->get('guru_start')" required />
            <x-input name="guru_end" label="Guru: jam pulang" type="time" :value="$settings->get('guru_end')" required />
            <x-input name="tendik_start" label="Staf tendik: jam masuk" type="time" :value="$settings->get('tendik_start')" required />
            <x-input name="tendik_end" label="Staf tendik: jam pulang" type="time" :value="$settings->get('tendik_end')" required />
            <x-input name="late_tolerance_minutes" label="Toleransi terlambat (menit)" type="number" min="0" max="120" :value="$settings->lateTolerance()" required
                     hint="Masuk lewat dari toleransi dicatat Terlambat, dihitung sejak jam masuk." />
            <x-input name="check_in_opens" label="Presensi masuk dibuka mulai" type="time" :value="$settings->checkInOpens()" required />
        </div>
        <p class="hint">Jam khusus per orang (mis. guru yang mengajar siang) diatur di data guru & staf.</p>
    </section>

    <section class="card">
        <fieldset class="day-picker">
            <legend><h2 class="card__title">Hari sekolah</h2></legend>
            <p class="hint">Hanya pada hari ini guru & staf perlu presensi dan bisa dicatat Alpa. Tanggal merah diatur di menu Hari Libur.</p>
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

        <label class="check check--lg">
            <input type="hidden" name="joint_holidays_off" value="0">
            <input type="checkbox" name="joint_holidays_off" value="1" @checked(old('joint_holidays_off', $settings->jointHolidaysAreOff()))>
            <span><strong>Cuti bersama dihitung hari libur</strong><br>
                <span class="muted">Libur nasional & cuti bersama diambil otomatis (lihat menu Hari Libur). Matikan bila sekolah tetap masuk saat cuti bersama.</span></span>
        </label>
    </section>

    <div class="form-actions">
        <button type="submit" class="btn btn--primary btn--lg"><x-icon name="check" /> Simpan pengaturan</button>
    </div>
</form>
@endsection
