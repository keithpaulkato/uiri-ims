<?php
require_once __DIR__ . '/../includes/config.php';

requireLogin();
requireRole('Administrator', 'Store Manager', 'Staff');

$pageTitle = 'Stock Adjustment';
$activePage = 'stock_adjustment';
$user = currentUser();
$pdo = db();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    
    $itemId = (int)($_POST['item_id'] ?? 0);
    $physicalCount = (int)($_POST['physical_count'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    
    if (!$itemId || $physicalCount < 0) {
        $error = 'Please select item and enter valid physical count.';
    } else {
        try {
            // Get current item data
            $itemStmt = $pdo->prepare("SELECT current_stock, branch_id FROM inventory_items WHERE id = ?");
            $itemStmt->execute([$itemId]);
            $item = $itemStmt->fetch();
            
            if (!$item) {
                throw new Exception('Item not found');
            }
            
            if (!canAccessBranch($item['branch_id'])) {
                throw new Exception('You do not have access to this branch');
            }
            
            $currentStock = $item['current_stock'];
            $difference = $physicalCount - $currentStock;
            
            if ($difference != 0) {
                // Determine transaction type
                $transactionType = $difference > 0 ? 'stock_in' : 'stock_out';
                $quantity = abs($difference);
                
                // Log transaction
                logStockTransaction(
                    $itemId,
                    $item['branch_id'],
                    $user['id'],
                    $transactionType,
                    $quantity,
                    0,
                    'ADJUSTMENT',
                    "Physical count adjustment: $currentStock → $physicalCount. Reason: $reason"
                );
                
                // Update item stock
                updateItemStock($itemId, $difference);
                
                // Create audit log
                auditLog('ADJUST_STOCK', 'inventory_items', $itemId, "Adjusted stock for item $itemId from $currentStock to $physicalCount. Reason: $reason");
                
                // Check low stock
                $itemStmt->execute([$itemId]);
                $updatedItem = $itemStmt->fetch();
                maybeNotifyLowStock($updatedItem);
                
                $success = "Stock adjusted successfully. Change: $difference unit(s)";
            } else {
                $success = 'Stock count matches current inventory - no adjustment needed.';
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get items for dropdown
$branchFilter = $user['branch_id'];
$items = $pdo->query("
    SELECT i.id, i.name, i.item_code, i.current_stock, i.minimum_stock, c.name as category_name, b.name as branch_name
    FROM inventory_items i
    JOIN categories c ON i.category_id = c.id
    JOIN branches b ON i.branch_id = b.id
    WHERE i.is_active = 1 AND i.branch_id = $branchFilter
    ORDER BY i.name
")->fetchAll();

// Get recent adjustments
$adjustmentCount = $pdo->query("
    SELECT COUNT(*)
    FROM stock_transactions st
    JOIN inventory_items i ON st.item_id = i.id
    WHERE i.branch_id = $branchFilter AND st.reference_number = 'ADJUSTMENT'
")->fetchColumn();
$pagination = getPagination((int)$adjustmentCount, 10);
$recentAdjustments = $pdo->query("
    SELECT st.*, i.name as item_name, i.item_code, u.full_name as user_name
    FROM stock_transactions st
    JOIN inventory_items i ON st.item_id = i.id
    JOIN users u ON st.user_id = u.id
    WHERE i.branch_id = $branchFilter AND st.reference_number = 'ADJUSTMENT'
    ORDER BY st.created_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Stock Adjustment</h1>
        <p class="page-sub">Adjust inventory to match physical count</p>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-error">
    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= clean($error) ?>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success">
    <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
    <?= clean($success) ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Record Stock Adjustment</h3>
    </div>
    <div class="card-body">
        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <div class="form-section-card">
                <h4>Item Selection</h4>
                <p>Choose the item and compare its current inventory with the physical count.</p>
                <div class="form-group">
                    <label for="item_id">Item *</label>
                    <select id="item_id" name="item_id" required onchange="updateCurrentStock(this.value)">
                        <option value="">-- Select Item --</option>
                        <?php foreach ($items as $item): ?>
                        <option value="<?= $item['id'] ?>" data-stock="<?= $item['current_stock'] ?>">
                            <?= clean($item['item_code']) ?> - <?= clean($item['name']) ?>
                            (Current: <?= $item['current_stock'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="current_stock">Current Stock</label>
                        <input type="text" id="current_stock" readonly value="0">
                    </div>
                    <div class="form-group">
                        <label for="physical_count">Physical Count *</label>
                        <input type="number" id="physical_count" name="physical_count" min="0" required placeholder="Enter quantity from physical count" value="<?= clean($_POST['physical_count'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="form-section-card">
                <h4>Adjustment Details</h4>
                <p>Capture the reason and confirm the inventory variance.</p>
                <div class="form-group">
                    <label for="reason">Adjustment Reason *</label>
                    <textarea id="reason" name="reason" rows="3" required placeholder="Explain reason for adjustment (e.g., Damaged items found, Theft, Miscounting, etc.)"><?= clean($_POST['reason'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Difference</label>
                    <div class="computed-field" id="difference">0 unit(s)</div>
                </div>
            </div>

            <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary">Confirm Adjustment</button>
                <a href="items.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

<div class="card" style="margin-top:40px;">
    <div class="card-header">
        <h3>Recent Adjustments</h3>
    </div>
    <div class="card-body p0">
        <?php if ($recentAdjustments): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Item</th>
                    <th>Type</th>
                    <th>Quantity</th>
                    <th>Reason</th>
                    <th>User</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentAdjustments as $adj): ?>
                <tr>
                    <td><?= formatDateTime($adj['created_at']) ?></td>
                    <td><span class="item-name"><?= clean($adj['item_name']) ?></span><span class="item-code"><?= clean($adj['item_code']) ?></span></td>
                    <td>
                        <span class="badge <?= $adj['transaction_type'] === 'stock_in' ? 'badge-success' : 'badge-danger' ?>">
                            <?= ucfirst(str_replace('_', ' ', $adj['transaction_type'])) ?>
                        </span>
                    </td>
                    <td><?= $adj['quantity'] ?></td>
                    <td><?= clean($adj['remarks']) ?></td>
                    <td><?= clean($adj['user_name']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?= renderPaginationBar($pagination, (int)$adjustmentCount) ?>
        <?php else: ?>
        <div class="empty-state"><p>No adjustments recorded yet.</p></div>
        <?php endif; ?>
    </div>
</div>

<script>
function updateCurrentStock(itemId) {
    if (!itemId) {
        document.getElementById('current_stock').value = '';
        document.getElementById('physical_count').value = '';
        document.getElementById('difference').textContent = '0';
        return;
    }
    
    const selectedOption = document.querySelector(`#item_id option[value="${itemId}"]`);
    if (selectedOption) {
        const stock = selectedOption.getAttribute('data-stock');
        document.getElementById('current_stock').value = stock;
        calculateDifference();
    }
}

function calculateDifference() {
    const current = parseInt(document.getElementById('current_stock').value) || 0;
    const physical = parseInt(document.getElementById('physical_count').value) || 0;
    const difference = physical - current;
    document.getElementById('difference').textContent = difference;
}

document.getElementById('physical_count').addEventListener('input', calculateDifference);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
