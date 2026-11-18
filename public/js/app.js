/*
 * Presensi guru & staf — skrip umum (tanpa framework, tanpa build).
 */
(function () {
    'use strict';

    /* ---------- Menu (laci di HP) ---------- */

    var sidebar = document.getElementById('sidebar');
    var toggles = document.querySelectorAll('[data-sidebar-toggle]');
    var backdrop = document.querySelector('[data-sidebar-backdrop]');
    var lastToggle = null;

    function setSidebar(open) {
        if (!sidebar) return;
        sidebar.classList.toggle('is-open', open);
        toggles.forEach(function (toggle) { toggle.setAttribute('aria-expanded', open ? 'true' : 'false'); });
        if (backdrop) backdrop.hidden = !open;
        if (open) {
            var first = sidebar.querySelector('a, button');
            if (first) first.focus();
        } else if (lastToggle) {
            lastToggle.focus();
        }
    }

    toggles.forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            lastToggle = toggle;
            setSidebar(!sidebar.classList.contains('is-open'));
        });
    });
    if (backdrop) backdrop.addEventListener('click', function () { setSidebar(false); });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && sidebar && sidebar.classList.contains('is-open')) setSidebar(false);
    });

    /* ---------- Konfirmasi, cetak, fokus ringkasan galat ---------- */

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        });
    });

    document.querySelectorAll('[data-print]').forEach(function (button) {
        button.addEventListener('click', function () { window.print(); });
    });

    var summary = document.querySelector('[data-autofocus]');
    if (summary) summary.focus();

    // Formulir filter: kirim otomatis saat pilihan diubah.
    document.querySelectorAll('[data-autosubmit]').forEach(function (input) {
        input.addEventListener('change', function () { input.form.requestSubmit ? input.form.requestSubmit() : input.form.submit(); });
    });

    /* ---------- Dashboard: perbarui berkala ---------- */

    var refresh = document.querySelector('[data-auto-refresh]');
    if (refresh) {
        var seconds = Number(refresh.dataset.autoRefresh) || 120;
        var loadedAt = Date.now();
        setInterval(function () {
            if (!document.hidden && Date.now() - loadedAt >= seconds * 1000) window.location.reload();
        }, 15000);
    }

    /* ---------- Pengaturan: ambil titik sekolah dari lokasi HP ---------- */

    var locate = document.querySelector('[data-locate]');
    if (locate) {
        var latInput = document.getElementById('f-school_lat');
        var lngInput = document.getElementById('f-school_lng');
        var status = document.querySelector('[data-locate-status]');
        var mapLink = document.querySelector('[data-map-link]');

        var updateMap = function () {
            if (!mapLink) return;
            var ok = latInput.value && lngInput.value;
            mapLink.hidden = !ok;
            if (ok) mapLink.href = 'https://www.google.com/maps?q=' + encodeURIComponent(latInput.value + ',' + lngInput.value);
        };
        latInput.addEventListener('input', updateMap);
        lngInput.addEventListener('input', updateMap);
        updateMap();

        locate.addEventListener('click', function () {
            if (!window.isSecureContext || !('geolocation' in navigator)) {
                status.textContent = 'Lokasi hanya bisa dibaca lewat alamat https:// di browser yang mendukung.';
                return;
            }
            locate.disabled = true;
            status.textContent = 'Membaca lokasi… tunggu sampai akurasinya kecil.';

            var best = null;
            var watch = navigator.geolocation.watchPosition(function (position) {
                if (!best || position.coords.accuracy < best.coords.accuracy) best = position;
                latInput.value = best.coords.latitude.toFixed(7);
                lngInput.value = best.coords.longitude.toFixed(7);
                status.textContent = 'Terbaca dengan akurasi ± ' + Math.round(best.coords.accuracy) + ' m' +
                    (best.coords.accuracy > 20 ? ' — tunggu sebentar agar lebih akurat…' : '. Simpan pengaturan bila sudah sesuai.');
                updateMap();
            }, function (error) {
                status.textContent = error.code === 1
                    ? 'Akses lokasi ditolak. Izinkan lokasi untuk situs ini lalu coba lagi.'
                    : 'Lokasi belum terbaca. Pastikan GPS menyala lalu coba lagi.';
                navigator.geolocation.clearWatch(watch);
                locate.disabled = false;
            }, { enableHighAccuracy: true, maximumAge: 0, timeout: 30000 });

            // Ambil bacaan terbaik selama 20 detik.
            setTimeout(function () {
                navigator.geolocation.clearWatch(watch);
                locate.disabled = false;
                if (best) status.textContent = 'Lokasi diisi (akurasi ± ' + Math.round(best.coords.accuracy) + ' m). Simpan pengaturan bila sudah sesuai.';
            }, 20000);
        });
    }

    /* ---------- Pengajuan izin ---------- */

    var leaveForm = document.querySelector('[data-leave-form]');
    if (leaveForm) {
        var start = leaveForm.querySelector('#f-start_date');
        var end = leaveForm.querySelector('#f-end_date');
        var syncEnd = function () {
            end.min = start.value || end.min;
            if (end.value && start.value && end.value < start.value) end.value = start.value;
        };
        start.addEventListener('change', syncEnd);
        syncEnd();

        // Foto dari kamera HP bisa beberapa MB: perkecil dulu sebelum diunggah.
        var file = leaveForm.querySelector('input[type="file"]');
        var fileStatus = leaveForm.querySelector('[data-file-status]');
        if (file && window.DataTransfer && window.URL && document.createElement('canvas').toBlob) {
            file.addEventListener('change', function () {
                var picked = file.files && file.files[0];
                if (!picked || !/^image\/(jpeg|png|webp|heic|heif)$/i.test(picked.type) || picked.size < 900 * 1024) return;

                var img = new Image();
                var url = URL.createObjectURL(picked);
                img.onload = function () {
                    var max = 1600;
                    var scale = Math.min(1, max / Math.max(img.width, img.height));
                    var canvas = document.createElement('canvas');
                    canvas.width = Math.round(img.width * scale);
                    canvas.height = Math.round(img.height * scale);
                    canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                    URL.revokeObjectURL(url);
                    canvas.toBlob(function (blob) {
                        if (!blob || blob.size >= picked.size) return;
                        try {
                            var smaller = new File([blob], picked.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' });
                            var transfer = new DataTransfer();
                            transfer.items.add(smaller);
                            file.files = transfer.files;
                            if (fileStatus) fileStatus.textContent = 'Foto diperkecil menjadi ' + Math.round(blob.size / 1024) + ' KB.';
                        } catch (e) { /* biarkan file asli */ }
                    }, 'image/jpeg', 0.82);
                };
                img.onerror = function () { URL.revokeObjectURL(url); };
                img.src = url;
            });
        }
    }

    /* ---------- Koreksi presensi: jam hanya untuk Hadir/Terlambat ---------- */

    var correction = document.querySelector('[data-correction-form]');
    if (correction) {
        var times = correction.querySelector('[data-times]');
        var sync = function () {
            var checked = correction.querySelector('input[name="status"]:checked');
            var present = checked && (checked.value === 'hadir' || checked.value === 'terlambat');
            times.hidden = !present;
            times.querySelectorAll('input').forEach(function (input) { input.disabled = !present; });
        };
        correction.querySelectorAll('input[name="status"]').forEach(function (radio) { radio.addEventListener('change', sync); });
        sync();
    }

    /* ---------- Kalender hari libur ---------- */

    // Di layar kecil (kalender bisa digeser), langsung buka bulan ini.
    var calGrid = document.querySelector('[data-cal-grid]');
    var calCurrent = calGrid && calGrid.querySelector('[data-cal-current]');
    if (calCurrent && calGrid.scrollWidth > calGrid.clientWidth) {
        calGrid.scrollLeft = calCurrent.offsetLeft - calGrid.offsetLeft - 16;
    }

    document.querySelectorAll('[data-cal-day]').forEach(function (day) {
        day.addEventListener('click', function () {
            var date = day.dataset.calDay;

            // Tanggal libur: tampilkan keterangannya di daftar.
            if (day.hasAttribute('data-holiday')) {
                var item = document.getElementById('libur-' + date);
                if (item) {
                    item.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    item.classList.remove('is-flash');
                    void item.offsetWidth;
                    item.classList.add('is-flash');
                }
                return;
            }

            // Tanggal kosong: isi formulir libur sekolah.
            var startDate = document.getElementById('f-date');
            var endDate = document.getElementById('f-end_date');
            var name = document.getElementById('f-name');
            if (!startDate) return;
            if (startDate.value && !endDate.value && date > startDate.value) {
                endDate.value = date;          // klik kedua: tanggal akhir rentang
            } else {
                startDate.value = date;
                endDate.value = '';
            }
            document.getElementById('form-libur').scrollIntoView({ behavior: 'smooth', block: 'center' });
            name.focus({ preventScroll: true });
        });
    });
})();
