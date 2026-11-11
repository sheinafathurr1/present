/*
 * Layar kiosk presensi.
 *
 * Pembaca kartu RFID USB bekerja seperti keyboard: saat kartu ditempel, ia
 * "mengetik" nomor kartu lalu menekan Enter. Skrip ini menangkap ketikan
 * tersebut di seluruh halaman (tidak perlu kotak isian yang difokuskan),
 * mengirimnya ke server, lalu memberi umpan balik lewat:
 *   - nada (Web Audio API) — berbeda untuk berhasil, terlambat, pulang, dan gagal,
 *   - suara ucapan bahasa Indonesia (Web Speech API),
 *   - layar berwarna penuh dengan ikon, kata, nama besar, dan foto siswa,
 *   - pengumuman aria-live untuk pembaca layar.
 */
(function () {
    'use strict';

    var kiosk = document.getElementById('kiosk');
    if (!kiosk) return;

    var $ = function (selector) { return kiosk.querySelector(selector); };
    var csrf = document.querySelector('meta[name="csrf-token"]').content;

    var el = {
        idle: $('[data-idle]'),
        result: $('[data-result]'),
        headline: $('[data-result-headline]'),
        name: $('[data-result-name]'),
        klass: $('[data-result-class]'),
        message: $('[data-result-message]'),
        photo: $('[data-result-photo]'),
        recent: $('[data-recent]'),
        offline: $('[data-offline]'),
        start: $('[data-start]'),
        announcer: $('[data-announcer]'),
        clockTime: $('[data-clock-time]'),
        clockDate: $('[data-clock-date]'),
        present: $('[data-stat-present]'),
        total: $('[data-stat-total]'),
        sound: $('[data-sound-toggle]')
    };

    /* Lama layar hasil ditampilkan (ms) sesuai mode umpan balik siswa. */
    var DISPLAY_MS = { both: 5000, audio: 6500, visual: 9000, error: 7000 };

    var state = {
        buffer: '',
        lastKeyAt: 0,
        busy: false,
        queue: [],
        resetTimer: null,
        sound: read('kiosk:sound') !== 'off',
        audio: null,
        voice: null
    };

    function read(key) { try { return localStorage.getItem(key); } catch (e) { return null; } }
    function write(key, value) { try { localStorage.setItem(key, value); } catch (e) { /* abaikan */ } }

    /* ---------- Jam ---------- */

    // Pakai jam & zona waktu server, supaya layar tetap benar walau jam komputer kiosk meleset.
    var skew = (parseInt(kiosk.dataset.serverTime, 10) || Date.now()) - Date.now();
    var timeZone = kiosk.dataset.timezone || undefined;
    var timeFormat = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: timeZone });
    var dateFormat = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: timeZone });

    function serverNow() {
        return new Date(Date.now() + skew);
    }

    function tickClock() {
        var now = serverNow();
        el.clockTime.textContent = timeFormat.format(now).replace(':', '.');
        el.clockDate.textContent = dateFormat.format(now);
    }
    tickClock();
    setInterval(tickClock, 1000);

    /* ---------- Suara: nada ---------- */

    var TONES = {
        success: [[660, 0.12], [880, 0.18]],
        warning: [[660, 0.15], [660, 0.15], [523, 0.22]],
        checkout: [[880, 0.12], [660, 0.18]],
        info: [[600, 0.16]],
        error: [[220, 0.22], [180, 0.35]]
    };

    function ensureAudio() {
        if (state.audio) return state.audio;
        var Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return null;
        state.audio = new Ctx();
        return state.audio;
    }

    function playTone(tone) {
        if (!state.sound) return;
        var ctx = ensureAudio();
        if (!ctx) return;
        if (ctx.state === 'suspended') ctx.resume();

        var at = ctx.currentTime + 0.02;
        (TONES[tone] || TONES.info).forEach(function (step) {
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.type = tone === 'error' ? 'square' : 'sine';
            osc.frequency.value = step[0];
            gain.gain.setValueAtTime(0.0001, at);
            gain.gain.exponentialRampToValueAtTime(tone === 'error' ? 0.18 : 0.35, at + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, at + step[1]);
            osc.connect(gain).connect(ctx.destination);
            osc.start(at);
            osc.stop(at + step[1] + 0.02);
            at += step[1] + 0.05;
        });
    }

    /* ---------- Suara: ucapan bahasa Indonesia ---------- */

    function pickVoice() {
        if (!('speechSynthesis' in window)) return;
        var voices = window.speechSynthesis.getVoices();
        state.voice = voices.find(function (v) { return /^id([-_]|$)/i.test(v.lang); })
            || voices.find(function (v) { return /indonesia/i.test(v.name); })
            || null;
    }

    if ('speechSynthesis' in window) {
        pickVoice();
        window.speechSynthesis.onvoiceschanged = pickVoice;
    }

    function speak(text, mode) {
        if (!state.sound || !text || !('speechSynthesis' in window)) return;
        window.speechSynthesis.cancel();
        var utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'id-ID';
        if (state.voice) utterance.voice = state.voice;
        utterance.rate = mode === 'audio' ? 0.85 : 0.95;
        utterance.volume = 1;
        // Beri jeda singkat agar tidak bertumpuk dengan nada.
        setTimeout(function () { window.speechSynthesis.speak(utterance); }, 350);
    }

    /* ---------- Tampilan ---------- */

    function showIdle() {
        clearTimeout(state.resetTimer);
        kiosk.dataset.state = 'idle';
        delete kiosk.dataset.tone;
        kiosk.classList.remove('is-visual-emphasis');
        el.result.hidden = true;
        el.result.setAttribute('aria-hidden', 'true');
        el.idle.hidden = false;
    }

    function setPhoto(student) {
        el.photo.textContent = '';
        if (!student) return;
        if (student.photo_url) {
            var img = document.createElement('img');
            img.src = student.photo_url;
            img.alt = '';
            el.photo.appendChild(img);
        } else {
            var initials = document.createElement('span');
            initials.className = 'k-initials';
            initials.textContent = student.initials;
            el.photo.appendChild(initials);
        }
    }

    function showResult(data) {
        var student = data.student;
        var mode = student ? student.feedback_mode : 'both';
        var tone = data.tone || 'info';

        clearTimeout(state.resetTimer);
        kiosk.dataset.state = 'result';
        kiosk.dataset.tone = tone;
        kiosk.classList.toggle('is-visual-emphasis', mode === 'visual');

        el.headline.textContent = data.headline;
        el.name.textContent = student ? student.name : '';
        el.klass.textContent = student && student['class'] ? student['class'] : '';
        el.message.textContent = data.message;
        setPhoto(student);

        el.idle.hidden = true;
        el.result.hidden = false;
        el.result.setAttribute('aria-hidden', 'false');

        // Pengumuman untuk pembaca layar.
        el.announcer.textContent = '';
        setTimeout(function () { el.announcer.textContent = data.headline + '. ' + data.message; }, 50);

        playTone(tone);
        if (mode !== 'visual') speak(mode === 'audio' ? data.speech_detailed : data.speech, mode);

        if (data.stats) updateStats(data.stats);
        if (student && data.ok) addRecent(student.call_name || student.name, data.time, data.headline);

        var duration = tone === 'error' ? DISPLAY_MS.error : (DISPLAY_MS[mode] || DISPLAY_MS.both);
        state.resetTimer = setTimeout(showIdle, duration);
    }

    function showLocalError(headline, message) {
        showResult({ ok: false, tone: 'error', headline: headline, message: message, speech: message, speech_detailed: message, student: null });
    }

    function addRecent(name, time, label) {
        var li = document.createElement('li');
        var t = document.createElement('time');
        t.textContent = time;
        li.appendChild(t);
        li.appendChild(document.createTextNode(name + ' · ' + label));
        el.recent.insertBefore(li, el.recent.firstChild);
        while (el.recent.children.length > 4) el.recent.removeChild(el.recent.lastChild);
    }

    function updateStats(stats) {
        el.present.textContent = stats.present;
        el.total.textContent = stats.total;
    }

    function setOffline(offline) {
        el.offline.hidden = !offline;
    }

    /* ---------- Kirim tap ke server ---------- */

    function submit(uid) {
        if (state.busy) {
            state.queue.push(uid);
            return;
        }
        state.busy = true;

        fetch(kiosk.dataset.tapUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ uid: uid })
        })
            .then(function (response) {
                if (response.status === 419) {
                    showLocalError('Sesi berakhir', 'Halaman akan dimuat ulang. Silakan tempel kartu lagi.');
                    setTimeout(function () { window.location.reload(); }, 3000);
                    return null;
                }
                if (response.status === 401) {
                    window.location.href = kiosk.dataset.loginUrl;
                    return null;
                }
                if (response.status === 429) {
                    showLocalError('Tunggu sebentar', 'Terlalu banyak kartu ditempel. Coba lagi beberapa detik lagi.');
                    return null;
                }
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            })
            .then(function (data) {
                setOffline(false);
                if (data) showResult(data);
            })
            .catch(function () {
                setOffline(true);
                showLocalError('Tidak terhubung', 'Presensi belum tercatat. Silakan minta bantuan guru.');
            })
            .then(function () {
                state.busy = false;
                if (state.queue.length) submit(state.queue.shift());
            });
    }

    /* ---------- Aktivasi suara ---------- */

    // Browser baru mengizinkan suara setelah ada interaksi pengguna. Klik tombol
    // "Mulai" ATAU tap kartu pertama (ketikan dari pembaca USB) sudah cukup,
    // sehingga kiosk tetap berfungsi walau tidak ada yang mengklik setelah dimuat ulang.
    var activated = false;

    function activate() {
        if (activated) return;
        activated = true;
        ensureAudio();
        if (state.audio && state.audio.state === 'suspended') state.audio.resume();
        if ('speechSynthesis' in window) {
            var warmup = new SpeechSynthesisUtterance(' ');
            warmup.volume = 0;
            window.speechSynthesis.speak(warmup);
        }
        el.start.hidden = true;
    }

    /* ---------- Tangkap ketikan dari pembaca USB ---------- */

    document.addEventListener('keydown', function (event) {
        if (event.ctrlKey || event.altKey || event.metaKey) return;
        activate();

        var now = Date.now();
        // Ketikan dari pembaca sangat cepat; jeda lama berarti awal kartu baru.
        if (now - state.lastKeyAt > 1500) state.buffer = '';
        state.lastKeyAt = now;

        if (event.key === 'Enter') {
            event.preventDefault();
            var uid = state.buffer;
            state.buffer = '';
            if (uid.length >= 4) submit(uid);
            return;
        }

        if (event.key.length === 1 && /[0-9a-z]/i.test(event.key)) {
            event.preventDefault();
            state.buffer = (state.buffer + event.key).slice(-64);
        }
    });

    /* ---------- Tombol-tombol ---------- */

    function syncSoundButton() {
        el.sound.setAttribute('aria-pressed', state.sound ? 'true' : 'false');
    }
    syncSoundButton();

    el.sound.addEventListener('click', function () {
        state.sound = !state.sound;
        write('kiosk:sound', state.sound ? 'on' : 'off');
        syncSoundButton();
        if (state.sound) playTone('info');
        el.sound.blur();
    });

    function requestFullscreen() {
        var target = document.documentElement;
        if (!document.fullscreenElement && target.requestFullscreen) {
            target.requestFullscreen().catch(function () { /* tidak didukung */ });
        }
    }

    kiosk.querySelector('[data-fullscreen]').addEventListener('click', function (event) {
        if (document.fullscreenElement) document.exitFullscreen(); else requestFullscreen();
        event.currentTarget.blur();
    });

    kiosk.querySelector('[data-start-button]').addEventListener('click', function () {
        activate();
        playTone('success');
        requestFullscreen();
        document.activeElement.blur();
    });

    /* ---------- Perbarui angka & jaga sesi tetap aktif ---------- */

    function refreshStats() {
        fetch(kiosk.dataset.statsUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (response) {
                if (response.status === 401) {
                    window.location.href = kiosk.dataset.loginUrl;
                    return null;
                }
                if (!response.ok) throw new Error(response.status);
                return response.json();
            })
            .then(function (data) {
                setOffline(false);
                if (data) updateStats(data.stats);
            })
            .catch(function () { setOffline(true); });
    }

    setInterval(refreshStats, 60000);

    // Muat ulang tiap pukul 01.00 (waktu server) agar tanggal & token sesi selalu segar.
    (function scheduleNightlyReload() {
        var parts = {};
        new Intl.DateTimeFormat('en-GB', { hour: 'numeric', minute: 'numeric', second: 'numeric', hour12: false, timeZone: timeZone })
            .formatToParts(serverNow())
            .forEach(function (part) { parts[part.type] = parseInt(part.value, 10); });
        var secondsNow = (parts.hour % 24) * 3600 + parts.minute * 60 + parts.second;
        var wait = 3600 - secondsNow;
        if (wait <= 60) wait += 24 * 3600;
        setTimeout(function () { window.location.reload(); }, wait * 1000);
    })();
})();
