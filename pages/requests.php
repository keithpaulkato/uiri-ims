<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
$pageTitle = 'Requests';
$activePage = 'requests';
$user = currentUser();
$pdo = db();

$canProcess = hasRole('Administrator', 'Store Manager');
$isAdmin = hasRole('Administrator');
$branchId = $user['branch_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'submit') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        if ($itemId && $quantity > 0) {
            $stmt = $pdo->prepare("SELECT id, branch_id, name, current_stock FROM inventory_items WHERE id = ? AND is_active = 1");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();
            if ($item) {
                $insertReq = $pdo->prepare("INSERT INTO inventory_requests (user_id, branch_id, item_id, quantity, reason, status) VALUES (?,?,?,?,?, 'Pending')");
                $insertReq->execute([$user['id'], $item['branch_id'], $itemId, $quantity, $reason]);
                $requestId = (int)$pdo->lastInsertId();
                auditLog('SUBMIT_REQUEST', 'inventory_requests', $requestId, 'Requested item');
                $notifyUsers = $pdo->prepare("SELECT id FROM users WHERE is_active = 1 AND role_id IN (1,2) AND branch_id = ?");
                $notifyUsers->execute([$item['branch_id']]);
                foreach ($notifyUsers->fetchAll() as $notifyUser) {
                    $pdo->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'request', 'New inventory request', ?)")
                        ->execute([$notifyUser['id'], 'A new request has been submitted for ' . $item['name']]);
                }
                setFlash('success', 'Request submitted successfully.');
            } else {
                setFlash('error', 'Selected item is not available.');
            }
        } else {
            setFlash('error', 'Please select an item and enter a valid quantity.');
        }
    } elseif ($action === 'approve' || $action === 'reject' || $action === 'issue' || $action === 'cancel') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM inventory_requests WHERE id = ?");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();
        if (!$request) {
            setFlash('error', 'Request not found.');
        } else {
            if ($action === 'cancel' && $request['user_id'] != $user['id'] && !$canProcess) {
                setFlash('error', 'You cannot cancel this request.');
            } elseif ($action === 'approve' && !$canProcess) {
                setFlash('error', 'You do not have permission to approve requests.');
            } elseif ($action === 'issue' && !$canProcess) {
                setFlash('error', 'You do not have permission to issue requests.');
            } elseif ($action === 'reject' && !$canProcess) {
                setFlash('error', 'You do not have permission to reject requests.');
            } else {
                if ($action === 'approve') {
                    if ($request['status'] !== 'Pending') {
                        setFlash('error', 'Only pending requests can be approved.');
                    } else {
                        $pdo->prepare("UPDATE inventory_requests SET status = 'Approved', approved_by = ?, approved_at = NOW() WHERE id = ?")
                            ->execute([$user['id'], $requestId]);
                        $pdo->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'request', 'Request approved', ?)")
                            ->execute([$request['user_id'], 'Your request for item #' . $requestId . ' has been approved.']);
                        auditLog('APPROVE_REQUEST', 'inventory_requests', $requestId, 'Approved request');
                        setFlash('success', 'Request approved.');
                    }
                } elseif ($action === 'reject') {
                    if (!in_array($request['status'], ['Pending', 'Approved'])) {
                        setFlash('error', 'This request cannot be rejected now.');
                    } else {
                        $pdo->prepare("UPDATE inventory_requests SET status = 'Rejected', approved_by = ?, approved_at = NOW(), remarks = ? WHERE id = ?")
                            ->execute([$user['id'], trim($_POST['remarks'] ?? ''), $requestId]);
                        $pdo->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'request', 'Request rejected', ?)")
                            ->execute([$request['user_id'], 'Your request for item #' . $requestId . ' was rejected.']);
                        auditLog('REJECT_REQUEST', 'inventory_requests', $requestId, 'Rejected request');
                        setFlash('success', 'Request rejected.');
                    }
                } elseif ($action === 'issue') {
                    if ($request['status'] !== 'Approved') {
                        setFlash('error', 'Only approved requests can be issued.');
                    } else {
                        $pdo->beginTransaction();
                        try {
                            $itemStmt = $pdo->prepare("SELECT id, current_stock, name FROM inventory_items WHERE id = ? FOR UPDATE");
                            $itemStmt->execute([$request['item_id']]);
                            $item = $itemStmt->fetch();
                            if (!$item || $item['current_stock'] < $request['quantity']) {
                                throw new Exception('Insufficient stock.');
                            }
                            $pdo->prepare("UPDATE inventory_items SET current_stock = current_stock - ? WHERE id = ?")
                                ->execute([$request['quantity'], $request['item_id']]);
                            $pdo->prepare("INSERT INTO stock_transactions (item_id, branch_id, user_id, transaction_type, quantity, reference_number, remarks, transaction_date) VALUES (?, ?, ?, 'stock_out', ?, ?, ?, CURDATE())")
                                ->execute([
                                    $request['item_id'],
                                    $request['branch_id'],
                                    $user['id'],
                                    $request['quantity'],
                                    'REQ-' . str_pad($requestId, 5, '0', STR_PAD_LEFT),
                                    'Issued via request #' . $requestId
                                ]);
                            $pdo->prepare("UPDATE inventory_requests SET status = 'Issued', processed_by = ?, processed_at = NOW() WHERE id = ?")
                                ->execute([$user['id'], $requestId]);
                            $pdo->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'request', 'Request issued', ?)")
                                ->execute([$request['user_id'], 'Your request for item #' . $requestId . ' has been issued.']);
                            $pdo->commit();
                            auditLog('ISSUE_REQUEST', 'inventory_requests', $requestId, 'Issued request');
                            setFlash('success', 'Request issued successfully.');
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            setFlash('error', $e->getMessage());
                        }
                    }
                } elseif ($action === 'cancel') {
                    if ($request['status'] !== 'Pending') {
                        setFlash('error', 'Only pending requests can be cancelled.');
                    } else {
                        $pdo->prepare("UPDATE inventory_requests SET status = 'Cancelled', remarks = ? WHERE id = ?")
                            ->execute([trim($_POST['remarks'] ?? ''), $requestId]);
                        auditLog('CANCEL_REQUEST', 'inventory_requests', $requestId, 'Cancelled request');
                        setFlash('success', 'Request cancelled.');
                    }
                }
            }
        }
    }

    header('Location: requests.php');
    exit;
}

$where = [];
$params = [];
if (!$isAdmin && !$canProcess) {
    $where[] = 'r.user_id = ?';
    $params[] = $user['id'];
} elseif (!$isAdmin && $canProcess) {
    $where[] = 'r.branch_id = ?';
    $params[] = $branchId;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM inventory_requests r $whereSQL");
$countStmt->execute($params);
$totalRequests = (int)$countStmt->fetchColumn();
$pagination = getPagination($totalRequests, 10);

$stmt = $pdo->prepare(
    "SELECT r.*, i.item_code, i.name AS item_name, u.full_name AS requester_name, b.name AS branch_name, d.name AS department_name
     FROM inventory_requests r
     JOIN inventory_items i ON r.item_id = i.id
     JOIN users u ON r.user_id = u.id
     JOIN branches b ON r.branch_id = b.id
     LEFT JOIN departments d ON r.department_id = d.id
     $whereSQL
     ORDER BY r.requested_at DESC
     LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}"
);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$items = $pdo->prepare(
    "SELECT i.id, i.item_code, i.name, i.current_stock, b.name AS branch_name
     FROM inventory_items i
     JOIN branches b ON i.branch_id = b.id
     WHERE i.is_active = 1 AND i.branch_id = ?
     ORDER BY i.name"
);
$items->execute([$branchId]);
$items = $items->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Inventory Requests</h1>
        <p class="page-sub"><?= number_format($totalRequests) ?> requests tracked across approvals and issue status</p>
    </div>
    <?php if (!$canProcess): ?>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal('requestModal')">Submit Request</button>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body p0">
        <?php if ($requests): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Request</th>
                    <th>Requester</th>
                    <th>Branch</th>
                    <th>Qty</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($requests as $i => $row): ?>
            <tr>
                <td><?= $pagination['offset'] + $i + 1 ?></td>
                <td>
                    <span class="item-name"><?= clean($row['item_name']) ?></span>
                    <span class="item-code"><?= clean($row['item_code']) ?></span>
                </td>
                <td><?= clean($row['requester_name']) ?></td>
                <td><?= clean($row['branch_name']) ?></td>
                <td><?= number_format($row['quantity']) ?></td>
                <td>
                    <span class="badge <?= $row['status'] === 'Approved' ? 'badge-success' : ($row['status'] === 'Issued' ? 'badge-blue' : ($row['status'] === 'Rejected' ? 'badge-danger' : 'badge-warn')) ?>">
                        <?= clean($row['status']) ?>
                    </span>
                </td>
                <td><?= date('d M Y', strtotime($row['requested_at'])) ?></td>
                <td>
                    <div class="action-btns">
                        <?php if ($canProcess && $row['status'] === 'Pending'): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="request_id" value="<?= $row['id'] ?>">
                            <button type="submit" class="btn btn-outline" style="padding:0.4rem 0.75rem">Approve</button>
                        </form>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="request_id" value="<?= $row['id'] ?>">
                            <button type="submit" class="btn btn-outline" style="padding:0.4rem 0.75rem">Reject</button>
                        </form>
                        <?php elseif ($canProcess && $row['status'] === 'Approved'): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="issue">
                            <input type="hidden" name="request_id" value="<?= $row['id'] ?>">
                            <button type="submit" class="btn btn-primary" style="padding:0.4rem 0.75rem">Issue</button>
                        </form>
                        <?php endif; ?>
                        <?php if (($row['user_id'] == $user['id'] && $row['status'] === 'Pending') || $canProcess): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="request_id" value="<?= $row['id'] ?>">
                            <button type="submit" class="btn btn-outline" style="padding:0.4rem 0.75rem">Cancel</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?= renderPaginationBar($pagination, $totalRequests) ?>
        <?php else: ?>
        <div class="empty-state">
            <p>No inventory requests found.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!$canProcess): ?>
<div class="modal-overlay" id="requestModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Submit Inventory Request</h3>
            <button class="modal-close" onclick="closeModal('requestModal')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="submit">
            <div class="modal-body">
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
                <div class="form-group">
                    <label>Reason</label>
                    <textarea name="reason" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('requestModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
