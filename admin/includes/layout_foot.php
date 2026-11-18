<?php
declare(strict_types=1);
?>
    </div><!-- /.admin-main -->
</div><!-- /.admin-shell -->
</main>

<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-live">
            <span class="tag">Admin</span>
            <span><?= e(setting($pdo, 'quiz_title', 'Quiz Dies Nat')) ?></span>
        </div>
        <p>&copy; <?= date('Y') ?> Admin Panel</p>
        <p class="footer-links">
            <a href="<?= e(APP_URL) ?>/index.php">Lihat Situs</a>
        </p>
    </div>
</footer>
<div id="toast" class="toast" role="status" aria-live="polite"></div>
<script>
    window.APP_BASE = <?= json_encode(APP_URL) ?>;
    window.APP_CSRF = <?= json_encode(csrf_token()) ?>;
</script>
<script src="<?= e(APP_URL) ?>/assets/js/app.js"></script>
</body>
</html>
