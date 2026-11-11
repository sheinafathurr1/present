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

        // Kartu ditempel saat kotak tidak sedang difokus (lihat card-reader.js).
        input.addEventListener('rfid:filled', function (event) {
            if (status) status.textContent = 'Kartu terbaca: ' + event.detail.uid;
            wrapper.classList.add('is-filled');
            setTimeout(function () { wrapper.classList.remove('is-filled'); }, 1500);
        });

        document.querySelectorAll('[data-fill-uid]').forEach(function (chip) {
            chip.addEventListener('click', function () {
                input.value = chip.dataset.fillUid;
                if (status) status.textContent = 'Kartu dipilih: ' + input.value;
                input.focus();
            });
        });
    });

    /* Usulkan mode umpan balik saat tap sesuai jenis kekhususan. */
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
        var missingList = document.querySelector('[data-missing]');
        var missingEmpty = document.querySelector('[data-missing-empty]');
        var missingMore = document.querySelector('[data-missing-more]');
        var seen = {};
        feed.querySelectorAll('[data-scan-id]').forEach(function (item) { seen[item.dataset.scanId] = true; });

        var make = function (tag, className, text) {
            var node = document.createElement(tag);
            if (className) node.className = className;
            if (text !== undefined) node.textContent = text;
            return node;
        };

        var renderScan = function (scan) {
            var li = make('li', 'feed__item');
            li.dataset.scanId = scan.id;
            var who = make('span', 'feed__who');
            who.appendChild(make('strong', '', scan.name));
            who.appendChild(make('span', '', [scan['class'], scan.source].filter(Boolean).join(' · ')));
            li.appendChild(make('span', 'feed__time', scan.time));
            li.appendChild(who);
            li.appendChild(make('span', 'badge badge--' + scan.tone, scan.result));
            return li;
        };

        var renderMissing = function (students, total) {
            if (!missingList) return;
            missingList.textContent = '';
            students.forEach(function (student) {
                var li = make('li');
                var link = make('a', '', student.name);
                link.href = student.url;
                li.appendChild(link);
                li.appendChild(make('span', 'muted', student['class'] || ''));
                missingList.appendChild(li);
            });
            missingList.hidden = students.length === 0;
            if (missingEmpty) missingEmpty.hidden = students.length > 0;
            if (missingMore) missingMore.hidden = total <= students.length;
        };

        var refresh = function () {
            fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (response) {
                    if (!response.ok) throw new Error(response.status);
                    return response.json();
                })
                .then(function (data) {
                    Object.keys(data.totals).forEach(function (key) {
                        document.querySelectorAll('[data-stat="' + key + '"]').forEach(function (node) {
                            node.textContent = data.totals[key];
                        });
                    });
                    document.querySelectorAll('[data-stat="percentage"]').forEach(function (node) { node.textContent = data.percentage; });
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

                    renderMissing(data.missing, data.totals.unrecorded);

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

        // Langsung perbarui setelah kartu ditempel di PC ini, dan berkala untuk tap dari PC lain.
        document.addEventListener('presensi:tap', refresh);
        setInterval(function () {
            if (!document.hidden) refresh();
        }, 15000);
    }

    /* ---------- Presensi harian: perbarui baris siswa setelah tap ---------- */

    document.addEventListener('presensi:tap', function (event) {
        var data = event.detail;
        if (!data.student || !data.attendance) return;
        var row = document.querySelector('[data-attendance-row="' + data.student.id + '"]');
        if (!row || row.dataset.date !== row.dataset.today) return;

        var att = data.attendance;
        var badge = row.querySelector('[data-cell="status"] .badge');
        if (badge) {
            badge.className = 'badge badge--' + att.status_tone;
            badge.textContent = '';
            var code = document.createElement('span');
            code.className = 'badge__code';
            code.setAttribute('aria-hidden', 'true');
            code.textContent = att.status_code;
            badge.appendChild(code);
            badge.appendChild(document.createTextNode(att.status_label));
        }
        var cellIn = row.querySelector('[data-cell="in"]');
        var cellOut = row.querySelector('[data-cell="out"]');
        if (cellIn) cellIn.textContent = att.check_in || '—';
        if (cellOut) cellOut.textContent = att.check_out || '—';
        var select = row.querySelector('select[name="status"]');
        if (select) select.value = att.status;
        row.classList.remove('is-updated');
        void row.offsetWidth; // mulai ulang animasi sorotan
        row.classList.add('is-updated');
    });
})();
