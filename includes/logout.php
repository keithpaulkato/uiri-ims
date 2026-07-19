<?php
require_once __DIR__ . '/config.php';

$redirectMode = $_GET['redirect'] ?? '';
$redirectTarget = BASE_URL . 'login.php?msg=logged_out';
if ($redirectMode === 'landing') {
    $redirectTarget = BASE_URL . 'pages/landing.php';
}

sendNoStoreHeaders();
endAuthenticatedSession('User logged out');

if ($redirectMode === 'none') {
    http_response_code(204);
    exit;
}

header('Location: ' . $redirectTarget);
exit;
