<?php
// ============================================================
//  UIRI IMS - Configuration
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'uiri_ims');

define('SITE_NAME', 'UIRI Inventory System');
define('SITE_SHORT', 'UIRI IMS');
define('BASE_URL', 'http://localhost/uiri-ims/');
define('UPLOAD_DIR', __DIR__ . '/../uploads/items/');
define('UPLOAD_URL', BASE_URL . 'uploads/items/');
define('PROFILE_UPLOAD_DIR', __DIR__ . '/../uploads/profiles/');
define('PROFILE_UPLOAD_URL', BASE_URL . 'uploads/profiles/');
define('APP_TIMEZONE', 'Africa/Kampala');
define('APP_TIMEZONE_ABBR', 'EAT');
define('APP_TIMEZONE_OFFSET', '+03:00');

date_default_timezone_set(APP_TIMEZONE);

// SMTP settings — configure these for real email delivery
define('SMTP_HOST', ''); // e.g. smtp.mailtrap.io or smtp.yourdomain.com
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM_EMAIL', 'no-reply@localhost');
define('SMTP_FROM_NAME', SITE_SHORT);

// Connect
function createDbConnection(): PDO {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
                PDO::ATTR_TIMEOUT            => 5,
            ]
        );
        $pdo->exec("SET time_zone = '" . APP_TIMEZONE_OFFSET . "'");
        return $pdo;
    } catch (PDOException $e) {
        die('<div style="font-family:sans-serif;padding:40px;background:#fff0f0;border-left:4px solid #e53e3e;margin:20px;">
            <h3 style="color:#e53e3e;">Database Connection Failed</h3>
            <p>Please ensure XAMPP MySQL is running and the database <strong>uiri_ims</strong> has been imported.</p>
            <small style="color:#666;">Error: ' . htmlspecialchars($e->getMessage()) . '</small>
        </div>');
    }
}

function isLostDatabaseConnection(PDOException $e): bool {
    $code = (int)($e->errorInfo[1] ?? 0);
    $message = $e->getMessage();

    return $code === 2006
        || $code === 2013
        || str_contains($message, '2006')
        || str_contains($message, '2013')
        || stripos($message, 'server has gone away') !== false
        || stripos($message, 'Lost connection') !== false;
}

function db(bool $forceReconnect = false): PDO {
    static $pdo = null;

    if ($forceReconnect || $pdo === null) {
        $pdo = createDbConnection();
        return $pdo;
    }

    try {
        $pdo->query('SELECT 1');
    } catch (PDOException $e) {
        if (isLostDatabaseConnection($e)) {
            $pdo = createDbConnection();
        } else {
            throw $e;
        }
    }

    return $pdo;
}

// Session start
if (session_status() === PHP_SESSION_NONE) {
    // Secure session configuration
    ini_set('session.use_strict_mode', 1);           // Reject uninitialized session IDs
    ini_set('session.use_only_cookies', 1);          // No URL-based sessions
    ini_set('session.cookie_httponly', 1);           // Prevent JavaScript access
    ini_set('session.cookie_secure', false);         // Set to true in production with HTTPS
    ini_set('session.cookie_samesite', 'Lax');       // CSRF protection
    ini_set('session.gc_maxlifetime', 3600);         // 1 hour session lifetime
    ini_set('session.cookie_lifetime', 3600);        // Session cookie lifetime
    
    session_start();
    
    // Regenerate session ID periodically for security
    if (!isset($_SESSION['_session_created'])) {
        $_SESSION['_session_created'] = time();
    } else if (time() - $_SESSION['_session_created'] > 600) {
        // Regenerate session ID every 10 minutes
        session_regenerate_id(true);
        $_SESSION['_session_created'] = time();
    }
}

// Auth helpers
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
}

function currentUser(): array {
    return $_SESSION['user'] ?? [];
}

function hasRole(string ...$roles): bool {
    return in_array($_SESSION['user']['role'] ?? '', $roles);
}

function requireRole(string ...$roles): void {
    requireLogin();
    if (!hasRole(...$roles)) {
        header('Location: ' . BASE_URL . 'pages/dashboard.php?error=unauthorized');
        exit;
    }
}

// CSRF
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    $token = $_SESSION['csrf_token'];
    if (empty($_COOKIE['csrf_token']) || !hash_equals($_COOKIE['csrf_token'], $token)) {
        setcookie('csrf_token', $token, [
            'expires' => time() + 3600,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    return $token;
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $cookieToken = $_COOKIE['csrf_token'] ?? '';

    $valid = false;
    if ($sessionToken !== '' && hash_equals($sessionToken, $token)) {
        $valid = true;
    } elseif ($cookieToken !== '' && hash_equals($cookieToken, $token)) {
        $valid = true;
    }

    if (!$valid) {
        if (isLoggedIn()) {
            setFlash('error', 'Your form session expired. Please try again.');
            $fallback = BASE_URL . 'pages/dashboard.php';
            $target = $_SERVER['HTTP_REFERER'] ?? $fallback;
            if (strpos($target, BASE_URL) !== 0) {
                $target = $fallback;
            }
            header('Location: ' . $target);
            exit;
        }

        http_response_code(403);
        die('Your form session expired. Please refresh and try again.');
    }
}

// Audit log
function auditLog(string $action, string $table = '', int $recordId = 0, string $details = ''): void {
    if (!isLoggedIn()) return;
    $pdo = db();
    $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, branch_id, action, table_name, record_id, details, ip_address) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([
        $_SESSION['user_id'],
        $_SESSION['user']['branch_id'],
        $action,
        $table,
        $recordId,
        $details,
        getUserIpAddress()
    ]);
}

// Record login attempts to login_history
function recordLoginAttempt(?int $userId, bool $success, ?string $details = null): void {
    $pdo = db();
    $stmt = $pdo->prepare("INSERT INTO login_history (user_id, branch_id, section_id, department_id, success, ip_address, user_agent, details) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $userId,
        $_SESSION['user']['branch_id'] ?? null,
        $_SESSION['user']['section_id'] ?? null,
        $_SESSION['user']['department_id'] ?? null,
        $success ? 1 : 0,
        getUserIpAddress(),
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        $details
    ]);
}

// Flash messages
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}


// ── Notifications ────────────────────────────────────────────────────────
// Send to a specific user (set $userId) or broadcast to everyone in a branch
// (set $branchId, leave $userId null — used e.g. for low-stock alerts seen
// by every Store Manager / Admin of that branch).
function notify(?int $userId, ?int $branchId, string $type, string $title, string $message = '', string $link = ''): void {
    db()->prepare("INSERT INTO notifications (user_id, branch_id, type, title, message, link) VALUES (?,?,?,?,?,?)")
        ->execute([$userId, $branchId, $type, $title, $message, $link ?: null]);
}
 
function unreadNotifications(array $user, int $limit = 8): array {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT * FROM notifications
        WHERE is_read = 0
          AND (user_id = ? OR (user_id IS NULL AND branch_id = ?))
        ORDER BY created_at DESC
        LIMIT $limit
    ");
    $stmt->execute([$user['id'], $user['branch_id']]);
    return $stmt->fetchAll();
}
 
function unreadNotificationCount(array $user): int {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM notifications
        WHERE is_read = 0 AND (user_id = ? OR (user_id IS NULL AND branch_id = ?))
    ");
    $stmt->execute([$user['id'], $user['branch_id']]);
    return (int)$stmt->fetchColumn();
}
 
// Broadcasts a low-stock / out-of-stock alert to a branch the first time an
// item crosses its minimum threshold (skips re-notifying while an existing
// unread alert for that item is still pending, to avoid spamming).
function maybeNotifyLowStock(array $item): void {
    if ((int)$item['current_stock'] > (int)$item['minimum_stock']) return;
    $pdo = db();
    $marker = 'item=' . $item['id'];
    $existing = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE type='low_stock' AND is_read=0 AND branch_id=? AND link LIKE ?");
    $existing->execute([$item['branch_id'], '%' . $marker]);
    if ($existing->fetchColumn() > 0) return;
 
    $title = (int)$item['current_stock'] === 0 ? 'Item out of stock' : 'Low stock alert';
    $msg   = $item['name'] . " is at {$item['current_stock']} unit(s) (minimum {$item['minimum_stock']}).";
    notify(null, (int)$item['branch_id'], 'low_stock', $title, $msg, BASE_URL . 'pages/items.php?' . $marker);
}
 
// Sanitize input
function clean(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

function normalizeUgandanPhone(?string $phone): string {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if ($digits === '') return '';

    if (strpos($digits, '256') === 0) {
        $digits = substr($digits, 3);
    }
    if (strpos($digits, '0') === 0) {
        $digits = substr($digits, 1);
    }

    return strlen($digits) === 9 ? '+256 ' . $digits : '';
}
 
// Format currency UGX
function ugx(float $amount): string {
    return 'UGX ' . number_format($amount, 0);
}

function ensureUsersProfilePhotoColumn(): void {
    try {
        $pdo = db();
        $check = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_photo'");
        if ($check->fetch()) return;
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) DEFAULT NULL");
    } catch (PDOException $e) {
        if (isLostDatabaseConnection($e)) {
            $pdo = db(true);
            $check = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_photo'");
            if ($check->fetch()) return;
            $pdo->exec("ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) DEFAULT NULL");
            return;
        }
        throw $e;
    }
}

function profilePhotoUrl(?array $user = null): string {
    $user = $user ?? currentUser();
    $photo = $user['profile_photo'] ?? '';
    if ($photo === '') return BASE_URL . 'assets/img/default-avatar.svg';
    if (preg_match('/^https?:\/\//', $photo)) return $photo;
    return strpos($photo, 'uploads/') === 0 ? BASE_URL . $photo : BASE_URL . ltrim($photo, '/');
}

function validateProfilePhotoUpload(array $file, string &$error = ''): bool {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'Please upload a valid image file (jpg, png, webp, gif) under 2MB.';
        return false;
    }

    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true) || (int)($file['size'] ?? 0) > 2 * 1024 * 1024) {
        $error = 'Please upload a valid image file (jpg, png, webp, gif) under 2MB.';
        return false;
    }

    return true;
}

function saveProfilePhotoUpload(array $file, int $userId): ?string {
    if ($userId <= 0) {
        return null;
    }

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }

    if (!is_dir(PROFILE_UPLOAD_DIR)) {
        mkdir(PROFILE_UPLOAD_DIR, 0755, true);
    }

    $error = '';
    if (!validateProfilePhotoUpload($file, $error)) {
        return null;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'user_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = PROFILE_UPLOAD_DIR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return null;
    }

    return 'uploads/profiles/' . $filename;
}

// Include helper functions
require_once __DIR__ . '/functions.php';
ensureUsersProfilePhotoColumn();

// Validate password strength
function validatePassword(string $password, string &$error = ''): bool {
    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
        return false;
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter.';
        return false;
    }
    if (!preg_match('/[a-z]/', $password)) {
        $error = 'Password must contain at least one lowercase letter.';
        return false;
    }
    if (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one number.';
        return false;
    }
    return true;
}

// ──── ROLE-BASED ACCESS CONTROL ────────────────────────────────────────────
// Role hierarchy: Administrator > Campus Manager > Store Manager > Section Manager > Staff
function hasRoleOrAbove(string $requiredRole): bool {
    if (!isLoggedIn()) return false;
    
    $roleHierarchy = [
        'Administrator' => 5,
        'Campus Manager' => 4,
        'Store Manager' => 3,
        'Section Manager' => 2,
        'Staff' => 1,
    ];
    
    $userRole = $_SESSION['user']['role'] ?? '';
    $userLevel = $roleHierarchy[$userRole] ?? 0;
    $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;
    
    return $userLevel >= $requiredLevel;
}

// Permission helpers
function userHasPermission(string $permissionName): bool {
    if (!isLoggedIn()) return false;
    $pdo = db();
    // Check user-specific permissions first
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_permissions up JOIN permissions p ON up.permission_id = p.id WHERE up.user_id = ? AND p.name = ?");
    $stmt->execute([$_SESSION['user_id'], $permissionName]);
    if ($stmt->fetchColumn() > 0) return true;

    // Then check role permissions
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.id JOIN roles r ON rp.role_id = r.id WHERE rp.role_id = ? AND p.name = ?");
    $stmt->execute([$_SESSION['user']['role_id'] ?? 0, $permissionName]);
    return $stmt->fetchColumn() > 0;
}

function requirePermission(string $permissionName): void {
    requireLogin();
    if (!userHasPermission($permissionName)) {
        header('Location: ' . BASE_URL . 'pages/dashboard.php?error=unauthorized');
        exit;
    }
}

// Check if user can access a specific branch
function canAccessBranch(int $branchId): bool {
    if (!isLoggedIn()) return false;
    
    $userRole = $_SESSION['user']['role'] ?? '';
    $userBranchId = $_SESSION['user']['branch_id'] ?? 0;
    
    // Administrators can access all branches
    if ($userRole === 'Administrator') return true;
    
    // Campus Managers can access their assigned campus
    if ($userRole === 'Campus Manager') return $userBranchId === $branchId;
    
    // Store Managers can access their assigned branch
    if ($userRole === 'Store Manager') return $userBranchId === $branchId;
    
    // Section Managers can access their assigned section's branch
    // Staff can access their department's branch
    return $userBranchId === $branchId;
}

// Check if user can access inventory item
function canAccessItem(int $itemId): bool {
    if (!isLoggedIn()) return false;
    
    $pdo = db();
    $stmt = $pdo->prepare("SELECT branch_id, section_id, department_id FROM inventory_items WHERE id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    
    if (!$item) return false;
    
    return canAccessBranch($item['branch_id']);
}
