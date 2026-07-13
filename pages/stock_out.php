<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole('Administrator', 'Store Manager', 'Staff');

$pageTitle = 'Stock Out';
$activePage = 'stock_out';
$user = currentUser();
$branchId = (int)$user['branch_id'];
$isAdmin = hasRole('Administrator');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $itemId = (int)($_POST['item_id'] ?? 0);
    $qty = (int)($_POST['quantity'] ?? 0);
    $refNo = trim($_POST['reference_number'] ?? '');
    $issuedTo = trim($_POST['issued_to'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $txDate = $_POST['transaction_date'] ?? date('Y-m-d');

    $errors = [];
    if (!$itemId) $errors[] = 'Please select an item.';
    if ($qty <= 0) $errors[] = 'Quantity must be greater than zero.';
    if ($txDate === '') $errors[] = 'Transaction date is required.';

    if ($errors) {
        setFlash('error', implode(' ', $errors));
        header('Location: stock_out.php');
        exit;
    }

    $pdo->beginTransaction();
    try {
        $itemStmt = $pdo->prepare("
            SELECT id, branch_id, current_stock, name, item_code, unit_price
            FROM inventory_items
            WHERE id = ? AND is_active = 1
            FOR UPDATE
        ");
        $itemStmt->execute([$itemId]);
        $itemData = $itemStmt->fetch();

        if (!$itemData) {
            throw new Exception('Selected item was not found.');
        }
        if (!$isAdmin && (int)$itemData['branch_id'] !== $branchId) {
            throw new Exception('Selected item is outside your active branch.');
        }
        if ((int)$itemData['current_stock'] < $qty) {
            throw new Exception("Insufficient stock. Available: {$itemData['current_stock']} unit(s).");
        }

        $detailParts = [];
        if ($issuedTo !== '') $detailParts[] = "Issued to: $issuedTo";
        if ($purpose !== '') $detailParts[] = "Purpose: $purpose";
        if ($remarks !== '') $detailParts[] = $remarks;
        $fullRemarks = implode(' | ', $detailParts);

        $txBranch = (int)$itemData['branch_id'];
        $unitPrice = (float)($itemData['unit_price'] ?? 0);

        $insert = $pdo->prepare("
            INSERT INTO stock_transactions
                (item_id, branch_id, user_id, transaction_type, quantity, unit_price, reference_number, remarks, transaction_date)
            VALUES (?, ?, ?, 'stock_out', ?, ?, ?, ?, ?)
        ");
        $insert->execute([$itemId, $txBranch, $user['id'], $qty, $unitPrice, $refNo, $fullRemarks, $txDate]);
        $newTransactionId = (int)$pdo->lastInsertId();

        $pdo->prepare("UPDATE inventory_items SET current_stock = current_stock - ? WHERE id = ?")
            ->execute([$qty, $itemId]);

        $pdo->commit();
        auditLog('STOCK_OUT', 'stock_transactions', $newTransactionId, "Stock out: $qty x {$itemData['name']}");
        setFlash('success', "Stock out recorded - $qty unit(s) issued.");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlash('error', $e->getMessage() ?: 'Transaction failed.');
    }

    header('Location: stock_out.php');
    exit;
}

$branchFilter = $isAdmin ? (int)($_GET['branch'] ?? 0) : $branchId;
$branchClause = $isAdmin ? ($branchFilter ? "AND i.branch_id = $branchFilter" : '') : "AND i.branch_id = $branchId";
$txClause = $isAdmin ? ($branchFilter ? "AND t.branch_id = $branchFilter" : '') : "AND t.branch_id = $branchId";

$items = $pdo->query("
    SELECT i.id, i.name, i.item_code, i.unit, i.unit_price, i.current_stock, i.minimum_stock,
           i.asset_type, i.branch_id, c.name AS category_name, b.name AS branch_name, b.location AS branch_location
    FROM inventory_items i
    JOIN categories c ON i.category_id = c.id
    JOIN branches b ON i.branch_id = b.id
    WHERE i.is_active = 1 AND i.current_stock > 0 $branchClause
    ORDER BY i.name
")->fetchAll();

$branches = $pdo->query("SELECT * FROM branches ORDER BY is_headquarters DESC")->fetchAll();
$recentCount = (int)$pdo->query("SELECT COUNT(*) FROM stock_transactions t WHERE t.transaction_type = 'stock_out' $txClause")->fetchColumn();
$pagination = getPagination($recentCount, 10);
$recent = $pdo->query("
    SELECT t.*, i.name AS item_name, i.item_code, i.unit, b.name AS branch_name, u.full_name AS issued_by
    FROM stock_transactions t
    JOIN inventory_items i ON t.item_id = i.id
    JOIN branches b ON t.branch_id = b.id
    JOIN users u ON t.user_id = u.id
    WHERE t.transaction_type = 'stock_out' $txClause
    ORDER BY t.transaction_date DESC, t.created_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header stock-page-header">
    <div>
        <h1 class="page-title">Stock Out</h1>
        <p class="page-sub">Record issued inventory and deduct quantities from the correct branch stock.</p>
    </div>
    <div class="stock-page-actions">
        <a class="btn btn-outline" href="transactions.php?type=stock_out">View Transactions</a>
        <button type="button" class="btn btn-primary" onclick="document.getElementById('stockOutForm').scrollIntoView({behavior:'smooth'})">New Stock Out</button>
    </div>
</div>

<div class="stock-workspace stock-workspace-out">
    <section class="card stock-form-card">
        <div class="card-header">
            <h3>Issue Stock</h3>
            <span class="badge badge-blue">Stock decreases</span>
        </div>
        <div class="card-body">
            <form method="POST" id="stockOutForm">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                <?php if ($isAdmin): ?>
                <div class="form-group">
                    <label>Branch Filter</label>
                    <select onchange="window.location='stock_out.php?branch='+this.value">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $b): ?>
                        <option value="<?= (int)$b['id'] ?>" <?= (int)$b['id'] === $branchFilter ? 'selected' : '' ?>><?= clean($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-note">The transaction branch is taken from the selected item.</small>
                </div>
                <?php endif; ?>

                <div class="form-section-card">
                    <h4>Item Selection</h4>
                    <p>Choose an available item. The system prevents issuing more than the current stock.</p>
                    <div class="form-group">
                        <label>Item *</label>
                        <select name="item_id" id="stockOutItem" required onchange="setStockOutItem(this)">
                            <option value="">Select item</option>
                            <?php foreach ($items as $item): ?>
                            <option
                                value="<?= (int)$item['id'] ?>"
                                data-stock="<?= (int)$item['current_stock'] ?>"
                                data-min="<?= (int)$item['minimum_stock'] ?>"
                                data-code="<?= clean($item['item_code']) ?>"
                                data-name="<?= clean($item['name']) ?>"
                                data-unit="<?= clean($item['unit']) ?>"
                                data-price="<?= (float)$item['unit_price'] ?>"
                                data-category="<?= clean($item['category_name']) ?>"
                                data-branch="<?= clean($item['branch_name']) ?>"
                                data-location="<?= clean($item['branch_location'] ?? '') ?>"
                            >
                                <?= clean($item['item_code']) ?> - <?= clean($item['name']) ?> (<?= clean($item['branch_name']) ?>, <?= number_format((int)$item['current_stock']) ?> available)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-section-card">
                    <h4>Issue Details</h4>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Quantity *</label>
                            <input type="number" name="quantity" id="stockOutQty" min="1" required placeholder="0" oninput="updateStockOutPreview()">
                            <small class="form-note" id="stockOutLimit">Select an item to see available stock.</small>
                        </div>
                        <div class="form-group">
                            <label>Issue Reference</label>
                            <input type="text" name="reference_number" placeholder="e.g. ISS-2026-010">
                        </div>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Issued To</label>
                            <input type="text" name="issued_to" placeholder="Person, department, or unit">
                        </div>
                        <div class="form-group">
                            <label>Transaction Date</label>
                            <input type="date" name="transaction_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Purpose</label>
                        <input type="text" name="purpose" placeholder="e.g. Production use, field work, maintenance">
                    </div>
                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="remarks" rows="3" placeholder="Additional issue notes"></textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block" id="stockOutSubmit">
                    Record Stock Out
                </button>
            </form>
        </div>
    </section>

    <aside class="stock-side-panel">
        <div class="card">
            <div class="card-header"><h3>Issue Preview</h3></div>
            <div class="card-body">
                <div class="stock-preview-title">
                    <strong id="outPreviewName">Select an item</strong>
                    <span id="outPreviewCode">SKU: -</span>
                </div>
                <div class="stock-metric-grid">
                    <div><span>Available</span><strong id="outAvailable">-</strong></div>
                    <div><span>After Issue</span><strong id="outAfter">-</strong></div>
                    <div><span>Minimum</span><strong id="outMinimum">-</strong></div>
                    <div><span>Unit Value</span><strong id="outUnitValue">-</strong></div>
                    <div><span>Total Value</span><strong id="outTotalValue">-</strong></div>
                    <div><span>Branch</span><strong id="outBranch">-</strong></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Recent Stock Out</h3>
                <a href="transactions.php?type=stock_out" class="card-link">View all</a>
            </div>
            <div class="card-body p0">
                <?php if ($recent): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Item</th><th>Qty</th><th>Ref</th><th>Issued By</th><th>Date</th></tr></thead>
                        <tbody>
                        <?php foreach ($recent as $tx): ?>
                        <tr>
                            <td><span class="item-name"><?= clean($tx['item_name']) ?></span><span class="item-code"><?= clean($tx['item_code']) ?> - <?= clean($tx['branch_name']) ?></span></td>
                            <td><span class="badge badge-blue"><?= number_format((int)$tx['quantity']) ?> <?= clean($tx['unit']) ?></span></td>
                            <td><?= clean($tx['reference_number'] ?: '-') ?></td>
                            <td><?= clean($tx['issued_by']) ?></td>
                            <td><?= date('d M Y', strtotime($tx['transaction_date'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?= renderPaginationBar($pagination, $recentCount) ?>
                <?php else: ?>
                <div class="empty-state"><p>No stock-out transactions yet.</p></div>
                <?php endif; ?>
            </div>
        </div>
    </aside>
</div>

<script>
function formatOutCurrency(value) {
    if (value === null || value === undefined || isNaN(value)) return '-';
    return 'UGX ' + Number(value).toLocaleString('en-US', { maximumFractionDigits: 0 });
}

function selectedStockOutOption() {
    const select = document.getElementById('stockOutItem');
    return select && select.selectedIndex > 0 ? select.options[select.selectedIndex] : null;
}

function setStockOutItem(select) {
    const option = select.options[select.selectedIndex];
    const qty = document.getElementById('stockOutQty');
    if (!option || !option.value) {
        qty.removeAttribute('max');
        document.getElementById('stockOutLimit').textContent = 'Select an item to see available stock.';
        updateStockOutPreview();
        return;
    }
    qty.max = option.dataset.stock || '';
    document.getElementById('stockOutLimit').textContent = `${option.dataset.stock || 0} ${option.dataset.unit || 'unit(s)'} available`;
    updateStockOutPreview();
}

function updateStockOutPreview() {
    const option = selectedStockOutOption();
    const qty = parseInt(document.getElementById('stockOutQty').value, 10) || 0;
    if (!option) {
        ['outPreviewName','outPreviewCode','outAvailable','outAfter','outMinimum','outUnitValue','outTotalValue','outBranch'].forEach(id => {
            document.getElementById(id).textContent = id === 'outPreviewName' ? 'Select an item' : '-';
        });
        return;
    }

    const stock = parseInt(option.dataset.stock, 10) || 0;
    const min = parseInt(option.dataset.min, 10) || 0;
    const price = parseFloat(option.dataset.price) || 0;
    const after = stock - qty;
    document.getElementById('outPreviewName').textContent = option.dataset.name || 'Selected item';
    document.getElementById('outPreviewCode').textContent = `SKU: ${option.dataset.code || '-'}`;
    document.getElementById('outAvailable').textContent = `${stock} ${option.dataset.unit || ''}`;
    document.getElementById('outAfter').textContent = qty ? `${after} ${option.dataset.unit || ''}` : '-';
    document.getElementById('outMinimum').textContent = `${min} ${option.dataset.unit || ''}`;
    document.getElementById('outUnitValue').textContent = formatOutCurrency(price);
    document.getElementById('outTotalValue').textContent = qty ? formatOutCurrency(price * qty) : '-';
    document.getElementById('outBranch').textContent = option.dataset.branch || '-';
    document.getElementById('stockOutSubmit').disabled = qty > stock;
    document.getElementById('stockOutLimit').textContent = qty > stock
        ? `Only ${stock} ${option.dataset.unit || 'unit(s)'} available`
        : `${stock} ${option.dataset.unit || 'unit(s)'} available`;
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
