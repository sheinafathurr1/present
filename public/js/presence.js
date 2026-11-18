/*
 * Halaman presensi: jam server, pembacaan lokasi GPS, dan tombol presensi.
 *
 * Lokasi hanya dikirim saat tombol presensi ditekan. Perhitungan jarak di sini
 * hanya untuk memberi tahu pengguna; keputusan akhir tetap di server.
 */
(function () {
    'use strict';

    /* ---------- Jam (mengikuti jam server, bukan jam HP) ---------- */

    var clock = document.querySelector('[data-clock]');
    var offset = clock ? Number(clock.dataset.serverTime) - Date.now() : 0;
    var tz = clock ? clock.dataset.tz : undefined;

    function formatter(options) {
        try {
            return new Intl.DateTimeFormat('id-ID', Object.assign({ hour12: false, timeZone: tz }, options));
        } catch (e) {
            return new Intl.DateTimeFormat('id-ID', Object.assign({ hour12: false }, options));
        }
    }

    var clockFormat = formatter({ hour: '2-digit', minute: '2-digit', second: '2-digit' });
    var hmFormat = formatter({ hour: '2-digit', minute: '2-digit' });

    function serverNow() { return new Date(Date.now() + offset); }
    function serverHM() { return hmFormat.format(serverNow()).replace(/\./g, ':'); }

    if (clock) {
        var tick = function () { clock.textContent = clockFormat.format(serverNow()).replace(/\./g, ':'); };
        tick();
        setInterval(tick, 1000);
    }

    /* ---------- Lokasi ---------- */

    var panel = document.querySelector('[data-geo]');
    var form = document.querySelector('[data-presence-form]');
    if (!panel || !form) return;

    var school = { lat: Number(panel.dataset.lat), lng: Number(panel.dataset.lng) };
    var radius = Number(panel.dataset.radius);
    var maxAccuracy = Number(panel.dataset.maxAccuracy);

    var title = panel.querySelector('[data-geo-title]');
    var detail = panel.querySelector('[data-geo-detail]');
    var retry = panel.querySelector('[data-geo-retry]');
    var me = panel.querySelector('[data-geo-me]');
    var accuracyRing = panel.querySelector('[data-geo-accuracy]');
    var submit = form.querySelector('[data-presence-submit]');
    var label = form.querySelector('[data-presence-label]');
    var hint = form.querySelector('[data-presence-hint]');
    var fields = {
        lat: form.querySelector('[data-geo-field="lat"]'),
        lng: form.querySelector('[data-geo-field="lng"]'),
        accuracy: form.querySelector('[data-geo-field="accuracy"]')
    };

    var AREA = 62;   // jari-jari lingkaran area sekolah di gambar
    var EDGE = 88;   // titik di luar area ditahan di tepi gambar
    var watchId = null;
    var submitting = false;

    function rad(deg) { return deg * Math.PI / 180; }

    function distance(lat, lng) {
        var dLat = rad(lat - school.lat);
        var dLng = rad(lng - school.lng);
        var a = Math.pow(Math.sin(dLat / 2), 2) +
            Math.cos(rad(school.lat)) * Math.cos(rad(lat)) * Math.pow(Math.sin(dLng / 2), 2);
        return 2 * 6371000 * Math.asin(Math.min(1, Math.sqrt(a)));
    }

    function meters(value) {
        value = Math.round(value);
        return value < 1000 ? value + ' m' : (Math.round(value / 100) / 10).toString().replace('.', ',') + ' km';
    }

    function setState(tone, heading, text, canSubmit, showRetry) {
        panel.dataset.tone = tone;
        title.textContent = heading;
        detail.textContent = text;
        retry.hidden = !showRetry;
        submit.disabled = !canSubmit || submitting;
        hint.hidden = canSubmit;
    }

    function drawPosition(lat, lng, accuracy) {
        var scale = AREA / radius;
        var east = (lng - school.lng) * Math.cos(rad(school.lat)) * 111320;
        var north = (lat - school.lat) * 110574;
        var x = east * scale;
        var y = -north * scale;
        var length = Math.sqrt(x * x + y * y);
        if (length > EDGE) {
            x = x / length * EDGE;
            y = y / length * EDGE;
        }
        me.setAttribute('cx', x.toFixed(1));
        me.setAttribute('cy', y.toFixed(1));
        me.removeAttribute('hidden');
        accuracyRing.setAttribute('cx', x.toFixed(1));
        accuracyRing.setAttribute('cy', y.toFixed(1));
        accuracyRing.setAttribute('r', Math.min(94, accuracy * scale).toFixed(1));
    }

    function onPosition(position) {
        var c = position.coords;
        var dist = distance(c.latitude, c.longitude);

        fields.lat.value = c.latitude.toFixed(7);
        fields.lng.value = c.longitude.toFixed(7);
        fields.accuracy.value = Math.round(c.accuracy);
        drawPosition(c.latitude, c.longitude, c.accuracy);

        if (c.accuracy > maxAccuracy) {
            setState('warn', 'Sinyal GPS belum akurat',
                'Akurasi saat ini ± ' + meters(c.accuracy) + ' (batas ± ' + maxAccuracy + ' m). Tunggu sebentar, atau pindah ke dekat jendela/tempat terbuka.',
                false, false);
        } else if (dist > radius) {
            setState('out', 'Di luar area sekolah',
                'Anda sekitar ' + meters(dist) + ' dari sekolah. Presensi hanya bisa dalam radius ' + radius + ' m.',
                false, false);
        } else {
            setState('in', 'Anda di area sekolah',
                meters(dist) + ' dari titik sekolah · akurasi ± ' + meters(c.accuracy) + '.',
                true, false);
        }
    }

    function onError(error) {
        if (error.code === 1) {
            setState('error', 'Akses lokasi ditolak',
                'Izinkan lokasi untuk situs ini: ketuk ikon di samping alamat situs, pilih Izin/Setelan situs → Lokasi → Izinkan. Setelah itu ketuk Coba lagi.',
                false, true);
        } else if (error.code === 3) {
            setState('warn', 'Lokasi belum terbaca',
                'Pastikan lokasi/GPS HP menyala, lalu coba di tempat yang lebih terbuka.',
                false, true);
        } else {
            setState('error', 'Lokasi tidak tersedia',
                'Nyalakan lokasi/GPS di pengaturan HP, lalu ketuk Coba lagi.',
                false, true);
        }
    }

    function start() {
        if (!window.isSecureContext) {
            setState('error', 'Lokasi tidak bisa dibaca',
                'Browser hanya membagikan lokasi lewat alamat aman (https://). Minta admin memasang HTTPS di hosting.',
                false, false);
            return;
        }
        if (!('geolocation' in navigator)) {
            setState('error', 'Browser tidak mendukung lokasi',
                'Buka halaman ini dengan Chrome, Safari, atau browser terbaru lainnya.',
                false, false);
            return;
        }
        stop();
        setState('pending', 'Mencari lokasi Anda…', 'Pastikan lokasi/GPS HP menyala, lalu izinkan browser membaca lokasi.', false, false);
        watchId = navigator.geolocation.watchPosition(onPosition, onError, {
            enableHighAccuracy: true,
            maximumAge: 0,
            timeout: 30000
        });
    }

    function stop() {
        if (watchId !== null) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }
    }

    retry.addEventListener('click', start);

    // Hemat baterai: berhenti membaca GPS saat halaman tidak terlihat.
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) stop(); else if (!submitting) start();
    });

    // Kembali ke halaman ini dengan tombol "back": muat ulang agar status terbaru tampil.
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) window.location.reload();
    });

    form.addEventListener('submit', function (event) {
        if (submitting || !fields.lat.value) {
            event.preventDefault();
            return;
        }

        var workEnd = form.dataset.workEnd;
        if (workEnd) {
            var now = serverHM();
            if (now < workEnd) {
                var parts = now.split(':');
                var end = workEnd.split(':');
                var early = (Number(end[0]) * 60 + Number(end[1])) - (Number(parts[0]) * 60 + Number(parts[1]));
                var text = early >= 60 ? Math.floor(early / 60) + ' jam ' + (early % 60 ? early % 60 + ' menit' : '') : early + ' menit';
                if (!window.confirm('Jam pulang Anda ' + workEnd + '. Pulang sekarang tercatat pulang awal ' + text.trim() + '. Lanjutkan?')) {
                    event.preventDefault();
                    return;
                }
            }
        }

        submitting = true;
        stop();
        submit.disabled = true;
        label.textContent = 'Menyimpan…';
    });

    start();
})();
