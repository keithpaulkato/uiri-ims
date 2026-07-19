<?php
require_once __DIR__ . '/includes/config.php';

// The project root is public, but landing.php closes any active session first.
sendNoStoreHeaders();
header('Location: ' . BASE_URL . 'pages/landing.php');
exit;
