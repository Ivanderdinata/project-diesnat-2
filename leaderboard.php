<?php
declare(strict_types=1);

/**
 * Leaderboard — halaman peringkat lengkap.
 * Menampilkan podium Top 3, tabel Top 10, dan statistik permainan.
 */

require_once __DIR__ . '/includes/functions.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/game.php';

$quizTitle = setting($pdo, 'quiz_title', 'Quiz Dies Nat');

$rows = leaderboard_rows($pdo, 10);

$totalPlayers = (int) $pdo->query(
    "SELECT COUNT(DISTINCT CONCAT(name, '|', kelas))
       FROM quiz_sessions s JOIN players p ON p.id = s.player_id
      WHERE s.finished_at IS NOT NULL"
)->fetchColumn();

$totalSessions = (int) $pdo->query(
    'SELECT COUNT(*) FROM quiz_sessions WHERE finished_at IS NOT NULL'
)->fetchColumn();

$avgScore = (int) $pdo->query(
    'SELECT COALESCE(ROUND(AVG(total_score)), 0) FROM quiz_sessions WHERE finished_at IS NOT NULL'
)->fetchColumn();

$topScore = $rows ? (int) $rows[0]['total_score'] : 0;

// Hitung medali peringkat
$podium = [];
foreach ([1, 2, 3] as $place) {
    $podium[$place] = $rows[$place - 1] ?? null;
}

$pageTitle = 'Hall of Fame — ' . $quizTitle;
$bodyClass = 'page-leaderboard';
require APP_ROOT . '/includes/header.php';
?>

<div class="lb-hero">
    <span class="lb-eyebrow">🏆 Papan Peringkat</span>
    <h1 class="lb-title">Hall of <span class="hl">Fame</span></h1>
    <p class="lb-sub">
        Satu pemain tampil sekali dengan skor terbaiknya. Jawab cepat dan tepat untuk menembus peringkat teratas.
    </p>

    <div class="lb-stats">
        <div class="lb-stat">
            <b><?= number_format($totalPlayers, 0, ',', '.') ?></b>
            <span>Pemain</span>
        </div>
        <div class="lb-stat">
            <b><?= number_format($totalSessions, 0, ',', '.') ?></b>
            <span>Sesi selesai</span>
        </div>
        <div class="lb-stat">
            <b><?= number_format($avgScore, 0, ',', '.') ?></b>
            <span>Rata-rata skor</span>
        </div>
        <div class="lb-stat">
            <b><?= number_format($topScore, 0, ',', '.') ?></b>
            <span>Skor tertinggi</span>
        </div>
    </div>
</div>

<?php if ($rows): ?>
    <div class="lb-podium">
        <?php
        $podiumMeta = [
            1 => ['label' => '👑 Juara', 'icon' => '👑'],
            2 => ['label' => '🥈 Runner-up', 'icon' => '🎖️'],
            3 => ['label' => '🥉 Ketiga', 'icon' => '🎖️'],
        ];
        foreach ([1, 2, 3] as $place):
            $row = $podium[$place];
        ?>
            <div class="lb-podium-card lb-podium-<?= $place ?>">
                <span class="lb-podium-tag"><?= $podiumMeta[$place]['label'] ?></span>
                <span class="lb-podium-avatar" aria-hidden="true"><?= $podiumMeta[$place]['icon'] ?></span>
                <?php if ($row): ?>
                    <span class="lb-podium-name"><?= e((string) $row['name']) ?></span>
                    <span class="lb-podium-kelas"><?= e((string) $row['kelas']) ?></span>
                    <span class="lb-podium-score"><?= number_format((int) $row['total_score'], 0, ',', '.') ?> PTS</span>
                    <span class="lb-podium-time">
                        ⏱ <?= number_format(((int) $row['total_time_ms']) / 1000, 1, ',', '.') ?>s
                    </span>
                <?php else: ?>
                    <span class="lb-podium-name">—</span>
                    <span class="lb-podium-kelas">Slot kosong</span>
                    <span class="lb-podium-score">0 PTS</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h2>Top 10</h2>
            <span class="badge badge-info"><?= count($rows) ?> pemain</span>
        </div>

        <div class="lb-table-wrap">
            <table class="lb-table">
                <thead>
                    <tr>
                        <th class="col-rank">#</th>
                        <th>Pemain</th>
                        <th class="col-kelas">Kelas</th>
                        <th class="col-time">Waktu</th>
                        <th class="col-score">Skor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $i => $row): ?>
                        <?php
                        $rank = $i + 1;
                        $rankClass = $rank <= 3 ? 'rank-' . $rank : 'rank-n';
                        ?>
                        <tr class="<?= $rankClass ?>">
                            <td>
                                <span class="lb-rank"><?= $rank ?></span>
                            </td>
                            <td class="lb-player"><?= e((string) $row['name']) ?></td>
                            <td class="lb-kelas"><?= e((string) $row['kelas']) ?></td>
                            <td class="lb-time"><?= number_format(((int) $row['total_time_ms']) / 1000, 1, ',', '.') ?>s</td>
                            <td class="lb-score"><?= number_format((int) $row['total_score'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="card lb-empty">
        <span class="lb-empty-emoji" aria-hidden="true">🎯</span>
        <h2>Belum ada pemain</h2>
        <p>Jadilah yang pertama mencetak skor dan puncaki papan peringkat!</p>
        <a class="btn btn-gold" href="<?= e(APP_URL) ?>/index.php?skip=1#main">
            <span aria-hidden="true">▶</span> Mulai Kuis
        </a>
    </div>
<?php endif; ?>

<div class="lb-actions">
    <a class="btn btn-gold" href="<?= e(APP_URL) ?>/index.php?skip=1#main">
        <span aria-hidden="true">🎮</span> Main Kuis
    </a>
    <a class="btn btn-ghost" href="<?= e(APP_URL) ?>/cafeteria.php">
        <span aria-hidden="true">🗺️</span> Kembali ke Cafeteria
    </a>
</div>

<?php
$pageScripts = ['leaderboard-page.js'];
require APP_ROOT . '/includes/footer.php';
?>
