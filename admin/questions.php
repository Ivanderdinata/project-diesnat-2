<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/auth.php';

require_admin();

$search = clean_text($_GET['q'] ?? '');
$status = (string) ($_GET['status'] ?? 'all');
$page   = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$where  = [];
$params = [];

if ($search !== '') {
    $where[]  = '(question LIKE ? OR option_a LIKE ? OR option_b LIKE ? OR option_c LIKE ? OR option_d LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [$like, $like, $like, $like, $like]);
}

if ($status === 'active') {
    $where[] = 'is_active = 1';
} elseif ($status === 'inactive') {
    $where[] = 'is_active = 0';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM questions $whereSql");
$stmt->execute($params);
$totalRows  = (int) $stmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT * FROM questions $whereSql ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$questions = $stmt->fetchAll();

// Jumlah jawaban tercatat per soal (untuk peringatan sebelum hapus).
$answeredCounts = [];
if ($questions) {
    $ids = array_map(fn($q) => (int) $q['id'], $questions);
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT question_id, COUNT(*) c FROM session_answers WHERE question_id IN ($in) GROUP BY question_id");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $answeredCounts[(int) $row['question_id']] = (int) $row['c'];
    }
}

$adminPage  = 'questions';
$adminTitle = 'Manajemen Soal';
require __DIR__ . '/includes/layout_head.php';

$queryBase = function (array $overrides = []) use ($search, $status, $page): string {
    $params = array_filter(array_merge([
        'q'      => $search,
        'status' => $status !== 'all' ? $status : null,
        'page'   => $page,
    ], $overrides), fn($v) => $v !== null && $v !== '');
    return $params ? '?' . http_build_query($params) : '';
};
?>

<div class="page-head">
    <div>
        <h1>Manajemen Soal</h1>
        <p><?= $totalRows ?> soal ditemukan<?= $search !== '' ? ' untuk "' . e($search) . '"' : '' ?>.</p>
    </div>
    <a class="btn btn-sm" href="<?= e(APP_URL) ?>/admin/question_form.php">+ Tambah Soal</a>
</div>

<form class="toolbar" method="get" action="<?= e(APP_URL) ?>/admin/questions.php">
    <label class="sr-only" for="q">Cari soal</label>
    <input class="input" type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Cari teks soal atau opsi…">
    <label class="sr-only" for="status">Status</label>
    <select class="input" id="status" name="status" style="max-width:170px" onchange="this.form.submit()">
        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Semua status</option>
        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Aktif</option>
        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
    </select>
    <button class="btn btn-sm btn-outline" type="submit">Cari</button>
    <?php if ($search !== '' || $status !== 'all'): ?>
        <a class="btn btn-sm btn-outline" href="<?= e(APP_URL) ?>/admin/questions.php">Reset</a>
    <?php endif; ?>
</form>

<div class="panel">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:60px">ID</th>
                    <th>Soal</th>
                    <th style="width:90px">Kunci</th>
                    <th style="width:110px">Status</th>
                    <th style="width:110px">Dijawab</th>
                    <th style="width:150px" class="actions">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$questions): ?>
                <tr>
                    <td colspan="6" style="text-align:center;padding:36px;color:var(--text-dim)">
                        Tidak ada soal. <a href="<?= e(APP_URL) ?>/admin/question_form.php">Tambah soal baru</a>.
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($questions as $q): ?>
                <?php $answered = $answeredCounts[(int) $q['id']] ?? 0; ?>
                <tr>
                    <td class="num" style="color:var(--text-dim)">#<?= (int) $q['id'] ?></td>
                    <td>
                        <div class="q-preview"><?= e((string) $q['question']) ?></div>
                        <div style="font-size:.76rem;color:var(--text-dim);margin-top:3px">
                            <?= e(mb_substr((string) $q['option_a'], 0, 26)) ?>
                            · <?= e(mb_substr((string) $q['option_b'], 0, 26)) ?>
                            · <?= e(mb_substr((string) $q['option_c'], 0, 26)) ?>
                            · <?= e(mb_substr((string) $q['option_d'], 0, 26)) ?>
                        </div>
                    </td>
                    <td><span class="badge badge-key"><?= e((string) $q['correct_answer']) ?></span></td>
                    <td>
                        <?php if ((int) $q['is_active'] === 1): ?>
                            <span class="badge badge-ok">Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-off">Nonaktif</span>
                        <?php endif; ?>
                    </td>
                    <td class="num" style="color:var(--text-dim)"><?= $answered ?>×</td>
                    <td class="actions">
                        <a class="btn btn-xs btn-outline" href="<?= e(APP_URL) ?>/admin/question_form.php?id=<?= (int) $q['id'] ?>">Edit</a>

                        <form method="post" action="<?= e(APP_URL) ?>/admin/question_delete.php" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
                            <input type="hidden" name="action" value="<?= (int) $q['is_active'] === 1 ? 'deactivate' : 'activate' ?>">
                            <button class="btn btn-xs btn-outline" type="submit"
                                    title="<?= (int) $q['is_active'] === 1 ? 'Nonaktifkan soal' : 'Aktifkan soal' ?>">
                                <?= (int) $q['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>
                            </button>
                        </form>

                        <?php if ($answered > 0): ?>
                            <button class="btn btn-xs btn-outline" type="button" disabled
                                    title="Soal sudah punya <?= $answered ?> jawaban tercatat. Nonaktifkan saja agar riwayat tetap utuh.">Hapus</button>
                        <?php else: ?>
                            <form method="post" action="<?= e(APP_URL) ?>/admin/question_delete.php" style="display:inline"
                                  onsubmit="return confirm('Hapus soal ini secara permanen?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button class="btn btn-xs btn-danger" type="submit">Hapus</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
    <nav class="pagination" aria-label="Pagination">
        <a class="<?= $page <= 1 ? 'disabled' : '' ?>" href="<?= e(APP_URL) ?>/admin/questions.php<?= e($queryBase(['page' => max(1, $page - 1)])) ?>" aria-label="Halaman sebelumnya">‹</a>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i === 1 || $i === $totalPages || abs($i - $page) <= 2): ?>
                <?php if ($i === $page): ?>
                    <span class="current" aria-current="page"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= e(APP_URL) ?>/admin/questions.php<?= e($queryBase(['page' => $i])) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php elseif (abs($i - $page) === 3): ?>
                <span class="disabled">…</span>
            <?php endif; ?>
        <?php endfor; ?>

        <a class="<?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= e(APP_URL) ?>/admin/questions.php<?= e($queryBase(['page' => min($totalPages, $page + 1)])) ?>" aria-label="Halaman berikutnya">›</a>
    </nav>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_foot.php'; ?>
