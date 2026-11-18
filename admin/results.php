<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/auth.php';

require_admin();

// ----- Aksi: hapus entri / reset leaderboard -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete_session') {
        $sessionId = (int) ($_POST['session_id'] ?? 0);
        if ($sessionId > 0) {
            $pdo->prepare('DELETE FROM quiz_sessions WHERE id = ?')->execute([$sessionId]);
            flash_set('success', 'Entri hasil dihapus.');
        }
    } elseif ($action === 'reset_all') {
        $pdo->exec('DELETE FROM quiz_sessions');
        $pdo->exec('DELETE FROM players');
        flash_set('success', 'Semua hasil bermain dan leaderboard telah direset.');
    } elseif ($action === 'delete_player') {
        $name  = clean_text($_POST['name'] ?? '');
        $kelas = clean_text($_POST['kelas'] ?? '');
        if ($name !== '' && $kelas !== '') {
            $stmt = $pdo->prepare(
                'DELETE s FROM quiz_sessions s
                   JOIN players p ON p.id = s.player_id
                  WHERE p.name = ? AND p.kelas = ?'
            );
            $stmt->execute([$name, $kelas]);
            flash_set('success', 'Semua entri milik pemain tersebut dihapus.');
        }
    }

    redirect(APP_URL . '/admin/results.php');
}

// ----- Filter & pagination -----
$search = clean_text($_GET['q'] ?? '');
$page   = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;

$where  = ['s.finished_at IS NOT NULL'];
$params = [];

if ($search !== '') {
    $where[]  = '(p.name LIKE ? OR p.kelas LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_sessions s JOIN players p ON p.id = s.player_id $whereSql");
$stmt->execute($params);
$totalRows  = (int) $stmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT s.*, p.name, p.kelas
       FROM quiz_sessions s JOIN players p ON p.id = s.player_id
       $whereSql
       ORDER BY s.finished_at DESC
       LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$sessions = $stmt->fetchAll();

$adminPage  = 'results';
$adminTitle = 'Hasil Bermain';
require __DIR__ . '/includes/layout_head.php';
?>

<div class="page-head">
    <div>
        <h1>Hasil Bermain</h1>
        <p><?= $totalRows ?> sesi selesai<?= $search !== '' ? ' untuk "' . e($search) . '"' : '' ?>.</p>
    </div>
    <form method="post" onsubmit="return confirm('Hapus SEMUA hasil bermain dan leaderboard? Tindakan ini tidak bisa dibatalkan.')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reset_all">
        <button class="btn btn-sm btn-danger" type="submit">Reset Leaderboard</button>
    </form>
</div>

<form class="toolbar" method="get" action="<?= e(APP_URL) ?>/admin/results.php">
    <label class="sr-only" for="q">Cari pemain</label>
    <input class="input" type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Cari nama atau kelas…">
    <button class="btn btn-sm btn-outline" type="submit">Cari</button>
    <?php if ($search !== ''): ?>
        <a class="btn btn-sm btn-outline" href="<?= e(APP_URL) ?>/admin/results.php">Reset</a>
    <?php endif; ?>
</form>

<div class="panel">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Pemain</th>
                    <th class="num">Benar</th>
                    <th class="num">Skor</th>
                    <th class="num">Waktu</th>
                    <th>Selesai</th>
                    <th class="actions">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$sessions): ?>
                <tr><td colspan="6" style="text-align:center;padding:36px;color:var(--text-dim)">Belum ada hasil bermain.</td></tr>
            <?php endif; ?>

            <?php foreach ($sessions as $row): ?>
                <tr>
                    <td>
                        <strong><?= e((string) $row['name']) ?></strong>
                        <div style="font-size:.78rem;color:var(--text-dim)"><?= e((string) $row['kelas']) ?></div>
                    </td>
                    <td class="num"><?= (int) $row['correct_count'] ?></td>
                    <td class="num" style="color:var(--gold);font-weight:700"><?= number_format((int) $row['total_score'], 0, ',', '.') ?></td>
                    <td class="num" style="color:var(--text-dim)"><?= number_format(((int) $row['total_time_ms']) / 1000, 1, ',', '.') ?>s</td>
                    <td style="font-size:.8rem;color:var(--text-dim);white-space:nowrap">
                        <?= e(date('d/m/Y H:i', strtotime((string) $row['finished_at']))) ?>
                    </td>
                    <td class="actions">
                        <form method="post" style="display:inline" onsubmit="return confirm('Hapus entri hasil ini?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_session">
                            <input type="hidden" name="session_id" value="<?= (int) $row['id'] ?>">
                            <button class="btn btn-xs btn-danger" type="submit">Hapus</button>
                        </form>
                        <form method="post" style="display:inline"
                              onsubmit="return confirm('Hapus SEMUA entri milik <?= e(addslashes((string) $row['name'])) ?> (<?= e(addslashes((string) $row['kelas'])) ?>)?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_player">
                            <input type="hidden" name="name" value="<?= e((string) $row['name']) ?>">
                            <input type="hidden" name="kelas" value="<?= e((string) $row['kelas']) ?>">
                            <button class="btn btn-xs btn-outline" type="submit">Hapus semua</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
    <nav class="pagination" aria-label="Pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i === $page): ?>
                <span class="current" aria-current="page"><?= $i ?></span>
            <?php else: ?>
                <a href="<?= e(APP_URL) ?>/admin/results.php?<?= e(http_build_query(array_filter(['q' => $search, 'page' => $i]))) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </nav>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_foot.php'; ?>
