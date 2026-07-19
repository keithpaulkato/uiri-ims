</main>
</div><!-- .main-wrapper -->

<?php if (isLoggedIn()): ?>
<div class="modal-overlay" id="deleteConfirmModal" role="dialog" aria-modal="true" aria-labelledby="deleteConfirmTitle">
    <div class="modal delete-user-modal">
        <div class="delete-user-topline"></div>
        <div class="delete-user-body">
            <div class="delete-user-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
            </div>
            <div class="delete-user-copy">
                <span class="delete-user-kicker" id="deleteConfirmKicker">Deletion confirmation</span>
                <h3 id="deleteConfirmTitle">Confirm deletion</h3>
                <p id="deleteConfirmText">This record will be deleted. Do you want to continue?</p>
            </div>
        </div>
        <div class="delete-user-warning" id="deleteConfirmWarning">
            This action may affect related records and cannot be undone.
        </div>
        <div class="delete-user-actions">
            <button type="button" class="btn btn-outline delete-user-cancel" id="cancelDeleteConfirm">No, keep record</button>
            <button type="button" class="btn btn-danger delete-user-confirm" id="confirmDeleteAction">Yes, delete</button>
        </div>
    </div>
</div>

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
