<?php
require_once __DIR__ . '/config.php';
if (isLoggedIn()) {
    auditLog('LOGOUT', 'users', $_SESSION['user_id'], 'User logged out');

    // Clear persistent remember-me login token on logout.
    $stmt = db()->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
}

// Destroy the current session and remove cookies.
if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

setcookie('remember_token', '', time() - 3600, '/', '', false, true);
header('Location: ' . BASE_URL . 'index.php?msg=logged_out');
exit;
