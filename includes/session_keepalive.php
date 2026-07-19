<?php
require_once __DIR__ . '/config.php';

sendNoStoreHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    exit;
}

http_response_code(204);
exit;
