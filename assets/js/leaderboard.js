/* Leaderboard: polling Top 10 tanpa reload + podium top 3 */
(function () {
    'use strict';

    const board = document.getElementById('board');
    if (!board) return;

    const podium = document.getElementById('podium');
    const POLL_MS = 7000;
    let running = true;
    let lastPayload = '';

    function rowsHtml(players) {
        return players.map((player) => `
            <tr class="rank-${player.rank <= 3 ? player.rank : 'n'}">
                <td><span class="rank-badge">${player.rank}</span></td>
                <td>
                    <div class="player-cell">
                        <span class="player-name">${window.esc(player.name)}</span>
                        <span class="player-kelas">${window.esc(player.kelas)}</span>
                    </div>
                </td>
                <td class="num col-time" style="color:var(--text-dim);font-size:.72rem">${window.formatTime(player.time_ms)}</td>
                <td class="col-score">${window.formatScore(player.score)}</td>
            </tr>
        `).join('');
    }

    function emptyHtml() {
        return `
            <div class="board-empty">
                <span class="emoji" aria-hidden="true">🎯</span>
                <p><strong>Belum ada pemain.</strong></p>
                <p>Jadilah yang pertama mencetak skor!</p>
            </div>`;
    }

    function podiumHtml(players) {
        const tags = { 1: '👑 1ST', 2: '2ND 🥈', 3: '3RD 🥉' };
        const icons = { 1: '👑', 2: '🎖️', 3: '🎖️' };

        return [1, 2, 3].map((place) => {
            const player = players[place - 1];
            return `
                <div class="podium-card podium-${place}">
                    <span class="podium-tag">${tags[place]}</span>
                    <span class="podium-avatar" aria-hidden="true">${icons[place]}</span>
                    <span class="podium-name">${player ? window.esc(player.name) : '—'}</span>
                    <span class="podium-kelas">${player ? window.esc(player.kelas) : 'Slot kosong'}</span>
                    <span class="podium-score">${player ? window.formatScore(player.score) + ' PTS' : '0 PTS'}</span>
                </div>`;
        }).join('');
    }

    function render(players) {
        if (podium) podium.innerHTML = podiumHtml(players);

        if (!players.length) {
            board.innerHTML = emptyHtml();
            return;
        }

        board.innerHTML = `
            <div class="table-wrap">
                <table class="board-table">
                    <thead>
                        <tr>
                            <th class="col-rank">#</th>
                            <th>Pemain</th>
                            <th class="col-time">Waktu</th>
                            <th class="col-score">Skor</th>
                        </tr>
                    </thead>
                    <tbody>${rowsHtml(players)}</tbody>
                </table>
            </div>`;
    }

    async function poll() {
        if (!running || document.hidden) return;

        try {
            const response = await fetch(window.APP_BASE + '/api/leaderboard.php', {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store',
            });
            if (!response.ok) return;

            const data = await response.json();
            if (!data.ok) return;

            const signature = JSON.stringify(data.players);
            if (signature === lastPayload) return;
            lastPayload = signature;

            render(data.players);

            // Kartu podium baru perlu dipasangi efek tilt lagi.
            if (window.fxBindTilt) window.fxBindTilt();

            // Kilau singkat agar terlihat papan baru saja diperbarui.
            board.classList.remove('fx-fresh');
            void board.offsetWidth;
            board.classList.add('fx-fresh');
        } catch (error) {
            /* Diamkan; coba lagi pada putaran berikutnya. */
        }
    }

    const indicator = document.getElementById('live-indicator');
    if (indicator) {
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) poll();
        });
    }

    setInterval(poll, POLL_MS);
    setTimeout(poll, 1500);

    window.addEventListener('beforeunload', () => { running = false; });
})();
