<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
$pageTitle = 'Procurement';
$activePage = 'procurement';
$user = currentUser();
$pdo = db();
$canManage = hasRole('Administrator', 'Store Manager');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_request') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $supplierId = (int)($_POST['supplier_id'] ?? 0) ?: null;
        $requestCode = 'PR-' . date('Ymd') . '-' . rand(100, 999);
        if ($title) {
            $pdo->prepare("INSERT INTO procurement_requests (request_code, branch_id, requested_by, supplier_id, title, description, requested_date, status) VALUES (?, ?, ?, ?, ?, ?, CURDATE(), 'Pending')")
                ->execute([$requestCode, $user['branch_id'], $user['id'], $supplierId, $title, $description]);
            auditLog('CREATE_PROCUREMENT_REQUEST', 'procurement_requests', $pdo->lastInsertId(), "Requested procurement: $title");
            setFlash('success', 'Procurement request created.');
        } else {
            setFlash('error', 'A request title is required.');
        }
    } elseif ($action === 'create_po') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        $poCode = 'PO-' . date('Ymd') . '-' . rand(1000, 9999);
        $request = $pdo->prepare("SELECT * FROM procurement_requests WHERE id = ?");
        $request->execute([$requestId]);
        $requestRow = $request->fetch();
        if ($requestRow && $supplierId) {
            $pdo->prepare("INSERT INTO purchase_orders (po_code, procurement_request_id, supplier_id, branch_id, created_by, order_date, status) VALUES (?, ?, ?, ?, ?, CURDATE(), 'Approved')")
                ->execute([$poCode, $requestId, $supplierId, $requestRow['branch_id'], $user['id']]);
            $pdo->prepare("UPDATE procurement_requests SET status = 'Approved' WHERE id = ?")->execute([$requestId]);
            auditLog('CREATE_PURCHASE_ORDER', 'purchase_orders', $pdo->lastInsertId(), "Created purchase order $poCode");
            setFlash('success', 'Purchase order created.');
        } else {
            setFlash('error', 'Invalid purchase order details.');
        }
    } elseif ($action === 'receive_grn') {
        $poId = (int)($_POST['po_id'] ?? 0);
        $grnCode = 'GRN-' . date('Ymd') . '-' . rand(1000, 9999);
        $remarks = trim($_POST['remarks'] ?? '');
        $pdo->prepare("INSERT INTO goods_received_notes (grn_code, purchase_order_id, received_by, received_date, remarks) VALUES (?, ?, ?, CURDATE(), ?)")
            ->execute([$grnCode, $poId, $user['id'], $remarks]);
        auditLog('RECEIVE_GRN', 'goods_received_notes', $pdo->lastInsertId(), "Received goods for PO $poId");
        setFlash('success', 'Goods received note recorded.');
    }

    header('Location: procurement.php');
    exit;
}

$totalRequests = (int)$pdo->query("SELECT COUNT(*) FROM procurement_requests")->fetchColumn();
$requestPagination = getPagination($totalRequests, 10, 'request_page');
$requests = $pdo->query("SELECT pr.*, u.full_name AS requested_by_name, s.company_name AS supplier_name FROM procurement_requests pr JOIN users u ON pr.requested_by = u.id LEFT JOIN suppliers s ON pr.supplier_id = s.id ORDER BY pr.created_at DESC LIMIT {$requestPagination['per_page']} OFFSET {$requestPagination['offset']}")->fetchAll();

$totalPurchaseOrders = (int)$pdo->query("SELECT COUNT(*) FROM purchase_orders")->fetchColumn();
$poPagination = getPagination($totalPurchaseOrders, 10, 'po_page');
$purchaseOrders = $pdo->query("SELECT po.*, pr.request_code, s.company_name AS supplier_name FROM purchase_orders po JOIN procurement_requests pr ON po.procurement_request_id = pr.id JOIN suppliers s ON po.supplier_id = s.id ORDER BY po.created_at DESC LIMIT {$poPagination['per_page']} OFFSET {$poPagination['offset']}")->fetchAll();

$totalGrns = (int)$pdo->query("SELECT COUNT(*) FROM goods_received_notes")->fetchColumn();
$grnPagination = getPagination($totalGrns, 10, 'grn_page');
$grns = $pdo->query("SELECT grn.*, po.po_code FROM goods_received_notes grn JOIN purchase_orders po ON grn.purchase_order_id = po.id ORDER BY grn.received_date DESC LIMIT {$grnPagination['per_page']} OFFSET {$grnPagination['offset']}")->fetchAll();
$suppliers = getSupplierOptions(true);

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div><h1 class="page-title">Procurement Management</h1><p class="page-sub"><?= number_format($totalRequests) ?> requests, <?= number_format($totalPurchaseOrders) ?> orders, and <?= number_format($totalGrns) ?> goods received notes</p></div>
    <?php if ($canManage): ?><div class="page-actions"><button class="btn btn-primary" onclick="openModal('procurementRequestModal')">New Request</button></div><?php endif; ?>
</div>

<div class="card">
    <div class="card-header"><h3>Procurement Requests</h3></div>
    <div class="card-body p0">
        <?php if ($requests): ?>
        <table class="data-table">
            <thead><tr><th>Code</th><th>Title</th><th>Requested By</th><th>Supplier</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($requests as $row): ?>
            <tr>
                <td><?= clean($row['request_code']) ?></td>
                <td><?= clean($row['title']) ?></td>
                <td><?= clean($row['requested_by_name']) ?></td>
                <td><?= clean($row['supplier_name'] ?: '—') ?></td>
                <td><span class="badge <?= $row['status'] === 'Approved' ? 'badge-success' : 'badge-warn' ?>"><?= clean($row['status']) ?></span></td>
                <td>
                    <?php if ($canManage): ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="create_po">
                        <input type="hidden" name="request_id" value="<?= $row['id'] ?>">
                        <select name="supplier_id" required style="padding:0.35rem;">
                            <option value="">Select supplier</option>
                            <?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= clean($s['company_name']) ?></option><?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-outline" style="margin-left:0.25rem;">Create PO</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?= renderPaginationBar($requestPagination, $totalRequests, ['po_page', 'grn_page']) ?>
        <?php else: ?><div class="empty-state"><p>No procurement requests found.</p></div><?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top: 20px;">
    <div class="card-header"><h3>Purchase Orders</h3></div>
    <div class="card-body p0">
        <?php if ($purchaseOrders): ?>
        <table class="data-table">
            <thead><tr><th>PO Code</th><th>Request</th><th>Supplier</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($purchaseOrders as $row): ?>
            <tr>
                <td><?= clean($row['po_code']) ?></td>
                <td><?= clean($row['request_code']) ?></td>
                <td><?= clean($row['supplier_name']) ?></td>
                <td><span class="badge badge-blue"><?= clean($row['status']) ?></span></td>
                <td><?= clean($row['order_date']) ?></td>
                <td>
                    <?php if ($canManage): ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="receive_grn">
                        <input type="hidden" name="po_id" value="<?= $row['id'] ?>">
                        <input type="text" name="remarks" placeholder="GRN remarks" style="padding:0.35rem;">
                        <button type="submit" class="btn btn-primary" style="margin-left:0.25rem;">Receive</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?= renderPaginationBar($poPagination, $totalPurchaseOrders, ['request_page', 'grn_page']) ?>
        <?php else: ?><div class="empty-state"><p>No purchase orders found.</p></div><?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top: 20px;">
    <div class="card-header"><h3>Goods Received Notes</h3></div>
    <div class="card-body p0">
        <?php if ($grns): ?>
        <table class="data-table">
            <thead><tr><th>GRN Code</th><th>PO Code</th><th>Received By</th><th>Date</th><th>Remarks</th></tr></thead>
            <tbody>
            <?php foreach ($grns as $row): ?>
            <tr><td><?= clean($row['grn_code']) ?></td><td><?= clean($row['po_code']) ?></td><td><?= clean($row['received_by']) ?></td><td><?= clean($row['received_date']) ?></td><td><?= clean($row['remarks'] ?: '—') ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?= renderPaginationBar($grnPagination, $totalGrns, ['request_page', 'po_page']) ?>
        <?php else: ?><div class="empty-state"><p>No goods received notes found.</p></div><?php endif; ?>
    </div>
</div>

<?php if ($canManage): ?>
<div class="modal-overlay" id="procurementRequestModal">
    <div class="modal">
        <div class="modal-header"><h3>New Procurement Request</h3><button class="modal-close" onclick="closeModal('procurementRequestModal')">×</button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="add_request">
            <div class="modal-body">
                <div class="form-group"><label>Title *</label><input type="text" name="title" required></div>
                <div class="form-group"><label>Description</label><textarea name="description" rows="3"></textarea></div>
                <div class="form-group"><label>Preferred Supplier</label><select name="supplier_id"><option value="">Select supplier</option><?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= clean($s['company_name']) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('procurementRequestModal')">Cancel</button><button type="submit" class="btn btn-primary">Submit Request</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
