<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
$pageTitle = 'Reports';
$activePage = 'reports';
$user = currentUser();
$branchId = $user['branch_id'];
$isAdmin = hasRole('Administrator', 'Executive');
$pdo = db();
ensureInventoryDecisionColumns();

$reportType       = $_GET['report'] ?? 'summary';
$branchFilter     = $isAdmin ? (int)($_GET['branch'] ?? 0) : $branchId;
$dateFrom         = $_GET['date_from'] ?? date('Y-m-01');
$dateTo           = $_GET['date_to'] ?? date('Y-m-d');
$catFilter        = (int)($_GET['category'] ?? 0);
$sectionFilter    = (int)($_GET['section'] ?? 0);
$departmentFilter = (int)($_GET['department'] ?? 0);
$supplierFilter   = (int)($_GET['supplier'] ?? 0);
$statusFilter     = $_GET['status'] ?? '';
$txTypeFilter     = $_GET['tx_type'] ?? '';
$assetStatusFilter = $_GET['asset_status'] ?? '';
$assetTypeFilter   = $_GET['asset_type'] ?? '';
$conditionFilter   = $_GET['condition'] ?? '';
$purchaseFrom      = $_GET['purchase_from'] ?? '';
$purchaseTo        = $_GET['purchase_to'] ?? '';
$searchQuery      = trim($_GET['search'] ?? '');

$validReports = ['summary', 'movement', 'valuation', 'low_stock'];
$reportType = in_array($reportType, $validReports, true) ? $reportType : 'summary';

$searchSql = '';
if ($searchQuery) {
    $searchLike = $pdo->quote('%' . $searchQuery . '%');
    $searchSql = " AND (i.name LIKE $searchLike OR i.item_code LIKE $searchLike OR i.asset_code LIKE $searchLike OR i.brand_model LIKE $searchLike OR i.description LIKE $searchLike OR c.name LIKE $searchLike OR b.name LIKE $searchLike OR d.name LIKE $searchLike OR s.company_name LIKE $searchLike)";
}

$branchSql = !$isAdmin ? "AND i.branch_id=$branchId" : ($branchFilter ? "AND i.branch_id=$branchFilter" : '');
$catSql = $catFilter ? "AND i.category_id=$catFilter" : '';
$departmentSql = $departmentFilter ? "AND i.department_id=$departmentFilter" : '';
$supplierSql = $supplierFilter ? "AND i.supplier_id=$supplierFilter" : '';
$sectionSql = $sectionFilter ? "AND d.section_id=$sectionFilter" : '';
$assetStatusOptions = ['Available','Working','Not Working','In Maintenance','In Use','Reserved','Issued','Decommissioned','Disposed'];
$conditionOptions = ['New','Good','Fair','Used','Refurbished','Needs Repair','Obsolete','Decommissioned'];
$assetStatusSql = in_array($assetStatusFilter, $assetStatusOptions, true) ? "AND i.asset_status=" . $pdo->quote($assetStatusFilter) : '';
$assetTypeSql = $assetTypeFilter !== '' ? "AND i.asset_type=" . $pdo->quote($assetTypeFilter) : '';
$conditionSql = in_array($conditionFilter, $conditionOptions, true) ? "AND i.asset_condition=" . $pdo->quote($conditionFilter) : '';
$purchaseFromSql = preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchaseFrom) ? "AND i.purchase_date >= " . $pdo->quote($purchaseFrom) : '';
$purchaseToSql = preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchaseTo) ? "AND i.purchase_date <= " . $pdo->quote($purchaseTo) : '';
$stockStatusSql = '';
if ($statusFilter === 'low_stock') {
    $stockStatusSql = 'AND i.current_stock <= i.minimum_stock';
} elseif ($statusFilter === 'out_of_stock') {
    $stockStatusSql = 'AND i.current_stock = 0';
} elseif ($statusFilter === 'available') {
    $stockStatusSql = 'AND i.current_stock > i.minimum_stock';
}
$validTxTypes = ['stock_in', 'stock_out', 'transfer_in', 'transfer_out', 'adjustment'];
$txTypeFilter = in_array($txTypeFilter, $validTxTypes, true) ? $txTypeFilter : '';

$branches = $pdo->query("SELECT * FROM branches ORDER BY is_headquarters DESC")->fetchAll();
if (!$isAdmin) {
    $categories = $pdo->prepare("SELECT * FROM categories WHERE branch_id = ? ORDER BY name");
    $categories->execute([$branchId]);
    $categories = $categories->fetchAll();
} elseif ($branchFilter) {
    $categories = $pdo->prepare("SELECT * FROM categories WHERE branch_id = ? ORDER BY name");
    $categories->execute([$branchFilter]);
    $categories = $categories->fetchAll();
} else {
    $categories = $pdo->query("SELECT * FROM categories ORDER BY branch_id, name")->fetchAll();
}
$sections = $pdo->query("SELECT * FROM sections WHERE is_active=1 ORDER BY name")->fetchAll();
$departments = $pdo->query("SELECT d.*, s.name AS section_name FROM departments d JOIN sections s ON d.section_id=s.id WHERE d.is_active=1 ORDER BY s.name, d.name")->fetchAll();
$suppliers = $pdo->query("SELECT * FROM suppliers WHERE is_active=1 ORDER BY company_name")->fetchAll();

$totalInventory = (int)$pdo->query("SELECT COUNT(*) FROM inventory_items i WHERE i.is_active=1 $branchSql")->fetchColumn();
$inventoryValue = (float)$pdo->query("SELECT COALESCE(SUM(i.current_stock * i.unit_price), 0) FROM inventory_items i WHERE i.is_active=1 $branchSql")->fetchColumn();
$lowStock = (int)$pdo->query("SELECT COUNT(*) FROM inventory_items i WHERE i.is_active=1 AND i.current_stock <= i.minimum_stock $branchSql")->fetchColumn();
$totalSuppliers = (int)$pdo->query("SELECT COUNT(*) FROM suppliers WHERE is_active=1")->fetchColumn();
$pendingRequests = (int)$pdo->query("SELECT COUNT(*) FROM inventory_requests WHERE status='Pending' " . (!$isAdmin ? "AND branch_id=$branchId" : ($branchFilter ? "AND branch_id=$branchFilter" : '')))->fetchColumn();
$assetsUnderMaintenance = (int)$pdo->query("SELECT COUNT(*) FROM equipment_maintenance WHERE status IN ('Scheduled','In Progress','Pending') " . (!$isAdmin ? "AND branch_id=$branchId" : ($branchFilter ? "AND branch_id=$branchFilter" : '')))->fetchColumn();

$reportTabs = [
    'summary'   => 'Inventory Summary',
    'movement'  => 'Stock Movement',
    'valuation' => 'Inventory Valuation',
    'low_stock' => 'Low Stock',
];

$branchNames = array_column($branches, 'name', 'id');
$categoryNames = array_column($categories, 'name', 'id');
$sectionNames = array_column($sections, 'name', 'id');
$departmentNames = array_column($departments, 'name', 'id');
$supplierNames = array_column($suppliers, 'company_name', 'id');
$printFilters = [
    'Report' => $reportTabs[$reportType] ?? $reportType,
    'Campus' => $branchFilter ? ($branchNames[$branchFilter] ?? 'Selected campus') : ($isAdmin ? 'All Campuses' : ($branchNames[$branchId] ?? 'Current campus')),
    'Transaction Date' => $reportType === 'movement' ? "$dateFrom to $dateTo" : 'Not applied',
    'Purchase Date' => ($purchaseFrom || $purchaseTo) ? (($purchaseFrom ?: 'Any') . ' to ' . ($purchaseTo ?: 'Any')) : 'All purchase dates',
    'Category' => $catFilter ? ($categoryNames[$catFilter] ?? 'Selected category') : 'All Categories',
    'Department' => $sectionFilter ? ($sectionNames[$sectionFilter] ?? 'Selected department') : 'All Departments',
    'Section / Unit' => $departmentFilter ? ($departmentNames[$departmentFilter] ?? 'Selected section/unit') : 'All Sections / Units',
    'Supplier' => $supplierFilter ? ($supplierNames[$supplierFilter] ?? 'Selected supplier') : 'All Suppliers',
    'Stock Status' => $statusFilter ?: 'All Stock Statuses',
    'Movement Type' => $txTypeFilter ? str_replace('_', ' ', $txTypeFilter) : 'All Movement Types',
    'Asset Status' => $assetStatusFilter ?: 'All Asset Statuses',
    'Asset Type' => $assetTypeFilter ?: 'All Asset Types',
    'Condition' => $conditionFilter ?: 'All Conditions',
    'Search' => $searchQuery ?: 'None',
];
auditLog('GENERATE_REPORT', 'reports', 0, 'Generated ' . ($reportTabs[$reportType] ?? $reportType) . ' with filters: ' . json_encode($printFilters));

$data = [];
$commonJoin = 'JOIN categories c ON i.category_id=c.id JOIN branches b ON i.branch_id=b.id LEFT JOIN suppliers s ON i.supplier_id=s.id LEFT JOIN sections sec ON i.section_id=sec.id LEFT JOIN departments d ON i.department_id=d.id';
$commonWhere = "WHERE i.is_active=1 $branchSql $catSql $departmentSql $supplierSql $sectionSql $stockStatusSql $assetStatusSql $assetTypeSql $conditionSql $purchaseFromSql $purchaseToSql $searchSql";

if ($reportType === 'summary') {
    $data = $pdo->query("SELECT i.*, c.name AS category_name, b.name AS branch_name, s.company_name AS supplier_name, sec.name AS section_name, d.name AS department_name, (i.current_stock * i.unit_price) AS total_value FROM inventory_items i $commonJoin $commonWhere ORDER BY b.name, c.name, i.name")->fetchAll();
} elseif ($reportType === 'movement') {
    $sql = "SELECT t.*, i.name AS item_name, i.item_code, i.brand_model, i.purchase_date, c.name AS category_name, s.company_name AS supplier_name, sec.name AS section_name, d.name AS department_name, b.name AS branch_name, u.full_name AS user_name
            FROM stock_transactions t
            JOIN inventory_items i ON t.item_id = i.id
            JOIN categories c ON i.category_id = c.id
            LEFT JOIN suppliers s ON i.supplier_id = s.id
            LEFT JOIN sections sec ON i.section_id = sec.id
            LEFT JOIN departments d ON i.department_id = d.id
            JOIN branches b ON t.branch_id = b.id
            JOIN users u ON t.user_id = u.id
            WHERE t.transaction_date BETWEEN ? AND ?";
    $params = [$dateFrom, $dateTo];

    if (!$isAdmin) {
        $sql .= " AND t.branch_id = ?";
        $params[] = $branchId;
    } elseif ($branchFilter) {
        $sql .= " AND t.branch_id = ?";
        $params[] = $branchFilter;
    }
    if ($catFilter) { $sql .= " AND i.category_id = ?"; $params[] = $catFilter; }
    if ($departmentFilter) { $sql .= " AND i.department_id = ?"; $params[] = $departmentFilter; }
    if ($supplierFilter) { $sql .= " AND i.supplier_id = ?"; $params[] = $supplierFilter; }
    if ($sectionFilter) { $sql .= " AND d.section_id = ?"; $params[] = $sectionFilter; }
    if ($txTypeFilter) { $sql .= " AND t.transaction_type = ?"; $params[] = $txTypeFilter; }
    if ($assetStatusSql) { $sql .= " $assetStatusSql"; }
    if ($assetTypeSql) { $sql .= " $assetTypeSql"; }
    if ($conditionSql) { $sql .= " $conditionSql"; }
    if ($purchaseFromSql) { $sql .= " $purchaseFromSql"; }
    if ($purchaseToSql) { $sql .= " $purchaseToSql"; }
    if ($searchQuery) {
        $sql .= " AND (i.name LIKE ? OR i.item_code LIKE ? OR i.brand_model LIKE ? OR b.name LIKE ? OR u.full_name LIKE ?)";
        $searchParam = '%' . $searchQuery . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    $sql .= " ORDER BY t.transaction_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
} elseif ($reportType === 'valuation') {
    $params = [];
    $reportWhere = $commonWhere;
    $data = $pdo->prepare("SELECT c.name AS category_name, b.name AS branch_name, COUNT(i.id) AS item_count, SUM(i.current_stock) AS total_units, SUM(i.current_stock * i.unit_price) AS total_value FROM inventory_items i $commonJoin $reportWhere GROUP BY c.id, b.id ORDER BY total_value DESC");
    $data->execute($params);
    $data = $data->fetchAll();
} elseif ($reportType === 'low_stock') {
    $sql = "SELECT i.*, c.name AS category_name, b.name AS branch_name, s.company_name AS supplier_name, sec.name AS section_name, d.name AS department_name FROM inventory_items i $commonJoin WHERE i.is_active=1 AND i.current_stock <= i.minimum_stock $branchSql $catSql $departmentSql $supplierSql $sectionSql $assetStatusSql $assetTypeSql $conditionSql $purchaseFromSql $purchaseToSql $searchSql ORDER BY i.current_stock ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $data = $stmt->fetchAll();
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-start animate__animated animate__fadeInDown" data-aos="fade-down">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-chart-pie me-2"></i>Reports</h1>
        <p class="page-sub text-muted">Generate and export inventory reports with modern dashboards and analytics.</p>
    </div>
    <div class="page-actions d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary">
            <i class="fa-solid fa-print me-2"></i>
            Print / PDF
        </button>
        <button type="button" class="btn btn-primary" onclick="exportCurrentReport()">
            <i class="fa-solid fa-file-csv me-2"></i>
            Export CSV
        </button>
    </div>
</div>

<div class="row row-deck gx-3 mb-4" data-aos="fade-up">
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="bg-primary text-white rounded-circle p-3">
                    <i class="fa-solid fa-boxes fa-lg"></i>
                </span>
                <div>
                    <div class="text-muted">Total Items</div>
                    <div class="h3 mb-0"><?= number_format($totalInventory) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="bg-success text-white rounded-circle p-3">
                    <i class="fa-solid fa-coins fa-lg"></i>
                </span>
                <div>
                    <div class="text-muted">Inventory Value</div>
                    <div class="h3 mb-0"><?= ugx($inventoryValue) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="bg-warning text-white rounded-circle p-3">
                    <i class="fa-solid fa-circle-exclamation fa-lg"></i>
                </span>
                <div>
                    <div class="text-muted">Low Stock</div>
                    <div class="h3 mb-0"><?= number_format($lowStock) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="bg-info text-white rounded-circle p-3">
                    <i class="fa-solid fa-hand-holding-medical fa-lg"></i>
                </span>
                <div>
                    <div class="text-muted">Pending Requests</div>
                    <div class="h3 mb-0"><?= number_format($pendingRequests) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="report-tabs nav nav-pills mb-4 animate__animated animate__fadeIn" data-aos="fade-right">
    <?php $tabs=['summary'=>'Inventory Summary','movement'=>'Stock Movement','valuation'=>'Stock Valuation','low_stock'=>'Low Stock Report']; ?>
    <?php foreach ($tabs as $key=>$label): ?>
    <?php $icon = $key==='summary' ? 'fa-boxes' : ($key==='movement' ? 'fa-warehouse' : ($key==='valuation' ? 'fa-coins' : 'fa-triangle-exclamation')); ?>
    <a href="?report=<?= $key ?>&branch=<?= $branchFilter ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="nav-link <?= $reportType===$key?'active':'' ?>">
        <i class="fa-solid <?= $icon ?> me-2"></i>
        <?= $label ?>
    </a>
    <?php endforeach; ?>
</div>

<div class="card filter-bar shadow-sm p-4 animate__animated animate__fadeInUp" data-aos="fade-up">
    <form method="GET" class="filter-form">
        <input type="hidden" name="report" value="<?= clean($reportType) ?>">
        <?php if ($txTypeFilter): ?><input type="hidden" name="tx_type" value="<?= clean($txTypeFilter) ?>"><?php endif; ?>
        <?php if ($isAdmin): ?>
        <div class="filter-group">
            <label for="branchSelect" class="form-label">Branch</label>
            <select id="branchSelect" name="branch" class="form-control">
                <option value="">All Branches</option>
                <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>" <?= $b['id']==$branchFilter?'selected':'' ?>><?= clean($b['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <?php if (in_array($reportType,['summary','movement','valuation','low_stock'])): ?>
    <div class="filter-group">
        <label for="categorySelect" class="form-label">Category</label>
        <select id="categorySelect" name="category" class="form-control">
            <option value="">All Categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $c['id']==$catFilter?'selected':'' ?>>
                    <?= clean($c['name']) ?><?= $isAdmin && !$branchFilter ? ' — ' . clean($branches[array_search($c['branch_id'], array_column($branches,'id'))]['name'] ?? '') : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-group">
        <label for="sectionSelect" class="form-label">Department</label>
        <select id="sectionSelect" name="section" class="form-control">
            <option value="">All Departments</option>
            <?php foreach ($sections as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $s['id']==$sectionFilter?'selected':'' ?>><?= clean($s['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-group">
        <label for="departmentSelect" class="form-label">Section / Unit</label>
        <select id="departmentSelect" name="department" class="form-control">
            <option value="">All Sections / Units</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $d['id']==$departmentFilter?'selected':'' ?>><?= isset($d['section_name']) ? clean($d['section_name']).' — ' : '' ?><?= clean($d['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-group">
        <label for="supplierSelect" class="form-label">Supplier</label>
        <select id="supplierSelect" name="supplier" class="form-control">
            <option value="">All Suppliers</option>
            <?php foreach ($suppliers as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $s['id']==$supplierFilter?'selected':'' ?>><?= clean($s['company_name'] ?? $s['name'] ?? '') ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-group">
        <label for="statusSelect" class="form-label">Status</label>
        <select id="statusSelect" name="status" class="form-control">
            <option value="">All Statuses</option>
            <option value="low_stock" <?= $statusFilter==='low_stock'?'selected':'' ?>>Low Stock</option>
            <option value="out_of_stock" <?= $statusFilter==='out_of_stock'?'selected':'' ?>>Out of Stock</option>
            <option value="available" <?= $statusFilter==='available'?'selected':'' ?>>Available</option>
        </select>
    </div>

    <div class="filter-group">
        <label for="assetStatusSelect" class="form-label">Asset Status</label>
        <select id="assetStatusSelect" name="asset_status" class="form-control">
            <option value="">All Asset Statuses</option>
            <?php foreach ($assetStatusOptions as $status): ?>
            <option value="<?= $status ?>" <?= $assetStatusFilter===$status?'selected':'' ?>><?= $status ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-group">
        <label for="assetTypeSelect" class="form-label">Asset Type</label>
        <select id="assetTypeSelect" name="asset_type" class="form-control">
            <option value="">All Asset Types</option>
            <?php foreach (['Fixed Asset','Consumable','Tool','Spare Part','Laboratory Equipment','Office Equipment'] as $type): ?>
            <option value="<?= $type ?>" <?= $assetTypeFilter===$type?'selected':'' ?>><?= $type ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-group">
        <label for="conditionSelect" class="form-label">Condition</label>
        <select id="conditionSelect" name="condition" class="form-control">
            <option value="">All Conditions</option>
            <?php foreach ($conditionOptions as $condition): ?>
            <option value="<?= $condition ?>" <?= $conditionFilter===$condition?'selected':'' ?>><?= $condition ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-group">
        <label for="purchaseFrom" class="form-label">Purchased From</label>
        <input id="purchaseFrom" type="date" name="purchase_from" value="<?= clean($purchaseFrom) ?>" class="form-control">
    </div>

    <div class="filter-group">
        <label for="purchaseTo" class="form-label">Purchased To</label>
        <input id="purchaseTo" type="date" name="purchase_to" value="<?= clean($purchaseTo) ?>" class="form-control">
    </div>
<?php endif; ?>
        <div class="filter-group flex-fill">
            <label for="searchInput" class="form-label">Search</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-search"></i></span>
                <input id="searchInput" type="text" name="search" value="<?= clean($searchQuery) ?>" class="form-control" placeholder="Item, branch, category, supplier...">
            </div>
        </div>
        <?php if ($reportType==='movement'): ?>
        <div class="filter-group">
            <label for="dateFrom" class="form-label">From</label>
            <input id="dateFrom" type="date" name="date_from" value="<?= $dateFrom ?>" class="form-control">
        </div>
        <div class="filter-group">
            <label for="dateTo" class="form-label">To</label>
            <input id="dateTo" type="date" name="date_to" value="<?= $dateTo ?>" class="form-control">
        </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary mt-4">Generate Report</button>
    </form>
</div>

<div class="card print-area">
    <div class="print-header">
        <div class="print-logo"><img src="<?= BASE_URL ?>assets/img/uiri-logo.webp" alt="UIRI"></div>
        <div>
            <h2><?= $tabs[$reportType] ?></h2>
            <p>Uganda Industrial Research Institute<?= $branchFilter ? ' — '.clean(array_column($branches,'name','id')[$branchFilter]??'') : ' — All Branches' ?></p>
            <p>Printed: <?= date('d F Y, H:i:s') ?> by <?= clean($user['full_name']) ?> (<?= clean($user['role']) ?>)</p>
        </div>
    </div>
    <div class="print-meta">
        <?php foreach ($printFilters as $label => $value): ?>
        <div><strong><?= clean($label) ?>:</strong> <?= clean((string)$value) ?></div>
        <?php endforeach; ?>
    </div>

    <?php if ($reportType==='summary' && $data): ?>
    <table class="data-table">
        <thead><tr><th>#</th><th>Item Code</th><th>Item Name</th><th>Model / Specs</th><th>Category</th><th>Supplier</th><th>Department</th><th>Section / Unit</th><?php if ($isAdmin): ?><th>Campus</th><?php endif; ?><th>Purchased</th><th>Asset Status</th><th>Unit</th><th>Unit Price</th><th>Stock</th><th>Min Stock</th><th>Total Value</th><th>Stock Status</th></tr></thead>
        <tbody>
        <?php $grandTotal=0; foreach ($data as $i=>$row): $grandTotal+=$row['total_value']; $ss=$row['current_stock']==0?'danger':($row['current_stock']<=$row['minimum_stock']?'warn':'success'); ?>
        <tr><td><?= $i+1 ?></td><td><?= clean($row['item_code']) ?></td><td><?= clean($row['name']) ?></td><td><?= clean($row['brand_model'] ?: '—') ?></td><td><?= clean($row['category_name']) ?></td><td><?= clean($row['supplier_name'] ?: '—') ?></td><td><?= clean($row['section_name'] ?: '—') ?></td><td><?= clean($row['department_name'] ?: '—') ?></td><?php if ($isAdmin): ?><td><?= clean($row['branch_name']) ?></td><?php endif; ?><td><?= $row['purchase_date'] ? date('d M Y', strtotime($row['purchase_date'])) : '—' ?></td><td><?= clean($row['asset_status'] ?: 'Available') ?></td><td><?= clean($row['unit']) ?></td><td><?= ugx($row['unit_price']) ?></td><td><strong><?= number_format($row['current_stock']) ?></strong></td><td><?= $row['minimum_stock'] ?></td><td><?= ugx($row['total_value']) ?></td><td><span class="badge badge-<?= $ss ?>"><?= $ss==='danger'?'Out':($ss==='warn'?'Low':'OK') ?></span></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="<?= $isAdmin?15:14 ?>"><strong>Grand Total Stock Value</strong></td><td colspan="2"><strong><?= ugx($grandTotal) ?></strong></td></tr></tfoot>
    </table>

    <?php elseif ($reportType==='movement' && $data): ?>
    <table class="data-table">
        <thead><tr><th>#</th><th>Date</th><th>Item</th><th>Model / Specs</th><th>Category</th><th>Supplier</th><th>Department</th><th>Section / Unit</th><th>Campus</th><th>Purchased</th><th>Type</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Reference</th><th>Recorded By</th></tr></thead>
        <tbody>
        <?php foreach ($data as $i=>$tx): $tc=['stock_in'=>'badge-success','stock_out'=>'badge-blue','adjustment'=>'badge-warn']; ?>
        <tr><td><?= $i+1 ?></td><td><?= date('d M Y',strtotime($tx['transaction_date'])) ?></td><td><span class="item-name"><?= clean($tx['item_name']) ?></span><span class="item-code"><?= clean($tx['item_code']) ?></span></td><td><?= clean($tx['brand_model'] ?: '—') ?></td><td><?= clean($tx['category_name']) ?></td><td><?= clean($tx['supplier_name'] ?: '—') ?></td><td><?= clean($tx['section_name'] ?: '—') ?></td><td><?= clean($tx['department_name'] ?: '—') ?></td><td><?= clean($tx['branch_name']) ?></td><td><?= $tx['purchase_date'] ? date('d M Y', strtotime($tx['purchase_date'])) : '—' ?></td><td><span class="badge <?= $tc[$tx['transaction_type']]??'badge-blue' ?>"><?= str_replace('_',' ',ucfirst($tx['transaction_type'])) ?></span></td><td><?= number_format($tx['quantity']) ?></td><td><?= $tx['unit_price']>0?ugx($tx['unit_price']):'—' ?></td><td><?= $tx['unit_price']>0?ugx($tx['quantity']*$tx['unit_price']):'—' ?></td><td><?= clean($tx['reference_number']?:'—') ?></td><td><?= clean($tx['user_name']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php elseif ($reportType==='valuation' && $data): ?>
    <table class="data-table">
        <thead><tr><th>#</th><th>Category</th><?php if ($isAdmin): ?><th>Branch</th><?php endif; ?><th>Items</th><th>Total Units</th><th>Total Value</th></tr></thead>
        <tbody>
        <?php $gt=0; foreach ($data as $i=>$row): $gt+=$row['total_value']; ?>
        <tr><td><?= $i+1 ?></td><td><?= clean($row['category_name']) ?></td><?php if ($isAdmin): ?><td><?= clean($row['branch_name']) ?></td><?php endif; ?><td><?= $row['item_count'] ?></td><td><?= number_format($row['total_units']) ?></td><td><?= ugx($row['total_value']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="<?= $isAdmin?4:3 ?>"><strong>Grand Total</strong></td><td></td><td><strong><?= ugx($gt) ?></strong></td></tr></tfoot>
    </table>

    <?php elseif ($reportType==='low_stock' && $data): ?>
    <table class="data-table">
        <thead><tr><th>#</th><th>Item Code</th><th>Item Name</th><th>Model / Specs</th><th>Category</th><th>Supplier</th><th>Department</th><th>Section / Unit</th><?php if ($isAdmin): ?><th>Campus</th><?php endif; ?><th>Purchased</th><th>Asset Status</th><th>Current Stock</th><th>Min Stock</th><th>Deficit</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($data as $i=>$row): $deficit=max(0,$row['minimum_stock']-$row['current_stock']); ?>
        <tr><td><?= $i+1 ?></td><td><?= clean($row['item_code']) ?></td><td><?= clean($row['name']) ?></td><td><?= clean($row['brand_model'] ?: '—') ?></td><td><?= clean($row['category_name']) ?></td><td><?= clean($row['supplier_name'] ?: '—') ?></td><td><?= clean($row['section_name'] ?: '—') ?></td><td><?= clean($row['department_name'] ?: '—') ?></td><?php if ($isAdmin): ?><td><?= clean($row['branch_name']) ?></td><?php endif; ?><td><?= $row['purchase_date'] ? date('d M Y', strtotime($row['purchase_date'])) : '—' ?></td><td><?= clean($row['asset_status'] ?: 'Available') ?></td><td><span class="badge <?= $row['current_stock']==0?'badge-danger':'badge-warn' ?>"><?= $row['current_stock'] ?></span></td><td><?= $row['minimum_stock'] ?></td><td><?= $deficit ?></td><td><?= $row['current_stock']==0?'<span class="badge badge-danger">Out of Stock</span>':'<span class="badge badge-warn">Low Stock</span>' ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php else: ?>
    <div class="empty-state text-center animate__animated animate__fadeIn" data-aos="fade-up">
        <div class="card card-sm shadow-sm p-5">
            <div class="empty-icon mb-3"><i class="fa-solid fa-circle-exclamation fa-3x text-warning"></i></div>
            <h3>No data found</h3>
            <p class="text-muted">Adjust your filters or report type and try again.</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function exportCurrentReport() {
    const table = document.querySelector('.print-area table');
    if (!table) {
        Swal.fire({
            icon: 'warning',
            title: 'Nothing to export',
            text: 'There is no report data available for export.',
            toast: true,
            position: 'top-end',
            timer: 3800,
            showConfirmButton: false,
        });
        return;
    }
    const rows = Array.from(table.querySelectorAll('tr')).map(row =>
        Array.from(row.querySelectorAll('th, td')).map(cell =>
            '"' + cell.innerText.replaceAll('"', '""') + '"'
        ).join(',')
    );
    const csv = rows.join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'uiri-report-' + encodeURIComponent('<?= clean($reportType) ?>') + '.csv';
    link.click();
    Swal.fire({
        icon: 'success',
        title: 'Export ready',
        text: 'Your CSV export has started.',
        toast: true,
        position: 'top-end',
        timer: 3000,
        showConfirmButton: false,
    });
}
</script>
<style>
@media print {
    .sidebar,.topnav,.filter-bar,.report-tabs,.page-header,.page-actions{display:none!important;}
    .main-wrapper{margin:0!important;padding:0!important;}
    .print-area{box-shadow:none!important;border:none!important;padding:0!important;}
    .print-header{display:flex!important;}
    body{font-size:12px;}
}
.page-header{margin-bottom:1.5rem;}
.page-title{font-size:2rem; display:flex; align-items:center; gap:.75rem;}
.page-actions .btn{min-width:170px;}
.report-tabs .nav-link{margin-right:.5rem; margin-bottom:.5rem;}
.report-tabs .nav-link.active{background:#0d6efd; color:#fff;}
.filter-bar .filter-group{margin-bottom:1rem; max-width:280px;}
.filter-bar .input-group-text{background:#f8f9fa; border-right:0;}
.filter-bar .form-control{border-left:0;}
.empty-state .card{border:none;}
.print-header{display:flex;align-items:center;gap:20px;padding:20px;border-bottom:2px solid #0A1628;margin-bottom:20px;}
.print-logo{background:#fff;padding:6px;border-radius:8px;border:1px solid #e2e8f0;}
.print-logo img{height:60px;display:block;}
.print-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px 16px;margin:0 0 18px;padding:12px 14px;border:1px solid #e2e8f0;background:#f8fafc;border-radius:8px;font-size:.82rem;}
.print-meta strong{color:#0A1628;}
@media print {
    .print-meta{grid-template-columns:repeat(3,1fr);break-inside:avoid;}
    .data-table{font-size:10px;}
    .data-table th,.data-table td{padding:6px 7px;}
}
</style>
<?php include __DIR__ . '/../includes/footer.php'; ?>
