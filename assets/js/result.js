/* Halaman hasil: rayakan bila masuk podium atau Top 10 */
(function () {
    'use strict';

    const rankChip = document.querySelector('.rank-chip b');
    if (!rankChip) return;

    const rank = Number(rankChip.textContent.replace(/\D/g, ''));
    if (!rank) return;

    if (rank <= 10) {
        setTimeout(() => {
            window.celebrate(rank <= 3 ? 120 : 60);
        }, 320);
    }
})();
