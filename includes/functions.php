<?php
/**
 * UIRI IMS - Helper Functions
 * Utility functions for common operations across the system
 */

/**
 * Format date to readable format
 */
function formatDate($datetime): string {
    if (!$datetime) return '';
    $dt = new DateTime((string)$datetime, appTimezone());
    return $dt->format('d M Y');
}

/**
 * Format datetime to readable format
 */
function formatDateTime($datetime, bool $includeSeconds = false): string {
    if (!$datetime) return '';
    $dt = new DateTime((string)$datetime, appTimezone());
    return $dt->format($includeSeconds ? 'd M Y, h:i:s A' : 'd M Y, h:i A') . ' ' . appTimezoneStamp();
}

/**
 * Format only the time with an evidential timezone trail.
 */
function formatTimeWithTimezone($datetime, bool $includeSeconds = true): string {
    if (!$datetime) return '';
    $dt = new DateTime((string)$datetime, appTimezone());
    return $dt->format($includeSeconds ? 'h:i:s A' : 'h:i A') . ' ' . appTimezoneStamp();
}

/**
 * Application timezone used for audit-grade timestamps.
 */
function appTimezone(): DateTimeZone {
    return new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Africa/Kampala');
}

/**
 * Human-readable timezone stamp for reports and audit trails.
 */
function appTimezoneStamp(): string {
    $abbr = defined('APP_TIMEZONE_ABBR') ? APP_TIMEZONE_ABBR : (new DateTime('now', appTimezone()))->format('T');
    $offset = defined('APP_TIMEZONE_OFFSET') ? APP_TIMEZONE_OFFSET : (new DateTime('now', appTimezone()))->format('P');
    return $abbr . ' (UTC' . $offset . ')';
}

/**
 * Format time since (e.g., "2 hours ago")
 */
function formatTimeSince($datetime): string {
    if (!$datetime) return '';
    
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' minute' . (floor($diff / 60) > 1 ? 's' : '') . ' ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hour' . (floor($diff / 3600) > 1 ? 's' : '') . ' ago';
    if ($diff < 604800) return floor($diff / 86400) . ' day' . (floor($diff / 86400) > 1 ? 's' : '') . ' ago';
    
    return formatDate($datetime);
}

/**
 * Generate a unique username from a user's full name and branch.
 */
function generateUsername(string $fullName, int $branchId): string {
    $pdo = db();
    $nameParts = array_filter(array_map('trim', explode(' ', strtolower($fullName))));
    if (count($nameParts) === 0) {
        $base = 'user';
    } elseif (count($nameParts) === 1) {
        $base = preg_replace('/[^a-z0-9]/', '', $nameParts[0]);
    } else {
        $first = preg_replace('/[^a-z0-9]/', '', $nameParts[0][0] ?? '');
        $last = preg_replace('/[^a-z0-9]/', '', end($nameParts));
        $base = trim($first . $last, '.');
    }
    if ($base === '') {
        $base = 'user';
    }

    $branchStmt = $pdo->prepare("SELECT name FROM branches WHERE id = ?");
    $branchStmt->execute([$branchId]);
    $branchName = $branchStmt->fetchColumn() ?: '';
    $branchCode = substr(preg_replace('/[^A-Z]/', '', strtoupper($branchName)), 0, 2) ?: 'XX';

    $candidate = $base . '.' . strtolower($branchCode);
    $counter = 1;
    while (true) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$candidate]);
        if ((int)$stmt->fetchColumn() === 0) {
            return $candidate;
        }
        $candidate = $base . '.' . strtolower($branchCode) . $counter;
        $counter++;
    }
}

/**
 * Generate a strong random password.
 */
function generatePassword(int $length = 10): string {
    $lower = 'abcdefghijklmnopqrstuvwxyz';
    $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $digits = '0123456789';
    $symbols = '!@#$%*?';
    $all = $lower . $upper . $digits . $symbols;

    $password = [];
    $password[] = $lower[random_int(0, strlen($lower) - 1)];
    $password[] = $upper[random_int(0, strlen($upper) - 1)];
    $password[] = $digits[random_int(0, strlen($digits) - 1)];
    $password[] = $symbols[random_int(0, strlen($symbols) - 1)];

    for ($i = 4; $i < $length; $i++) {
        $password[] = $all[random_int(0, strlen($all) - 1)];
    }

    shuffle($password);
    return implode('', $password);
}

/**
 * Generate unique item code
 * Format: BR-CAT-### (e.g., NK-ICT-001)
 */
function generateItemCode(int $categoryId, int $branchId): string {
    $pdo = db();
    
    // Get branch code (first 2 letters of branch name or NK/NM)
    $branchStmt = $pdo->prepare("SELECT name FROM branches WHERE id = ?");
    $branchStmt->execute([$branchId]);
    $branch = $branchStmt->fetch();
    $branchCode = substr(preg_replace('/[^A-Z]/', '', strtoupper($branch['name'] ?? 'XX')), 0, 2) ?: 'XX';
    
    // Get category code
    $catStmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
    $catStmt->execute([$categoryId]);
    $category = $catStmt->fetch();
    $catCode = substr(preg_replace('/[^A-Z]/', '', strtoupper($category['name'] ?? 'XX')), 0, 3) ?: 'XXX';
    
    // Get next sequence number
    $query = "SELECT COUNT(*) + 1 as next_num FROM inventory_items WHERE item_code LIKE ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$branchCode . '-' . substr($catCode, 0, 3) . '-%']);
    $result = $stmt->fetch();
    $sequence = str_pad($result['next_num'], 3, '0', STR_PAD_LEFT);
    
    return $branchCode . '-' . substr($catCode, 0, 3) . '-' . $sequence;
}

/**
 * Generate unique transfer code
 * Format: TR-YYYYMMDD-### (e.g., TR-20260515-001)
 */
function generateTransferCode(): string {
    $pdo = db();
    
    $dateCode = date('Ymd');
    $query = "SELECT COUNT(*) + 1 as next_num FROM transfers WHERE transfer_code LIKE ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['TR-' . $dateCode . '-%']);
    $result = $stmt->fetch();
    $sequence = str_pad($result['next_num'], 3, '0', STR_PAD_LEFT);
    
    return 'TR-' . $dateCode . '-' . $sequence;
}

/**
 * Generate unique request code
 * Format: REQ-YYYYMMDD-### (e.g., REQ-20260515-001)
 */
function generateRequestCode(): string {
    $pdo = db();
    
    $dateCode = date('Ymd');
    $query = "SELECT COUNT(*) + 1 as next_num FROM inventory_requests WHERE id IN (SELECT id FROM inventory_requests WHERE DATE(requested_at) = CURDATE()) ";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM inventory_requests WHERE DATE(requested_at) = CURDATE()");
    $result = $stmt->fetch();
    $sequence = str_pad($result['count'] + 1, 3, '0', STR_PAD_LEFT);
    
    return 'REQ-' . $dateCode . '-' . $sequence;
}

/**
 * Check if stock is available
 */
function checkStockAvailability(int $itemId, int $branchId, int $quantity): bool {
    $stmt = db()->prepare("SELECT current_stock FROM inventory_items WHERE id = ? AND branch_id = ?");
    $stmt->execute([$itemId, $branchId]);
    $item = $stmt->fetch();
    
    return $item && $item['current_stock'] >= $quantity;
}

/**
 * Get item value (quantity * unit_price)
 */
function getItemValue(array $item): float {
    return ($item['current_stock'] ?? 0) * ($item['unit_price'] ?? 0);
}

/**
 * Check if item can be deleted
 */
function canDeleteItem(int $itemId): bool {
    $pdo = db();
    $check = $pdo->prepare("SELECT COUNT(*) FROM stock_transactions WHERE item_id = ?");
    $check->execute([$itemId]);
    return $check->fetchColumn() == 0;
}

/**
 * Check if supplier can be deleted
 */
function canDeleteSupplier(int $supplierId): bool {
    $pdo = db();
    $check = $pdo->prepare("SELECT COUNT(*) FROM stock_transactions WHERE ? OR inventory_items.supplier_id = ?", [$supplierId, $supplierId]);
    $check = $pdo->prepare("SELECT COUNT(*) FROM inventory_items WHERE supplier_id = ?");
    $check->execute([$supplierId]);
    return $check->fetchColumn() == 0;
}

/**
 * Log stock transaction
 */
function logStockTransaction(int $itemId, int $branchId, int $userId, string $type, int $quantity, float $unitPrice = 0, string $reference = '', string $remarks = ''): int {
    $pdo = db();
    
    $stmt = $pdo->prepare("
        INSERT INTO stock_transactions (item_id, branch_id, user_id, transaction_type, quantity, unit_price, reference_number, remarks, transaction_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE())
    ");
    
    $stmt->execute([$itemId, $branchId, $userId, $type, $quantity, $unitPrice, $reference, $remarks]);
    
    return $pdo->lastInsertId();
}

/**
 * Update item stock level
 */
function updateItemStock(int $itemId, int $quantityChange): void {
    $stmt = db()->prepare("UPDATE inventory_items SET current_stock = current_stock + ? WHERE id = ?");
    $stmt->execute([$quantityChange, $itemId]);
}

/**
 * Get low stock items for a branch
 */
function getLowStockItems(int $branchId = 0, int $limit = 10): array {
    $query = "SELECT i.*, c.name as category_name, b.name as branch_name 
              FROM inventory_items i 
              JOIN categories c ON i.category_id = c.id 
              JOIN branches b ON i.branch_id = b.id 
              WHERE i.current_stock <= i.minimum_stock AND i.is_active = 1";
    
    if ($branchId) {
        $query .= " AND i.branch_id = " . (int)$branchId;
    }
    
    $query .= " ORDER BY i.current_stock ASC LIMIT " . (int)$limit;
    
    return db()->query($query)->fetchAll();
}

/**
 * Get total inventory value
 */
function getTotalInventoryValue(int $branchId = 0): float {
    $query = "SELECT SUM(current_stock * unit_price) as total FROM inventory_items WHERE is_active = 1";
    
    if ($branchId) {
        $query .= " AND branch_id = " . (int)$branchId;
    }
    
    $result = db()->query($query)->fetch();
    return (float)($result['total'] ?? 0);
}

/**
 * Get inventory items count
 */
function getInventoryCount(int $branchId = 0): int {
    $query = "SELECT COUNT(*) FROM inventory_items WHERE is_active = 1";
    
    if ($branchId) {
        $query .= " AND branch_id = " . (int)$branchId;
    }
    
    return (int)db()->query($query)->fetchColumn();
}

/**
 * Get pending requests count
 */
function getPendingRequestsCount(int $branchId = 0): int {
    $query = "SELECT COUNT(*) FROM inventory_requests WHERE status = 'Pending'";
    
    if ($branchId) {
        $query .= " AND branch_id = " . (int)$branchId;
    }
    
    return (int)db()->query($query)->fetchColumn();
}

/**
 * Get active transfers count
 */
function getActiveTransfersCount(int $branchId = 0): int {
    $query = "SELECT COUNT(*) FROM transfers WHERE status IN ('Requested', 'Approved', 'Dispatched')";
    
    if ($branchId) {
        $query .= " AND (from_branch_id = " . (int)$branchId . " OR to_branch_id = " . (int)$branchId . ")";
    }
    
    return (int)db()->query($query)->fetchColumn();
}

/**
 * Get monthly stock in value
 */
function getMonthlyStockInValue(int $branchId = 0, int $month = 0, int $year = 0): float {
    if (!$month) $month = date('m');
    if (!$year) $year = date('Y');
    
    $query = "SELECT SUM(quantity * unit_price) as total FROM stock_transactions 
              WHERE transaction_type IN ('stock_in', 'transfer_in')
              AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?";
    
    $params = [$month, $year];
    
    if ($branchId) {
        $query .= " AND branch_id = ?";
        $params[] = $branchId;
    }
    
    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch();
    
    return (float)($result['total'] ?? 0);
}

/**
 * Get monthly stock out value
 */
function getMonthlyStockOutValue(int $branchId = 0, int $month = 0, int $year = 0): float {
    if (!$month) $month = date('m');
    if (!$year) $year = date('Y');
    
    $query = "SELECT SUM(quantity * unit_price) as total FROM stock_transactions 
              WHERE transaction_type IN ('stock_out', 'transfer_out', 'adjustment')
              AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?";
    
    $params = [$month, $year];
    
    if ($branchId) {
        $query .= " AND branch_id = ?";
        $params[] = $branchId;
    }
    
    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch();
    
    return (float)($result['total'] ?? 0);
}

/**
 * Get top suppliers by purchase value
 */
function getTopSuppliers(int $limit = 5, int $branchId = 0): array {
    $query = "SELECT s.*, SUM(st.quantity * st.unit_price) as total_purchased, COUNT(st.id) as transaction_count
              FROM suppliers s 
              LEFT JOIN stock_transactions st ON s.id = (SELECT supplier_id FROM inventory_items WHERE id = st.item_id)
              WHERE st.transaction_type = 'stock_in'";
    
    if ($branchId) {
        $query .= " AND st.branch_id = " . (int)$branchId;
    }
    
    $query .= " GROUP BY s.id ORDER BY total_purchased DESC LIMIT " . (int)$limit;
    
    return db()->query($query)->fetchAll() ?? [];
}

/**
 * Get user's last login time
 */
function getLastLogin(int $userId): ?string {
    $stmt = db()->prepare("SELECT last_login FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return $result['last_login'] ?? null;
}

/**
 * Send notification to user
 */
function sendNotificationToUser(int $userId, string $type, string $title, string $message = '', string $link = ''): void {
    notify($userId, null, $type, $title, $message, $link);
}

/**
 * Send notification to branch
 */
function sendNotificationToBranch(int $branchId, string $type, string $title, string $message = '', string $link = ''): void {
    notify(null, $branchId, $type, $title, $message, $link);
}

/**
 * Get asset depreciation value (simplified)
 */
function getDepreciationValue(array $item, int $yearsInUse = 0): float {
    if (!$item['purchase_date']) return 0;
    
    $years = (int)((time() - strtotime($item['purchase_date'])) / (365 * 24 * 3600));
    $depreciation = $years * 0.15; // 15% per year
    $depreciation = min($depreciation, 0.80); // Max 80% depreciation
    
    return $item['unit_price'] * (1 - $depreciation) * $item['current_stock'];
}

/**
 * Check warranty status
 */
function getWarrantyStatus(array $item): string {
    if (!$item['warranty_date']) return 'No warranty';
    
    $warrantyTime = strtotime($item['warranty_date']);
    $now = time();
    
    if ($warrantyTime < $now) return 'Expired';
    
    $daysLeft = ($warrantyTime - $now) / (24 * 3600);
    
    if ($daysLeft < 30) return 'Expiring soon (' . ceil($daysLeft) . ' days)';
    
    return 'Active until ' . formatDate($item['warranty_date']);
}

/**
 * Get request status badge class
 */
function getRequestStatusClass(string $status): string {
    return match($status) {
        'Pending' => 'badge-warning',
        'Approved' => 'badge-info',
        'Rejected' => 'badge-danger',
        'Issued' => 'badge-success',
        'Cancelled' => 'badge-secondary',
        default => 'badge-secondary',
    };
}

/**
 * Get transfer status badge class
 */
function getTransferStatusClass(string $status): string {
    return match($status) {
        'Requested' => 'badge-warning',
        'Approved' => 'badge-info',
        'Dispatched' => 'badge-primary',
        'Received' => 'badge-success',
        'Rejected' => 'badge-danger',
        'Cancelled' => 'badge-secondary',
        default => 'badge-secondary',
    };
}

/**
 * Send mail using configured SMTP or fallback to PHP mail().
 * Returns true on success, false on failure.
 */
function sendMail(string $to, string $subject, string $body, string $fromName = SMTP_FROM_NAME, string $fromEmail = SMTP_FROM_EMAIL, bool $isHtml = false): bool {
    $pdo = db();
    // Load SMTP settings from DB if constants are empty
    $dbSettings = [];
    foreach ($pdo->query("SELECT setting_key, setting_value FROM settings") as $row) {
        $dbSettings[$row['setting_key']] = $row['setting_value'];
    }

    $host = !empty(SMTP_HOST) ? SMTP_HOST : ($dbSettings['smtp_host'] ?? '');
    $port = !empty(SMTP_PORT) ? SMTP_PORT : (int)($dbSettings['smtp_port'] ?? 587);
    $user = !empty(SMTP_USER) ? SMTP_USER : ($dbSettings['smtp_user'] ?? '');
    $pass = !empty(SMTP_PASS) ? SMTP_PASS : ($dbSettings['smtp_pass'] ?? '');
    $dbFromEmail = $dbSettings['smtp_from_email'] ?? '';
    $dbFromName = $dbSettings['smtp_from_name'] ?? '';
    $fromEmail = !empty($fromEmail) ? $fromEmail : (!empty(SMTP_FROM_EMAIL) ? SMTP_FROM_EMAIL : ($dbFromEmail ?: 'no-reply@localhost'));
    $fromName = !empty($fromName) ? $fromName : (!empty(SMTP_FROM_NAME) ? SMTP_FROM_NAME : ($dbFromName ?: SITE_SHORT));

    // Prefer PHPMailer (installed via Composer)
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            if (!empty($host)) {
                $mail->isSMTP();
                $mail->Host = $host;
                $mail->Port = $port;
                $mail->SMTPAuth = !empty($user);
                if (!empty($user)) {
                    $mail->Username = $user;
                    $mail->Password = $pass;
                }
                if ($port == 465) {
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                } else {
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                }
            }
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            if ($isHtml) {
                $mail->isHTML(true);
                $mail->Body = $body;
                $mail->AltBody = strip_tags($body);
            } else {
                $mail->Body = $body;
            }
            $mail->send();
            return true;
        } catch (Exception $e) {
            // fallback to PHP mail
        }
    }

    // If no SMTP host configured, use mail()
    if (empty($host)) {
        $headers = "From: {$fromName} <{$fromEmail}>\r\n";
        if ($isHtml) {
            $headers .= "MIME-Version: 1.0\r\nContent-type: text/html; charset=utf-8\r\n";
        }
        return (bool) @mail($to, $subject, $body, $headers);
    }

    // Best-effort fallback when SMTP_HOST is set but PHPMailer isn't installed
    $headers = "From: {$fromName} <{$fromEmail}>\r\n";
    if ($isHtml) {
        $headers .= "MIME-Version: 1.0\r\nContent-type: text/html; charset=utf-8\r\n";
    }
    return (bool) @mail($to, $subject, $body, $headers);
}

/**
 * Rate limiting helper: check if an IP has exceeded max attempts in a time window.
 * Returns true if rate limit exceeded, false otherwise.
 */
function isRateLimited(string $identifier, int $maxAttempts = 5, int $windowSeconds = 300): bool {
    $pdo = db();

    try {
        // Clean old rate limit records (older than 1 hour)
        $pdo->query("DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");

        // Count recent attempts
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM rate_limits
            WHERE identifier = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$identifier, $windowSeconds]);
        $count = (int)$stmt->fetchColumn();

        return $count >= $maxAttempts;
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), '42S02') || str_contains($e->getMessage(), 'doesn\'t exist')) {
            return false;
        }
        throw $e;
    }
}

/**
 * Count recent rate limit attempts for an identifier.
 */
function getRateLimitAttemptCount(string $identifier, int $windowSeconds = 300): int {
    $pdo = db();

    try {
        $pdo->query("DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM rate_limits
            WHERE identifier = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$identifier, $windowSeconds]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), '42S02') || str_contains($e->getMessage(), 'doesn\'t exist')) {
            return 0;
        }
        throw $e;
    }
}

/**
 * Record a rate limit attempt for tracking.
 */
function recordRateLimitAttempt(string $identifier, string $action = 'login'): void {
    $pdo = db();

    try {
        $stmt = $pdo->prepare("INSERT INTO rate_limits (identifier, action, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$identifier, $action, getUserIpAddress()]);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), '42S02') || str_contains($e->getMessage(), 'doesn\'t exist')) {
            return;
        }
        throw $e;
    }
}

/**
 * Clear rate limit attempts for an identifier.
 */
function clearRateLimitAttempts(string $identifier): void {
    $pdo = db();

    try {
        $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE identifier = ?");
        $stmt->execute([$identifier]);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), '42S02') || str_contains($e->getMessage(), 'doesn\'t exist')) {
            return;
        }
        throw $e;
    }
}

/**
 * Generate email verification token.
 */
function generateEmailVerificationToken(): string {
    return bin2hex(random_bytes(32));
}

/**
 * Check if account is locked due to too many failed attempts.
 * Returns true if locked, false otherwise.
 */
function isAccountLocked(int $userId, int $maxAttempts = 4): bool {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT failed_login_attempts, last_login_attempt FROM users WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) return false;
    
    if ($user['failed_login_attempts'] < $maxAttempts) return false;
    
    $lockoutTime = strtotime('-30 minutes');
    $lastAttemptTime = strtotime($user['last_login_attempt']);
    
    return $lastAttemptTime > $lockoutTime;
}

/**
 * Unlock an account (reset failed login attempts).
 */
function unlockAccount(int $userId): void {
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = 0, last_login_attempt = NULL WHERE id = ?");
    $stmt->execute([$userId]);
}

/**
 * Get user's IP address safely.
 */
function getUserIpAddress(): string {
    $sources = [
        $_SERVER['HTTP_CLIENT_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['HTTP_X_REAL_IP'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    foreach ($sources as $source) {
        foreach (explode(',', (string)$source) as $candidate) {
            $ip = normalizeIpAddress($candidate);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $ip;
            }
        }
    }

    foreach ($sources as $source) {
        foreach (explode(',', (string)$source) as $candidate) {
            $ip = normalizeIpAddress($candidate);
            if ($ip !== '') {
                return $ip;
            }
        }
    }

    return 'unknown';
}

function normalizeIpAddress(string $ip): string {
    $ip = trim($ip);
    if ($ip === '') {
        return '';
    }

    if ($ip === '::1' || strcasecmp($ip, 'localhost') === 0) {
        return '127.0.0.1';
    }

    if (stripos($ip, '::ffff:') === 0) {
        $mapped = substr($ip, 7);
        if (filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $mapped;
        }
    }

    return $ip;
}

/**
 * Validate email address strength and format.
 */
function validateEmailStrength(string $email): bool {
    // Check basic email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // Prevent disposable email domains (basic check)
    $disposableDomains = [
        'tempmail.com', 'guerrillamail.com', 'mailinator.com',
        'temp-mail.org', 'throwaway.email', 'maildrop.cc'
    ];
    
    $domain = substr(strrchr($email, "@"), 1);
    if (in_array($domain, $disposableDomains)) {
        return false;
    }
    
    return true;
}

/**
 * Ensure decision/reporting fields exist on inventory items.
 */
function ensureInventoryDecisionColumns(): void {
    $pdo = db();
    $columns = [
        'brand_model' => "ALTER TABLE inventory_items ADD COLUMN brand_model VARCHAR(150) DEFAULT NULL AFTER name",
        'serial_number' => "ALTER TABLE inventory_items ADD COLUMN serial_number VARCHAR(120) DEFAULT NULL AFTER asset_code",
        'asset_status' => "ALTER TABLE inventory_items ADD COLUMN asset_status VARCHAR(30) DEFAULT 'Available' AFTER warranty_date",
        'asset_condition' => "ALTER TABLE inventory_items ADD COLUMN asset_condition VARCHAR(30) DEFAULT 'New' AFTER asset_status",
        'funding_source' => "ALTER TABLE inventory_items ADD COLUMN funding_source VARCHAR(120) DEFAULT NULL AFTER asset_condition",
        'storage_location' => "ALTER TABLE inventory_items ADD COLUMN storage_location VARCHAR(120) DEFAULT NULL AFTER funding_source"
    ];

    foreach ($columns as $column => $sql) {
        try {
            $check = $pdo->query("SHOW COLUMNS FROM inventory_items LIKE " . $pdo->quote($column));
            if (!$check->fetch()) {
                $pdo->exec($sql);
            }
        } catch (PDOException $e) {
            if (!str_contains($e->getMessage(), '42S02') && !str_contains($e->getMessage(), "doesn't exist")) {
                throw $e;
            }
        }
    }
}

function inventoryUnitCatalog(): array {
    return [
        'EA' => ['label' => 'EA - Each', 'short' => 'EA'],
        'SET' => ['label' => 'SET - Set', 'short' => 'SET'],
        'KIT' => ['label' => 'KIT - Kit', 'short' => 'KIT'],
        'BOX' => ['label' => 'BOX - Box', 'short' => 'BOX'],
        'PACK' => ['label' => 'PACK - Pack', 'short' => 'PACK'],
        'CARTON' => ['label' => 'CARTON - Carton', 'short' => 'CARTON'],
        'REAM' => ['label' => 'REAM - Ream', 'short' => 'REAM'],
        'ROLL' => ['label' => 'ROLL - Roll', 'short' => 'ROLL'],
        'BAG' => ['label' => 'BAG - Bag', 'short' => 'BAG'],
        'KG' => ['label' => 'KG - Kilogram', 'short' => 'KG'],
        'L' => ['label' => 'L - Litre', 'short' => 'L'],
        'ML' => ['label' => 'ML - Millilitre', 'short' => 'ML'],
        'M' => ['label' => 'M - Metre', 'short' => 'M'],
        'PAIR' => ['label' => 'PAIR - Pair', 'short' => 'PAIR'],
        'DOZEN' => ['label' => 'DOZEN - Dozen', 'short' => 'DOZEN'],
        'LICENSE' => ['label' => 'LICENSE - Software licence', 'short' => 'LICENSE'],
        'USER' => ['label' => 'USER - Licensed user', 'short' => 'USER'],
        'SERVICE' => ['label' => 'SERVICE - Service', 'short' => 'SERVICE'],
    ];
}

function inventoryUnitProfiles(): array {
    return [
        [
            'id' => 'software_service',
            'label' => 'Software, subscriptions and services',
            'units' => ['LICENSE', 'USER', 'SERVICE'],
            'default' => 'LICENSE',
            'hint' => 'Use licence/user/service units for software, hosting and subscriptions.',
            'keywords' => ['software', 'license', 'licence', 'subscription', 'hosting', 'cloud', 'service'],
        ],
        [
            'id' => 'paper',
            'label' => 'Paper and printed stock',
            'units' => ['REAM', 'PACK', 'BOX'],
            'default' => 'REAM',
            'hint' => 'Paper stock is normally recorded by ream unless it is deliberately boxed or packed.',
            'keywords' => ['a4 paper', 'paper ream', 'ream', 'printer paper', 'copy paper'],
        ],
        [
            'id' => 'cable',
            'label' => 'Cables and wiring',
            'units' => ['M', 'ROLL', 'EA'],
            'default' => 'M',
            'hint' => 'Bulk cable is measured in metres; prepacked cable rolls use roll.',
            'keywords' => ['cable', 'wire', 'wiring', 'fibre', 'fiber'],
        ],
        [
            'id' => 'liquid_chemical',
            'label' => 'Liquids, chemicals and coatings',
            'units' => ['L', 'ML', 'KG', 'BAG'],
            'default' => 'L',
            'hint' => 'Use litres for liquid chemicals/coatings, kilograms or bags for dry bulk material.',
            'keywords' => ['chemical', 'reagent', 'solvent', 'varnish', 'paint', 'oil', 'detergent', 'sanitizer', 'acid', 'alkali', 'liquid'],
        ],
        [
            'id' => 'dry_bulk',
            'label' => 'Dry bulk materials',
            'units' => ['BAG', 'KG', 'PACK'],
            'default' => 'BAG',
            'hint' => 'Dry bulk materials are recorded by bag or kilogram depending on packaging.',
            'keywords' => ['powder', 'clay', 'sand', 'cement', 'flour', 'grain', 'pellet'],
        ],
        [
            'id' => 'packaging',
            'label' => 'Packaging materials',
            'units' => ['ROLL', 'PACK', 'BOX', 'CARTON', 'EA'],
            'default' => 'ROLL',
            'hint' => 'Packaging film uses roll; labels, cartons and boxes use their packed unit.',
            'keywords' => ['packaging', 'film', 'label', 'carton', 'wrap', 'wrapping'],
        ],
        [
            'id' => 'ict_hardware',
            'label' => 'ICT hardware',
            'units' => ['EA'],
            'default' => 'EA',
            'hint' => 'ICT hardware is counted as individual assets: use EA for each PC, workstation, monitor, printer or network device.',
            'keywords' => ['ict', 'computer', 'pc', 'workstation', 'desktop', 'laptop', 'monitor', 'printer', 'scanner', 'server', 'router', 'switch', 'ups', 'keyboard', 'mouse', 'tablet', 'phone', 'hmi', 'plc', 'dell', 'hp ', 'lenovo'],
        ],
        [
            'id' => 'machinery_equipment',
            'label' => 'Machinery and equipment',
            'units' => ['EA'],
            'default' => 'EA',
            'hint' => 'Machinery, instruments and equipment are distinct countable assets: use EA.',
            'keywords' => ['machine', 'machinery', 'equipment', 'oven', 'mixer', 'pasteurizer', 'lathe', 'cnc', 'robot', 'pump', 'compressor', 'furnace', 'kiln', 'motor', 'panel', 'workbench', 'microscope', 'meter', 'gauge', 'caliper', 'holder', 'arm', 'jack', 'wheel', 'rack', 'sealer', 'extractor', 'dehydrator', 'kettle', 'vat', 'churn', 'grinder', 'sheeter', 'tumbler', 'cylinder', 'hydraulic', 'pneumatic', 'welding', 'frame', 'probe', 'timer', 'scale', 'trolley', 'bin'],
        ],
        [
            'id' => 'furniture',
            'label' => 'Furniture and fittings',
            'units' => ['EA'],
            'default' => 'EA',
            'hint' => 'Furniture and fittings are counted as individual assets: use EA.',
            'keywords' => ['furniture', 'chair', 'desk', 'table', 'cabinet', 'shelf', 'shelves', 'stool', 'bench'],
        ],
        [
            'id' => 'safety',
            'label' => 'Safety and PPE',
            'units' => ['EA', 'PAIR', 'PACK'],
            'default' => 'EA',
            'hint' => 'Use EA for devices, PAIR for paired PPE, and PACK only for packed consumable PPE.',
            'keywords' => ['safety', 'ppe', 'glove', 'goggles', 'helmet', 'harness', 'extinguisher', 'mask', 'apron'],
        ],
        [
            'id' => 'kit_set',
            'label' => 'Kits and sets',
            'units' => ['KIT', 'SET', 'EA'],
            'default' => 'SET',
            'hint' => 'Use SET or KIT only when the item is intentionally managed as one grouped kit.',
            'keywords' => ['kit', 'set', 'tool set', 'manual set', 'models'],
        ],
        [
            'id' => 'packaged_consumable',
            'label' => 'Packaged consumables',
            'units' => ['PACK', 'BOX', 'BAG', 'CARTON', 'DOZEN', 'EA'],
            'default' => 'PACK',
            'hint' => 'Consumables should use the package unit printed on the stock record.',
            'keywords' => ['consumable', 'stationery', 'supplies', 'spare', 'manual', 'material'],
        ],
        [
            'id' => 'countable_default',
            'label' => 'Countable item',
            'units' => ['EA'],
            'default' => 'EA',
            'hint' => 'Use EA when the item is a distinct countable asset.',
            'keywords' => [],
        ],
    ];
}

function inventoryNormalizeUnitCode(?string $unit): string {
    $key = strtolower(trim((string)$unit));
    $key = rtrim($key, '.');
    $aliases = [
        '' => '',
        'ea' => 'EA',
        'each' => 'EA',
        'unit' => 'EA',
        'piece' => 'EA',
        'pieces' => 'EA',
        'pc' => 'EA',
        'pcs' => 'EA',
        'set' => 'SET',
        'kit' => 'KIT',
        'box' => 'BOX',
        'pack' => 'PACK',
        'packet' => 'PACK',
        'carton' => 'CARTON',
        'ream' => 'REAM',
        'roll' => 'ROLL',
        'bag' => 'BAG',
        'kg' => 'KG',
        'kgs' => 'KG',
        'kilogram' => 'KG',
        'kilograms' => 'KG',
        'l' => 'L',
        'ltr' => 'L',
        'litre' => 'L',
        'liter' => 'L',
        'litres' => 'L',
        'liters' => 'L',
        'ml' => 'ML',
        'millilitre' => 'ML',
        'milliliter' => 'ML',
        'm' => 'M',
        'metre' => 'M',
        'meter' => 'M',
        'metres' => 'M',
        'meters' => 'M',
        'pair' => 'PAIR',
        'dozen' => 'DOZEN',
        'license' => 'LICENSE',
        'licence' => 'LICENSE',
        'user' => 'USER',
        'service' => 'SERVICE',
    ];

    return $aliases[$key] ?? strtoupper($key);
}

function inventoryUnitProfileForItem(string $assetType = '', string $categoryName = '', string $itemName = '', string $brandModel = '', string $description = ''): array {
    $profiles = inventoryUnitProfiles();
    $profileById = static function (string $id) use ($profiles): array {
        foreach ($profiles as $profile) {
            if ($profile['id'] === $id) {
                return $profile;
            }
        }
        return end($profiles);
    };
    $keywordMatches = static function (string $text, string $keyword): bool {
        $keyword = strtolower(trim($keyword));
        if ($keyword === '') {
            return false;
        }
        if (str_contains($keyword, ' ')) {
            return str_contains($text, $keyword);
        }
        return (bool)preg_match('/\b' . preg_quote($keyword, '/') . '\b/', $text);
    };
    $matchProfile = static function (string $text, array $priority) use ($profileById, $keywordMatches): ?array {
        foreach ($priority as $id) {
            $profile = $profileById($id);
            foreach ($profile['keywords'] as $keyword) {
                if ($keywordMatches($text, $keyword)) {
                    return $profile;
                }
            }
        }
        return null;
    };

    $itemText = strtolower(trim($assetType . ' ' . $itemName . ' ' . $brandModel));
    $categoryText = strtolower(trim($categoryName));
    $itemPriority = ['software_service', 'paper', 'cable', 'ict_hardware', 'kit_set', 'machinery_equipment', 'furniture', 'safety', 'packaging', 'liquid_chemical', 'dry_bulk', 'packaged_consumable'];
    $categoryPriority = ['paper', 'cable', 'ict_hardware', 'packaging', 'liquid_chemical', 'dry_bulk', 'machinery_equipment', 'furniture', 'safety', 'packaged_consumable'];

    $matched = $matchProfile($itemText, $itemPriority);
    if ($matched) {
        return $matched;
    }
    $matched = $matchProfile($categoryText, $categoryPriority);
    if ($matched) {
        return $matched;
    }

    $assetTypeKey = strtolower(trim($assetType));
    if (in_array($assetTypeKey, ['fixed asset', 'laboratory equipment', 'office equipment'], true)) {
        return $profileById('countable_default');
    }

    if ($assetTypeKey === 'tool') {
        return $profileById('kit_set');
    }
    if (in_array($assetTypeKey, ['consumable', 'spare part'], true)) {
        return $profileById('packaged_consumable');
    }

    return end($profiles);
}

function inventoryUnitOptionsForItem(string $assetType = '', string $categoryName = '', string $itemName = '', string $brandModel = '', string $description = ''): array {
    $catalog = inventoryUnitCatalog();
    $profile = inventoryUnitProfileForItem($assetType, $categoryName, $itemName, $brandModel, $description);
    $options = [];

    foreach ($profile['units'] as $code) {
        if (isset($catalog[$code])) {
            $options[$code] = $catalog[$code];
        }
    }

    return $options ?: ['EA' => $catalog['EA']];
}

function inventoryDefaultUnitForItem(string $assetType = '', string $categoryName = '', string $itemName = '', string $brandModel = '', string $description = ''): string {
    $profile = inventoryUnitProfileForItem($assetType, $categoryName, $itemName, $brandModel, $description);
    return $profile['default'] ?? 'EA';
}

function inventoryNormalizeUnitForItem(?string $unit, string $assetType = '', string $categoryName = '', string $itemName = '', string $brandModel = '', string $description = ''): string {
    $code = inventoryNormalizeUnitCode($unit);
    $options = inventoryUnitOptionsForItem($assetType, $categoryName, $itemName, $brandModel, $description);

    if ($code !== '' && isset($options[$code])) {
        return $code;
    }

    return inventoryDefaultUnitForItem($assetType, $categoryName, $itemName, $brandModel, $description);
}

function inventoryDisplayUnit(?string $unit, string $assetType = '', string $categoryName = '', string $itemName = '', string $brandModel = '', string $description = ''): string {
    $code = ($assetType || $categoryName || $itemName || $brandModel || $description)
        ? inventoryNormalizeUnitForItem($unit, $assetType, $categoryName, $itemName, $brandModel, $description)
        : inventoryNormalizeUnitCode($unit);
    $catalog = inventoryUnitCatalog();

    return $catalog[$code]['short'] ?? ($code ?: 'EA');
}

function inventoryUnitFrontendPayload(): array {
    $catalog = inventoryUnitCatalog();
    $profiles = array_map(static function (array $profile): array {
        return [
            'id' => $profile['id'],
            'label' => $profile['label'],
            'units' => $profile['units'],
            'default' => $profile['default'],
            'hint' => $profile['hint'],
            'keywords' => $profile['keywords'],
        ];
    }, inventoryUnitProfiles());

    return [
        'catalog' => $catalog,
        'profiles' => $profiles,
    ];
}

/**
 * Build pagination values for list pages.
 */
function getPagination(int $totalItems, int $itemsPerPage = 10, string $pageParam = 'page'): array {
    $itemsPerPage = max(1, $itemsPerPage);
    $totalPages = max(1, (int)ceil($totalItems / $itemsPerPage));
    $page = max(1, (int)($_GET[$pageParam] ?? 1));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $itemsPerPage;
    $pageStart = $totalItems ? $offset + 1 : 0;
    $pageEnd = min($offset + $itemsPerPage, $totalItems);

    return [
        'page' => $page,
        'per_page' => $itemsPerPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'start' => $pageStart,
        'end' => $pageEnd,
        'page_param' => $pageParam,
    ];
}

/**
 * Render the shared pagination bar used by database list pages.
 */
function renderPaginationBar(array $pagination, int $totalItems, array $excludeParams = [], ?string $pageParam = null): string {
    if (($pagination['total_pages'] ?? 1) <= 1) {
        return '';
    }

    $page = (int)$pagination['page'];
    $totalPages = (int)$pagination['total_pages'];
    $pageParam = $pageParam ?: ($pagination['page_param'] ?? 'page');
    $params = $_GET;
    foreach (array_merge([$pageParam], $excludeParams) as $key) {
        unset($params[$key]);
    }

    $urlFor = function (int $targetPage) use ($params, $pageParam): string {
        $query = http_build_query(array_merge($params, [$pageParam => $targetPage]));
        return basename($_SERVER['PHP_SELF']) . ($query ? '?' . $query : '');
    };

    $windowStart = max(1, $page - 2);
    $windowEnd = min($totalPages, $page + 2);
    if ($windowEnd - $windowStart < 4) {
        $windowStart = max(1, min($windowStart, $windowEnd - 4));
        $windowEnd = min($totalPages, max($windowEnd, $windowStart + 4));
    }

    ob_start();
    ?>
    <div class="pagination-bar">
        <nav class="pagination-nav pagination-nav-left" aria-label="Previous pages">
            <a class="pagination-link pagination-direction <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $page > 1 ? clean($urlFor($page - 1)) : '#' ?>" aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>">&lt;&lt;Previous</a>
            <?php if ($windowStart > 1): ?>
                <a class="pagination-link" href="<?= clean($urlFor(1)) ?>">1</a>
                <?php if ($windowStart > 2): ?><span class="pagination-ellipsis">...</span><?php endif; ?>
            <?php endif; ?>
            <?php for ($p = $windowStart; $p <= $windowEnd; $p++): ?>
                <a class="pagination-link <?= $p === $page ? 'active' : '' ?>" href="<?= clean($urlFor($p)) ?>" aria-current="<?= $p === $page ? 'page' : 'false' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </nav>
        <div class="pagination-summary">
            Showing <?= number_format((int)$pagination['start']) ?>-<?= number_format((int)$pagination['end']) ?> of <?= number_format($totalItems) ?>
        </div>
        <nav class="pagination-nav pagination-nav-right" aria-label="Next pages">
            <?php if ($windowEnd < $totalPages): ?>
                <?php if ($windowEnd < $totalPages - 1): ?><span class="pagination-ellipsis">...</span><?php endif; ?>
                <a class="pagination-link" href="<?= clean($urlFor($totalPages)) ?>"><?= $totalPages ?></a>
            <?php endif; ?>
            <a class="pagination-link pagination-direction <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= $page < $totalPages ? clean($urlFor($page + 1)) : '#' ?>" aria-disabled="<?= $page >= $totalPages ? 'true' : 'false' ?>">Next&gt;&gt;</a>
        </nav>
    </div>
    <?php
    return ob_get_clean();
}

