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
$printMode        = (int)($_GET['print'] ?? 0) === 1;

$validReports = ['summary', 'movement', 'valuation', 'low_stock'];
$reportType = in_array($reportType, $validReports, true) ? $reportType : 'summary';

$branches = $pdo->query("SELECT * FROM branches ORDER BY is_headquarters DESC")->fetchAll();
$assetStatusMaster = ['Available','Working','Not Working','In Maintenance','In Use','Reserved','Issued','Decommissioned','Disposed'];
$conditionMaster = ['New','Good','Fair','Used','Refurbished','Needs Repair','Obsolete','Decommissioned'];

$buildOptionWhere = function (array $keys) use (
    &$branchFilter,
    &$catFilter,
    &$sectionFilter,
    &$departmentFilter,
    &$supplierFilter,
    &$statusFilter,
    &$assetStatusFilter,
    &$assetTypeFilter,
    &$conditionFilter,
    $isAdmin,
    $branchId
) {
    $where = ['i.is_active = 1'];
    $params = [];

    if (!$isAdmin) {
        $where[] = 'i.branch_id = ?';
        $params[] = $branchId;
    } elseif (in_array('branch', $keys, true) && $branchFilter) {
        $where[] = 'i.branch_id = ?';
        $params[] = $branchFilter;
    }

    if (in_array('category', $keys, true) && $catFilter) {
        $where[] = 'i.category_id = ?';
        $params[] = $catFilter;
    }
    if (in_array('section', $keys, true) && $sectionFilter) {
        $where[] = 'i.section_id = ?';
        $params[] = $sectionFilter;
    }
    if (in_array('department', $keys, true) && $departmentFilter) {
        $where[] = 'i.department_id = ?';
        $params[] = $departmentFilter;
    }
    if (in_array('supplier', $keys, true) && $supplierFilter) {
        $where[] = 'i.supplier_id = ?';
        $params[] = $supplierFilter;
    }
    if (in_array('status', $keys, true) && $statusFilter) {
        if ($statusFilter === 'low_stock') {
            $where[] = 'i.current_stock <= i.minimum_stock';
        } elseif ($statusFilter === 'out_of_stock') {
            $where[] = 'i.current_stock = 0';
        } elseif ($statusFilter === 'available') {
            $where[] = 'i.current_stock > i.minimum_stock';
        }
    }
    if (in_array('asset_status', $keys, true) && $assetStatusFilter !== '') {
        $where[] = 'i.asset_status = ?';
        $params[] = $assetStatusFilter;
    }
    if (in_array('asset_type', $keys, true) && $assetTypeFilter !== '') {
        $where[] = 'i.asset_type = ?';
        $params[] = $assetTypeFilter;
    }
    if (in_array('condition', $keys, true) && $conditionFilter !== '') {
        $where[] = 'i.asset_condition = ?';
        $params[] = $conditionFilter;
    }

    return ['WHERE ' . implode(' AND ', $where), $params];
};

$fetchOptionRows = function (string $sql, array $keys) use ($pdo, $buildOptionWhere) {
    [$where, $params] = $buildOptionWhere($keys);
    $stmt = $pdo->prepare(str_replace('{WHERE}', $where, $sql));
    $stmt->execute($params);
    return $stmt->fetchAll();
};

$hasSelectedId = static function (array $rows, int $selected): bool {
    return $selected === 0 || in_array($selected, array_map('intval', array_column($rows, 'id')), true);
};

$categories = $fetchOptionRows("
    SELECT c.id, c.name, c.branch_id, COUNT(i.id) AS item_count
    FROM inventory_items i
    JOIN categories c ON c.id = i.category_id
    {WHERE}
    GROUP BY c.id, c.name, c.branch_id
    ORDER BY c.branch_id, c.name
", ['branch']);
if (!$hasSelectedId($categories, $catFilter)) {
    $catFilter = 0;
}

$sections = $fetchOptionRows("
    SELECT sec.id, sec.name, sec.code, sec.branch_id, COUNT(i.id) AS item_count
    FROM inventory_items i
    JOIN sections sec ON sec.id = i.section_id
    {WHERE}
    GROUP BY sec.id, sec.name, sec.code, sec.branch_id
    ORDER BY sec.name
", ['branch', 'category']);
if (!$hasSelectedId($sections, $sectionFilter)) {
    $sectionFilter = 0;
    $departmentFilter = 0;
}

$departments = $fetchOptionRows("
    SELECT d.id, d.name, d.section_id, sec.name AS section_name, sec.branch_id, COUNT(i.id) AS item_count
    FROM inventory_items i
    JOIN departments d ON d.id = i.department_id
    JOIN sections sec ON sec.id = d.section_id
    {WHERE}
    GROUP BY d.id, d.name, d.section_id, sec.name, sec.branch_id
    ORDER BY sec.name, d.name
", ['branch', 'category', 'section']);
if (!$hasSelectedId($departments, $departmentFilter)) {
    $departmentFilter = 0;
}

$suppliers = $fetchOptionRows("
    SELECT s.id, s.company_name, COUNT(i.id) AS item_count
    FROM inventory_items i
    JOIN suppliers s ON s.id = i.supplier_id
    {WHERE}
    GROUP BY s.id, s.company_name
    ORDER BY s.company_name
", ['branch', 'category', 'section', 'department']);
if (!$hasSelectedId($suppliers, $supplierFilter)) {
    $supplierFilter = 0;
}

$stockStatusOptions = [];
[$stockWhere, $stockParams] = $buildOptionWhere(['branch', 'category', 'section', 'department', 'supplier']);
$stockStmt = $pdo->prepare("
    SELECT
        SUM(CASE WHEN i.current_stock <= i.minimum_stock THEN 1 ELSE 0 END) AS low_stock,
        SUM(CASE WHEN i.current_stock = 0 THEN 1 ELSE 0 END) AS out_of_stock,
        SUM(CASE WHEN i.current_stock > i.minimum_stock THEN 1 ELSE 0 END) AS available
    FROM inventory_items i
    {$stockWhere}
");
$stockStmt->execute($stockParams);
$stockCounts = $stockStmt->fetch() ?: [];
foreach ([
    'low_stock' => 'Low Stock',
    'out_of_stock' => 'Out of Stock',
    'available' => 'Available',
] as $value => $label) {
    if ((int)($stockCounts[$value] ?? 0) > 0) {
        $stockStatusOptions[] = ['value' => $value, 'label' => $label, 'item_count' => (int)$stockCounts[$value]];
    }
}
if ($statusFilter && !in_array($statusFilter, array_column($stockStatusOptions, 'value'), true)) {
    $statusFilter = '';
}

$assetStatusOptions = $fetchOptionRows("
    SELECT i.asset_status AS value, i.asset_status AS label, COUNT(i.id) AS item_count
    FROM inventory_items i
    {WHERE}
      AND i.asset_status IS NOT NULL
      AND i.asset_status <> ''
    GROUP BY i.asset_status
    ORDER BY i.asset_status
", ['branch', 'category', 'section', 'department', 'supplier', 'status']);
if ($assetStatusFilter && !in_array($assetStatusFilter, array_column($assetStatusOptions, 'value'), true)) {
    $assetStatusFilter = '';
}

$assetTypeOptions = $fetchOptionRows("
    SELECT i.asset_type AS value, i.asset_type AS label, COUNT(i.id) AS item_count
    FROM inventory_items i
    {WHERE}
      AND i.asset_type IS NOT NULL
      AND i.asset_type <> ''
    GROUP BY i.asset_type
    ORDER BY i.asset_type
", ['branch', 'category', 'section', 'department', 'supplier', 'status', 'asset_status']);
if ($assetTypeFilter && !in_array($assetTypeFilter, array_column($assetTypeOptions, 'value'), true)) {
    $assetTypeFilter = '';
}

$conditionOptions = $fetchOptionRows("
    SELECT i.asset_condition AS value, i.asset_condition AS label, COUNT(i.id) AS item_count
    FROM inventory_items i
    {WHERE}
      AND i.asset_condition IS NOT NULL
      AND i.asset_condition <> ''
    GROUP BY i.asset_condition
    ORDER BY i.asset_condition
", ['branch', 'category', 'section', 'department', 'supplier', 'status', 'asset_status', 'asset_type']);
if ($conditionFilter && !in_array($conditionFilter, array_column($conditionOptions, 'value'), true)) {
    $conditionFilter = '';
}

[$dateBoundsWhere, $dateBoundsParams] = $buildOptionWhere(['branch', 'category', 'section', 'department', 'supplier', 'status', 'asset_status', 'asset_type', 'condition']);
$dateBoundsStmt = $pdo->prepare("
    SELECT MIN(i.purchase_date) AS min_purchase_date, MAX(i.purchase_date) AS max_purchase_date
    FROM inventory_items i
    {$dateBoundsWhere}
      AND i.purchase_date IS NOT NULL
");
$dateBoundsStmt->execute($dateBoundsParams);
$purchaseBounds = $dateBoundsStmt->fetch() ?: [];
$minPurchaseDate = $purchaseBounds['min_purchase_date'] ?? null;
$maxPurchaseDate = $purchaseBounds['max_purchase_date'] ?? null;
if ($purchaseFrom && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchaseFrom) || ($minPurchaseDate && $purchaseFrom < $minPurchaseDate) || ($maxPurchaseDate && $purchaseFrom > $maxPurchaseDate))) {
    $purchaseFrom = '';
}
if ($purchaseTo && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchaseTo) || ($minPurchaseDate && $purchaseTo < $minPurchaseDate) || ($maxPurchaseDate && $purchaseTo > $maxPurchaseDate))) {
    $purchaseTo = '';
}
if ($purchaseFrom && $purchaseTo && $purchaseFrom > $purchaseTo) {
    $purchaseFrom = '';
    $purchaseTo = '';
}

$filterRows = $pdo->query("
    SELECT i.branch_id, i.category_id, i.section_id, i.department_id, i.supplier_id,
           i.asset_status, i.asset_type, i.asset_condition,
           i.purchase_date,
           CASE
               WHEN i.current_stock = 0 THEN 'out_of_stock'
               WHEN i.current_stock <= i.minimum_stock THEN 'low_stock'
               ELSE 'available'
           END AS stock_status
    FROM inventory_items i
    WHERE i.is_active = 1
")->fetchAll();
if (!$isAdmin) {
    $filterRows = array_values(array_filter($filterRows, static fn($row) => (int)$row['branch_id'] === (int)$branchId));
}

$allCategories = $pdo->query("SELECT id, name, branch_id FROM categories ORDER BY branch_id, name")->fetchAll();
$allSections = $pdo->query("SELECT id, name, branch_id FROM sections WHERE is_active=1 ORDER BY name")->fetchAll();
$allDepartments = $pdo->query("SELECT d.id, d.name, d.section_id, s.name AS section_name FROM departments d JOIN sections s ON d.section_id=s.id WHERE d.is_active=1 ORDER BY s.name, d.name")->fetchAll();
$allSuppliers = $pdo->query("SELECT id, company_name FROM suppliers WHERE is_active=1 ORDER BY company_name")->fetchAll();

$filterOptionLabels = [
    'categories' => array_map(static fn($row) => [
        'id' => (int)$row['id'],
        'label' => $row['name'],
        'branch_id' => (int)$row['branch_id'],
    ], $allCategories),
    'sections' => array_map(static fn($row) => [
        'id' => (int)$row['id'],
        'label' => $row['name'],
        'branch_id' => (int)$row['branch_id'],
    ], $allSections),
    'departments' => array_map(static fn($row) => [
        'id' => (int)$row['id'],
        'label' => ($row['section_name'] ? $row['section_name'] . ' - ' : '') . $row['name'],
        'section_id' => (int)$row['section_id'],
    ], $allDepartments),
    'suppliers' => array_map(static fn($row) => [
        'id' => (int)$row['id'],
        'label' => $row['company_name'],
    ], $allSuppliers),
    'stock_statuses' => [
        ['id' => 'low_stock', 'label' => 'Low Stock'],
        ['id' => 'out_of_stock', 'label' => 'Out of Stock'],
        ['id' => 'available', 'label' => 'Available'],
    ],
];

$searchSql = '';
if ($searchQuery) {
    $searchLike = $pdo->quote('%' . $searchQuery . '%');
    $searchSql = " AND (i.name LIKE $searchLike OR i.item_code LIKE $searchLike OR i.asset_code LIKE $searchLike OR i.brand_model LIKE $searchLike OR i.description LIKE $searchLike OR c.name LIKE $searchLike OR b.name LIKE $searchLike OR d.name LIKE $searchLike OR s.company_name LIKE $searchLike)";
}

$branchSql = !$isAdmin ? "AND i.branch_id=$branchId" : ($branchFilter ? "AND i.branch_id=$branchFilter" : '');
$catSql = $catFilter ? "AND i.category_id=$catFilter" : '';
$departmentSql = $departmentFilter ? "AND i.department_id=$departmentFilter" : '';
$supplierSql = $supplierFilter ? "AND i.supplier_id=$supplierFilter" : '';
$sectionSql = $sectionFilter ? "AND i.section_id=$sectionFilter" : '';
$assetStatusSql = in_array($assetStatusFilter, $assetStatusMaster, true) ? "AND i.asset_status=" . $pdo->quote($assetStatusFilter) : '';
$assetTypeSql = $assetTypeFilter !== '' ? "AND i.asset_type=" . $pdo->quote($assetTypeFilter) : '';
$conditionSql = in_array($conditionFilter, $conditionMaster, true) ? "AND i.asset_condition=" . $pdo->quote($conditionFilter) : '';
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

$totalReportRows = count($data);
$pagination = getPagination($totalReportRows, 10);
$displayData = $printMode ? $data : array_slice($data, (int)$pagination['offset'], (int)$pagination['per_page']);
$rowOffset = $printMode ? 0 : (int)$pagination['offset'];
$paginationHtml = $printMode ? '' : renderPaginationBar($pagination, $totalReportRows);

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-start animate__animated animate__fadeInDown" data-aos="fade-down">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-chart-pie me-2"></i>Reports</h1>
        <p class="page-sub text-muted">Generate and export inventory reports with modern dashboards and analytics.</p>
    </div>
    <div class="page-actions d-flex gap-2">
        <button onclick="printFullReport()" class="btn btn-outline-secondary">
            <i class="fa-solid fa-print me-2"></i>
            Print / PDF
        </button>
        <button type="button" class="btn btn-primary" onclick="exportCurrentReport()">
            <i class="fa-solid fa-file-csv me-2"></i>
            Export CSV
        </button>
    </div>
</div>

<div class="report-summary-grid" data-aos="fade-up">
    <div class="report-summary-card">
        <span class="report-summary-icon blue"><i class="fa-solid fa-boxes"></i></span>
        <div>
            <span>Total Items</span>
            <strong><?= number_format($totalInventory) ?></strong>
        </div>
    </div>
    <div class="report-summary-card">
        <span class="report-summary-icon green"><i class="fa-solid fa-coins"></i></span>
        <div>
            <span>Inventory Value</span>
            <strong><?= ugx($inventoryValue) ?></strong>
        </div>
    </div>
    <div class="report-summary-card">
        <span class="report-summary-icon amber"><i class="fa-solid fa-circle-exclamation"></i></span>
        <div>
            <span>Low Stock</span>
            <strong><?= number_format($lowStock) ?></strong>
        </div>
    </div>
    <div class="report-summary-card">
        <span class="report-summary-icon cyan"><i class="fa-solid fa-hand-holding-medical"></i></span>
        <div>
            <span>Pending Requests</span>
            <strong><?= number_format($pendingRequests) ?></strong>
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
            <?php foreach ($stockStatusOptions as $status): ?>
            <option value="<?= clean($status['value']) ?>" <?= $statusFilter===$status['value']?'selected':'' ?>><?= clean($status['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-group">
        <label for="assetStatusSelect" class="form-label">Asset Status</label>
        <select id="assetStatusSelect" name="asset_status" class="form-control">
            <option value="">All Asset Statuses</option>
            <?php foreach ($assetStatusOptions as $status): ?>
            <option value="<?= clean($status['value']) ?>" <?= $assetStatusFilter===$status['value']?'selected':'' ?>><?= clean($status['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-group">
        <label for="assetTypeSelect" class="form-label">Asset Type</label>
        <select id="assetTypeSelect" name="asset_type" class="form-control">
            <option value="">All Asset Types</option>
            <?php foreach ($assetTypeOptions as $type): ?>
            <option value="<?= clean($type['value']) ?>" <?= $assetTypeFilter===$type['value']?'selected':'' ?>><?= clean($type['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-group">
        <label for="conditionSelect" class="form-label">Condition</label>
        <select id="conditionSelect" name="condition" class="form-control">
            <option value="">All Conditions</option>
            <?php foreach ($conditionOptions as $condition): ?>
            <option value="<?= clean($condition['value']) ?>" <?= $conditionFilter===$condition['value']?'selected':'' ?>><?= clean($condition['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-group">
        <label for="purchaseFrom" class="form-label">Purchased From</label>
        <input id="purchaseFrom" type="date" name="purchase_from" value="<?= clean($purchaseFrom) ?>" <?= $minPurchaseDate ? 'min="'.clean($minPurchaseDate).'"' : '' ?> <?= $maxPurchaseDate ? 'max="'.clean($maxPurchaseDate).'"' : '' ?> class="form-control">
    </div>

    <div class="filter-group">
        <label for="purchaseTo" class="form-label">Purchased To</label>
        <input id="purchaseTo" type="date" name="purchase_to" value="<?= clean($purchaseTo) ?>" <?= $minPurchaseDate ? 'min="'.clean($minPurchaseDate).'"' : '' ?> <?= $maxPurchaseDate ? 'max="'.clean($maxPurchaseDate).'"' : '' ?> class="form-control">
    </div>
<?php endif; ?>
        <div class="filter-group flex-fill">
            <label for="searchInput" class="form-label">Search</label>
            <input id="searchInput" type="text" name="search" value="<?= clean($searchQuery) ?>" class="form-control report-search-control" placeholder="Item, branch, category, supplier...">
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
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-filter"></i>
                Apply Filters
            </button>
            <a class="btn btn-outline-secondary" href="?report=<?= clean($reportType) ?>">
                <i class="fa-solid fa-rotate-left"></i>
                Reset
            </a>
        </div>
    </form>
</div>

<div class="card print-area">
    <div class="print-header">
        <div class="print-logo"><img src="<?= BASE_URL ?>assets/img/uiri-logo.webp" alt="UIRI"></div>
        <div>
            <h2><?= $tabs[$reportType] ?></h2>
            <p>Uganda Industrial Research Institute<?= $branchFilter ? ' — '.clean(array_column($branches,'name','id')[$branchFilter]??'') : ' — All Branches' ?></p>
            <p>Printed: <?= formatDateTime('now', true) ?> by <?= clean($user['full_name']) ?> (<?= clean($user['role']) ?>)</p>
        </div>
    </div>
    <div class="print-meta">
        <?php foreach ($printFilters as $label => $value): ?>
        <div><strong><?= clean($label) ?>:</strong> <?= clean((string)$value) ?></div>
        <?php endforeach; ?>
    </div>

    <?php if ($reportType==='summary' && $data): ?>
    <div class="report-table-scroll">
    <table class="data-table">
        <thead><tr><th>#</th><th>Item Code</th><th>Item Name</th><th>Model / Specs</th><th>Category</th><th>Supplier</th><th>Department</th><th>Section / Unit</th><?php if ($isAdmin): ?><th>Campus</th><?php endif; ?><th>Purchased</th><th>Asset Status</th><th>Unit</th><th>Unit Price</th><th>Stock</th><th>Min Stock</th><th>Total Value</th><th>Stock Status</th></tr></thead>
        <tbody>
        <?php $grandTotal=0; foreach ($data as $row) { $grandTotal += $row['total_value']; } ?>
        <?php foreach ($displayData as $i=>$row): $ss=$row['current_stock']==0?'danger':($row['current_stock']<=$row['minimum_stock']?'warn':'success'); ?>
        <tr><td><?= $rowOffset+$i+1 ?></td><td><?= clean($row['item_code']) ?></td><td><?= clean($row['name']) ?></td><td><?= clean($row['brand_model'] ?: '—') ?></td><td><?= clean($row['category_name']) ?></td><td><?= clean($row['supplier_name'] ?: '—') ?></td><td><?= clean($row['section_name'] ?: '—') ?></td><td><?= clean($row['department_name'] ?: '—') ?></td><?php if ($isAdmin): ?><td><?= clean($row['branch_name']) ?></td><?php endif; ?><td><?= $row['purchase_date'] ? date('d M Y', strtotime($row['purchase_date'])) : '—' ?></td><td><?= clean($row['asset_status'] ?: 'Available') ?></td><td><?= clean($row['unit']) ?></td><td><?= ugx($row['unit_price']) ?></td><td><strong><?= number_format($row['current_stock']) ?></strong></td><td><?= $row['minimum_stock'] ?></td><td><?= ugx($row['total_value']) ?></td><td><span class="badge badge-<?= $ss ?>"><?= $ss==='danger'?'Out':($ss==='warn'?'Low':'OK') ?></span></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="<?= $isAdmin?15:14 ?>"><strong>Grand Total Stock Value</strong></td><td colspan="2"><strong><?= ugx($grandTotal) ?></strong></td></tr></tfoot>
    </table>
    </div>
    <?= $paginationHtml ?>

    <?php elseif ($reportType==='movement' && $data): ?>
    <div class="report-table-scroll">
    <table class="data-table">
        <thead><tr><th>#</th><th>Date</th><th>Item</th><th>Model / Specs</th><th>Category</th><th>Supplier</th><th>Department</th><th>Section / Unit</th><th>Campus</th><th>Purchased</th><th>Type</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Reference</th><th>Recorded By</th></tr></thead>
        <tbody>
        <?php foreach ($displayData as $i=>$tx): $tc=['stock_in'=>'badge-success','stock_out'=>'badge-blue','adjustment'=>'badge-warn']; ?>
        <tr><td><?= $rowOffset+$i+1 ?></td><td><?= date('d M Y',strtotime($tx['transaction_date'])) ?></td><td><span class="item-name"><?= clean($tx['item_name']) ?></span><span class="item-code"><?= clean($tx['item_code']) ?></span></td><td><?= clean($tx['brand_model'] ?: '—') ?></td><td><?= clean($tx['category_name']) ?></td><td><?= clean($tx['supplier_name'] ?: '—') ?></td><td><?= clean($tx['section_name'] ?: '—') ?></td><td><?= clean($tx['department_name'] ?: '—') ?></td><td><?= clean($tx['branch_name']) ?></td><td><?= $tx['purchase_date'] ? date('d M Y', strtotime($tx['purchase_date'])) : '—' ?></td><td><span class="badge <?= $tc[$tx['transaction_type']]??'badge-blue' ?>"><?= str_replace('_',' ',ucfirst($tx['transaction_type'])) ?></span></td><td><?= number_format($tx['quantity']) ?></td><td><?= $tx['unit_price']>0?ugx($tx['unit_price']):'—' ?></td><td><?= $tx['unit_price']>0?ugx($tx['quantity']*$tx['unit_price']):'—' ?></td><td><?= clean($tx['reference_number']?:'—') ?></td><td><?= clean($tx['user_name']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?= $paginationHtml ?>

    <?php elseif ($reportType==='valuation' && $data): ?>
    <div class="report-table-scroll">
    <table class="data-table">
        <thead><tr><th>#</th><th>Category</th><?php if ($isAdmin): ?><th>Branch</th><?php endif; ?><th>Items</th><th>Total Units</th><th>Total Value</th></tr></thead>
        <tbody>
        <?php $gt=0; foreach ($data as $row) { $gt += $row['total_value']; } ?>
        <?php foreach ($displayData as $i=>$row): ?>
        <tr><td><?= $rowOffset+$i+1 ?></td><td><?= clean($row['category_name']) ?></td><?php if ($isAdmin): ?><td><?= clean($row['branch_name']) ?></td><?php endif; ?><td><?= $row['item_count'] ?></td><td><?= number_format($row['total_units']) ?></td><td><?= ugx($row['total_value']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="<?= $isAdmin?4:3 ?>"><strong>Grand Total</strong></td><td></td><td><strong><?= ugx($gt) ?></strong></td></tr></tfoot>
    </table>
    </div>
    <?= $paginationHtml ?>

    <?php elseif ($reportType==='low_stock' && $data): ?>
    <div class="report-table-scroll">
    <table class="data-table">
        <thead><tr><th>#</th><th>Item Code</th><th>Item Name</th><th>Model / Specs</th><th>Category</th><th>Supplier</th><th>Department</th><th>Section / Unit</th><?php if ($isAdmin): ?><th>Campus</th><?php endif; ?><th>Purchased</th><th>Asset Status</th><th>Current Stock</th><th>Min Stock</th><th>Deficit</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($displayData as $i=>$row): $deficit=max(0,$row['minimum_stock']-$row['current_stock']); ?>
        <tr><td><?= $rowOffset+$i+1 ?></td><td><?= clean($row['item_code']) ?></td><td><?= clean($row['name']) ?></td><td><?= clean($row['brand_model'] ?: '—') ?></td><td><?= clean($row['category_name']) ?></td><td><?= clean($row['supplier_name'] ?: '—') ?></td><td><?= clean($row['section_name'] ?: '—') ?></td><td><?= clean($row['department_name'] ?: '—') ?></td><?php if ($isAdmin): ?><td><?= clean($row['branch_name']) ?></td><?php endif; ?><td><?= $row['purchase_date'] ? date('d M Y', strtotime($row['purchase_date'])) : '—' ?></td><td><?= clean($row['asset_status'] ?: 'Available') ?></td><td><span class="badge <?= $row['current_stock']==0?'badge-danger':'badge-warn' ?>"><?= $row['current_stock'] ?></span></td><td><?= $row['minimum_stock'] ?></td><td><?= $deficit ?></td><td><?= $row['current_stock']==0?'<span class="badge badge-danger">Out of Stock</span>':'<span class="badge badge-warn">Low Stock</span>' ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?= $paginationHtml ?>

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
const reportFilterRows = <?= json_encode(array_map(static fn($row) => [
    'branch' => (int)$row['branch_id'],
    'category' => (int)$row['category_id'],
    'section' => (int)$row['section_id'],
    'department' => (int)$row['department_id'],
    'supplier' => (int)$row['supplier_id'],
    'status' => (string)$row['stock_status'],
    'asset_status' => (string)$row['asset_status'],
    'asset_type' => (string)$row['asset_type'],
    'condition' => (string)$row['asset_condition'],
    'purchase_date' => (string)$row['purchase_date'],
], $filterRows), JSON_UNESCAPED_SLASHES) ?>;
const reportFilterLabels = <?= json_encode($filterOptionLabels, JSON_UNESCAPED_SLASHES) ?>;
const reportFilterFixedBranch = <?= $isAdmin ? 'null' : (int)$branchId ?>;

function printFullReport() {
    const url = new URL(window.location.href);
    url.searchParams.set('print', '1');
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

<?php if ($printMode): ?>
window.addEventListener('load', () => {
    setTimeout(() => window.print(), 350);
});
<?php endif; ?>

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

function initSmartReportFilters() {
    const fields = {
        branch: document.getElementById('branchSelect'),
        category: document.getElementById('categorySelect'),
        section: document.getElementById('sectionSelect'),
        department: document.getElementById('departmentSelect'),
        supplier: document.getElementById('supplierSelect'),
        status: document.getElementById('statusSelect'),
        asset_status: document.getElementById('assetStatusSelect'),
        asset_type: document.getElementById('assetTypeSelect'),
        condition: document.getElementById('conditionSelect'),
        purchase_from: document.getElementById('purchaseFrom'),
        purchase_to: document.getElementById('purchaseTo'),
    };

    const order = ['branch', 'category', 'section', 'department', 'supplier', 'status', 'asset_status', 'asset_type', 'condition'];
    const optionSources = {
        category: reportFilterLabels.categories,
        section: reportFilterLabels.sections,
        department: reportFilterLabels.departments,
        supplier: reportFilterLabels.suppliers,
        status: reportFilterLabels.stock_statuses,
    };
    const defaultLabels = {
        category: 'All Categories',
        section: 'All Departments',
        department: 'All Sections / Units',
        supplier: 'All Suppliers',
        status: 'All Statuses',
        asset_status: 'All Asset Statuses',
        asset_type: 'All Asset Types',
        condition: 'All Conditions',
    };

    const valueOf = (key) => {
        if (key === 'branch' && reportFilterFixedBranch !== null) {
            return String(reportFilterFixedBranch);
        }
        return fields[key] ? fields[key].value : '';
    };

    const matchesBefore = (row, targetKey) => {
        const targetIndex = order.indexOf(targetKey);

        return order.slice(0, targetIndex).every((key) => {
            const selected = valueOf(key);
            if (!selected) {
                return true;
            }
            return String(row[key]) === selected;
        });
    };

    const uniqueValues = (key) => {
        const values = new Set();
        reportFilterRows.forEach((row) => {
            if (matchesBefore(row, key) && row[key] !== null && row[key] !== '') {
                values.add(String(row[key]));
            }
        });
        return values;
    };

    const labelFor = (key, value) => {
        if (key === 'asset_status' || key === 'asset_type' || key === 'condition') {
            return value;
        }

        const source = optionSources[key] || [];
        const option = source.find((item) => String(item.id) === String(value));
        return option ? option.label : value;
    };

    const renderSelect = (key) => {
        const select = fields[key];
        if (!select) {
            return;
        }

        const selected = select.value;
        const values = Array.from(uniqueValues(key)).sort((a, b) => labelFor(key, a).localeCompare(labelFor(key, b)));
        select.innerHTML = '';
        select.add(new Option(defaultLabels[key], ''));

        values.forEach((value) => {
            select.add(new Option(labelFor(key, value), value));
        });

        if (selected && values.includes(String(selected))) {
            select.value = selected;
        } else {
            select.value = '';
        }

        select.disabled = values.length === 0;
    };

    const refreshAfter = (changedKey = 'branch') => {
        const start = Math.max(1, order.indexOf(changedKey) + 1);
        order.slice(start).forEach(renderSelect);
        updatePurchaseDateBounds();
    };

    const updatePurchaseDateBounds = () => {
        const matchingDates = reportFilterRows
            .filter((row) => order.every((key) => {
                const selected = valueOf(key);
                return !selected || String(row[key]) === selected;
            }))
            .map((row) => row.purchase_date)
            .filter(Boolean)
            .sort();

        if (!fields.purchase_from || !fields.purchase_to) {
            return;
        }

        fields.purchase_from.removeAttribute('min');
        fields.purchase_from.removeAttribute('max');
        fields.purchase_to.removeAttribute('min');
        fields.purchase_to.removeAttribute('max');

        if (!matchingDates.length) {
            fields.purchase_from.value = '';
            fields.purchase_to.value = '';
            return;
        }

        const minDate = matchingDates[0];
        const maxDate = matchingDates[matchingDates.length - 1];
        fields.purchase_from.min = minDate;
        fields.purchase_from.max = maxDate;
        fields.purchase_to.min = minDate;
        fields.purchase_to.max = maxDate;

        if (fields.purchase_from.value && (fields.purchase_from.value < minDate || fields.purchase_from.value > maxDate)) {
            fields.purchase_from.value = '';
        }
        if (fields.purchase_to.value && (fields.purchase_to.value < minDate || fields.purchase_to.value > maxDate)) {
            fields.purchase_to.value = '';
        }
    };

    order.forEach((key) => {
        if (!fields[key]) {
            return;
        }
        fields[key].addEventListener('change', () => refreshAfter(key));
    });

    order.slice(1).forEach(renderSelect);
    updatePurchaseDateBounds();
}

document.addEventListener('DOMContentLoaded', initSmartReportFilters);
</script>
<style>
@media print {
    .sidebar,.topnav,.filter-bar,.report-tabs,.page-header,.page-actions,.pagination-bar,.report-summary-grid{display:none!important;}
    .main-wrapper{margin:0!important;padding:0!important;}
    .print-area{box-shadow:none!important;border:none!important;padding:0!important;}
    .print-header{display:flex!important;}
    body{font-size:12px;}
}
.page-header{margin-bottom:1.5rem;}
.page-title{font-size:2rem; display:flex; align-items:center; gap:.75rem;}
.page-actions .btn{min-width:170px;}
.report-summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px;}
.report-summary-card{min-height:86px;display:flex;align-items:center;gap:14px;padding:16px;border:1px solid #b8c9dc;border-radius:8px;background:#fff;box-shadow:0 10px 24px rgba(15,23,42,.06);}
.report-summary-card div{min-width:0;}
.report-summary-card span:not(.report-summary-icon){display:block;color:#526174;font-size:.72rem;font-weight:850;text-transform:uppercase;letter-spacing:.04em;}
.report-summary-card strong{display:block;color:#0A1628;font-size:1.15rem;line-height:1.2;font-weight:900;white-space:normal;word-break:break-word;}
.report-summary-icon{width:42px;height:42px;display:inline-flex;align-items:center;justify-content:center;flex:0 0 42px;border-radius:10px;color:#fff;font-size:1rem;}
.report-summary-icon.blue{background:#2f80ed;}
.report-summary-icon.green{background:#22c55e;}
.report-summary-icon.amber{background:#f59e0b;}
.report-summary-icon.cyan{background:#06b6d4;}
.report-tabs{display:flex;gap:8px;flex-wrap:wrap;padding:8px;border:1px solid #b8c9dc;border-radius:8px;background:#f8fbff;user-select:none;}
.report-tabs .nav-link{margin:0!important;min-height:36px;display:inline-flex;align-items:center;gap:7px;padding:8px 12px!important;border:1px solid #cbd5e1!important;border-radius:7px!important;background:#fff!important;color:#0A1628!important;font-size:.82rem;font-weight:850;text-decoration:none!important;}
.report-tabs .nav-link.active{background:#0f2744!important;border-color:#0f2744!important;color:#fff!important;box-shadow:0 8px 16px rgba(15,39,68,.14);}
.report-tabs .nav-link::selection,.report-tabs .nav-link *::selection{background:transparent;color:inherit;}
.filter-bar{border:1px solid #b8c9dc!important;border-radius:8px!important;background:#f8fbff!important;}
.filter-bar .filter-form{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));align-items:end;gap:12px;}
.filter-bar .filter-group{min-width:0;margin-bottom:0;padding:10px 11px;border:1px solid #d4e0ec;border-radius:8px;background:#fff;box-shadow:0 8px 18px rgba(15,23,42,.04);}
.filter-bar .filter-group:not(.flex-fill){grid-column:span 2;}
.filter-bar .filter-group.flex-fill{grid-column:span 3;}
.filter-bar .form-label{display:block;font-size:.68rem;font-weight:900;color:#334155;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;line-height:1.1;}
.filter-bar .form-control,.report-search-control{width:100%;height:34px;border:1px solid #cbd5e1!important;border-radius:7px!important;background:#fff;color:#0A1628;padding:6px 9px;font-size:.8rem;font-weight:750;box-shadow:none!important;outline:none!important;}
.filter-bar .form-control:focus,.report-search-control:focus{border-color:#8bb8dd!important;box-shadow:0 0 0 3px rgba(47,128,237,.12)!important;}
.filter-actions{grid-column:span 4;display:flex;align-items:center;gap:10px;align-self:end;min-height:56px;padding:8px 0;}
.filter-actions .btn{min-height:40px;margin-top:0!important;box-shadow:0 10px 22px rgba(15,23,42,.08);}
.filter-actions .btn-primary{min-width:180px;}
.filter-actions .btn-outline-secondary{min-width:110px;background:#fff;border:1px solid #cbd5e1;color:#0A1628;}
.report-search-control{min-width:0;}
.print-area{overflow:hidden;}
.report-table-scroll{width:100%;overflow-x:auto;overflow-y:hidden;padding-bottom:10px;border-bottom:1px solid #cbd5e1;scrollbar-color:#6b7280 #e5edf5;scrollbar-width:auto;}
.report-table-scroll .data-table{min-width:1720px;}
.report-table-scroll::-webkit-scrollbar{height:14px;}
.report-table-scroll::-webkit-scrollbar-track{background:#e5edf5;border-radius:999px;}
.report-table-scroll::-webkit-scrollbar-thumb{background:#6b7280;border-radius:999px;border:3px solid #e5edf5;}
.report-table-scroll::-webkit-scrollbar-thumb:hover{background:#475569;}
.print-area .pagination-bar{margin:12px 14px 16px;}
.empty-state .card{border:none;}
.print-header{display:flex;align-items:center;gap:20px;padding:20px;border-bottom:2px solid #0A1628;margin-bottom:20px;}
.print-logo{background:#fff;padding:6px;border-radius:8px;border:1px solid #e2e8f0;}
.print-logo img{height:60px;display:block;}
.print-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px 16px;margin:0 0 18px;padding:12px 14px;border:1px solid #e2e8f0;background:#f8fafc;border-radius:8px;font-size:.82rem;}
.print-meta strong{color:#0A1628;}
@media print {
    .report-table-scroll{overflow:visible!important;padding-bottom:0!important;border-bottom:none!important;}
    .report-table-scroll .data-table{min-width:0!important;width:100%!important;}
    .print-meta{grid-template-columns:repeat(3,1fr);break-inside:avoid;}
    .data-table{font-size:10px;}
    .data-table th,.data-table td{padding:6px 7px;}
}
@media (max-width:1180px){.report-summary-grid{grid-template-columns:repeat(2,minmax(0,1fr));}.filter-bar .filter-form{grid-template-columns:repeat(6,minmax(0,1fr));}.filter-bar .filter-group:not(.flex-fill),.filter-bar .filter-group.flex-fill{grid-column:span 2;}.filter-actions{grid-column:span 4;}}
@media (max-width:768px){.report-summary-grid{grid-template-columns:1fr;}.filter-bar .filter-form{grid-template-columns:1fr;align-items:stretch;}.filter-bar .filter-group:not(.flex-fill),.filter-bar .filter-group.flex-fill,.filter-actions{grid-column:1;}.filter-actions{flex-direction:column;align-items:stretch;}.filter-actions .btn{width:100%;}.report-search-control{max-width:none;width:100%;}}
</style>
<?php include __DIR__ . '/../includes/footer.php'; ?>
