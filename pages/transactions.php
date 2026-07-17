<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
$pageTitle = 'Transactions'; $activePage = 'transactions';
$user = currentUser(); $branchId = $user['branch_id'];
$isAdmin = hasRole('Administrator'); $pdo = db();

$typeFilter   = $_GET['type'] ?? '';
$branchFilter = $isAdmin ? (int)($_GET['branch']??0) : $branchId;
$dateFrom     = $_GET['date_from'] ?? '';
$dateTo       = $_GET['date_to'] ?? '';
$search       = trim($_GET['search']??'');

$where = []; $params = [];
if (!$isAdmin) { $where[] = "t.branch_id=?"; $params[] = $branchId; }
elseif ($branchFilter) { $where[] = "t.branch_id=?"; $params[] = $branchFilter; }
if ($typeFilter) { $where[] = "t.transaction_type=?"; $params[] = $typeFilter; }
if ($dateFrom) { $where[] = "t.transaction_date>=?"; $params[] = $dateFrom; }
if ($dateTo) { $where[] = "t.transaction_date<=?"; $params[] = $dateTo; }
if ($search) { $where[] = "(i.name LIKE ? OR i.item_code LIKE ? OR t.reference_number LIKE ?)"; $params=array_merge($params,["%$search%","%$search%","%$search%"]); }
$whereSQL = $where ? 'WHERE '.implode(' AND ',$where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM stock_transactions t JOIN inventory_items i ON t.item_id=i.id $whereSQL");
$countStmt->execute($params);
$totalTransactions = (int)$countStmt->fetchColumn();
$pagination = getPagination($totalTransactions, 10);

$stmt = $pdo->prepare("SELECT t.*,i.name AS item_name,i.item_code,i.brand_model,i.description,i.unit,i.asset_type,c.name AS category_name,u.full_name AS user_name,b.name AS branch_name FROM stock_transactions t JOIN inventory_items i ON t.item_id=i.id JOIN categories c ON c.id=i.category_id JOIN users u ON t.user_id=u.id JOIN branches b ON t.branch_id=b.id $whereSQL ORDER BY t.created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$branches = $pdo->query("SELECT * FROM branches ORDER BY is_headquarters DESC")->fetchAll();
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div><h1 class="page-title">Stock Transactions</h1><p class="page-sub"><?= number_format($totalTransactions) ?> transaction(s) found</p></div>
</div>
<div class="card filter-bar">
    <form method="GET" class="filter-form">
        <div class="filter-group">
            <div class="input-wrap">
                <svg class="input-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="search" placeholder="Search…" value="<?= clean($search) ?>">
            </div>
        </div>
        <div class="filter-group">
            <select name="type">
                <option value="">All Types</option>
                <?php foreach (['stock_in'=>'Stock In','stock_out'=>'Stock Out','transfer_in'=>'Transfer In','transfer_out'=>'Transfer Out','adjustment'=>'Adjustment'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= $typeFilter===$v?'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($isAdmin): ?>
        <div class="filter-group">
            <select name="branch">
                <option value="">All Branches</option>
                <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $b['id']==$branchFilter?'selected':'' ?>><?= clean($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="filter-group"><input type="date" name="date_from" value="<?= clean($dateFrom) ?>" placeholder="From"></div>
        <div class="filter-group"><input type="date" name="date_to" value="<?= clean($dateTo) ?>" placeholder="To"></div>
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="transactions.php" class="btn btn-outline">Reset</a>
    </form>
</div>
<div class="card">
    <div class="card-body p0">
        <?php if ($transactions): ?>
        <table class="data-table">
            <thead><tr><th>#</th><th>Item</th><th>Branch</th><th>Type</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Reference</th><th>Recorded By</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($transactions as $i=>$tx):
                $typeBadge = ['stock_in'=>'badge-success','stock_out'=>'badge-blue','transfer_in'=>'badge-purple','transfer_out'=>'badge-purple','adjustment'=>'badge-warn'];
            ?>
            <tr>
                <td><?= $pagination['offset'] + $i + 1 ?></td>
                <td><span class="item-name"><?= clean($tx['item_name']) ?></span><span class="item-code"><?= clean($tx['item_code']) ?></span></td>
                <td><?= clean($tx['branch_name']) ?></td>
                <td><span class="badge <?= $typeBadge[$tx['transaction_type']]??'badge-blue' ?>"><?= str_replace('_',' ',ucfirst($tx['transaction_type'])) ?></span></td>
                <td><?= clean(inventoryQuantityWithUnit($tx['quantity'], $tx)) ?></td>
                <td><?= $tx['unit_price']>0?ugx($tx['unit_price']):'—' ?></td>
                <td><?= $tx['unit_price']>0?ugx($tx['quantity']*$tx['unit_price']):'—' ?></td>
                <td><?= clean($tx['reference_number']?:'—') ?></td>
                <td><?= clean($tx['user_name']) ?></td>
                <td><?= date('d M Y',strtotime($tx['transaction_date'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?= renderPaginationBar($pagination, $totalTransactions) ?>
        <?php else: ?>
        <div class="empty-state"><svg viewBox="0 0 24 24"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg><h3>No transactions found</h3><p>Adjust your filters to see results.</p></div>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
