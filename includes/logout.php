<?php
require_once __DIR__ . '/config.php';

$redirectMode = $_GET['redirect'] ?? '';
$logoutReason = $_GET['reason'] ?? '';
$redirectTarget = BASE_URL . 'login.php?msg=logged_out';
if ($redirectMode === 'landing') {
    $redirectTarget = BASE_URL . 'pages/landing.php';
} elseif ($logoutReason === 'idle') {
    $redirectTarget = BASE_URL . 'login.php?msg=session_expired';
}

sendNoStoreHeaders();
endAuthenticatedSession($logoutReason === 'idle' ? 'Session expired due to inactivity' : 'User logged out');

if ($redirectMode === 'none') {
    http_response_code(204);
    exit;
}

header('Location: ' . $redirectTarget);
exit;
