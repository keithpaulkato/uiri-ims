<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('Administrator', 'Campus Manager', 'Store Manager');
$pageTitle = 'Transfers';
$activePage = 'transfers';
$user = currentUser();
$pdo = db();

$isAdmin = hasRole('Administrator');
$canRequest = hasRole('Store Manager');
$canApprove = hasRole('Campus Manager');
$canReceive = hasRole('Store Manager');
$branchId = $user['branch_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $fromBranch = (int)($_POST['from_branch_id'] ?? 0);
        $toBranch = (int)($_POST['to_branch_id'] ?? 0);
        $itemId = (int)($_POST['item_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        $remarks = trim($_POST['remarks'] ?? '');

        if (!$canRequest || $fromBranch !== $branchId) {
            setFlash('error', 'Only store managers can create transfer requests for their assigned branch.');
        } elseif (!$fromBranch || !$toBranch || !$itemId || $quantity <= 0 || $fromBranch === $toBranch) {
            setFlash('error', 'Please provide valid transfer details.');
        } else {
            $itemStmt = $pdo->prepare("SELECT id, current_stock, name FROM inventory_items WHERE id = ? AND branch_id = ? AND is_active = 1");
            $itemStmt->execute([$itemId, $fromBranch]);
            $item = $itemStmt->fetch();
            if (!$item || $item['current_stock'] < $quantity) {
                setFlash('error', 'Selected item is not available for transfer.');
            } else {
                $transferCode = 'TRF-' . date('Ymd') . '-' . rand(1000, 9999);
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("INSERT INTO transfers (transfer_code, from_branch_id, to_branch_id, requested_by, status, request_date, remarks) VALUES (?, ?, ?, ?, 'Requested', CURDATE(), ?)")
                        ->execute([$transferCode, $fromBranch, $toBranch, $user['id'], $remarks]);
                    $transferId = (int)$pdo->lastInsertId();
                    $pdo->prepare("INSERT INTO transfer_items (transfer_id, item_id, quantity, remarks) VALUES (?, ?, ?, ?)")
                        ->execute([$transferId, $itemId, $quantity, $remarks]);
                    $pdo->commit();

                    $notifyStmt = $pdo->prepare("SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE u.is_active = 1 AND u.branch_id = ? AND r.name = 'Campus Manager'");
                    $notifyBranches = [$fromBranch, $toBranch];
                    foreach (array_unique($notifyBranches) as $notifyBranch) {
                        $notifyStmt->execute([$notifyBranch]);
                        foreach ($notifyStmt->fetchAll() as $notifyUser) {
                            $pdo->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'transfer', 'Transfer request pending approval', ?)")
                                ->execute([$notifyUser['id'], 'Transfer request ' . $transferCode . ' is awaiting approval.']);
                        }
                    }

                    auditLog('CREATE_TRANSFER', 'transfers', $transferId, 'Created transfer');
                    setFlash('success', 'Transfer request created successfully.');
                } catch (Exception $e) {
                    $pdo->rollBack();
                    setFlash('error', 'Unable to create transfer request.');
                }
            }
        }
    } elseif ($action === 'update_status') {
        $transferId = (int)($_POST['transfer_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $allowedUpdates = ['Approved', 'Dispatched', 'Received'];
    if (in_array($status, $allowedUpdates, true)) {
        $stmt = $pdo->prepare("SELECT * FROM transfers WHERE id = ?");
        $stmt->execute([$transferId]);
        $transfer = $stmt->fetch();
        if ($transfer) {
            $canPerform = false;
            $notificationTargets = [];
            $notificationMessage = '';
            if ($status === 'Approved' && $canApprove && $transfer['status'] === 'Requested' && $transfer['from_branch_id'] === $branchId) {
                $canPerform = true;
                $pdo->prepare("UPDATE transfers SET status = 'Approved', approved_by = ?, approved_date = CURDATE() WHERE id = ?")
                    ->execute([$user['id'], $transferId]);
                $notificationMessage = 'Transfer ' . $transfer['transfer_code'] . ' has been approved and is awaiting destination confirmation.';
                $notifyStmt = $pdo->prepare("SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE u.is_active = 1 AND u.branch_id = ? AND r.name = 'Campus Manager'");
                $notifyStmt->execute([$transfer['to_branch_id']]);
                $notificationTargets = array_column($notifyStmt->fetchAll(), 'id');
                $notificationTargets[] = $transfer['requested_by'];
            } elseif ($status === 'Dispatched' && $canApprove && $transfer['status'] === 'Approved' && $transfer['to_branch_id'] === $branchId) {
                $canPerform = true;
                $pdo->prepare("UPDATE transfers SET status = 'Dispatched', dispatched_date = CURDATE() WHERE id = ?")
                    ->execute([$transferId]);
                $notificationMessage = 'Transfer ' . $transfer['transfer_code'] . ' has been confirmed by destination campus and is ready for receipt.';
                $notifyStmt = $pdo->prepare("SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE u.is_active = 1 AND u.branch_id = ? AND r.name = 'Store Manager'");
                $notifyStmt->execute([$transfer['to_branch_id']]);
                $notificationTargets = array_column($notifyStmt->fetchAll(), 'id');
                $notificationTargets[] = $transfer['requested_by'];
            } elseif ($status === 'Received' && $canReceive && $transfer['status'] === 'Dispatched' && $transfer['to_branch_id'] === $branchId) {
                $canPerform = true;
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE transfers SET status = 'Received', received_date = CURDATE() WHERE id = ?")
                        ->execute([$transferId]);
                    $items = $pdo->prepare("SELECT item_id, quantity FROM transfer_items WHERE transfer_id = ?");
                    $items->execute([$transferId]);
                    foreach ($items->fetchAll() as $ti) {
                        $pdo->prepare("UPDATE inventory_items SET current_stock = current_stock + ? WHERE id = ?")
                            ->execute([$ti['quantity'], $ti['item_id']]);
                        $pdo->prepare("INSERT INTO stock_transactions (item_id, branch_id, user_id, transaction_type, quantity, reference_number, remarks, transaction_date) VALUES (?, ?, ?, 'transfer_in', ?, ?, ?, CURDATE())")
                            ->execute([$ti['item_id'], $transfer['to_branch_id'], $user['id'], $ti['quantity'], $transfer['transfer_code'], 'Transfer received']);
                    }
                    $pdo->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'transfer', 'Transfer received', ?)")
                        ->execute([$transfer['requested_by'], 'Transfer ' . $transfer['transfer_code'] . ' has been received by destination store.']);
                    $pdo->commit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    setFlash('error', 'Unable to complete receipt.');
                    $canPerform = false;
                }
            }

            if ($canPerform && $notificationMessage !== '') {
                foreach (array_unique($notificationTargets) as $targetId) {
                    if (!$targetId) {
                        continue;
                    }
                    $pdo->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'transfer', 'Transfer update', ?)")
                        ->execute([$targetId, $notificationMessage]);
                }
                auditLog('UPDATE_TRANSFER', 'transfers', $transferId, 'Updated transfer status to ' . $status);
                setFlash('success', 'Transfer status updated.');
            } elseif (!$canPerform) {
                setFlash('error', 'You do not have permission to update transfer status.');
            }
        } else {
            setFlash('error', 'Transfer not found.');
        }
    } else {
        setFlash('error', 'You do not have permission to update transfer status.');
    }
    }

    header('Location: transfers.php');
    exit;
}

$branchFilter = $isAdmin ? (int)($_GET['branch'] ?? 0) : $branchId;
$branches = $pdo->query("SELECT * FROM branches ORDER BY is_headquarters DESC, name")->fetchAll();
$branchMap = array_column($branches, 'name', 'id');
function getTransferStatusDescription(string $status, string $fromBranch, string $toBranch): string {
    switch ($status) {
        case 'Requested':
            return 'Waiting for approval from ' . clean($fromBranch) . ' campus manager.';
        case 'Approved':
            return 'Waiting for confirmation from ' . clean($toBranch) . ' campus manager.';
        case 'Dispatched':
            return 'Waiting for receipt confirmation from ' . clean($toBranch) . ' store manager.';
        case 'Received':
            return 'Transfer completed successfully.';
        case 'Rejected':
            return 'Transfer was rejected by ' . clean($fromBranch) . ' campus manager.';
        case 'Cancelled':
            return 'Transfer has been cancelled.';
        default:
            return 'Status: ' . clean($status);
    }
}

$transferWhere = '';
if (!$isAdmin) {
    $transferWhere = "WHERE (t.from_branch_id = $branchId OR t.to_branch_id = $branchId)";
} elseif ($branchFilter) {
    $transferWhere = "WHERE (t.from_branch_id = $branchFilter OR t.to_branch_id = $branchFilter)";
}
$totalTransfers = (int)$pdo->query("SELECT COUNT(*) FROM transfers t $transferWhere")->fetchColumn();
$pagination = getPagination($totalTransfers, 10);

$transfersQuery = "SELECT t.*, f.name AS from_branch, tb.name AS to_branch, u.full_name AS requested_by_name, a.full_name AS approved_by_name
                  FROM transfers t
                  JOIN branches f ON t.from_branch_id = f.id
                  JOIN branches tb ON t.to_branch_id = tb.id
                  JOIN users u ON t.requested_by = u.id
                  LEFT JOIN users a ON t.approved_by = a.id
                  $transferWhere";
$transfersQuery .= " ORDER BY t.created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}";
$transfers = $pdo->query($transfersQuery)->fetchAll();

$items = [];
if ($canRequest) {
    $itemStmt = $pdo->prepare("SELECT i.id, i.item_code, i.name, i.current_stock FROM inventory_items i WHERE i.is_active = 1 AND i.branch_id = ? ORDER BY i.name");
    $itemStmt->execute([$branchId]);
    $items = $itemStmt->fetchAll();
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Transfers</h1>
        <p class="page-sub"><?= number_format($totalTransfers) ?> inter-branch transfer requests</p>
    </div>
    <?php if ($canRequest): ?>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal('transferModal')">New Transfer</button>
    </div>
    <?php endif; ?>
</div>

<div class="card filter-bar">
    <form method="GET" class="filter-form">
        <?php if ($isAdmin): ?>
        <div class="filter-group">
            <select name="branch" onchange="this.form.submit()">
                <option value="">All Branches</option>
                <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $b['id'] == $branchFilter ? 'selected' : '' ?>><?= clean($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <div class="card-body p0">
        <?php if ($transfers): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Requested By</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($transfers as $row): ?>
            <tr>
                <td><strong><?= clean($row['transfer_code']) ?></strong></td>
                <td><?= clean($row['from_branch']) ?></td>
                <td><?= clean($row['to_branch']) ?></td>
                <td><?= clean($row['requested_by_name']) ?></td>
                <td>
                    <span class="badge <?= $row['status'] === 'Received' ? 'badge-success' : ($row['status'] === 'Approved' || $row['status'] === 'Dispatched' ? 'badge-blue' : ($row['status'] === 'Rejected' ? 'badge-danger' : 'badge-warn')) ?>">
                        <?= clean($row['status']) ?>
                    </span>
                    <div class="status-note"><small><?= getTransferStatusDescription($row['status'], $row['from_branch'], $row['to_branch']) ?></small></div>
                </td>
                <td>
                    <div class="action-btns">
                        <?php if ($row['status'] === 'Requested' && $canApprove && $row['from_branch_id'] === $branchId): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="transfer_id" value="<?= $row['id'] ?>">
                            <input type="hidden" name="status" value="Approved">
                            <button type="submit" class="btn btn-outline">Approve</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($row['status'] === 'Approved' && $canApprove && $row['to_branch_id'] === $branchId): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="transfer_id" value="<?= $row['id'] ?>">
                            <input type="hidden" name="status" value="Dispatched">
                            <button type="submit" class="btn btn-outline">Confirm Transfer</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($row['status'] === 'Dispatched' && $canReceive && $row['to_branch_id'] === $branchId): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="transfer_id" value="<?= $row['id'] ?>">
                            <input type="hidden" name="status" value="Received">
                            <button type="submit" class="btn btn-primary">Confirm Receipt</button>
                        </form>
                    <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?= renderPaginationBar($pagination, $totalTransfers) ?>
        <?php else: ?>
        <div class="empty-state"><p>No transfers found.</p></div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canRequest): ?>
<div class="modal-overlay" id="transferModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Create Transfer</h3>
            <button class="modal-close" onclick="closeModal('transferModal')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>From Branch *</label>
                        <input type="hidden" name="from_branch_id" value="<?= $branchId ?>">
                        <input type="text" class="form-control" value="<?= clean($branchMap[$branchId] ?? 'Current branch') ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>To Branch *</label>
                        <select name="to_branch_id" required>
                            <option value="">Select destination</option>
                            <?php foreach ($branches as $b): ?>
                                <?php if ($b['id'] === $branchId) continue; ?>
                                <option value="<?= $b['id'] ?>"><?= clean($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Item *</label>
                        <select name="item_id" required>
                            <option value="">Select item</option>
                            <?php foreach ($items as $item): ?>
                            <option value="<?= $item['id'] ?>"><?= clean($item['item_code']) ?> — <?= clean($item['name']) ?> (<?= $item['current_stock'] ?> available)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantity *</label>
                        <input type="number" name="quantity" min="1" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Remarks</label>
                    <textarea name="remarks" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('transferModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Transfer</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
