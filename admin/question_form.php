<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/auth.php';

require_admin();

const OPTION_LETTERS = ['A', 'B', 'C', 'D'];

$id       = (int) ($_GET['id'] ?? 0);
$isEdit   = $id > 0;
$errors   = [];
$question = [
    'question'       => '',
    'option_a'       => '',
    'option_b'       => '',
    'option_c'       => '',
    'option_d'       => '',
    'correct_answer' => 'A',
    'explanation'    => '',
    'is_active'      => 1,
];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM questions WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $found = $stmt->fetch();

    if (!$found) {
        flash_set('error', 'Soal tidak ditemukan.');
        redirect(APP_URL . '/admin/questions.php');
    }
    $question = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $question['question']       = clean_text($_POST['question'] ?? '');
    $question['option_a']       = clean_text($_POST['option_a'] ?? '');
    $question['option_b']       = clean_text($_POST['option_b'] ?? '');
    $question['option_c']       = clean_text($_POST['option_c'] ?? '');
    $question['option_d']       = clean_text($_POST['option_d'] ?? '');
    $question['correct_answer'] = strtoupper(trim((string) ($_POST['correct_answer'] ?? '')));
    $question['explanation']    = clean_text($_POST['explanation'] ?? '');
    $question['is_active']      = isset($_POST['is_active']) ? 1 : 0;

    // ----- Validasi di server -----
    if ($question['question'] === '') {
        $errors['question'] = 'Teks soal wajib diisi.';
    } elseif (text_length($question['question']) > 2000) {
        $errors['question'] = 'Teks soal maksimal 2000 karakter.';
    }

    foreach (['a', 'b', 'c', 'd'] as $letter) {
        $key = 'option_' . $letter;
        if ($question[$key] === '') {
            $errors[$key] = 'Opsi ' . strtoupper($letter) . ' wajib diisi.';
        } elseif (text_length($question[$key]) > 255) {
            $errors[$key] = 'Opsi ' . strtoupper($letter) . ' maksimal 255 karakter.';
        }
    }

    if (!in_array($question['correct_answer'], OPTION_LETTERS, true)) {
        $errors['correct_answer'] = 'Kunci jawaban harus salah satu dari A, B, C, atau D.';
    }

    if (text_length($question['explanation']) > 2000) {
        $errors['explanation'] = 'Pembahasan maksimal 2000 karakter.';
    }

    if (!$errors) {
        $explanation = $question['explanation'] !== '' ? $question['explanation'] : null;

        if ($isEdit) {
            $stmt = $pdo->prepare(
                'UPDATE questions
                    SET question = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?,
                        correct_answer = ?, explanation = ?, is_active = ?
                  WHERE id = ?'
            );
            $stmt->execute([
                $question['question'],
                $question['option_a'],
                $question['option_b'],
                $question['option_c'],
                $question['option_d'],
                $question['correct_answer'],
                $explanation,
                (int) $question['is_active'],
                $id,
            ]);
            flash_set('success', 'Soal berhasil diperbarui.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO questions
                    (question, option_a, option_b, option_c, option_d, correct_answer, explanation, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $question['question'],
                $question['option_a'],
                $question['option_b'],
                $question['option_c'],
                $question['option_d'],
                $question['correct_answer'],
                $explanation,
                (int) $question['is_active'],
            ]);
            flash_set('success', 'Soal baru berhasil ditambahkan.');
        }

        redirect(APP_URL . '/admin/questions.php');
    }
}

$adminPage  = 'questions';
$adminTitle = $isEdit ? 'Edit Soal' : 'Tambah Soal';
require __DIR__ . '/includes/layout_head.php';
?>

<div class="page-head">
    <div>
        <h1><?= $isEdit ? 'Edit Soal #' . $id : 'Tambah Soal' ?></h1>
        <p>Isi teks soal, empat opsi jawaban, dan kunci jawaban.</p>
    </div>
    <a class="btn btn-sm btn-outline" href="<?= e(APP_URL) ?>/admin/questions.php">← Kembali</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-err">
        <span aria-hidden="true">⚠️</span>
        <span>Periksa kembali isian yang ditandai.</span>
    </div>
<?php endif; ?>

<form method="post" novalidate>
    <?= csrf_field() ?>

    <div class="panel">
        <div class="panel-head"><h2>Pertanyaan</h2></div>
        <div class="panel-body">
            <div class="field">
                <label for="question">Teks soal *</label>
                <textarea class="input" id="question" name="question" maxlength="2000" required
                          placeholder="Tulis pertanyaan di sini…"><?= e((string) $question['question']) ?></textarea>
                <?php if (isset($errors['question'])): ?>
                    <span class="field-error"><?= e($errors['question']) ?></span>
                <?php endif; ?>
            </div>

            <h3 style="font-family:var(--font-body);font-size:.8rem;letter-spacing:.1em;text-transform:uppercase;color:var(--text-dim);margin:22px 0 12px">
                Pilihan jawaban *
            </h3>

            <?php foreach (OPTION_LETTERS as $letter): ?>
                <?php $key = 'option_' . strtolower($letter); ?>
                <div class="option-row">
                    <span class="option-letter" aria-hidden="true"><?= $letter ?></span>
                    <div style="flex:1">
                        <label class="sr-only" for="<?= $key ?>">Opsi <?= $letter ?></label>
                        <input class="input" type="text" id="<?= $key ?>" name="<?= $key ?>" maxlength="255"
                               value="<?= e((string) $question[$key]) ?>" required
                               placeholder="Isi opsi <?= $letter ?>…">
                        <?php if (isset($errors[$key])): ?>
                            <span class="field-error"><?= e($errors[$key]) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head"><h2>Kunci &amp; tambahan</h2></div>
        <div class="panel-body">
            <div class="form-grid">
                <div class="field">
                    <label for="correct_answer">Kunci jawaban *</label>
                    <select class="input" id="correct_answer" name="correct_answer" required>
                        <?php foreach (OPTION_LETTERS as $letter): ?>
                            <option value="<?= $letter ?>" <?= $question['correct_answer'] === $letter ? 'selected' : '' ?>>
                                <?= $letter ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['correct_answer'])): ?>
                        <span class="field-error"><?= e($errors['correct_answer']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="is_active">Status</label>
                    <select class="input" id="is_active" name="is_active">
                        <option value="1" <?= (int) $question['is_active'] === 1 ? 'selected' : '' ?>>Aktif — tampil di kuis</option>
                        <option value="0" <?= (int) $question['is_active'] === 0 ? 'selected' : '' ?>>Nonaktif — disembunyikan</option>
                    </select>
                </div>

                <div class="field full">
                    <label for="explanation">Pembahasan (opsional)</label>
                    <textarea class="input" id="explanation" name="explanation" maxlength="2000"
                              placeholder="Jelaskan mengapa jawaban tersebut benar…"><?= e((string) ($question['explanation'] ?? '')) ?></textarea>
                    <?php if (isset($errors['explanation'])): ?>
                        <span class="field-error"><?= e($errors['explanation']) ?></span>
                    <?php endif; ?>
                    <span class="hint">Pembahasan ditampilkan pada feedback setelah pemain menjawab.</span>
                </div>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn" type="submit"><?= $isEdit ? 'Simpan Perubahan' : 'Simpan Soal' ?></button>
        <a class="btn btn-outline" href="<?= e(APP_URL) ?>/admin/questions.php">Batal</a>
    </div>
</form>

<?php require __DIR__ . '/includes/layout_foot.php'; ?>
