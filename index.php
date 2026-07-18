<?php
require_once __DIR__ . '/includes/config.php';

// The project root is public. Authentication remains available at /login.php.
header('Location: ' . BASE_URL . 'pages/landing.html');
exit;
