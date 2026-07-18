<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole('Administrator');
$pageTitle = 'Settings';
$activePage = 'settings';
$user = currentUser();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $siteName = trim($_POST['site_name'] ?? '');
    $currencyCode = strtoupper(trim($_POST['currency_code'] ?? ''));
    $threshold = (int)($_POST['low_stock_threshold'] ?? 5);
    $allowRegistration = isset($_POST['allow_self_registration']) ? '1' : '0';

    $smtpHost = trim($_POST['smtp_host'] ?? '');
    $smtpPort = (int)($_POST['smtp_port'] ?? 587);
    $smtpUser = trim($_POST['smtp_user'] ?? '');
    $smtpPass = trim($_POST['smtp_pass'] ?? '');
    $smtpFromEmail = trim($_POST['smtp_from_email'] ?? '');
    $smtpFromName = trim($_POST['smtp_from_name'] ?? '');

    $updates = [
        ['site_name', $siteName],
        ['currency_code', $currencyCode],
        ['low_stock_threshold', (string)$threshold],
        ['allow_self_registration', $allowRegistration],
        ['smtp_host', $smtpHost],
        ['smtp_port', (string)$smtpPort],
        ['smtp_user', $smtpUser],
        ['smtp_pass', $smtpPass],
        ['smtp_from_email', $smtpFromEmail],
        ['smtp_from_name', $smtpFromName]
    ];

    foreach ($updates as [$key, $value]) {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()");
        $stmt->execute([$key, $value, $user['id'] ?? 0]);
    }

    auditLog('UPDATE_SETTINGS', 'settings', 0, 'Updated system settings');
    setFlash('success', 'Settings updated successfully.');
    header('Location: settings.php');
    exit;
}

$settings = [];
foreach ($pdo->query("SELECT setting_key, setting_value FROM settings") as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">System Settings</h1>
        <p class="page-sub">Configure global inventory settings</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <div class="form-section-card">
                <h4>General Settings</h4>
                <p>Core system identity and inventory threshold options.</p>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Site Name</label>
                        <input type="text" name="site_name" value="<?= clean($settings['site_name'] ?? 'UIRI Inventory Management System') ?>">
                    </div>
                    <div class="form-group">
                        <label>Currency Code</label>
                        <input type="text" name="currency_code" maxlength="3" value="<?= clean($settings['currency_code'] ?? 'UGX') ?>">
                    </div>
                    <div class="form-group">
                        <label>Low Stock Threshold</label>
                        <input type="number" name="low_stock_threshold" min="0" value="<?= (int)($settings['low_stock_threshold'] ?? 5) ?>">
                    </div>
                    <div class="form-group">
                        <label style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" name="allow_self_registration" value="1" <?= (($settings['allow_self_registration'] ?? '1') == '1') ? 'checked' : '' ?>>
                            Allow self-registration
                        </label>
                    </div>
                </div>
            </div>

            <div class="form-section-card">
                <h4>Mail Configuration</h4>
                <p>For Gmail, use smtp.gmail.com, port 587, your full Gmail address, and a Google App Password.</p>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>SMTP Host</label>
                        <input type="text" name="smtp_host" placeholder="smtp.gmail.com" value="<?= clean($settings['smtp_host'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>SMTP Port</label>
                        <input type="number" name="smtp_port" min="1" placeholder="587" value="<?= (int)($settings['smtp_port'] ?? 587) ?>">
                    </div>
                    <div class="form-group">
                        <label>SMTP Username</label>
                        <input type="text" name="smtp_user" placeholder="your.name@gmail.com" value="<?= clean($settings['smtp_user'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>SMTP Password</label>
                        <input type="password" name="smtp_pass" placeholder="Google App Password" value="<?= clean($settings['smtp_pass'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>From Email</label>
                        <input type="email" name="smtp_from_email" value="<?= clean($settings['smtp_from_email'] ?? SMTP_FROM_EMAIL) ?>">
                    </div>
                    <div class="form-group">
                        <label>From Name</label>
                        <input type="text" name="smtp_from_name" value="<?= clean($settings['smtp_from_name'] ?? SMTP_FROM_NAME) ?>">
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Save Settings</button>
                <a href="<?= BASE_URL ?>test-email.php" class="btn btn-secondary" style="margin-left:8px;">Test Email Configuration</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
