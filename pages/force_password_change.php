<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");

$pdo = db();
$userId = (int)($_SESSION['user_id'] ?? 0);
$stmt = $pdo->prepare("SELECT id, full_name, email, password, must_change_password FROM users WHERE id = ? AND is_active = 1");
$stmt->execute([$userId]);
$account = $stmt->fetch();

if (!$account) {
    header('Location: ' . BASE_URL . 'includes/logout.php');
    exit;
}

if ((int)($account['must_change_password'] ?? 0) !== 1) {
    header('Location: ' . BASE_URL . 'pages/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $passError = '';

    if ($newPassword === '' || $confirmPassword === '') {
        $error = 'Enter and confirm your new password.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New passwords do not match.';
    } elseif (!validatePassword($newPassword, $passError)) {
        $error = $passError;
    } elseif (password_verify($newPassword, $account['password'])) {
        $error = 'Choose a password different from the temporary password.';
    } else {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $pdo->prepare("
            UPDATE users
            SET password = ?,
                must_change_password = 0,
                password_changed_at = NOW(),
                failed_login_attempts = 0,
                last_login_attempt = NULL,
                remember_token = NULL
            WHERE id = ?
        ")->execute([$hash, $userId]);

        $_SESSION['user']['must_change_password'] = 0;
        session_regenerate_id(true);
        auditLog('PASSWORD_CHANGED', 'users', $userId, 'User changed temporary password on first login');
        setFlash('success', 'Password changed successfully. Welcome to UIRI IMS.');
        header('Location: ' . BASE_URL . 'pages/dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - <?= SITE_SHORT ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <style>
        body.force-password-body {
            min-height: 100vh;
            margin: 0;
            background: #0a1628;
            color: #0f172a;
            font-family: Inter, Arial, sans-serif;
        }
        .force-password-overlay {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background:
                linear-gradient(rgba(10, 22, 40, .84), rgba(10, 22, 40, .88)),
                url("<?= BASE_URL ?>assets/img/uiri-logo.webp") center / 460px no-repeat;
        }
        .force-password-modal {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 24px 70px rgba(2, 8, 23, .36);
            padding: 28px;
        }
        .force-password-logo {
            width: 52px;
            height: 52px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            padding: 5px;
            margin-bottom: 18px;
        }
        .force-password-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .force-password-modal h1 {
            margin: 0;
            color: #0f172a;
            font-size: 1.55rem;
            line-height: 1.2;
            letter-spacing: 0;
        }
        .force-password-modal p {
            margin: 8px 0 20px;
            color: #64748b;
            line-height: 1.6;
            font-size: .92rem;
        }
        .force-password-account {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 12px;
            margin-bottom: 18px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            font-size: .84rem;
        }
        .force-password-account strong,
        .force-password-account span {
            display: block;
        }
        .force-password-account strong {
            color: #0f172a;
        }
        .force-password-account span {
            color: #64748b;
            margin-top: 2px;
        }
        .force-password-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 20px;
        }
        .force-password-actions .btn {
            width: 100%;
            justify-content: center;
        }
        .force-password-signout {
            color: #64748b;
            text-align: center;
            font-size: .84rem;
        }
        .force-password-signout a {
            color: #0f172a;
            font-weight: 700;
            text-decoration: underline;
            text-underline-offset: 4px;
        }
        @media (max-width: 520px) {
            .force-password-overlay {
                padding: 16px;
            }
            .force-password-modal {
                padding: 22px;
            }
        }
    </style>
</head>
<body class="force-password-body">
    <main class="force-password-overlay">
        <section class="force-password-modal" role="dialog" aria-modal="true" aria-labelledby="forcePasswordTitle">
            <div class="force-password-logo">
                <img src="<?= BASE_URL ?>assets/img/uiri-logo.webp" alt="UIRI">
            </div>
            <h1 id="forcePasswordTitle">Create your own password</h1>
            <p>You signed in with a temporary password. Set a private password before continuing. Use at least 8 characters with uppercase, lowercase, and a number.</p>

            <div class="force-password-account">
                <div>
                    <strong><?= clean($account['full_name']) ?></strong>
                    <span><?= clean($account['email']) ?></span>
                </div>
                <span><?= SITE_SHORT ?></span>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-error"><?= clean($error) ?></div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <div class="form-group">
                    <label for="new_password">New password</label>
                    <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password" placeholder="Repeat new password">
                </div>
                <div class="force-password-actions">
                    <button type="submit" class="btn btn-primary">Save new password</button>
                    <div class="force-password-signout">
                        Not your account? <a href="<?= BASE_URL ?>includes/logout.php">Sign out</a>
                    </div>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
