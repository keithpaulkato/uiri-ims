</main>
</div><!-- .main-wrapper -->

<?php if (isLoggedIn()): ?>
<script>
window.UIRI_SESSION = {
    idleTimeoutMs: <?= (int)SESSION_IDLE_TIMEOUT * 1000 ?>,
    keepaliveUrl: <?= json_encode(BASE_URL . 'includes/session_keepalive.php') ?>,
    timeoutUrl: <?= json_encode(BASE_URL . 'includes/logout.php?reason=idle') ?>,
    expiredUrl: <?= json_encode(BASE_URL . 'login.php?msg=session_expired') ?>
};
</script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.4/dist/js/adminlte.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/1.7.0/flowbite.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js" defer></script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</body>
</html>
