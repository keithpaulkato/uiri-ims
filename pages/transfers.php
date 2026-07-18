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
ensureInventoryDecisionColumns();

function transferResolveDestinationCategoryId(PDO $pdo, int $sourceCategoryId, int $toBranchId): int {
    $sourceStmt = $pdo->prepare("SELECT name, description FROM categories WHERE id = ?");
    $sourceStmt->execute([$sourceCategoryId]);
    $sourceCategory = $sourceStmt->fetch();
    if (!$sourceCategory) {
        throw new Exception('Source item category could not be found.');
    }

    $matchStmt = $pdo->prepare("SELECT id FROM categories WHERE branch_id = ? AND name = ? LIMIT 1");
    $matchStmt->execute([$toBranchId, $sourceCategory['name']]);
    $categoryId = $matchStmt->fetchColumn();
    if ($categoryId) {
        return (int)$categoryId;
    }

    $insertStmt = $pdo->prepare("INSERT INTO categories (branch_id, name, description) VALUES (?, ?, ?)");
    $insertStmt->execute([$toBranchId, $sourceCategory['name'], $sourceCategory['description'] ?? null]);
    return (int)$pdo->lastInsertId();
}

function transferResolveDestinationSectionId(PDO $pdo, ?int $sourceSectionId, int $toBranchId): ?int {
    if (!$sourceSectionId) {
        return null;
    }

    $sourceStmt = $pdo->prepare("SELECT name FROM sections WHERE id = ?");
    $sourceStmt->execute([$sourceSectionId]);
    $sectionName = $sourceStmt->fetchColumn();
    if (!$sectionName) {
        return null;
    }

    $matchStmt = $pdo->prepare("SELECT id FROM sections WHERE branch_id = ? AND name = ? AND is_active = 1 LIMIT 1");
    $matchStmt->execute([$toBranchId, $sectionName]);
    $sectionId = $matchStmt->fetchColumn();

    return $sectionId ? (int)$sectionId : null;
}

function transferResolveDestinationDepartmentId(PDO $pdo, ?int $sourceDepartmentId, ?int $destinationSectionId): ?int {
    if (!$sourceDepartmentId || !$destinationSectionId) {
        return null;
    }

    $sourceStmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
    $sourceStmt->execute([$sourceDepartmentId]);
    $departmentName = $sourceStmt->fetchColumn();
    if (!$departmentName) {
        return null;
    }

    $matchStmt = $pdo->prepare("SELECT id FROM departments WHERE section_id = ? AND name = ? AND is_active = 1 LIMIT 1");
    $matchStmt->execute([$destinationSectionId, $departmentName]);
    $departmentId = $matchStmt->fetchColumn();

    return $departmentId ? (int)$departmentId : null;
}

function transferFindOrCreateDestinationItem(PDO $pdo, array $sourceItem, int $toBranchId, int $userId): int {
    $destinationCategoryId = transferResolveDestinationCategoryId($pdo, (int)$sourceItem['category_id'], $toBranchId);
    $brandModel = (string)($sourceItem['brand_model'] ?? '');
    $unit = (string)($sourceItem['unit'] ?? '');
    $assetType = (string)($sourceItem['asset_type'] ?? '');

    $matchSql = "
        SELECT id
        FROM inventory_items
        WHERE branch_id = ?
          AND category_id = ?
          AND name = ?
          AND COALESCE(brand_model, '') = ?
          AND COALESCE(unit, '') = ?
          AND COALESCE(asset_type, '') = ?
          AND is_active = 1
    ";
    $matchParams = [$toBranchId, $destinationCategoryId, $sourceItem['name'], $brandModel, $unit, $assetType];
    if (!empty($sourceItem['serial_number'])) {
        $matchSql .= " AND serial_number = ?";
        $matchParams[] = $sourceItem['serial_number'];
    } else {
        $matchSql .= " AND (serial_number IS NULL OR serial_number = '')";
    }
    $matchSql .= " ORDER BY id LIMIT 1 FOR UPDATE";

    $matchStmt = $pdo->prepare($matchSql);
    $matchStmt->execute($matchParams);
    $existingId = $matchStmt->fetchColumn();
    if ($existingId) {
        return (int)$existingId;
    }

    $destinationSectionId = transferResolveDestinationSectionId($pdo, isset($sourceItem['section_id']) ? (int)$sourceItem['section_id'] : null, $toBranchId);
    $destinationDepartmentId = transferResolveDestinationDepartmentId($pdo, isset($sourceItem['department_id']) ? (int)$sourceItem['department_id'] : null, $destinationSectionId);
    $itemCode = generateItemCode($destinationCategoryId, $toBranchId);
    $assetCode = $itemCode . '-A';

    $insertStmt = $pdo->prepare("
        INSERT INTO inventory_items (
            branch_id, section_id, department_id, category_id, supplier_id, item_code, asset_code, serial_number, qr_code,
            name, brand_model, description, unit, unit_price, current_stock, minimum_stock, asset_type,
            purchase_date, warranty_date, asset_status, asset_condition, funding_source, storage_location, image,
            is_active, created_by
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, 0, ?, ?,
            ?, ?, ?, ?, ?, ?, ?,
            1, ?
        )
    ");
    $insertStmt->execute([
        $toBranchId,
        $destinationSectionId,
        $destinationDepartmentId,
        $destinationCategoryId,
        $sourceItem['supplier_id'] ?? null,
        $itemCode,
        $assetCode,
        $sourceItem['serial_number'] ?? null,
        null,
        $sourceItem['name'],
        $sourceItem['brand_model'] ?? null,
        $sourceItem['description'] ?? null,
        $sourceItem['unit'] ?? null,
        $sourceItem['unit_price'] ?? 0,
        $sourceItem['minimum_stock'] ?? 0,
        $sourceItem['asset_type'] ?? 'Consumable',
        $sourceItem['purchase_date'] ?? null,
        $sourceItem['warranty_date'] ?? null,
        $sourceItem['asset_status'] ?? 'Available',
        $sourceItem['asset_condition'] ?? 'New',
        $sourceItem['funding_source'] ?? null,
        null,
        $sourceItem['image'] ?? null,
        $userId,
    ]);

    return (int)$pdo->lastInsertId();
}

function transferLoadItems(PDO $pdo, int $transferId): array {
    $itemsStmt = $pdo->prepare("
        SELECT ti.item_id, ti.quantity, i.*, c.name AS category_name
        FROM transfer_items ti
        JOIN inventory_items i ON ti.item_id = i.id
        JOIN categories c ON i.category_id = c.id
        WHERE ti.transfer_id = ?
        FOR UPDATE
    ");
    $itemsStmt->execute([$transferId]);

    return $itemsStmt->fetchAll();
}

function transferEnsureTransferOut(PDO $pdo, array $transfer, array $transferItem, int $userId): void {
    if ((int)$transferItem['branch_id'] !== (int)$transfer['from_branch_id']) {
        throw new Exception('Transfer source item no longer belongs to the source branch.');
    }

    $existsStmt = $pdo->prepare("
        SELECT id
        FROM stock_transactions
        WHERE transaction_type = 'transfer_out'
          AND reference_number = ?
          AND item_id = ?
          AND branch_id = ?
        LIMIT 1
    ");
    $existsStmt->execute([$transfer['transfer_code'], $transferItem['item_id'], $transfer['from_branch_id']]);
    if ($existsStmt->fetchColumn()) {
        return;
    }

    $quantity = (int)$transferItem['quantity'];
    if ((int)$transferItem['current_stock'] < $quantity) {
        $available = inventoryQuantityWithUnit($transferItem['current_stock'], $transferItem);
        throw new Exception('Cannot dispatch transfer. Available source stock: ' . $available . '.');
    }

    $pdo->prepare("UPDATE inventory_items SET current_stock = current_stock - ? WHERE id = ?")
        ->execute([$quantity, $transferItem['item_id']]);
    $pdo->prepare("
        INSERT INTO stock_transactions
            (item_id, branch_id, user_id, transaction_type, quantity, unit_price, reference_number, destination_branch_id, remarks, transaction_date)
        VALUES (?, ?, ?, 'transfer_out', ?, ?, ?, ?, ?, CURDATE())
    ")->execute([
        $transferItem['item_id'],
        $transfer['from_branch_id'],
        $userId,
        $quantity,
        $transferItem['unit_price'] ?? 0,
        $transfer['transfer_code'],
        $transfer['to_branch_id'],
        'Transfer dispatched to destination branch',
    ]);
}

function transferDispatchItems(PDO $pdo, array $transfer, int $userId): void {
    $transferItems = transferLoadItems($pdo, (int)$transfer['id']);
    if (!$transferItems) {
        throw new Exception('Cannot dispatch a transfer with no items.');
    }

    foreach ($transferItems as $transferItem) {
        transferEnsureTransferOut($pdo, $transfer, $transferItem, $userId);
    }
}

function transferReceiveItems(PDO $pdo, array $transfer, int $userId): void {
    $transferItems = transferLoadItems($pdo, (int)$transfer['id']);
    if (!$transferItems) {
        throw new Exception('Cannot receive a transfer with no items.');
    }

    foreach ($transferItems as $transferItem) {
        transferEnsureTransferOut($pdo, $transfer, $transferItem, $userId);
        $quantity = (int)$transferItem['quantity'];
        $destinationItemId = transferFindOrCreateDestinationItem($pdo, $transferItem, (int)$transfer['to_branch_id'], $userId);

        $pdo->prepare("UPDATE inventory_items SET current_stock = current_stock + ? WHERE id = ?")
            ->execute([$quantity, $destinationItemId]);
        $pdo->prepare("
            INSERT INTO stock_transactions
                (item_id, branch_id, user_id, transaction_type, quantity, unit_price, reference_number, remarks, transaction_date)
            VALUES (?, ?, ?, 'transfer_in', ?, ?, ?, ?, CURDATE())
        ")->execute([
            $destinationItemId,
            $transfer['to_branch_id'],
            $userId,
            $quantity,
            $transferItem['unit_price'] ?? 0,
            $transfer['transfer_code'],
            'Transfer received from source branch',
        ]);
    }
}

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
            $itemStmt = $pdo->prepare("SELECT i.*, c.name AS category_name FROM inventory_items i JOIN categories c ON i.category_id = c.id WHERE i.id = ? AND i.branch_id = ? AND i.is_active = 1");
            $itemStmt->execute([$itemId, $fromBranch]);
            $item = $itemStmt->fetch();
            if (!$item || $item['current_stock'] < $quantity) {
                $available = $item ? inventoryQuantityWithUnit($item['current_stock'], $item) : '0';
                setFlash('error', 'Selected item is not available for transfer. Available: ' . $available . '.');
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
        if (!in_array($status, $allowedUpdates, true)) {
            setFlash('error', 'You do not have permission to update transfer status.');
        } else {
            $notificationTargets = [];
            $notificationMessage = '';
            $successMessage = 'Transfer status updated.';

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("SELECT * FROM transfers WHERE id = ? FOR UPDATE");
                $stmt->execute([$transferId]);
                $transfer = $stmt->fetch();
                if (!$transfer) {
                    throw new Exception('Transfer not found.');
                }

                if ($status === 'Approved' && $canApprove && $transfer['status'] === 'Requested' && (int)$transfer['from_branch_id'] === (int)$branchId) {
                    $pdo->prepare("UPDATE transfers SET status = 'Approved', approved_by = ?, approved_date = CURDATE() WHERE id = ?")
                        ->execute([$user['id'], $transferId]);
                    $notificationMessage = 'Transfer ' . $transfer['transfer_code'] . ' has been approved and is awaiting destination confirmation.';
                    $notifyStmt = $pdo->prepare("SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE u.is_active = 1 AND u.branch_id = ? AND r.name = 'Campus Manager'");
                    $notifyStmt->execute([$transfer['to_branch_id']]);
                    $notificationTargets = array_column($notifyStmt->fetchAll(), 'id');
                    $notificationTargets[] = $transfer['requested_by'];
                    $successMessage = 'Transfer approved.';
                } elseif ($status === 'Dispatched' && $canApprove && $transfer['status'] === 'Approved' && (int)$transfer['to_branch_id'] === (int)$branchId) {
                    transferDispatchItems($pdo, $transfer, (int)$user['id']);
                    $pdo->prepare("UPDATE transfers SET status = 'Dispatched', dispatched_date = CURDATE() WHERE id = ?")
                        ->execute([$transferId]);
                    $notificationMessage = 'Transfer ' . $transfer['transfer_code'] . ' has been dispatched and is ready for destination receipt.';
                    $notifyStmt = $pdo->prepare("SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE u.is_active = 1 AND u.branch_id = ? AND r.name = 'Store Manager'");
                    $notifyStmt->execute([$transfer['to_branch_id']]);
                    $notificationTargets = array_column($notifyStmt->fetchAll(), 'id');
                    $notificationTargets[] = $transfer['requested_by'];
                    $successMessage = 'Transfer dispatched and source stock deducted.';
                } elseif ($status === 'Received' && $canReceive && $transfer['status'] === 'Dispatched' && (int)$transfer['to_branch_id'] === (int)$branchId) {
                    transferReceiveItems($pdo, $transfer, (int)$user['id']);
                    $pdo->prepare("UPDATE transfers SET status = 'Received', received_date = CURDATE() WHERE id = ?")
                        ->execute([$transferId]);
                    $notificationMessage = 'Transfer ' . $transfer['transfer_code'] . ' has been received by the destination store.';
                    $notificationTargets[] = $transfer['requested_by'];
                    $successMessage = 'Transfer received and destination stock updated.';
                } else {
                    throw new Exception('You do not have permission to update transfer status.');
                }

                foreach (array_unique($notificationTargets) as $targetId) {
                    if (!$targetId) {
                        continue;
                    }
                    $pdo->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'transfer', 'Transfer update', ?)")
                        ->execute([$targetId, $notificationMessage]);
                }

                $pdo->commit();
                auditLog('UPDATE_TRANSFER', 'transfers', $transferId, 'Updated transfer status to ' . $status);
                setFlash('success', $successMessage);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                setFlash('error', $e->getMessage() ?: 'Unable to update transfer status.');
            }
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
    $itemStmt = $pdo->prepare("SELECT i.id, i.item_code, i.name, i.current_stock, i.brand_model, i.description, i.unit, i.asset_type, c.name AS category_name FROM inventory_items i JOIN categories c ON i.category_id = c.id WHERE i.is_active = 1 AND i.branch_id = ? ORDER BY i.name");
    $itemStmt->execute([$branchId]);
    $items = $itemStmt->fetchAll();
    foreach ($items as &$transferItem) {
        $transferItem['display_unit'] = inventoryDisplayUnitForRow($transferItem);
    }
    unset($transferItem);
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
                            <option value="<?= $item['id'] ?>"><?= clean($item['item_code']) ?> — <?= clean($item['name']) ?> (<?= number_format($item['current_stock']) ?> <?= clean($item['display_unit']) ?> available)</option>
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
