<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pageTitle = 'Analytics';
$activePage = 'analytics';
$user = currentUser();
$pdo = db();
ensureInventoryDecisionColumns();

$isAdmin = hasRole('Administrator', 'Executive');
$branchId = (int)$user['branch_id'];
$selectedBranch = $isAdmin ? (int)($_GET['branch'] ?? 0) : $branchId;
$dateFrom = $_GET['date_from'] ?? date('Y-m-01', strtotime('-5 months'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$categoryFilter = (int)($_GET['category'] ?? 0);
$departmentFilter = (int)($_GET['department'] ?? 0);
$supplierFilter = (int)($_GET['supplier'] ?? 0);
$assetStatusFilter = trim($_GET['asset_status'] ?? '');
$conditionFilter = trim($_GET['condition'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = date('Y-m-01', strtotime('-5 months'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) $dateTo = date('Y-m-d');

$assetStatusOptions = ['Available','Working','Not Working','In Maintenance','In Use','Reserved','Issued','Decommissioned','Disposed'];
$conditionOptions = ['New','Good','Fair','Used','Refurbished','Needs Repair','Obsolete','Decommissioned'];
$assetStatusFilter = in_array($assetStatusFilter, $assetStatusOptions, true) ? $assetStatusFilter : '';
$conditionFilter = in_array($conditionFilter, $conditionOptions, true) ? $conditionFilter : '';

$branches = $pdo->query("SELECT id, name, is_headquarters FROM branches ORDER BY is_headquarters DESC, name")->fetchAll();
$categories = $pdo->query("SELECT id, branch_id, name FROM categories ORDER BY branch_id, name")->fetchAll();
$departments = $pdo->query("SELECT d.id, d.name, s.name AS section_name, s.branch_id FROM departments d JOIN sections s ON d.section_id=s.id WHERE d.is_active=1 ORDER BY s.name, d.name")->fetchAll();
$suppliers = $pdo->query("SELECT id, company_name FROM suppliers WHERE is_active=1 ORDER BY company_name")->fetchAll();

$itemWhere = ["i.is_active=1"];
if ($selectedBranch) $itemWhere[] = "i.branch_id=" . $selectedBranch;
if ($categoryFilter) $itemWhere[] = "i.category_id=" . $categoryFilter;
if ($departmentFilter) $itemWhere[] = "i.department_id=" . $departmentFilter;
if ($supplierFilter) $itemWhere[] = "i.supplier_id=" . $supplierFilter;
if ($assetStatusFilter) $itemWhere[] = "i.asset_status=" . $pdo->quote($assetStatusFilter);
if ($conditionFilter) $itemWhere[] = "i.asset_condition=" . $pdo->quote($conditionFilter);
$itemSql = 'WHERE ' . implode(' AND ', $itemWhere);

$txWhere = ["t.transaction_date BETWEEN " . $pdo->quote($dateFrom) . " AND " . $pdo->quote($dateTo)];
if ($selectedBranch) $txWhere[] = "t.branch_id=" . $selectedBranch;
if ($categoryFilter) $txWhere[] = "i.category_id=" . $categoryFilter;
if ($departmentFilter) $txWhere[] = "i.department_id=" . $departmentFilter;
if ($supplierFilter) $txWhere[] = "i.supplier_id=" . $supplierFilter;
if ($assetStatusFilter) $txWhere[] = "i.asset_status=" . $pdo->quote($assetStatusFilter);
if ($conditionFilter) $txWhere[] = "i.asset_condition=" . $pdo->quote($conditionFilter);
$txSql = 'WHERE ' . implode(' AND ', $txWhere);

$requestWhere = ["r.requested_at BETWEEN " . $pdo->quote($dateFrom . ' 00:00:00') . " AND " . $pdo->quote($dateTo . ' 23:59:59')];
if ($selectedBranch) $requestWhere[] = "r.branch_id=" . $selectedBranch;
if ($categoryFilter) $requestWhere[] = "i.category_id=" . $categoryFilter;
if ($departmentFilter) $requestWhere[] = "i.department_id=" . $departmentFilter;
if ($supplierFilter) $requestWhere[] = "i.supplier_id=" . $supplierFilter;
if ($assetStatusFilter) $requestWhere[] = "i.asset_status=" . $pdo->quote($assetStatusFilter);
if ($conditionFilter) $requestWhere[] = "i.asset_condition=" . $pdo->quote($conditionFilter);
$requestSql = 'WHERE ' . implode(' AND ', $requestWhere);

function pct(float $part, float $total): int {
    return $total > 0 ? (int)round(($part / $total) * 100) : 0;
}

function analyticsStops(array $rows, string $valueKey, array $colors): string {
    $total = array_sum(array_map(fn($row) => (float)$row[$valueKey], $rows));
    if ($total <= 0) return '#e2e8f0 0% 100%';
    $cursor = 0;
    $stops = [];
    foreach ($rows as $index => $row) {
        $slice = ((float)$row[$valueKey] / $total) * 100;
        $color = $colors[$index % count($colors)];
        $stops[] = $color . ' ' . round($cursor, 2) . '% ' . round($cursor + $slice, 2) . '%';
        $cursor += $slice;
    }
    return implode(', ', $stops);
}

function svgPoints(array $values, int $width = 520, int $height = 150, int $pad = 18): string {
    $max = max(1, max($values ?: [1]));
    $count = max(1, count($values) - 1);
    $points = [];
    foreach ($values as $index => $value) {
        $x = $pad + ($index * (($width - ($pad * 2)) / $count));
        $y = $height - $pad - (((float)$value / $max) * ($height - ($pad * 2)));
        $points[] = round($x, 1) . ',' . round($y, 1);
    }
    return implode(' ', $points);
}

$totalItems = (int)$pdo->query("SELECT COUNT(*) FROM inventory_items i $itemSql")->fetchColumn();
$totalUnits = (int)$pdo->query("SELECT COALESCE(SUM(i.current_stock),0) FROM inventory_items i $itemSql")->fetchColumn();
$inventoryValue = (float)$pdo->query("SELECT COALESCE(SUM(i.current_stock * i.unit_price),0) FROM inventory_items i $itemSql")->fetchColumn();
$lowStock = (int)$pdo->query("SELECT COUNT(*) FROM inventory_items i $itemSql AND i.current_stock <= i.minimum_stock AND i.current_stock > 0")->fetchColumn();
$outStock = (int)$pdo->query("SELECT COUNT(*) FROM inventory_items i $itemSql AND i.current_stock=0")->fetchColumn();
$maintenanceItems = (int)$pdo->query("SELECT COUNT(*) FROM inventory_items i $itemSql AND i.asset_status IN ('Maintenance','In Maintenance')")->fetchColumn();
$notWorkingItems = (int)$pdo->query("SELECT COUNT(*) FROM inventory_items i $itemSql AND i.asset_status='Not Working'")->fetchColumn();
$missingPurchase = (int)$pdo->query("SELECT COUNT(*) FROM inventory_items i $itemSql AND i.purchase_date IS NULL")->fetchColumn();
$stockInQty = (int)$pdo->query("SELECT COALESCE(SUM(t.quantity),0) FROM stock_transactions t JOIN inventory_items i ON t.item_id=i.id $txSql AND t.transaction_type IN ('stock_in','transfer_in')")->fetchColumn();
$stockOutQty = (int)$pdo->query("SELECT COALESCE(SUM(t.quantity),0) FROM stock_transactions t JOIN inventory_items i ON t.item_id=i.id $txSql AND t.transaction_type IN ('stock_out','transfer_out')")->fetchColumn();
$stockOutValue = (float)$pdo->query("SELECT COALESCE(SUM(t.quantity * t.unit_price),0) FROM stock_transactions t JOIN inventory_items i ON t.item_id=i.id $txSql AND t.transaction_type IN ('stock_out','transfer_out')")->fetchColumn();
$requestJoin = "JOIN inventory_items i ON r.item_id=i.id";
$requestTotal = (int)$pdo->query("SELECT COUNT(*) FROM inventory_requests r $requestJoin $requestSql")->fetchColumn();
$requestPending = (int)$pdo->query("SELECT COUNT(*) FROM inventory_requests r $requestJoin $requestSql AND r.status='Pending'")->fetchColumn();
$requestApproved = (int)$pdo->query("SELECT COUNT(*) FROM inventory_requests r $requestJoin $requestSql AND r.status='Approved'")->fetchColumn();
$requestIssued = (int)$pdo->query("SELECT COUNT(*) FROM inventory_requests r $requestJoin $requestSql AND r.status='Issued'")->fetchColumn();
$requestRejected = (int)$pdo->query("SELECT COUNT(*) FROM inventory_requests r $requestJoin $requestSql AND r.status='Rejected'")->fetchColumn();
$requestCancelled = (int)$pdo->query("SELECT COUNT(*) FROM inventory_requests r $requestJoin $requestSql AND r.status='Cancelled'")->fetchColumn();

$riskItems = $lowStock + $outStock + $maintenanceItems + $notWorkingItems;
$riskRate = pct($riskItems, max(1, $totalItems));
$serviceReadiness = max(0, 100 - $riskRate);
$issueReceiptRatio = pct($stockOutQty, max(1, $stockInQty));
$approvalRate = pct($requestApproved + $requestIssued, max(1, $requestTotal - $requestCancelled));
$fulfillmentRate = pct($requestIssued, max(1, $requestTotal));
$rejectionRate = pct($requestRejected, max(1, $requestTotal));
$maintenanceRate = pct($maintenanceItems + $notWorkingItems, max(1, $totalItems));
$dataCompleteness = max(0, 100 - pct($missingPurchase, max(1, $totalItems)));

$movementRows = $pdo->query("
    SELECT DATE_FORMAT(t.transaction_date,'%Y-%m') AS month_key, DATE_FORMAT(t.transaction_date,'%b') AS label,
           SUM(CASE WHEN t.transaction_type IN ('stock_in','transfer_in') THEN t.quantity ELSE 0 END) AS stock_in,
           SUM(CASE WHEN t.transaction_type IN ('stock_out','transfer_out') THEN t.quantity ELSE 0 END) AS stock_out
    FROM stock_transactions t
    JOIN inventory_items i ON t.item_id=i.id
    $txSql
    GROUP BY month_key, label
    ORDER BY month_key
")->fetchAll();

$movementByMonth = [];
$cursor = new DateTime($dateFrom);
$end = new DateTime($dateTo);
$cursor->modify('first day of this month');
while ($cursor <= $end) {
    $key = $cursor->format('Y-m');
    $movementByMonth[$key] = ['label' => $cursor->format('M'), 'stock_in' => 0, 'stock_out' => 0];
    $cursor->modify('+1 month');
}
foreach ($movementRows as $row) {
    $movementByMonth[$row['month_key']] = [
        'label' => $row['label'],
        'stock_in' => (int)$row['stock_in'],
        'stock_out' => (int)$row['stock_out'],
    ];
}
$movementData = array_values($movementByMonth);
$movementMax = max(1, max(array_merge(array_column($movementData, 'stock_in'), array_column($movementData, 'stock_out'))));
$issueTrendPoints = svgPoints(array_map(fn($row) => (int)$row['stock_out'], $movementData));

$statusMix = $pdo->query("
    SELECT COALESCE(NULLIF(i.asset_status,''),'Available') AS label, COUNT(*) AS total
    FROM inventory_items i
    $itemSql
    GROUP BY label
    ORDER BY total DESC
")->fetchAll();
$conditionMix = $pdo->query("
    SELECT COALESCE(NULLIF(i.asset_condition,''),'New') AS label, COUNT(*) AS total
    FROM inventory_items i
    $itemSql
    GROUP BY label
    ORDER BY total DESC
")->fetchAll();
$categoryValue = $pdo->query("
    SELECT c.name, COUNT(i.id) AS items, COALESCE(SUM(i.current_stock * i.unit_price),0) AS value
    FROM inventory_items i
    JOIN categories c ON i.category_id=c.id
    $itemSql
    GROUP BY c.id, c.name
    HAVING value > 0
    ORDER BY value DESC
    LIMIT 8
")->fetchAll();
$supplierValue = $pdo->query("
    SELECT COALESCE(s.company_name,'Unassigned') AS name, COUNT(i.id) AS items, COALESCE(SUM(i.current_stock * i.unit_price),0) AS value
    FROM inventory_items i
    LEFT JOIN suppliers s ON i.supplier_id=s.id
    $itemSql
    GROUP BY i.supplier_id, s.company_name
    HAVING value > 0
    ORDER BY value DESC
    LIMIT 8
")->fetchAll();
$campusJoinFilters = ["i.branch_id=b.id", "i.is_active=1"];
if ($categoryFilter) $campusJoinFilters[] = "i.category_id=" . $categoryFilter;
if ($departmentFilter) $campusJoinFilters[] = "i.department_id=" . $departmentFilter;
if ($supplierFilter) $campusJoinFilters[] = "i.supplier_id=" . $supplierFilter;
if ($assetStatusFilter) $campusJoinFilters[] = "i.asset_status=" . $pdo->quote($assetStatusFilter);
if ($conditionFilter) $campusJoinFilters[] = "i.asset_condition=" . $pdo->quote($conditionFilter);
$campusJoinSql = implode(' AND ', $campusJoinFilters);
$campusComparison = $pdo->query("
    SELECT b.name, COUNT(i.id) AS items, COALESCE(SUM(i.current_stock),0) AS units,
           COALESCE(SUM(i.current_stock * i.unit_price),0) AS value,
           SUM(CASE WHEN i.current_stock <= i.minimum_stock OR i.asset_status IN ('Not Working','In Maintenance','Maintenance') THEN 1 ELSE 0 END) AS risk
    FROM branches b
    LEFT JOIN inventory_items i ON $campusJoinSql
    " . ($selectedBranch ? "WHERE b.id=$selectedBranch" : '') . "
    GROUP BY b.id, b.name
    ORDER BY value DESC
")->fetchAll();
$requestMix = [
    ['label' => 'Pending', 'total' => $requestPending],
    ['label' => 'Approved', 'total' => $requestApproved],
    ['label' => 'Issued', 'total' => $requestIssued],
    ['label' => 'Rejected', 'total' => $requestRejected],
    ['label' => 'Cancelled', 'total' => $requestCancelled],
];
$ageBuckets = $pdo->query("
    SELECT
        SUM(CASE WHEN i.purchase_date IS NULL THEN 1 ELSE 0 END) AS unknown_age,
        SUM(CASE WHEN i.purchase_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR) THEN 1 ELSE 0 END) AS under_1,
        SUM(CASE WHEN i.purchase_date < DATE_SUB(CURDATE(), INTERVAL 1 YEAR) AND i.purchase_date >= DATE_SUB(CURDATE(), INTERVAL 3 YEAR) THEN 1 ELSE 0 END) AS one_to_three,
        SUM(CASE WHEN i.purchase_date < DATE_SUB(CURDATE(), INTERVAL 3 YEAR) AND i.purchase_date >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR) THEN 1 ELSE 0 END) AS three_to_five,
        SUM(CASE WHEN i.purchase_date < DATE_SUB(CURDATE(), INTERVAL 5 YEAR) THEN 1 ELSE 0 END) AS over_five
    FROM inventory_items i
    $itemSql
")->fetch();
$ageData = [
    ['label' => 'Unknown', 'total' => (int)($ageBuckets['unknown_age'] ?? 0)],
    ['label' => '< 1 Year', 'total' => (int)($ageBuckets['under_1'] ?? 0)],
    ['label' => '1-3 Years', 'total' => (int)($ageBuckets['one_to_three'] ?? 0)],
    ['label' => '3-5 Years', 'total' => (int)($ageBuckets['three_to_five'] ?? 0)],
    ['label' => '5+ Years', 'total' => (int)($ageBuckets['over_five'] ?? 0)],
];

$categoryMax = max(1, (float)max(array_column($categoryValue, 'value') ?: [1]));
$supplierMax = max(1, (float)max(array_column($supplierValue, 'value') ?: [1]));
$campusMax = max(1, (float)max(array_column($campusComparison, 'value') ?: [1]));
$ageMax = max(1, max(array_column($ageData, 'total') ?: [1]));
$colors = ['#1f7ae0','#20b26b','#f59e0b','#ef4444','#8b5cf6','#14b8a6','#64748b','#111827'];
$statusPie = analyticsStops($statusMix, 'total', $colors);
$conditionPie = analyticsStops($conditionMix, 'total', array_reverse($colors));
$requestPie = analyticsStops($requestMix, 'total', ['#f59e0b','#22c55e','#2563eb','#ef4444','#64748b']);

$scopeLabel = $selectedBranch ? (array_column($branches, 'name', 'id')[$selectedBranch] ?? 'Selected campus') : ($isAdmin ? 'All UIRI Campuses' : ($user['branch_name'] ?? 'Current campus'));
$filterSummary = $scopeLabel . ' · ' . formatDate($dateFrom) . ' to ' . formatDate($dateTo);

auditLog('VIEW_ANALYTICS', 'analytics', 0, 'Viewed analytics workspace: ' . $filterSummary);

include __DIR__ . '/../includes/header.php';
?>

<div class="analytics-shell">
    <div class="analytics-hero">
        <div>
            <span class="analytics-eyebrow">Enterprise Decision Intelligence</span>
            <h1>Analytics Command Centre</h1>
            <p><?= clean($filterSummary) ?> · Live operational signals from inventory, movements, requests, suppliers, campuses, asset health and audit-grade records.</p>
        </div>
        <div class="analytics-scorecard">
            <span>Readiness Score</span>
            <strong><?= $serviceReadiness ?>%</strong>
            <small><?= $riskItems ?> risk-linked assets from <?= number_format($totalItems) ?> live items</small>
        </div>
    </div>

    <form method="GET" class="analytics-filter-bar">
        <?php if ($isAdmin): ?>
        <label>Campus
            <select name="branch">
                <option value="0">All Campuses</option>
                <?php foreach ($branches as $branch): ?>
                <option value="<?= (int)$branch['id'] ?>" <?= $selectedBranch === (int)$branch['id'] ? 'selected' : '' ?>><?= clean($branch['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
        <label>From <input type="date" name="date_from" value="<?= clean($dateFrom) ?>"></label>
        <label>To <input type="date" name="date_to" value="<?= clean($dateTo) ?>"></label>
        <label>Category
            <select name="category">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $category): ?>
                <option value="<?= (int)$category['id'] ?>" <?= $categoryFilter === (int)$category['id'] ? 'selected' : '' ?>><?= clean($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Section / Unit
            <select name="department">
                <option value="0">All Sections / Units</option>
                <?php foreach ($departments as $department): ?>
                <option value="<?= (int)$department['id'] ?>" <?= $departmentFilter === (int)$department['id'] ? 'selected' : '' ?>><?= clean($department['section_name'] . ' - ' . $department['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Supplier
            <select name="supplier">
                <option value="0">All Suppliers</option>
                <?php foreach ($suppliers as $supplier): ?>
                <option value="<?= (int)$supplier['id'] ?>" <?= $supplierFilter === (int)$supplier['id'] ? 'selected' : '' ?>><?= clean($supplier['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Asset Status
            <select name="asset_status">
                <option value="">All Statuses</option>
                <?php foreach ($assetStatusOptions as $status): ?>
                <option value="<?= clean($status) ?>" <?= $assetStatusFilter === $status ? 'selected' : '' ?>><?= clean($status) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Condition
            <select name="condition">
                <option value="">All Conditions</option>
                <?php foreach ($conditionOptions as $condition): ?>
                <option value="<?= clean($condition) ?>" <?= $conditionFilter === $condition ? 'selected' : '' ?>><?= clean($condition) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit"><i class="fa-solid fa-filter"></i> Apply Filters</button>
    </form>

    <section class="analytics-kpi-grid">
        <div class="analytics-kpi analytics-kpi-inventory-value"><span>Inventory Value</span><strong><?= ugx($inventoryValue) ?></strong><small><?= number_format($totalUnits) ?> units under control</small></div>
        <div class="analytics-kpi"><span>Stock Turnover Signal</span><strong><?= $issueReceiptRatio ?>%</strong><small><?= number_format($stockOutQty) ?> issued vs <?= number_format($stockInQty) ?> received</small></div>
        <div class="analytics-kpi risk"><span>Risk Exposure</span><strong><?= $riskRate ?>%</strong><small><?= $lowStock ?> low, <?= $outStock ?> out, <?= $maintenanceItems + $notWorkingItems ?> technical risk</small></div>
        <div class="analytics-kpi"><span>Request Fulfillment</span><strong><?= $fulfillmentRate ?>%</strong><small><?= number_format($requestIssued) ?> issued from <?= number_format($requestTotal) ?> requests</small></div>
        <div class="analytics-kpi"><span>Approval Conversion</span><strong><?= $approvalRate ?>%</strong><small><?= number_format($requestApproved + $requestIssued) ?> approved or issued</small></div>
        <div class="analytics-kpi warn"><span>Data Completeness</span><strong><?= $dataCompleteness ?>%</strong><small><?= number_format($missingPurchase) ?> assets missing purchase dates</small></div>
    </section>

    <section class="analytics-grid analytics-grid-main">
        <div class="analytics-card analytics-wide">
            <div class="analytics-card-header"><h3>Stock Movement Trend</h3><span>Receipts, issues and issue trend</span></div>
            <div class="analytics-combo">
                <svg viewBox="0 0 520 150" role="img" aria-label="Issue trend line">
                    <line x1="18" y1="132" x2="502" y2="132"></line>
                    <polyline points="<?= clean($issueTrendPoints) ?>"></polyline>
                </svg>
                <div class="analytics-bars">
                    <?php foreach ($movementData as $row): ?>
                    <div>
                        <span class="bar-in" style="height: <?= max(6, round(((int)$row['stock_in'] / $movementMax) * 116)) ?>px" title="Stock in: <?= number_format($row['stock_in']) ?>"></span>
                        <span class="bar-out" style="height: <?= max(6, round(((int)$row['stock_out'] / $movementMax) * 116)) ?>px" title="Stock out: <?= number_format($row['stock_out']) ?>"></span>
                        <small><?= clean($row['label']) ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="analytics-legend"><span><i class="in"></i>Stock In</span><span><i class="out"></i>Stock Out</span><span><i class="trend"></i>Issue Trend</span></div>
            </div>
        </div>

        <div class="analytics-card">
            <div class="analytics-card-header"><h3>Request Lifecycle</h3><span>Approval and issue conversion</span></div>
            <div class="analytics-donut-layout">
                <div class="analytics-donut" style="background: conic-gradient(<?= clean($requestPie) ?>);"></div>
                <div class="analytics-legend-list">
                    <?php foreach ($requestMix as $index => $row): ?>
                    <div><i style="background: <?= clean(['#f59e0b','#22c55e','#2563eb','#ef4444','#64748b'][$index]) ?>"></i><span><?= clean($row['label']) ?></span><strong><?= number_format($row['total']) ?></strong></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="analytics-mini-metrics">
                <div><span>Rejected</span><strong><?= $rejectionRate ?>%</strong></div>
                <div><span>Pending</span><strong><?= pct($requestPending, max(1, $requestTotal)) ?>%</strong></div>
            </div>
        </div>
    </section>

    <section class="analytics-grid analytics-grid-two">
        <div class="analytics-card">
            <div class="analytics-card-header"><h3>Asset Status Mix</h3><span>Operational availability profile</span></div>
            <div class="analytics-donut-layout">
                <div class="analytics-donut" style="background: conic-gradient(<?= clean($statusPie) ?>);"></div>
                <div class="analytics-legend-list">
                    <?php foreach ($statusMix as $index => $row): ?>
                    <div><i style="background: <?= clean($colors[$index % count($colors)]) ?>"></i><span><?= clean($row['label']) ?></span><strong><?= pct($row['total'], max(1, $totalItems)) ?>%</strong></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="analytics-card">
            <div class="analytics-card-header"><h3>Condition Intelligence</h3><span>Asset health and lifecycle quality</span></div>
            <div class="analytics-donut-layout">
                <div class="analytics-donut" style="background: conic-gradient(<?= clean($conditionPie) ?>);"></div>
                <div class="analytics-legend-list">
                    <?php foreach ($conditionMix as $index => $row): ?>
                    <div><i style="background: <?= clean(array_reverse($colors)[$index % count($colors)]) ?>"></i><span><?= clean($row['label']) ?></span><strong><?= pct($row['total'], max(1, $totalItems)) ?>%</strong></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="analytics-grid analytics-grid-two">
        <div class="analytics-card">
            <div class="analytics-card-header"><h3>Value by Category</h3><span>Capital concentration and funding exposure</span></div>
            <div class="analytics-hbars">
                <?php foreach ($categoryValue as $index => $row): ?>
                <div class="analytics-hbar">
                    <div><strong><?= clean($row['name']) ?></strong><span><?= number_format($row['items']) ?> items · <?= ugx((float)$row['value']) ?></span></div>
                    <span><i style="width: <?= max(3, round(((float)$row['value'] / $categoryMax) * 100)) ?>%; background: <?= clean($colors[$index % count($colors)]) ?>"></i></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="analytics-card">
            <div class="analytics-card-header"><h3>Supplier Exposure</h3><span>Dependency and concentration watch</span></div>
            <div class="analytics-hbars">
                <?php foreach ($supplierValue as $index => $row): ?>
                <div class="analytics-hbar">
                    <div><strong><?= clean($row['name']) ?></strong><span><?= number_format($row['items']) ?> items · <?= ugx((float)$row['value']) ?></span></div>
                    <span><i style="width: <?= max(3, round(((float)$row['value'] / $supplierMax) * 100)) ?>%; background: <?= clean($colors[($index + 2) % count($colors)]) ?>"></i></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="analytics-grid analytics-grid-two">
        <div class="analytics-card">
            <div class="analytics-card-header"><h3>Campus Comparison</h3><span>Value, stock depth and risk by campus</span></div>
            <div class="analytics-campus-list">
                <?php foreach ($campusComparison as $row): ?>
                <?php $riskPct = pct((int)$row['risk'], max(1, (int)$row['items'])); ?>
                <div>
                    <strong><?= clean($row['name']) ?></strong>
                    <span><?= number_format($row['items']) ?> items · <?= number_format($row['units']) ?> units · <?= $riskPct ?>% risk</span>
                    <em><i style="width: <?= max(3, round(((float)$row['value'] / $campusMax) * 100)) ?>%;"></i></em>
                    <small><?= ugx((float)$row['value']) ?></small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="analytics-card">
            <div class="analytics-card-header"><h3>Asset Aging Profile</h3><span>Replacement planning and audit defensibility</span></div>
            <div class="analytics-age-bars">
                <?php foreach ($ageData as $index => $row): ?>
                <div>
                    <span style="height: <?= max(8, round(((int)$row['total'] / $ageMax) * 145)) ?>px; background: <?= clean($colors[$index % count($colors)]) ?>"></span>
                    <strong><?= number_format($row['total']) ?></strong>
                    <small><?= clean($row['label']) ?></small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="analytics-card analytics-decision-card">
        <div class="analytics-card-header"><h3>Decision Signals for Stakeholders</h3><span>Board-level interpretation of operational patterns</span></div>
        <div class="analytics-signal-grid">
            <div class="<?= $riskRate >= 30 ? 'danger' : ($riskRate >= 15 ? 'warn' : 'good') ?>">
                <strong>Inventory Risk</strong>
                <p><?= $riskRate ?>% of assets are tied to low stock, outage, maintenance or non-working states. This indicates <?= $riskRate >= 30 ? 'urgent replenishment and repair prioritisation' : 'manageable but visible operational exposure' ?>.</p>
            </div>
            <div class="<?= $issueReceiptRatio > 100 ? 'warn' : 'good' ?>">
                <strong>Consumption Pressure</strong>
                <p>Issue-to-receipt ratio is <?= $issueReceiptRatio ?>%. Values above 100% mean stock is being consumed faster than it is replenished.</p>
            </div>
            <div class="<?= $dataCompleteness < 90 ? 'warn' : 'good' ?>">
                <strong>Audit Readiness</strong>
                <p>Purchase-date completeness is <?= $dataCompleteness ?>%. Higher completeness improves asset aging, depreciation, warranty and disposal decisions.</p>
            </div>
            <div class="<?= $maintenanceRate >= 10 ? 'warn' : 'good' ?>">
                <strong>Technical Reliability</strong>
                <p><?= $maintenanceRate ?>% of assets are in maintenance or not working. This supports preventive maintenance scheduling and budget justification.</p>
            </div>
        </div>
    </section>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
