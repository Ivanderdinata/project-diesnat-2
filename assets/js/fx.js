/* FX — interaksi ringan: reveal, ripple, partikel piksel, parallax bintang.
   Tidak bergantung pada library apa pun. */
(function () {
    'use strict';

    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const finePointer = window.matchMedia('(pointer: fine)').matches;

    /* ---------------- Reveal saat masuk viewport ---------------- */
    if (!reduce && 'IntersectionObserver' in window) {
        const targets = document.querySelectorAll(
            '.section-head, .board, .podium, .panel, .result-card, .auth-card, ' +
            '.admin-main > .page-head, .admin-main .stats-grid, .admin-main .panel'
        );

        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-in');
                io.unobserve(entry.target);
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

        targets.forEach((el) => {
            if (el.closest('.hero')) return;
            el.classList.add('fx-reveal');
            io.observe(el);
        });

        // Jaring pengaman: apa pun yang terjadi, konten tidak boleh
        // tertinggal tak terlihat karena animasi reveal.
        window.setTimeout(() => {
            document.querySelectorAll('.fx-reveal:not(.is-in)').forEach((el) => {
                el.classList.add('is-in');
            });
        }, 1600);
    }

    /* ---------------- Scroll progress ---------------- */
    if (!reduce) {
        const bar = document.createElement('div');
        bar.className = 'fx-scrollbar';
        bar.innerHTML = '<span></span>';
        document.body.appendChild(bar);

        const fill = bar.firstElementChild;
        let queued = false;

        const paint = () => {
            const doc = document.documentElement;
            const max = doc.scrollHeight - window.innerHeight;
            const pct = max > 0 ? Math.min(100, (window.scrollY / max) * 100) : 0;
            fill.style.width = pct.toFixed(2) + '%';
            queued = false;
        };

        window.addEventListener('scroll', () => {
            if (queued) return;
            queued = true;
            requestAnimationFrame(paint);
        }, { passive: true });

        paint();
    }

    /* ---------------- Ripple saat klik ---------------- */
    if (!reduce) {
        document.addEventListener('pointerdown', (event) => {
            const target = event.target.closest('.btn, .answer, .nav-link, .icon-btn');
            if (!target) return;

            const rect = target.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height) * 1.6;
            const ripple = document.createElement('span');
            ripple.className = 'fx-ripple';
            ripple.style.width = size + 'px';
            ripple.style.height = size + 'px';
            ripple.style.left = (event.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (event.clientY - rect.top - size / 2) + 'px';
            target.appendChild(ripple);
            ripple.addEventListener('animationend', () => ripple.remove());
        }, { passive: true });
    }

    /* ---------------- Partikel piksel ---------------- */
    const PALETTE = ['#00f0ff', '#ff4a8d', '#00fa64', '#ffcb45', '#dbfcff'];

    window.pixelBurst = function (x, y, colors, count) {
        if (reduce) return;
        const palette = Array.isArray(colors) && colors.length ? colors : PALETTE;
        const total = count || 16;

        for (let i = 0; i < total; i++) {
            const particle = document.createElement('span');
            const angle = (Math.PI * 2 * i) / total + Math.random() * 0.5;
            const distance = 40 + Math.random() * 70;

            particle.className = 'fx-pixel';
            particle.style.left = x + 'px';
            particle.style.top = y + 'px';
            particle.style.background = palette[Math.floor(Math.random() * palette.length)];
            particle.style.setProperty('--fx-dx', Math.cos(angle) * distance + 'px');
            particle.style.setProperty('--fx-dy', Math.sin(angle) * distance + 'px');

            document.body.appendChild(particle);
            particle.addEventListener('animationend', () => particle.remove());
        }
    };

    /* ---------------- Kilat layar ---------------- */
    window.fxFlash = function (color) {
        if (reduce) return;
        const flash = document.createElement('div');
        flash.className = 'fx-flash';
        flash.style.background = color || 'rgba(0, 250, 100, .16)';
        document.body.appendChild(flash);
        flash.addEventListener('animationend', () => flash.remove());
    };

    /* ---------------- Jejak kursor ---------------- */
    if (!reduce && finePointer) {
        let lastTrail = 0;

        document.addEventListener('pointermove', (event) => {
            const now = performance.now();
            if (now - lastTrail < 45) return;
            lastTrail = now;

            const dot = document.createElement('span');
            dot.className = 'fx-trail';
            dot.style.left = (event.clientX - 2) + 'px';
            dot.style.top = (event.clientY - 2) + 'px';
            dot.style.background = Math.random() > 0.5 ? 'var(--cyan)' : 'var(--pink)';
            document.body.appendChild(dot);
            dot.addEventListener('animationend', () => dot.remove());
        }, { passive: true });
    }

    /* ---------------- Parallax bintang latar ---------------- */
    if (!reduce && finePointer) {
        const stars = document.querySelector('.px-stars');
        if (stars) {
            let frame = 0;
            let targetX = 0;
            let targetY = 0;

            document.addEventListener('pointermove', (event) => {
                targetX = (event.clientX / window.innerWidth - 0.5) * 16;
                targetY = (event.clientY / window.innerHeight - 0.5) * 16;
                if (frame) return;

                frame = requestAnimationFrame(() => {
                    stars.style.transform = 'translate3d(' + (-targetX) + 'px,' + (-targetY) + 'px,0)';
                    frame = 0;
                });
            }, { passive: true });
        }
    }

    /* ---------------- Easter egg: klik logo ---------------- */
    document.addEventListener('click', (event) => {
        const brand = event.target.closest('.brand-mark');
        if (!brand) return;
        const rect = brand.getBoundingClientRect();
        window.pixelBurst(rect.left + rect.width / 2, rect.top + rect.height / 2, null, 12);
    });

    /* ---------------- Semburan piksel saat tombol diklik ---------------- */
    document.addEventListener('click', (event) => {
        const btn = event.target.closest('.btn');
        if (!btn || !window.pixelBurst) return;
        const rect = btn.getBoundingClientRect();
        window.pixelBurst(
            event.clientX || rect.left + rect.width / 2,
            event.clientY || rect.top + rect.height / 2,
            null,
            10
        );
    });

    /* ---------------- Angka statistik berhitung naik ----------------
       Hanya angka bulat di beranda (mis. "10" / "15s"). Nilai berdesimal
       atau berformat ribuan sengaja dilewati agar tidak berubah arti. */
    if (!reduce) {
        document.querySelectorAll('.page-home .stat-chip b').forEach((el) => {
            const raw = el.textContent.trim();
            const match = raw.match(/^(\d+)(.*)$/);
            if (!match) return;

            const target = Number(match[1]);
            if (!Number.isFinite(target) || target < 3) return;

            const suffix = match[2] || '';
            const startedAt = performance.now();
            const duration = 900;

            el.textContent = '0' + suffix;

            const step = (now) => {
                const progress = Math.min(1, (now - startedAt) / duration);
                const eased = 1 - Math.pow(1 - progress, 3);
                el.textContent = Math.round(target * eased).toLocaleString('id-ID') + suffix;
                if (progress < 1) requestAnimationFrame(step);
            };

            requestAnimationFrame(step);
        });
    }

    /* ---------------- Tilt ringan pada kartu podium ----------------
       Diekspos sebagai fungsi agar bisa dipanggil ulang setiap kali
       leaderboard merender ulang kartu podium. */
    window.fxBindTilt = function () {
        if (reduce || !finePointer) return;

        document.querySelectorAll('.podium-card:not([data-fx-tilt])').forEach((card) => {
            card.dataset.fxTilt = '1';

            card.addEventListener('pointermove', (event) => {
                const rect = card.getBoundingClientRect();
                const px = (event.clientX - rect.left) / rect.width - 0.5;
                const py = (event.clientY - rect.top) / rect.height - 0.5;
                card.style.transform =
                    'perspective(700px) rotateX(' + (-py * 6).toFixed(2) + 'deg) ' +
                    'rotateY(' + (px * 6).toFixed(2) + 'deg)';
            });

            card.addEventListener('pointerleave', () => {
                card.style.transform = '';
            });
        });
    };

    window.fxBindTilt();
})();
