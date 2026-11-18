<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/auth.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/admin/questions.php');
}

csrf_require();

$id     = (int) ($_POST['id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');

if ($id <= 0) {
    flash_set('error', 'Soal tidak valid.');
    redirect(APP_URL . '/admin/questions.php');
}

$stmt = $pdo->prepare('SELECT id FROM questions WHERE id = ? LIMIT 1');
$stmt->execute([$id]);

if (!$stmt->fetchColumn()) {
    flash_set('error', 'Soal tidak ditemukan.');
    redirect(APP_URL . '/admin/questions.php');
}

switch ($action) {
    case 'activate':
        $pdo->prepare('UPDATE questions SET is_active = 1 WHERE id = ?')->execute([$id]);
        flash_set('success', 'Soal diaktifkan.');
        break;

    case 'deactivate':
        $pdo->prepare('UPDATE questions SET is_active = 0 WHERE id = ?')->execute([$id]);
        flash_set('success', 'Soal dinonaktifkan.');
        break;

    case 'delete':
        // Jangan hapus soal yang sudah punya riwayat jawaban.
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM session_answers WHERE question_id = ?');
        $stmt->execute([$id]);

        if ((int) $stmt->fetchColumn() > 0) {
            $pdo->prepare('UPDATE questions SET is_active = 0 WHERE id = ?')->execute([$id]);
            flash_set('info', 'Soal sudah punya riwayat jawaban, jadi hanya dinonaktifkan.');
        } else {
            $pdo->prepare('DELETE FROM questions WHERE id = ?')->execute([$id]);
            flash_set('success', 'Soal dihapus permanen.');
        }
        break;

    default:
        flash_set('error', 'Aksi tidak dikenal.');
}

redirect(APP_URL . '/admin/questions.php');
