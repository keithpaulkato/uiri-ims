<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole('Administrator','Campus Manager');
$pdo = db();

// Filters
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$branchId = (int)($_GET['branch_id'] ?? 0);
$userId = (int)($_GET['user_id'] ?? 0);
$success = isset($_GET['success']) && $_GET['success'] !== '' ? (int)$_GET['success'] : null;

$params = [];
$where = "WHERE 1=1";
if ($from) { $where .= " AND lh.created_at >= ?"; $params[] = $from . ' 00:00:00'; }
if ($to) { $where .= " AND lh.created_at <= ?"; $params[] = $to . ' 23:59:59'; }
if ($branchId) { $where .= " AND lh.branch_id = ?"; $params[] = $branchId; }
if ($userId) { $where .= " AND lh.user_id = ?"; $params[] = $userId; }
if ($success !== null) { $where .= " AND lh.success = ?"; $params[] = $success; }

// If export=csv, output CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $pdo->prepare("SELECT lh.*, u.full_name AS user_name, b.name AS branch_name, s.name AS section_name, d.name AS department_name FROM login_history lh LEFT JOIN users u ON lh.user_id = u.id LEFT JOIN branches b ON lh.branch_id = b.id LEFT JOIN sections s ON lh.section_id = s.id LEFT JOIN departments d ON lh.department_id = d.id $where ORDER BY lh.created_at DESC");
    $stmt->execute($params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="login_history_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','User ID','User Name','Branch','Section','Department','Success','IP','User Agent','Details','Timestamp']);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [
            $row['id'],
            $row['user_id'],
            $row['user_name'] ?? '',
            $row['branch_name'] ?? '',
            $row['section_name'] ?? '',
            $row['department_name'] ?? '',
            $row['success'] ? 'Success' : 'Failure',
            $row['ip_address'] ?? '',
            $row['user_agent'] ?? '',
            $row['details'] ?? '',
            formatDateTime($row['created_at'] ?? '', true)
        ]);
    }
    fclose($out);
    exit;
}

// Normal page view
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM login_history lh $where");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$pagination = getPagination($totalRows, 10);

$stmt = $pdo->prepare("SELECT lh.*, u.full_name AS user_name, b.name AS branch_name, s.name AS section_name, d.name AS department_name FROM login_history lh LEFT JOIN users u ON lh.user_id = u.id LEFT JOIN branches b ON lh.branch_id = b.id LEFT JOIN sections s ON lh.section_id = s.id LEFT JOIN departments d ON lh.department_id = d.id $where ORDER BY lh.created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$branches = $pdo->query("SELECT * FROM branches ORDER BY name")->fetchAll();
$users = $pdo->query("SELECT id, full_name FROM users ORDER BY full_name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div><h1 class="page-title">Login History</h1><p class="page-sub"><?= number_format($totalRows) ?> sign-in attempts available for review and CSV export</p></div>
    <div class="page-actions"><a class="btn" href="login_history.php?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>">Export CSV</a></div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;align-items:end;">
            <div class="form-group"><label>From</label><input type="date" name="from" value="<?= clean($from) ?>"></div>
            <div class="form-group"><label>To</label><input type="date" name="to" value="<?= clean($to) ?>"></div>
            <div class="form-group"><label>Branch</label>
                <select name="branch_id"><option value="0">All</option><?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>" <?= $branchId== $b['id'] ? 'selected':'' ?>><?= clean($b['name']) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group"><label>User</label>
                <select name="user_id"><option value="0">All</option><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= $userId== $u['id'] ? 'selected':'' ?>><?= clean($u['full_name']) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group"><label>Result</label>
                <select name="success"><option value="">All</option><option value="1" <?= $success==='1' ? 'selected':'' ?>>Success</option><option value="0" <?= $success==='0' ? 'selected':'' ?>>Failure</option></select>
            </div>
            <div><button class="btn btn-primary">Filter</button></div>
        </form>

        <table class="data-table">
            <thead><tr><th>#</th><th>User</th><th>Branch</th><th>Section</th><th>Department</th><th>Result</th><th>IP</th><th>User Agent</th><th>Details</th><th>Time</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $i=>$r): ?>
                <tr>
                    <td><?= $pagination['offset'] + $i + 1 ?></td>
                    <td><?= clean($r['user_name'] ?? '—') ?> (<?= $r['user_id'] ?? '—' ?>)</td>
                    <td><?= clean($r['branch_name'] ?? '—') ?></td>
                    <td><?= clean($r['section_name'] ?? '—') ?></td>
                    <td><?= clean($r['department_name'] ?? '—') ?></td>
                    <td><span class="badge <?= $r['success'] ? 'badge-success':'badge-danger' ?>"><?= $r['success'] ? 'Success':'Failure' ?></span></td>
                    <td><?= clean($r['ip_address'] ?? '') ?></td>
                    <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= clean($r['user_agent'] ?? '') ?></td>
                    <td><?= clean($r['details'] ?? '') ?></td>
                    <td><?= formatDateTime($r['created_at'], true) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?= renderPaginationBar($pagination, $totalRows, ['export']) ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

