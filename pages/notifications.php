<?php
require_once __DIR__ . '/../includes/config.php';

requireLogin();

$pageTitle = 'Notifications';
$activePage = 'notifications';
$user = currentUser();
$pdo = db();

// Handle mark as read
if (isset($_GET['mark_read']) && $_GET['mark_read']) {
    verifyCsrf();
    $id = (int)$_GET['mark_read'];
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$id, $user['id']]);
}

// Handle mark all as read
if (isset($_GET['mark_all_read'])) {
    verifyCsrf();
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$user['id']]);
    setFlash('success', 'All notifications marked as read.');
    header('Location: notifications.php');
    exit;
}

// Handle delete
if (isset($_GET['delete']) && $_GET['delete']) {
    verifyCsrf();
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?")->execute([$id, $user['id']]);
    setFlash('success', 'Notification deleted.');
    header('Location: notifications.php');
    exit;
}

// Filters
$typeFilter = $_GET['type'] ?? '';
$readFilter = $_GET['read'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build query
$where = ['(user_id = ? OR (user_id IS NULL AND branch_id = ?))'];
$params = [$user['id'], $user['branch_id']];

if ($typeFilter) {
    $where[] = "type = ?";
    $params[] = $typeFilter;
}

if ($readFilter === 'read') {
    $where[] = "is_read = 1";
} elseif ($readFilter === 'unread') {
    $where[] = "is_read = 0";
}

if ($dateFrom) {
    $where[] = "DATE(created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $where[] = "DATE(created_at) <= ?";
    $params[] = $dateTo;
}

$whereSQL = implode(' AND ', $where);

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE $whereSQL");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pagination = getPagination($total, 10);

// Get notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE $whereSQL ORDER BY created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmt->execute($params);
$notifications = $stmt->fetchAll();

// Get unique types for filter
$typesStmt = $pdo->prepare("SELECT DISTINCT type FROM notifications WHERE user_id = ? OR (user_id IS NULL AND branch_id = ?) ORDER BY type");
$typesStmt->execute([$user['id'], $user['branch_id']]);
$types = $typesStmt->fetchAll();

// Group by type for display
$grouped = [];
foreach ($notifications as $notif) {
    $type = $notif['type'];
    if (!isset($grouped[$type])) {
        $grouped[$type] = [];
    }
    $grouped[$type][] = $notif;
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_SHORT ?> — Notifications</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Notifications</h1>
        <p class="page-sub">Stay on top of stock alerts, requests, transfers, approvals, and branch activity with a calm, clear review space that helps your team follow through without delay.</p>
    </div>
    <div class="page-actions">
        <a href="?mark_all_read&csrf_token=<?= csrfToken() ?>" class="btn btn-outline">Mark All as Read</a>
    </div>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'error' ? 'error' : 'info') ?>"><?= clean($flash['message']) ?></div>
<?php endif; ?>

<div class="notification-summary-grid">
    <div class="notification-summary-card accent-gold">
        <div class="notification-summary-icon">🔔</div>
        <div>
            <strong><?= $total ?></strong>
            <span>Total</span>
        </div>
    </div>
    <div class="notification-summary-card accent-sky">
        <div class="notification-summary-icon">●</div>
        <div>
            <strong><?= count(array_filter($notifications, fn($n) => !$n['is_read'])) ?></strong>
            <span>Unread</span>
        </div>
    </div>
    <div class="notification-summary-card accent-emerald">
        <div class="notification-summary-icon">↻</div>
        <div>
            <strong><?= count(array_filter($notifications, fn($n) => $n['type'] === 'low_stock')) ?></strong>
            <span>Low Stock</span>
        </div>
    </div>
    <div class="notification-summary-card accent-violet">
        <div class="notification-summary-icon">⇄</div>
        <div>
            <strong><?= count(array_filter($notifications, fn($n) => $n['type'] === 'transfers')) ?></strong>
            <span>Transfers</span>
        </div>
    </div>
</div>

<div class="notification-hero-panel">
    <div class="notification-hero-copy">
        <div class="notification-badge notification-badge-strong">Operations overview</div>
        <h3>This is your calm command center for branch activity.</h3>
        <p>This notifications area brings important operational updates together in one place so your team can quickly see what needs attention, what is already underway, and what has already been handled. It is designed to make daily follow-up easier, clearer, and less stressful when the workload becomes busy.</p>
        <p>Use this page to review low-stock warnings, fresh requests, transfer updates, approvals, and other activity that can affect planning, fulfillment, and branch coordination. The more consistently this space is reviewed, the easier it becomes to stay ahead of issues before they grow into larger problems.</p>
    </div>
    <div class="notification-hero-stack">
        <div class="notification-tip">
            <strong>How to work with this page</strong>
            <p>Filter by type or read state to narrow the list to what matters right now. Review unread updates first, mark them as read after you act, and use the clear actions to keep the page tidy and useful.</p>
        </div>
        <div class="notification-tip">
            <strong>Why this matters</strong>
            <p>When stock levels drop, requests pile up, or transfers need attention, this page becomes a practical reminder system. It helps your team focus on the next best action instead of losing track of small but important updates.</p>
        </div>
    </div>
</div>

<div class="notification-guidance-grid">
    <div class="notification-guidance-card">
        <h4>Watch stock pressure</h4>
        <p>Low-stock notices are meant to highlight the items that may need replenishment soon. Review them quickly so you can prevent avoidable shortages before they affect operations or customer service.</p>
    </div>
    <div class="notification-guidance-card">
        <h4>Check request urgency</h4>
        <p>New requests often carry immediate priorities that should not be left waiting. Keeping them visible helps your team respond quickly and makes the workflow feel more organized and accountable.</p>
    </div>
    <div class="notification-guidance-card">
        <h4>Confirm transfer progress</h4>
        <p>Transfer updates help the branch understand what is moving, what is pending, and what needs follow-up. Staying aware of them improves coordination and reduces confusion across departments.</p>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Activity Center</h3>
        <div class="filter-bar">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <select name="type" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="low_stock" <?= $typeFilter === 'low_stock' ? 'selected' : '' ?>>Low Stock</option>
                    <option value="pending_requests" <?= $typeFilter === 'pending_requests' ? 'selected' : '' ?>>New Requests</option>
                    <option value="transfers" <?= $typeFilter === 'transfers' ? 'selected' : '' ?>>Transfers</option>
                    <option value="approvals" <?= $typeFilter === 'approvals' ? 'selected' : '' ?>>Approvals</option>
                </select>
            </div>
            <div class="filter-group">
                <select name="read" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="unread" <?= $readFilter === 'unread' ? 'selected' : '' ?>>Unread</option>
                    <option value="read" <?= $readFilter === 'read' ? 'selected' : '' ?>>Read</option>
                </select>
            </div>
        </form>
        </div>
    </div>

    <div class="card-body">
        <?php if ($notifications): ?>
            <?php foreach ($grouped as $type => $notifs): ?>
            <div class="notification-group-card">
                <h4>
                    <span><?= ucfirst(str_replace('_', ' ', $type)) ?></span>
                    <span class="notification-pill"><?= count($notifs) ?></span>
                </h4>
                <?php foreach ($notifs as $notif): ?>
                <div class="notification-item <?= $notif['is_read'] ? '' : 'unread' ?>">
                    <div>
                        <div class="notification-badge"><?= ucfirst(str_replace('_', ' ', $notif['type'])) ?></div>
                        <h5><?= clean($notif['title']) ?></h5>
                        <p><?= clean($notif['message']) ?></p>
                        <div class="notification-meta">
                            <?= formatTimeSince($notif['created_at']) ?>
                            <?php if ($notif['link']): ?>
                            • <a href="<?= clean($notif['link']) ?>">View Details</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="notification-actions">
                        <?php if (!$notif['is_read']): ?>
                        <a href="?mark_read=<?= $notif['id'] ?>&csrf_token=<?= csrfToken() ?>" class="btn btn-sm btn-secondary">Mark Read</a>
                        <?php endif; ?>
                        <a href="?delete=<?= $notif['id'] ?>&csrf_token=<?= csrfToken() ?>"
                           class="btn btn-sm btn-danger js-delete-confirm"
                           data-delete-title="Delete notification?"
                           data-delete-text="<?= clean('Delete notification: ' . ($notif['title'] ?: 'Untitled notification') . '?') ?>"
                           data-delete-confirm="Yes, delete notification">Delete</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>

            <?= renderPaginationBar($pagination, $total, ['mark_read', 'mark_all_read', 'delete', 'csrf_token']) ?>
        <?php else: ?>
            <p style="text-align:center;padding:40px;color:#999;">No notifications found.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
