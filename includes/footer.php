<?php
declare(strict_types=1);

/** Script tambahan per halaman, diisi sebelum include footer. */
$pageScripts = $pageScripts ?? [];
?>
</main>
<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-live">
            <span class="tag">Live</span>
            <span>Insert Coin / Press Start &bull; <?= e(setting($pdo, 'quiz_title', 'Quiz Dies Nat')) ?></span>
        </div>
        <p>&copy; <?= date('Y') ?> &mdash; Dibuat dengan PHP native</p>
        <p class="footer-links">
            <a href="<?= e(APP_URL) ?>/cafeteria.php">Cafeteria</a>
            <a href="<?= e(APP_URL) ?>/index.php?skip=1">Beranda</a>
            <a href="<?= e(APP_URL) ?>/leaderboard.php">Leaderboard</a>
            <a href="<?= e(APP_URL) ?>/admin/login.php">Admin</a>
        </p>
    </div>
</footer>
<div id="toast" class="toast" role="status" aria-live="polite"></div>
<script>
    window.APP_BASE = <?= json_encode(APP_URL) ?>;
    window.APP_CSRF = <?= json_encode(csrf_token()) ?>;
</script>
<script src="<?= e(APP_URL) ?>/assets/js/app.js"></script>
<script src="<?= e(APP_URL) ?>/assets/js/fx.js" defer></script>
<?php foreach ($pageScripts as $script): ?>
<script src="<?= e(APP_URL) ?>/assets/js/<?= e($script) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
