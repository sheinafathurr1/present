/*
 * Penangkap tap kartu dari pembaca RFID USB yang dicolok ke PC guru.
 *
 * Pembaca USB bekerja seperti keyboard: saat kartu ditempel, ia "mengetik"
 * nomor kartu lalu menekan Enter. Skrip ini menangkap ketikan tersebut di
 * halaman dashboard mana pun yang sedang dibuka, mengirimnya ke server, lalu
 * menampilkan hasilnya di jendela besar di tengah layar, disertai:
 *   - nada bunyi (berbeda untuk masuk, terlambat, pulang, dan gagal),
 *   - suara ucapan bahasa Indonesia,
 *   - pengumuman aria-live untuk pembaca layar.
 *
 * Saat guru sedang mengetik di kotak isian, penangkap dijeda agar tidak
 * mengganggu. Di halaman yang punya isian nomor kartu (form siswa), kartu yang
 * ditempel justru mengisi kotak tersebut.
 */
(function () {
    'use strict';

    var config = window.Presensi || {};
    var overlay = document.querySelector('[data-tap-overlay]');
    if (!overlay || !config.tapUrl) return;

    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.content : '';

    var el = {
        card: overlay.querySelector('[data-tap-card]'),
        headline: overlay.querySelector('[data-tap-headline]'),
        time: overlay.querySelector('[data-tap-time]'),
        name: overlay.querySelector('[data-tap-name]'),
        klass: overlay.querySelector('[data-tap-class]'),
        message: overlay.querySelector('[data-tap-message]'),
        photo: overlay.querySelector('[data-tap-photo]'),
        timer: overlay.querySelector('[data-tap-timer]'),
        close: overlay.querySelector('[data-tap-close]'),
        announcer: document.querySelector('[data-tap-announcer]'),
        statusLine: overlay.querySelector('#tap-status'),
        status: document.querySelector('[data-reader-status]'),
        statusLabel: document.querySelector('[data-reader-label]'),
        panelText: document.querySelector('[data-reader-text]'),
        sound: document.querySelector('[data-sound-toggle]')
    };

    /* Lama jendela hasil ditampilkan (ms) sesuai mode umpan balik siswa. */
    var DISPLAY_MS = { both: 5000, audio: 6500, visual: 9000, error: 7000 };

    var state = {
        buffer: '',
        lastKeyAt: 0,
        busy: false,
        queue: [],
        hideTimer: null,
        sessionExpired: false,
        sound: read('presensi:sound') !== 'off',
        audio: null,
        voice: null
    };

    function read(key) { try { return localStorage.getItem(key); } catch (e) { return null; } }
    function write(key, value) { try { localStorage.setItem(key, value); } catch (e) { /* abaikan */ } }

    /* ---------- Status pembaca di bilah atas ---------- */

    function registrationField() {
        return document.querySelector('[data-rfid-input]');
    }

    function isEditable(node) {
        if (!node || node === document.body) return false;
        if (node.isContentEditable) return true;
        var tag = node.tagName;
        if (tag === 'TEXTAREA' || tag === 'SELECT') return true;
        if (tag !== 'INPUT') return false;
        return !/^(button|submit|reset|checkbox|radio|file|image|range|color|hidden)$/i.test(node.type);
    }

    function setStatus(kind, label) {
        if (!el.status) return;
        el.status.dataset.state = kind;
        el.statusLabel.textContent = label;
        if (el.panelText) el.panelText.textContent = label;
    }

    function refreshStatus() {
        if (state.sessionExpired) {
            setStatus('error', 'Sesi berakhir — muat ulang halaman');
        } else if (registrationField()) {
            setStatus('register', document.activeElement === registrationField()
                ? 'Tempel kartu untuk mengisi nomor kartu'
                : 'Kartu yang ditempel akan mengisi nomor kartu');
        } else if (isEditable(document.activeElement)) {
            setStatus('paused', 'Pembaca dijeda — sedang mengetik');
        } else {
            setStatus('ready', 'Siap menerima kartu');
        }
    }

    document.addEventListener('focusin', refreshStatus);
    document.addEventListener('focusout', function () { setTimeout(refreshStatus, 0); });
    refreshStatus();

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
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(at);
            osc.stop(at + step[1] + 0.02);
            at += step[1] + 0.05;
        });
    }

    /* ---------- Suara: ucapan bahasa Indonesia ---------- */

    function pickVoice() {
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
        // Jeda singkat agar tidak bertumpuk dengan nada.
        setTimeout(function () { window.speechSynthesis.speak(utterance); }, 350);
    }

    /* ---------- Jendela hasil tap ---------- */

    function hideResult() {
        clearTimeout(state.hideTimer);
        overlay.hidden = true;
        delete overlay.dataset.tone;
        overlay.classList.remove('is-visual-emphasis');
    }

    /* Foto hanya tampil bila siswa punya foto; ikon status lalu jadi lencana di sudutnya. */
    function setPhoto(student) {
        el.photo.textContent = '';
        var hasPhoto = !!(student && student.photo_url);
        el.photo.parentNode.classList.toggle('has-photo', hasPhoto);
        if (!hasPhoto) return;
        var img = document.createElement('img');
        img.src = student.photo_url;
        img.alt = '';
        el.photo.appendChild(img);
    }

    /* Mulai ulang animasi jendela untuk setiap tap baru. */
    function replay(node) {
        node.style.animation = 'none';
        void node.offsetWidth;
        node.style.animation = '';
    }

    function showResult(data) {
        var student = data.student;
        var mode = student ? student.feedback_mode : 'both';
        var tone = data.tone || 'info';

        clearTimeout(state.hideTimer);
        overlay.dataset.tone = tone;
        overlay.classList.toggle('is-visual-emphasis', mode === 'visual');

        var duration = tone === 'error' ? DISPLAY_MS.error : (DISPLAY_MS[mode] || DISPLAY_MS.both);

        if (student) {
            el.name.textContent = student.name;
            el.klass.textContent = [student['class'], student.nis ? 'NIS ' + student.nis : null].filter(Boolean).join(' · ');
            el.headline.textContent = data.headline;
            el.time.textContent = data.time || '';
        } else {
            // Kartu tak dikenal / galat: judulnya langsung keterangan, tanpa baris status ganda.
            el.name.textContent = data.headline;
            el.klass.textContent = data.uid ? 'Nomor kartu ' + data.uid : '';
            el.headline.textContent = '';
            el.time.textContent = '';
        }
        el.statusLine.classList.toggle('is-hidden', !student);
        el.message.textContent = data.message;
        setPhoto(student);
        el.card.style.setProperty('--tap-duration', duration + 'ms');
        overlay.hidden = false;
        replay(el.card);
        replay(el.timer);

        // Pengumuman untuk pembaca layar (tanpa memindahkan fokus guru).
        el.announcer.textContent = '';
        setTimeout(function () { el.announcer.textContent = data.headline + '. ' + data.message; }, 50);

        playTone(tone);
        if (mode !== 'visual') speak(mode === 'audio' ? data.speech_detailed : data.speech, mode);

        state.hideTimer = setTimeout(hideResult, duration);
    }

    function showLocalError(headline, message) {
        showResult({ tone: 'error', headline: headline, message: message, speech: message, speech_detailed: message, student: null });
    }

    el.close.addEventListener('click', hideResult);
    overlay.addEventListener('click', function (event) {
        if (event.target === overlay) hideResult();
    });

    /* ---------- Kirim tap ke server ---------- */

    function submit(uid) {
        if (state.busy) {
            state.queue.push(uid);
            return;
        }
        state.busy = true;

        fetch(config.tapUrl, {
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
                if (response.status === 419 || response.status === 401) {
                    state.sessionExpired = true;
                    refreshStatus();
                    showLocalError('Sesi berakhir', 'Presensi belum tercatat. Halaman dimuat ulang, silakan tempel kartu lagi.');
                    setTimeout(function () { window.location.reload(); }, 3500);
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
                if (!data) return;
                showResult(data);
                document.dispatchEvent(new CustomEvent('presensi:tap', { detail: data }));
            })
            .catch(function () {
                showLocalError('Tidak terhubung', 'Presensi belum tercatat. Periksa koneksi ke server, lalu tempel kartu lagi.');
            })
            .then(function () {
                state.busy = false;
                if (state.queue.length) submit(state.queue.shift());
            });
    }

    /* Di form siswa: kartu yang ditempel mengisi kotak nomor kartu, bukan presensi. */
    function fillRegistrationField(field, uid) {
        field.value = uid;
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new CustomEvent('rfid:filled', { bubbles: true, detail: { uid: uid } }));
        playTone('info');
    }

    /* ---------- Tangkap ketikan dari pembaca USB ---------- */

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !overlay.hidden) {
            hideResult();
            return;
        }
        if (event.ctrlKey || event.altKey || event.metaKey) return;
        // Guru sedang mengetik di kotak isian: biarkan (kotak nomor kartu menangani sendiri).
        if (isEditable(event.target)) return;

        var now = Date.now();
        // Ketikan pembaca sangat cepat; jeda lama berarti awal kartu baru.
        if (now - state.lastKeyAt > 1500) state.buffer = '';
        state.lastKeyAt = now;

        if (event.key === 'Enter') {
            var uid = state.buffer;
            state.buffer = '';
            if (uid.length < 4) return;

            // Cegah Enter "mengklik" tombol/tautan yang kebetulan sedang terfokus.
            event.preventDefault();
            event.stopPropagation();

            var field = registrationField();
            if (field) fillRegistrationField(field, uid.toUpperCase());
            else submit(uid);
            return;
        }

        if (event.key.length === 1 && /[0-9a-z]/i.test(event.key)) {
            state.buffer = (state.buffer + event.key).slice(-64);
        }
    }, true);

    /* ---------- Tombol suara ---------- */

    function syncSoundButton() {
        if (el.sound) el.sound.setAttribute('aria-pressed', state.sound ? 'true' : 'false');
    }
    syncSoundButton();

    if (el.sound) {
        el.sound.addEventListener('click', function () {
            state.sound = !state.sound;
            write('presensi:sound', state.sound ? 'on' : 'off');
            syncSoundButton();
            if (state.sound) {
                playTone('info');
                speak('Suara aktif.', 'both');
            }
        });
    }

    /* ---------- Jaga sesi login tetap aktif selama halaman terbuka ---------- */

    if (config.heartbeatUrl) {
        setInterval(function () {
            fetch(config.heartbeatUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then(function (response) {
                    if (response.status === 401 || response.status === 419) {
                        state.sessionExpired = true;
                        refreshStatus();
                    }
                })
                .catch(function () { /* jaringan putus sementara: coba lagi nanti */ });
        }, 5 * 60 * 1000);
    }
})();
