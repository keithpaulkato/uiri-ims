<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
$pageTitle = 'Equipment Maintenance';
$activePage = 'maintenance';
$user = currentUser();
$pdo = db();
$canManage = hasRole('Administrator', 'Store Manager');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $equipmentName = trim($_POST['equipment_name'] ?? '');
        $equipmentType = trim($_POST['equipment_type'] ?? '');
        $maintenanceDate = $_POST['maintenance_date'] ?? date('Y-m-d');
        $nextServiceDate = $_POST['next_service_date'] ?? null;
        $serviceCost = (float)($_POST['service_cost'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'Scheduled';
        if ($equipmentName && $equipmentType) {
            $pdo->prepare("INSERT INTO equipment_maintenance (equipment_name, equipment_type, branch_id, assigned_to, maintenance_date, next_service_date, service_cost, status, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$equipmentName, $equipmentType, $user['branch_id'], $user['id'], $maintenanceDate, $nextServiceDate, $serviceCost, $status, $description]);
            auditLog('ADD_MAINTENANCE', 'equipment_maintenance', $pdo->lastInsertId(), "Scheduled maintenance for $equipmentName");
            setFlash('success', 'Maintenance record added.');
        } else {
            setFlash('error', 'Equipment name and type are required.');
        }
    }

    header('Location: maintenance.php');
    exit;
}

$totalRecords = (int)$pdo->query("SELECT COUNT(*) FROM equipment_maintenance")->fetchColumn();
$pagination = getPagination($totalRecords, 10);
$records = $pdo->query("SELECT em.*, b.name AS branch_name, u.full_name AS assigned_name FROM equipment_maintenance em JOIN branches b ON em.branch_id = b.id LEFT JOIN users u ON em.assigned_to = u.id ORDER BY em.maintenance_date DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}")->fetchAll();
$branches = $pdo->query("SELECT id, name FROM branches ORDER BY is_headquarters DESC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div><h1 class="page-title">Equipment Maintenance</h1><p class="page-sub"><?= number_format($totalRecords) ?> maintenance schedules, history, service costs and equipment status records</p></div>
    <?php if ($canManage): ?><div class="page-actions"><button class="btn btn-primary" onclick="openModal('maintenanceModal')">Add Record</button></div><?php endif; ?>
</div>

<div class="card">
    <div class="card-header"><h3>Maintenance Schedule</h3></div>
    <div class="card-body p0">
        <?php if ($records): ?>
        <table class="data-table">
            <thead><tr><th>Equipment</th><th>Type</th><th>Branch</th><th>Maintenance Date</th><th>Next Service</th><th>Cost</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($records as $row): ?>
            <tr>
                <td><strong><?= clean($row['equipment_name']) ?></strong></td>
                <td><?= clean($row['equipment_type']) ?></td>
                <td><?= clean($row['branch_name']) ?></td>
                <td><?= clean($row['maintenance_date']) ?></td>
                <td><?= clean($row['next_service_date'] ?: '—') ?></td>
                <td><?= ugx($row['service_cost']) ?></td>
                <td><span class="badge <?= $row['status'] === 'Completed' ? 'badge-success' : 'badge-warn' ?>"><?= clean($row['status']) ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?= renderPaginationBar($pagination, $totalRecords) ?>
        <?php else: ?><div class="empty-state"><p>No maintenance records found.</p></div><?php endif; ?>
    </div>
</div>

<?php if ($canManage): ?>
<div class="modal-overlay" id="maintenanceModal">
    <div class="modal">
        <div class="modal-header"><h3>New Maintenance Record</h3><button class="modal-close" onclick="closeModal('maintenanceModal')">×</button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-grid-2">
                    <div class="form-group"><label>Equipment Name *</label><input type="text" name="equipment_name" required></div>
                    <div class="form-group"><label>Equipment Type *</label><input type="text" name="equipment_type" required placeholder="CNC, Laboratory, Welding, Compressor, Generator, Production"></div>
                    <div class="form-group"><label>Maintenance Date</label><input type="date" name="maintenance_date" value="<?= date('Y-m-d') ?>"></div>
                    <div class="form-group"><label>Next Service Date</label><input type="date" name="next_service_date"></div>
                    <div class="form-group"><label>Service Cost (UGX)</label><input type="number" name="service_cost" min="0" step="100"></div>
                    <div class="form-group"><label>Status</label><select name="status"><option value="Scheduled">Scheduled</option><option value="In Progress">In Progress</option><option value="Completed">Completed</option></select></div>
                </div>
                <div class="form-group"><label>Description</label><textarea name="description" rows="3"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('maintenanceModal')">Cancel</button><button type="submit" class="btn btn-primary">Save Record</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
