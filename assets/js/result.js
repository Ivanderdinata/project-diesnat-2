/* Halaman hasil: skor berhitung naik, konfeti, dan semburan piksel. */
(function () {
    'use strict';

    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---------------- Skor berhitung naik ---------------- */
    const scoreEl = document.querySelector('.big-score');
    if (scoreEl) {
        const target = Number(scoreEl.textContent.replace(/\D/g, '')) || 0;

        if (!reduce && target > 0) {
            const startedAt = performance.now();
            const duration = 1100;

            scoreEl.textContent = '0';

            const step = (now) => {
                const progress = Math.min(1, (now - startedAt) / duration);
                const eased = 1 - Math.pow(1 - progress, 3);
                scoreEl.textContent = Math.round(target * eased).toLocaleString('id-ID');
                if (progress < 1) requestAnimationFrame(step);
            };

            requestAnimationFrame(step);
        }
    }

    /* ---------------- Perayaan peringkat ---------------- */
    const rankChip = document.querySelector('.rank-chip b');
    if (!rankChip) return;

    const rank = Number(rankChip.textContent.replace(/\D/g, ''));
    if (!rank) return;

    if (rank <= 10) {
        setTimeout(() => {
            window.celebrate(rank <= 3 ? 120 : 60);

            if (window.pixelBurst) {
                const rect = rankChip.getBoundingClientRect();
                window.pixelBurst(
                    rect.left + rect.width / 2,
                    rect.top + rect.height / 2,
                    rank <= 3
                        ? ['#ffcb45', '#00fa64', '#dbfcff']
                        : ['#00f0ff', '#ff4a8d', '#ffcb45'],
                    rank <= 3 ? 26 : 14
                );
            }

            if (window.fxFlash && rank <= 3) {
                window.fxFlash('rgba(255, 203, 69, .14)');
            }
        }, 420);
    }
})();
