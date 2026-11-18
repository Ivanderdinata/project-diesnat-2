<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/auth.php';

require_admin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $duration  = (int) ($_POST['question_duration'] ?? 0);
    $perSess   = (int) ($_POST['questions_per_session'] ?? 0);
    $title     = clean_text($_POST['quiz_title'] ?? '');
    $subtitle  = clean_text($_POST['quiz_subtitle'] ?? '');
    $newPass   = (string) ($_POST['new_password'] ?? '');
    $confirm   = (string) ($_POST['confirm_password'] ?? '');
    $changePwd = isset($_POST['change_password']);

    if ($duration < 3 || $duration > 120) {
        $errors['question_duration'] = 'Durasi harus antara 3 dan 120 detik.';
    }
    if ($perSess < 1 || $perSess > 100) {
        $errors['questions_per_session'] = 'Jumlah soal per sesi harus antara 1 dan 100.';
    }
    if ($title === '' || text_length($title) > 60) {
        $errors['quiz_title'] = 'Judul kuis wajib diisi, maksimal 60 karakter.';
    }
    if (text_length($subtitle) > 160) {
        $errors['quiz_subtitle'] = 'Subjudul maksimal 160 karakter.';
    }
    if ($changePwd) {
        if (text_length($newPass) < 8) {
            $errors['new_password'] = 'Password baru minimal 8 karakter.';
        }
        if ($newPass !== $confirm) {
            $errors['confirm_password'] = 'Konfirmasi password tidak cocok.';
        }
    }

    if (!$errors) {
        setting_set($pdo, 'question_duration', (string) $duration);
        setting_set($pdo, 'questions_per_session', (string) $perSess);
        setting_set($pdo, 'quiz_title', $title);
        setting_set($pdo, 'quiz_subtitle', $subtitle);

        if ($changePwd) {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                ->execute([$hash, (int) $_SESSION['admin_id']]);
            flash_set('success', 'Pengaturan dan password berhasil disimpan.');
        } else {
            flash_set('success', 'Pengaturan kuis berhasil disimpan.');
        }

        redirect(APP_URL . '/admin/settings.php');
    }
}

$current = settings_all($pdo);

$questionStats = $pdo->query(
    'SELECT COUNT(*) total, COALESCE(SUM(is_active = 1), 0) aktif FROM questions'
)->fetch();

$adminPage  = 'settings';
$adminTitle = 'Pengaturan Kuis';
require __DIR__ . '/includes/layout_head.php';
?>

<div class="page-head">
    <div>
        <h1>Pengaturan Kuis</h1>
        <p>Atur durasi, jumlah soal, dan identitas kuis.</p>
    </div>
</div>

<div class="alert alert-info">
    <span aria-hidden="true">ℹ️</span>
    <span>
        Soal aktif saat ini: <strong><?= (int) $questionStats['aktif'] ?></strong> dari <?= (int) $questionStats['total'] ?>.
        <?php if ((int) $questionStats['aktif'] < (int) ($current['questions_per_session'] ?? 10)): ?>
            Jumlah soal per sesi akan otomatis dibatasi sebanyak soal aktif yang tersedia.
        <?php endif; ?>
    </span>
</div>

<form method="post" novalidate>
    <?= csrf_field() ?>

    <div class="panel">
        <div class="panel-head"><h2>Bermain</h2></div>
        <div class="panel-body">
            <div class="form-grid">
                <div class="field">
                    <label for="question_duration">Durasi per soal (detik)</label>
                    <input class="input" type="number" id="question_duration" name="question_duration"
                           min="3" max="120" value="<?= (int) ($current['question_duration'] ?? 15) ?>" required>
                    <?php if (isset($errors['question_duration'])): ?>
                        <span class="field-error"><?= e($errors['question_duration']) ?></span>
                    <?php endif; ?>
                    <span class="hint">Rentang 3–120 detik. Standar: 15 detik.</span>
                </div>

                <div class="field">
                    <label for="questions_per_session">Jumlah soal per sesi</label>
                    <input class="input" type="number" id="questions_per_session" name="questions_per_session"
                           min="1" max="100" value="<?= (int) ($current['questions_per_session'] ?? 10) ?>" required>
                    <?php if (isset($errors['questions_per_session'])): ?>
                        <span class="field-error"><?= e($errors['questions_per_session']) ?></span>
                    <?php endif; ?>
                    <span class="hint">Soal dipilih acak dari seluruh soal aktif.</span>
                </div>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head"><h2>Identitas</h2></div>
        <div class="panel-body">
            <div class="form-grid">
                <div class="field full">
                    <label for="quiz_title">Judul kuis</label>
                    <input class="input" type="text" id="quiz_title" name="quiz_title" maxlength="60"
                           value="<?= e((string) ($current['quiz_title'] ?? '')) ?>" required>
                    <?php if (isset($errors['quiz_title'])): ?>
                        <span class="field-error"><?= e($errors['quiz_title']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="field full">
                    <label for="quiz_subtitle">Subjudul / slogan</label>
                    <input class="input" type="text" id="quiz_subtitle" name="quiz_subtitle" maxlength="160"
                           value="<?= e((string) ($current['quiz_subtitle'] ?? '')) ?>">
                    <?php if (isset($errors['quiz_subtitle'])): ?>
                        <span class="field-error"><?= e($errors['quiz_subtitle']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head"><h2>Keamanan Akun</h2></div>
        <div class="panel-body">
            <div class="field" style="display:flex;align-items:center;gap:10px">
                <input type="checkbox" id="change_password" name="change_password" value="1"
                       <?= isset($_POST['change_password']) ? 'checked' : '' ?>
                       onchange="document.getElementById('password-fields').hidden = !this.checked"
                       style="width:18px;height:18px">
                <label for="change_password" style="margin:0;text-transform:none;letter-spacing:0;font-size:.92rem;color:var(--text)">
                    Ganti password admin
                </label>
            </div>

            <div id="password-fields" <?= isset($_POST['change_password']) ? '' : 'hidden' ?>>
                <div class="form-grid">
                    <div class="field">
                        <label for="new_password">Password baru</label>
                        <input class="input" type="password" id="new_password" name="new_password"
                               autocomplete="new-password" minlength="8">
                        <?php if (isset($errors['new_password'])): ?>
                            <span class="field-error"><?= e($errors['new_password']) ?></span>
                        <?php endif; ?>
                        <span class="hint">Minimal 8 karakter. Gunakan kombinasi huruf dan angka.</span>
                    </div>

                    <div class="field">
                        <label for="confirm_password">Konfirmasi password</label>
                        <input class="input" type="password" id="confirm_password" name="confirm_password"
                               autocomplete="new-password" minlength="8">
                        <?php if (isset($errors['confirm_password'])): ?>
                            <span class="field-error"><?= e($errors['confirm_password']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <button class="btn" type="submit">Simpan Pengaturan</button>
</form>

<?php require __DIR__ . '/includes/layout_foot.php'; ?>
