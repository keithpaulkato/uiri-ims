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

