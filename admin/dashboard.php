<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/includes/game.php';

require_admin();

$totalQuestions = (int) $pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn();
$activeQuestions = (int) $pdo->query('SELECT COUNT(*) FROM questions WHERE is_active = 1')->fetchColumn();
$totalSessions = (int) $pdo->query('SELECT COUNT(*) FROM quiz_sessions WHERE finished_at IS NOT NULL')->fetchColumn();
$totalPlayers = (int) $pdo->query(
    "SELECT COUNT(DISTINCT CONCAT(p.name, '|', p.kelas))
       FROM quiz_sessions s JOIN players p ON p.id = s.player_id
      WHERE s.finished_at IS NOT NULL"
)->fetchColumn();
$averageScore = (float) $pdo->query(
    'SELECT COALESCE(AVG(total_score), 0) FROM quiz_sessions WHERE finished_at IS NOT NULL'
)->fetchColumn();
$topScore = (int) $pdo->query(
    'SELECT COALESCE(MAX(total_score), 0) FROM quiz_sessions WHERE finished_at IS NOT NULL'
)->fetchColumn();
$todaySessions = (int) $pdo->query(
    'SELECT COUNT(*) FROM quiz_sessions WHERE finished_at IS NOT NULL AND DATE(finished_at) = CURDATE()'
)->fetchColumn();

$leaderboard = leaderboard_rows($pdo, 5);

$stmt = $pdo->prepare(
    'SELECT s.id, s.total_score, s.correct_count, s.total_time_ms, s.finished_at, p.name, p.kelas
       FROM quiz_sessions s JOIN players p ON p.id = s.player_id
      WHERE s.finished_at IS NOT NULL
      ORDER BY s.finished_at DESC
      LIMIT 6'
);
$stmt->execute();
$recent = $stmt->fetchAll();

$adminPage  = 'dashboard';
$adminTitle = 'Dashboard';
require __DIR__ . '/includes/layout_head.php';
?>

<div class="page-head">
    <div>
        <h1>Halo, <?= e((string) ($_SESSION['admin_username'] ?? 'Admin')) ?> 👋</h1>
        <p>Ringkasan aktivitas kuis hari ini.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn btn-sm" href="<?= e(APP_URL) ?>/admin/question_form.php">+ Tambah Soal</a>
        <a class="btn btn-sm btn-outline" href="<?= e(APP_URL) ?>/admin/settings.php">Pengaturan</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="label">Soal aktif</div>
        <div class="value"><?= $activeQuestions ?> <span style="font-size:.9rem;color:var(--text-dim)">/ <?= $totalQuestions ?></span></div>
    </div>
    <div class="stat-card">
        <div class="label">Total pemain</div>
        <div class="value gold"><?= $totalPlayers ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Sesi selesai</div>
        <div class="value"><?= $totalSessions ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Rata-rata skor</div>
        <div class="value green"><?= number_format($averageScore, 0, ',', '.') ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Skor tertinggi</div>
        <div class="value gold"><?= number_format($topScore, 0, ',', '.') ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Main hari ini</div>
        <div class="value"><?= $todaySessions ?></div>
    </div>
</div>

<?php if ($activeQuestions === 0): ?>
    <div class="alert alert-info">
        <span aria-hidden="true">💡</span>
        <span>Belum ada soal aktif. Pemain tidak bisa memulai kuis sampai kamu menambahkan minimal satu soal.</span>
    </div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:22px">
    <div class="panel">
        <div class="panel-head">
            <h2>🏆 Top 5</h2>
            <a class="btn btn-xs btn-outline" href="<?= e(APP_URL) ?>/admin/results.php">Lihat semua</a>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>#</th><th>Pemain</th><th class="num">Skor</th></tr>
                </thead>
                <tbody>
                <?php if (!$leaderboard): ?>
                    <tr><td colspan="3" style="text-align:center;color:var(--text-dim);padding:26px">Belum ada data.</td></tr>
                <?php endif; ?>
                <?php foreach ($leaderboard as $i => $row): ?>
                    <tr>
                        <td class="num"><?= $i + 1 ?></td>
                        <td>
                            <strong><?= e($row['name']) ?></strong>
                            <div style="font-size:.78rem;color:var(--text-dim)"><?= e($row['kelas']) ?></div>
                        </td>
                        <td class="num" style="color:var(--gold);font-weight:700"><?= number_format((int) $row['total_score'], 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h2>🕒 Sesi terbaru</h2>
            <a class="btn btn-xs btn-outline" href="<?= e(APP_URL) ?>/admin/results.php">Kelola hasil</a>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Pemain</th><th class="num">Benar</th><th class="num">Skor</th><th>Waktu</th></tr>
                </thead>
                <tbody>
                <?php if (!$recent): ?>
                    <tr><td colspan="4" style="text-align:center;color:var(--text-dim);padding:26px">Belum ada sesi selesai.</td></tr>
                <?php endif; ?>
                <?php foreach ($recent as $row): ?>
                    <tr>
                        <td>
                            <strong><?= e($row['name']) ?></strong>
                            <div style="font-size:.78rem;color:var(--text-dim)"><?= e($row['kelas']) ?></div>
                        </td>
                        <td class="num"><?= (int) $row['correct_count'] ?></td>
                        <td class="num"><?= number_format((int) $row['total_score'], 0, ',', '.') ?></td>
                        <td style="font-size:.78rem;color:var(--text-dim);white-space:nowrap">
                            <?= e(date('d/m H:i', strtotime((string) $row['finished_at']))) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout_foot.php'; ?>
