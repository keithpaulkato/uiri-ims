<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin(); requireRole('Administrator');
$pageTitle = 'Audit Trail'; $activePage = 'audit'; $pdo = db();

$search = trim($_GET['search']??''); $dateFrom = $_GET['date_from']??''; $dateTo = $_GET['date_to']??'';
$where=[]; $params=[];
if ($search) { $where[]="(a.action LIKE ? OR a.details LIKE ? OR u.full_name LIKE ? OR u.username LIKE ?)"; $params=array_merge($params,["%$search%","%$search%","%$search%","%$search%"]); }
if ($dateFrom) { $where[]="DATE(a.created_at)>=?"; $params[]=$dateFrom; }
if ($dateTo) { $where[]="DATE(a.created_at)<=?"; $params[]=$dateTo; }
$whereSQL = $where ? 'WHERE '.implode(' AND ',$where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_log a LEFT JOIN users u ON a.user_id=u.id $whereSQL");
$countStmt->execute($params);
$totalLogs = (int)$countStmt->fetchColumn();
$pagination = getPagination($totalLogs, 10);

$logs = $pdo->prepare("SELECT a.*, u.full_name, u.username, b.name AS branch_name FROM audit_log a LEFT JOIN users u ON a.user_id=u.id LEFT JOIN branches b ON a.branch_id=b.id $whereSQL ORDER BY a.created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$logs->execute($params); $logs=$logs->fetchAll();

$activityLabels = [
    'LOGIN' => 'Login',
    'LOGOUT' => 'Logout',
    'ADD_ITEM' => 'Item Created',
    'EDIT_ITEM' => 'Item Updated',
    'DELETE_ITEM' => 'Item Deleted',
    'STOCK_IN' => 'Stock Movement',
    'STOCK_OUT' => 'Stock Movement',
    'ADJUST_STOCK' => 'Stock Adjustment',
    'APPROVE_REQUEST' => 'Approval',
    'REJECT_REQUEST' => 'Approval',
    'ISSUE_REQUEST' => 'Approval',
    'CREATE_TRANSFER' => 'Transfer',
    'UPDATE_TRANSFER' => 'Transfer',
    'ADD_USER' => 'User Added',
    'EDIT_USER' => 'User Updated',
    'ADD_DEPARTMENT' => 'Department Added',
    'EDIT_DEPARTMENT' => 'Department Updated',
    'DELETE_DEPARTMENT' => 'Department Deleted',
];

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div><h1 class="page-title">Audit Trail</h1><p class="page-sub"><?= number_format($totalLogs) ?> tracked login, item, stock, approval, and transfer activities</p></div>
</div>
<div class="card filter-bar">
    <form method="GET" class="filter-form">
        <div class="filter-group"><div class="input-wrap"><svg class="input-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" name="search" placeholder="Search activity, user or details…" value="<?= clean($search) ?>"></div></div>
        <div class="filter-group"><input type="date" name="date_from" value="<?= clean($dateFrom) ?>"></div>
        <div class="filter-group"><input type="date" name="date_to" value="<?= clean($dateTo) ?>"></div>
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="audit.php" class="btn btn-outline">Reset</a>
    </form>
</div>
<div class="card">
    <div class="card-body p0">
        <?php if ($logs): ?>
        <table class="data-table">
            <thead><tr><th>#</th><th>Activity</th><th>User</th><th>Branch</th><th>Details</th><th>Date</th><th>Time</th><th>IP Address</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $i=>$log): ?>
            <tr>
                <td><?= $pagination['offset'] + $i + 1 ?></td>
                <td><span class="badge badge-info" style="display:inline-block"><?= clean($activityLabels[$log['action']] ?? $log['action']) ?></span></td>
                <td><?= clean($log['full_name'] ?? 'System') ?><span class="item-code"><?= clean($log['username'] ?? '') ?></span></td>
                <td><?= clean($log['branch_name'] ?? '—') ?></td>
                <td style="max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= clean($log['details'] ?: '—') ?></td>
                <td><?= formatDate($log['created_at']) ?></td>
                <td><?= formatTimeWithTimezone($log['created_at'], true) ?></td>
                <td><?= clean($log['ip_address'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?= renderPaginationBar($pagination, $totalLogs) ?>
        <?php else: ?><div class="empty-state"><p>No audit logs found.</p></div><?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
