<?php
require_once __DIR__ . '/includes/config.php';
requireRole('Administrator');

// Set security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");

$user = currentUser();
$pdo = db();
$testResult = null;
$testEmail = '';
$testStatus = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $testEmail = trim($_POST['test_email'] ?? '');
    
    if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        $testStatus = 'Invalid email address.';
        $testResult = 'error';
    } else {
        $mailSettings = getMailSettings();
        $host = $mailSettings['host'];
        $port = (int) $mailSettings['port'];
        $fromEmail = $mailSettings['from_email'];
        $fromName = $mailSettings['from_name'];
        
        // Check if SMTP is configured
        if (empty($host)) {
            $testStatus = 'SMTP not configured. Please configure SMTP settings in System Settings first.';
            $testResult = 'warning';
        } else {
            // Attempt to send test email
            $subject = SITE_SHORT . ' — Test Email';
            $body = "Hello,\n\n" .
                    "This is a test email from " . SITE_NAME . ".\n\n" .
                    "If you received this, your email configuration is working correctly!\n\n" .
                    "Email sent at: " . date('Y-m-d H:i:s') . "\n" .
                    "SMTP Host: " . $host . ":" . $port . "\n\n" .
                    "Best regards,\n" . SITE_SHORT . " Team";
            
            $sent = sendMail($testEmail, $subject, $body, $fromName, $fromEmail, false);
            
            if ($sent) {
                $testStatus = 'Test email sent successfully to ' . htmlspecialchars($testEmail) . '. Check your inbox!';
                $testResult = 'success';
                auditLog('TEST_EMAIL', 'system', 0, 'Test email sent to ' . $testEmail);
            } else {
                $testStatus = 'Failed to send test email. Check SMTP settings and verify credentials.';
                $testResult = 'error';
                auditLog('TEST_EMAIL_FAILED', 'system', 0, 'Test email failed for ' . $testEmail);
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Email Configuration Test</h1>
        <p class="page-sub">Test SMTP configuration and send a test email</p>
    </div>
</div>

<div style="max-width: 600px;">
    <?php if ($testResult): ?>
    <div class="alert alert-<?= $testResult ?>">
        <?php if ($testResult === 'success'): ?>
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        <?php elseif ($testResult === 'error'): ?>
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?php else: ?>
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?php endif; ?>
        <?= htmlspecialchars($testStatus) ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h2>Current Configuration</h2>
        </div>
        <div class="card-body">
            <?php
            $mailSettings = getMailSettings();
            $host = $mailSettings['host'];
            $port = (int) $mailSettings['port'];
            $user_smtp = $mailSettings['user'];
            $fromEmail = $mailSettings['from_email'];
            $fromName = $mailSettings['from_name'];
            ?>
            <table class="table">
                <tr>
                    <td><strong>SMTP Host:</strong></td>
                    <td><?= $host ?: '<span style="color:#999;">Not configured</span>' ?></td>
                </tr>
                <tr>
                    <td><strong>SMTP Port:</strong></td>
                    <td><?= $port ?></td>
                </tr>
                <tr>
                    <td><strong>SMTP Username:</strong></td>
                    <td><?= $user_smtp ?: '<span style="color:#999;">Not configured</span>' ?></td>
                </tr>
                <tr>
                    <td><strong>From Email:</strong></td>
                    <td><?= htmlspecialchars($fromEmail) ?></td>
                </tr>
                <tr>
                    <td><strong>From Name:</strong></td>
                    <td><?= htmlspecialchars($fromName) ?></td>
                </tr>
                <tr>
                    <td><strong>PHPMailer Status:</strong></td>
                    <td>
                        <?php if (file_exists(__DIR__ . '/vendor/autoload.php')): ?>
                        <span style="color: green; font-weight: bold;">✓ Installed</span>
                        <?php else: ?>
                        <span style="color: orange; font-weight: bold;">✗ Not installed (using PHP mail() fallback)</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="card" style="margin-top: 20px;">
        <div class="card-header">
            <h2>Send Test Email</h2>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <div class="form-group">
                    <label for="test_email">Recipient Email Address</label>
                    <input type="email" id="test_email" name="test_email" placeholder="your-email@example.com" required value="<?= htmlspecialchars($testEmail) ?>">
                    <small class="form-note">Enter your email to receive the test message</small>
                </div>
                <button type="submit" class="btn btn-primary">Send Test Email</button>
            </form>
        </div>
    </div>

    <div class="card" style="margin-top: 20px;">
        <div class="card-header">
            <h2>Configuration Steps</h2>
        </div>
        <div class="card-body" style="line-height: 1.8;">
            <ol style="margin-left: 20px;">
                <li>Go to <strong>Settings > System Settings</strong></li>
                <li>Fill in your SMTP details:
                    <ul style="margin: 10px 0 10px 20px;">
                        <li><strong>SMTP Host:</strong> e.g., smtp.gmail.com</li>
                        <li><strong>SMTP Port:</strong> 587 (TLS) or 465 (SSL)</li>
                        <li><strong>SMTP Username:</strong> Your email account</li>
                        <li><strong>SMTP Password:</strong> Google App Password, not your normal Google password</li>
                        <li><strong>From Email:</strong> Email address emails will come from</li>
                        <li><strong>From Name:</strong> Display name in emails</li>
                    </ul>
                </li>
                <li>Click <strong>Save Settings</strong></li>
                <li>Return to this page and send a test email</li>
                <li>Check your inbox for the test message</li>
            </ol>

            <hr style="margin: 20px 0;">
            
            <h3 style="margin-top: 20px;">Recommended SMTP Services:</h3>
            <ul style="margin-left: 20px;">
                <li><strong>Gmail:</strong> smtp.gmail.com:587 (use App Password)</li>
                <li><strong>Office 365:</strong> smtp.office365.com:587</li>
                <li><strong>Mailtrap (Testing):</strong> live.smtp.mailtrap.io:587</li>
                <li><strong>SendGrid:</strong> smtp.sendgrid.net:587</li>
            </ul>

            <h3 style="margin-top: 20px;">Troubleshooting:</h3>
            <ul style="margin-left: 20px;">
                <li>If PHPMailer shows "Not installed", <a href="COMPOSER_INSTALLATION.md" target="_blank">install PHPMailer via Composer</a></li>
                <li>Verify credentials are correct in Settings</li>
                <li>Check firewall allows outgoing port 587 or 465</li>
                <li>Gmail users: Use <a href="https://myaccount.google.com/apppasswords" target="_blank">App Passwords</a>, not your regular password</li>
            </ul>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
