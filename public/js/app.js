/*
 * Presensi SLB — skrip dashboard (tanpa framework, tanpa build).
 */
(function () {
    'use strict';

    var root = document.documentElement;

    function store(key, value) {
        try {
            if (value === null) localStorage.removeItem(key); else localStorage.setItem(key, value);
        } catch (e) { /* mode privat: abaikan */ }
    }

    /* ---------- Ukuran teks & kontras tinggi ---------- */

    var SIZES = ['-1', '0', '1', '2', '3'];

    function currentSize() {
        return root.dataset.textSize || '0';
    }

    document.querySelectorAll('[data-text-size]').forEach(function (button) {
        button.addEventListener('click', function () {
            var index = SIZES.indexOf(currentSize());
            var action = button.dataset.textSize;
            var next = action === 'reset' ? '0'
                : SIZES[Math.max(0, Math.min(SIZES.length - 1, index + (action === 'up' ? 1 : -1)))];

            if (next === '0') {
                delete root.dataset.textSize;
                store('a11y:text-size', null);
            } else {
                root.dataset.textSize = next;
                store('a11y:text-size', next);
            }
        });
    });

    var contrastButton = document.querySelector('[data-contrast-toggle]');
    if (contrastButton) {
        var syncContrast = function () {
            contrastButton.setAttribute('aria-pressed', root.dataset.contrast === 'high' ? 'true' : 'false');
        };
        syncContrast();
        contrastButton.addEventListener('click', function () {
            if (root.dataset.contrast === 'high') {
                delete root.dataset.contrast;
                store('a11y:contrast', null);
            } else {
                root.dataset.contrast = 'high';
                store('a11y:contrast', 'high');
            }
            syncContrast();
        });
    }

    /* ---------- Menu samping (layar kecil) ---------- */

    var sidebar = document.getElementById('sidebar');
    var toggle = document.querySelector('[data-sidebar-toggle]');
    var backdrop = document.querySelector('[data-sidebar-backdrop]');

    function setSidebar(open) {
        if (!sidebar || !toggle) return;
        sidebar.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (backdrop) backdrop.hidden = !open;
        if (open) {
            var first = sidebar.querySelector('a, button');
            if (first) first.focus();
        }
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            setSidebar(!sidebar.classList.contains('is-open'));
        });
    }
    if (backdrop) backdrop.addEventListener('click', function () { setSidebar(false); });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && sidebar && sidebar.classList.contains('is-open')) {
            setSidebar(false);
            toggle.focus();
        }
    });

    /* ---------- Konfirmasi, cetak, salin ---------- */

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        });
    });

    document.querySelectorAll('[data-print]').forEach(function (button) {
        button.addEventListener('click', function () { window.print(); });
    });

    document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.querySelector(button.dataset.copy);
            if (!input) return;
            input.select();
            var done = function () { button.textContent = 'Tersalin ✓'; };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(input.value).then(done, function () {
                    document.execCommand('copy');
                    done();
                });
            } else {
                document.execCommand('copy');
                done();
            }
        });
    });

    var summary = document.querySelector('[data-autofocus]');
    if (summary) summary.focus();

    /* ---------- Isian nomor kartu (pembaca USB = keyboard) ---------- */

    document.querySelectorAll('[data-rfid-capture]').forEach(function (wrapper) {
        var input = wrapper.querySelector('[data-rfid-input]');
        var status = wrapper.querySelector('[data-rfid-status]');
        if (!input) return;

        input.addEventListener('focus', function () {
            wrapper.classList.add('is-listening');
            if (status && !input.value) status.textContent = 'Siap — silakan tempelkan kartu ke pembaca.';
        });
        input.addEventListener('blur', function () {
            wrapper.classList.remove('is-listening');
        });
        // Pembaca USB mengetik nomor lalu menekan Enter. Jangan sampai formulir terkirim.
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                input.value = input.value.trim().toUpperCase().replace(/[^A-Z0-9]/g, '');
                if (status) status.textContent = input.value ? 'Kartu terbaca: ' + input.value : '';
            }
        });

        document.querySelectorAll('[data-fill-uid]').forEach(function (chip) {
            chip.addEventListener('click', function () {
                input.value = chip.dataset.fillUid;
                if (status) status.textContent = 'Kartu dipilih: ' + input.value;
                input.focus();
            });
        });
    });

    /* Usulkan mode umpan balik kiosk sesuai jenis kekhususan. */
    var disability = document.querySelector('[data-disability-select]');
    var feedback = document.querySelector('[data-feedback-select]');
    if (disability && feedback) {
        var touched = false;
        feedback.addEventListener('change', function () { touched = true; });
        disability.addEventListener('change', function () {
            if (touched) return;
            var suggestion = { A: 'audio', B: 'visual' }[disability.value] || 'both';
            feedback.value = suggestion;
        });
    }

    /* ---------- Dashboard yang memperbarui diri ---------- */

    var live = document.getElementById('live-dashboard');
    if (live && window.fetch) {
        var url = live.dataset.liveUrl;
        var feed = live.querySelector('[data-feed]');
        var indicator = live.querySelector('[data-live-status]');
        var seen = {};
        feed.querySelectorAll('[data-scan-id]').forEach(function (item) { seen[item.dataset.scanId] = true; });

        var renderScan = function (scan) {
            var li = document.createElement('li');
            li.className = 'feed__item';
            li.dataset.scanId = scan.id;

            var time = document.createElement('span');
            time.className = 'feed__time';
            time.textContent = scan.time;

            var who = document.createElement('span');
            who.className = 'feed__who';
            var name = document.createElement('strong');
            name.textContent = scan.name;
            var meta = document.createElement('span');
            meta.textContent = [scan['class'], scan.device].filter(Boolean).join(' · ');
            who.appendChild(name);
            who.appendChild(meta);

            var badge = document.createElement('span');
            badge.className = 'badge badge--' + scan.tone;
            badge.textContent = scan.result;

            li.appendChild(time);
            li.appendChild(who);
            li.appendChild(badge);
            return li;
        };

        var refresh = function () {
            fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (response) {
                    if (!response.ok) throw new Error(response.status);
                    return response.json();
                })
                .then(function (data) {
                    Object.keys(data.totals).forEach(function (key) {
                        live.querySelectorAll('[data-stat="' + key + '"]').forEach(function (el) {
                            el.textContent = data.totals[key];
                        });
                    });
                    live.querySelectorAll('[data-stat="percentage"]').forEach(function (el) { el.textContent = data.percentage; });
                    var meter = live.querySelector('[data-meter]');
                    if (meter) meter.style.width = data.percentage + '%';

                    var fresh = data.scans.filter(function (scan) { return !seen[scan.id]; });
                    if (fresh.length) {
                        var empty = feed.querySelector('[data-feed-empty]');
                        if (empty) empty.remove();
                        fresh.reverse().forEach(function (scan) {
                            seen[scan.id] = true;
                            var item = renderScan(scan);
                            item.classList.add('is-new');
                            feed.insertBefore(item, feed.firstChild);
                        });
                        while (feed.children.length > 8) feed.removeChild(feed.lastChild);
                    }
                    if (indicator) {
                        indicator.classList.remove('is-error');
                        indicator.lastChild.textContent = ' Diperbarui otomatis';
                    }
                })
                .catch(function () {
                    if (indicator) {
                        indicator.classList.add('is-error');
                        indicator.lastChild.textContent = ' Gagal memperbarui';
                    }
                });
        };

        setInterval(function () {
            if (!document.hidden) refresh();
        }, 10000);
    }
})();
