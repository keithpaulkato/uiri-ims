<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole('Administrator', 'Store Manager', 'Staff');
$pageTitle = 'Stock Out'; $activePage = 'stock_out';
$user = currentUser(); $branchId = $user['branch_id'];
$isAdmin = hasRole('Administrator'); $pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $itemId = (int)($_POST['item_id']??0); $qty = (int)($_POST['quantity']??0);
    $refNo = trim($_POST['reference_number']??''); $remarks = trim($_POST['remarks']??'');
    $txDate = $_POST['transaction_date']??date('Y-m-d');
    $txBranch = $isAdmin ? (int)($_POST['branch_id']??$branchId) : $branchId;

    if (!$itemId || $qty<=0) { setFlash('error','Please select an item and enter a valid quantity.'); }
    else {
        // Check available stock
        $avail = $pdo->prepare("SELECT current_stock,name FROM inventory_items WHERE id=?");
        $avail->execute([$itemId]);
        $itemData = $avail->fetch();
        if ($itemData['current_stock'] < $qty) {
            setFlash('error',"Insufficient stock. Available: {$itemData['current_stock']} unit(s).");
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("INSERT INTO stock_transactions (item_id,branch_id,user_id,transaction_type,quantity,reference_number,remarks,transaction_date) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$itemId,$txBranch,$user['id'],'stock_out',$qty,$refNo,$remarks,$txDate]);
                $pdo->prepare("UPDATE inventory_items SET current_stock=current_stock-? WHERE id=?")->execute([$qty,$itemId]);
                $pdo->commit();
                auditLog('STOCK_OUT','stock_transactions',0,"Stock out: $qty x {$itemData['name']}");
                setFlash('success',"Stock out recorded — $qty unit(s) issued.");
            } catch(Exception $e) { $pdo->rollBack(); setFlash('error','Transaction failed.'); }
        }
    }
    header('Location: stock_out.php'); exit;
}

$branchFilter = $isAdmin ? (int)($_GET['branch']??0) : $branchId;
$bWhere = $isAdmin ? ($branchFilter ? "AND i.branch_id=$branchFilter" : '') : "AND i.branch_id=$branchId";
$items = $pdo->query("SELECT i.id,i.name,i.item_code,i.unit,i.current_stock,b.name AS branch_name FROM inventory_items i JOIN branches b ON i.branch_id=b.id WHERE i.is_active=1 AND i.current_stock>0 $bWhere ORDER BY i.name")->fetchAll();
$branches = $pdo->query("SELECT * FROM branches ORDER BY is_headquarters DESC")->fetchAll();
$tWhere = $isAdmin ? ($branchFilter ? "AND t.branch_id=$branchFilter" : '') : "AND t.branch_id=$branchId";
$recent = $pdo->query("SELECT t.*,i.name AS item_name,i.item_code,b.name AS branch_name FROM stock_transactions t JOIN inventory_items i ON t.item_id=i.id JOIN branches b ON t.branch_id=b.id WHERE t.transaction_type='stock_out' $tWhere ORDER BY t.created_at DESC LIMIT 20")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div><h1 class="page-title">Stock Out</h1><p class="page-sub">Record items issued from inventory</p></div>
</div>
<div class="section-grid-2" style="align-items:start">
<div class="card">
    <div class="card-header"><h3>Record Stock Issued</h3></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <div class="form-section-card">
                <h4>Issue Details</h4>
                <p>Select the stock item and branch for the issue.</p>
                <?php if ($isAdmin): ?>
                <div class="form-group">
                    <label>Branch</label>
                    <select name="branch_id" onchange="window.location='stock_out.php?branch='+this.value">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $b['id']==$branchFilter?'selected':'' ?>><?= clean($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="form-group">
                    <label>Item *</label>
                    <select name="item_id" required onchange="showStock(this)">
                        <option value="">Select item</option>
                        <?php foreach ($items as $item): ?>
                        <option value="<?= $item['id'] ?>" data-stock="<?= $item['current_stock'] ?>">
                            <?= clean($item['item_code']) ?> — <?= clean($item['name']) ?> (<?= clean($item['branch_name']) ?>) [<?= $item['current_stock'] ?> available]
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="stockInfo" style="margin-top:6px;color:#64748b;font-size:.85rem;display:none">
                        Available: <strong id="availStock"></strong> unit(s)
                    </div>
                </div>
            </div>

            <div class="form-section-card">
                <h4>Transaction Details</h4>
                <p>Capture the quantity, reference, and issue notes.</p>
                <div class="form-grid-2">
                    <div class="form-group"><label>Quantity *</label><input type="number" name="quantity" id="qty" min="1" required placeholder="0"></div>
                    <div class="form-group"><label>Issue Reference</label><input type="text" name="reference_number" placeholder="e.g. ISS-2026-010"></div>
                </div>
                <div class="form-group"><label>Transaction Date</label><input type="date" name="transaction_date" value="<?= date('Y-m-d') ?>" required></div>
                <div class="form-group"><label>Remarks / Issued To</label><textarea name="remarks" rows="2" placeholder="e.g. Issued to ICT Department…"></textarea></div>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <svg viewBox="0 0 24 24"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
                Record Stock Out
            </button>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-header"><h3>Recent Stock Out</h3><a href="transactions.php?type=stock_out" class="card-link">View all</a></div>
    <div class="card-body p0">
        <?php if ($recent): ?>
        <table class="data-table">
            <thead><tr><th>Item</th><th>Branch</th><th>Qty</th><th>Ref</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $tx): ?>
            <tr>
                <td><span class="item-name"><?= clean($tx['item_name']) ?></span><span class="item-code"><?= clean($tx['item_code']) ?></span></td>
                <td><?= clean($tx['branch_name']) ?></td>
                <td><span class="badge badge-blue"><?= number_format($tx['quantity']) ?></span></td>
                <td><?= clean($tx['reference_number']?:'—') ?></td>
                <td><?= date('d M Y',strtotime($tx['transaction_date'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?><div class="empty-state"><p>No stock-out transactions yet.</p></div><?php endif; ?>
    </div>
</div>
</div>
<script>
function showStock(sel){const opt=sel.options[sel.selectedIndex];const s=opt.dataset.stock;document.getElementById('availStock').textContent=s;document.getElementById('stockInfo').style.display=s?'block':'none';document.getElementById('qty').max=s;}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
