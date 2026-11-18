/* Hall of Fame — animasi angka & pembaruan otomatis papan peringkat */
(function () {
    'use strict';

    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---------------- Angka statistik berhitung naik ---------------- */
    if (!reduce) {
        document.querySelectorAll('.lb-stat b').forEach((el) => {
            const raw = el.textContent.trim();
            const match = raw.match(/^(\d[\d.,]*)(.*)$/);
            if (!match) return;

            const target = Number(match[1].replace(/[.,]/g, ''));
            if (!Number.isFinite(target) || target < 3) return;

            const suffix = match[2] || '';
            const startedAt = performance.now();
            const duration = 900;

            el.textContent = '0' + suffix;

            const tick = (now) => {
                const progress = Math.min(1, (now - startedAt) / duration);
                const eased = 1 - Math.pow(1 - progress, 3);
                el.textContent = Math.round(target * eased).toLocaleString('id-ID') + suffix;
                if (progress < 1) requestAnimationFrame(tick);
            };

            requestAnimationFrame(tick);
        });
    }

    /* ---------------- Sorot baris pemain sendiri ----------------
       Skor terakhir disimpan di localStorage oleh halaman kuis. */
    let lastEntry = null;
    try {
        lastEntry = JSON.parse(localStorage.getItem('quiz_last_entry') || 'null');
    } catch (err) {
        lastEntry = null;
    }

    if (lastEntry && lastEntry.name) {
        const needle = String(lastEntry.name).toLowerCase();
        const rows = document.querySelectorAll('.lb-table tbody tr');

        rows.forEach((row) => {
            const nameEl = row.querySelector('.lb-player');
            if (!nameEl) return;

            if (nameEl.textContent.trim().toLowerCase() === needle) {
                row.classList.add('lb-is-you');
                const label = document.createElement('span');
                label.className = 'lb-you-tag';
                label.textContent = 'KAMU';
                nameEl.appendChild(label);
            }
        });
    }
})();
