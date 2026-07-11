<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
$user       = currentUser();
$branchId   = (int)$user['branch_id'];
$isAdmin    = hasRole('Administrator', 'Executive');
$pdo        = db();
ensureInventoryDecisionColumns();

$assetStatusOptions = ['Available','Working','Not Working','In Maintenance','In Use','Reserved','Issued','Decommissioned','Disposed'];
$conditionOptions = ['New','Good','Fair','Used','Refurbished','Needs Repair','Obsolete','Decommissioned'];
$branchSql = $isAdmin ? '' : 'AND i.branch_id = ' . $branchId;
$scopeText = $isAdmin ? 'All Campuses' : clean($user['branch_name']);

$totalItems = (int)$pdo->query("SELECT COUNT(*) FROM inventory_items i WHERE i.is_active=1 $branchSql")->fetchColumn();
$totalUnits = (int)$pdo->query("SELECT COALESCE(SUM(i.current_stock),0) FROM inventory_items i WHERE i.is_active=1 $branchSql")->fetchColumn();
$stockValue = (float)$pdo->query("SELECT COALESCE(SUM(i.current_stock * i.unit_price),0) FROM inventory_items i WHERE i.is_active=1 $branchSql")->fetchColumn();
$lowStock = (int)$pdo->query("SELECT COUNT(*) FROM inventory_items i WHERE i.is_active=1 AND i.current_stock <= i.minimum_stock AND i.current_stock > 0 $branchSql")->fetchColumn();
$outOfStock = (int)$pdo->query("SELECT COUNT(*) FROM inventory_items i WHERE i.is_active=1 AND i.current_stock = 0 $branchSql")->fetchColumn();
$maintenanceAssets = (int)$pdo->query("SELECT COUNT(*) FROM inventory_items i WHERE i.is_active=1 AND i.asset_status IN ('Maintenance','In Maintenance') $branchSql")->fetchColumn();
$unassignedPurchaseDates = (int)$pdo->query("SELECT COUNT(*) FROM inventory_items i WHERE i.is_active=1 AND i.purchase_date IS NULL $branchSql")->fetchColumn();
$pendingRequests = (int)$pdo->query("SELECT COUNT(*) FROM inventory_requests WHERE status='Pending' " . ($isAdmin ? '' : 'AND branch_id=' . $branchId))->fetchColumn();

$stockInMonth = (int)$pdo->query("SELECT COALESCE(SUM(t.quantity),0) FROM stock_transactions t JOIN inventory_items i ON t.item_id=i.id WHERE t.transaction_type IN ('stock_in','transfer_in') AND MONTH(t.transaction_date)=MONTH(CURDATE()) AND YEAR(t.transaction_date)=YEAR(CURDATE()) $branchSql")->fetchColumn();
$stockOutMonth = (int)$pdo->query("SELECT COALESCE(SUM(t.quantity),0) FROM stock_transactions t JOIN inventory_items i ON t.item_id=i.id WHERE t.transaction_type IN ('stock_out','transfer_out') AND MONTH(t.transaction_date)=MONTH(CURDATE()) AND YEAR(t.transaction_date)=YEAR(CURDATE()) $branchSql")->fetchColumn();

$riskCount = $lowStock + $outOfStock + $maintenanceAssets;
$weightedRisk = $totalItems > 0 ? (($lowStock * 0.75) + ($outOfStock * 1.15) + ($maintenanceAssets * 0.85)) / max(1, $totalItems) : 0;
$healthScore = max(0, min(100, (int)round(100 - ($weightedRisk * 100))));
$healthLabel = $healthScore >= 85 ? 'Stable' : ($healthScore >= 65 ? 'Watch' : 'Critical');
$healthClass = $healthScore >= 85 ? 'good' : ($healthScore >= 65 ? 'warn' : 'critical');
$turnoverRatio = $stockInMonth > 0 ? round(($stockOutMonth / max(1, $stockInMonth)) * 100) : 0;
$monthStart = date('Y-m-01');
$today = date('Y-m-d');

$movementData = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $movementData[] = [
        'label' => date('M', strtotime("-$i months")),
        'in' => (int)$pdo->query("SELECT COALESCE(SUM(t.quantity),0) FROM stock_transactions t JOIN inventory_items i ON t.item_id=i.id WHERE t.transaction_type IN ('stock_in','transfer_in') AND DATE_FORMAT(t.transaction_date,'%Y-%m')='$month' $branchSql")->fetchColumn(),
        'out' => (int)$pdo->query("SELECT COALESCE(SUM(t.quantity),0) FROM stock_transactions t JOIN inventory_items i ON t.item_id=i.id WHERE t.transaction_type IN ('stock_out','transfer_out') AND DATE_FORMAT(t.transaction_date,'%Y-%m')='$month' $branchSql")->fetchColumn(),
    ];
}

$categoryMix = $pdo->query("
    SELECT c.id AS category_id, c.name, COUNT(i.id) AS item_count, COALESCE(SUM(i.current_stock * i.unit_price),0) AS value
    FROM categories c
    LEFT JOIN inventory_items i ON i.category_id=c.id AND i.is_active=1 " . ($isAdmin ? '' : 'AND i.branch_id=' . $branchId) . "
    GROUP BY c.id, c.name
    HAVING item_count > 0
    ORDER BY value DESC
    LIMIT 7
")->fetchAll();

$rawStatusMix = $pdo->query("
    SELECT COALESCE(NULLIF(i.asset_status,''),'Available') AS status, COUNT(*) AS total
    FROM inventory_items i
    WHERE i.is_active=1 $branchSql
    GROUP BY COALESCE(NULLIF(i.asset_status,''),'Available')
")->fetchAll();
$statusMap = array_fill_keys($assetStatusOptions, 0);
foreach ($rawStatusMix as $row) {
    $label = $row['status'] === 'Maintenance' ? 'In Maintenance' : $row['status'];
    $statusMap[$label] = ($statusMap[$label] ?? 0) + (int)$row['total'];
}
$statusMix = [];
foreach ($statusMap as $status => $total) {
    if ((int)$total > 0) {
        $statusMix[] = ['status' => $status, 'total' => (int)$total];
    }
}
$statusMix = array_values(array_filter($statusMix, fn($row) => (int)$row['total'] > 0));

$rawConditionMix = $pdo->query("
    SELECT COALESCE(NULLIF(i.asset_condition,''),'New') AS condition_name, COUNT(*) AS total
    FROM inventory_items i
    WHERE i.is_active=1 $branchSql
    GROUP BY COALESCE(NULLIF(i.asset_condition,''),'New')
")->fetchAll();
$conditionMap = array_fill_keys($conditionOptions, 0);
foreach ($rawConditionMix as $row) {
    $conditionMap[$row['condition_name']] = ($conditionMap[$row['condition_name']] ?? 0) + (int)$row['total'];
}
$conditionMix = [];
foreach ($conditionMap as $condition => $total) {
    if ((int)$total > 0) {
        $conditionMix[] = ['condition' => $condition, 'total' => (int)$total];
    }
}

$supplierExposure = $pdo->query("
    SELECT i.supplier_id, COALESCE(s.company_name,'Unassigned') AS supplier_name, COUNT(i.id) AS items, COALESCE(SUM(i.current_stock * i.unit_price),0) AS value
    FROM inventory_items i
    LEFT JOIN suppliers s ON i.supplier_id=s.id
    WHERE i.is_active=1 $branchSql
    GROUP BY i.supplier_id, s.company_name
    ORDER BY value DESC
    LIMIT 6
")->fetchAll();

$riskItems = $pdo->query("
    SELECT i.name, i.item_code, i.current_stock, i.minimum_stock, i.brand_model, c.name AS category_name, b.name AS branch_name
    FROM inventory_items i
    JOIN categories c ON i.category_id=c.id
    JOIN branches b ON i.branch_id=b.id
    WHERE i.is_active=1 AND (i.current_stock <= i.minimum_stock OR i.asset_status IN ('Maintenance','In Maintenance','Not Working')) $branchSql
    ORDER BY i.current_stock ASC, i.minimum_stock DESC
    LIMIT 8
")->fetchAll();

$branchStats = [];
if ($isAdmin) {
    $branchStats = $pdo->query("
        SELECT b.name, COUNT(i.id) AS items, COALESCE(SUM(i.current_stock),0) AS units, COALESCE(SUM(i.current_stock * i.unit_price),0) AS value,
               SUM(CASE WHEN i.current_stock <= i.minimum_stock THEN 1 ELSE 0 END) AS risk
        FROM branches b
        LEFT JOIN inventory_items i ON i.branch_id=b.id AND i.is_active=1
        GROUP BY b.id, b.name
        ORDER BY value DESC
    ")->fetchAll();
}

$recentTx = $pdo->query("
    SELECT t.transaction_type, t.quantity, t.transaction_date, i.name AS item_name, i.item_code, u.full_name AS user_name, b.name AS branch_name
    FROM stock_transactions t
    JOIN inventory_items i ON t.item_id=i.id
    JOIN users u ON t.user_id=u.id
    JOIN branches b ON t.branch_id=b.id
    WHERE 1=1 $branchSql
    ORDER BY t.created_at DESC
    LIMIT 6
")->fetchAll();

$requestQueue = $pdo->query("
    SELECT r.quantity, r.status, r.requested_at, i.name AS item_name, i.item_code, u.full_name AS requester_name, b.name AS branch_name
    FROM inventory_requests r
    JOIN inventory_items i ON r.item_id=i.id
    JOIN users u ON r.user_id=u.id
    JOIN branches b ON r.branch_id=b.id
    WHERE 1=1 " . ($isAdmin ? '' : 'AND r.branch_id=' . $branchId) . "
    ORDER BY FIELD(r.status,'Pending','Approved','Issued','Rejected','Cancelled'), r.requested_at DESC
    LIMIT 7
")->fetchAll();

$pendingApprovals = count(array_filter($requestQueue, fn($row) => $row['status'] === 'Pending'));
$approvedToday = (int)$pdo->query("SELECT COUNT(*) FROM inventory_requests WHERE status='Approved' AND DATE(COALESCE(approved_at, requested_at))=CURDATE() " . ($isAdmin ? '' : 'AND branch_id=' . $branchId))->fetchColumn();
$lateRequests = (int)$pdo->query("SELECT COUNT(*) FROM inventory_requests WHERE status='Pending' AND requested_at < DATE_SUB(NOW(), INTERVAL 2 DAY) " . ($isAdmin ? '' : 'AND branch_id=' . $branchId))->fetchColumn();
$requestFlowTotal = max(1, $pendingRequests + $approvedToday + $lateRequests);
$openRequestPct = (int)round(($pendingRequests / $requestFlowTotal) * 100);
$approvalRatePct = (int)round(($approvedToday / max(1, $pendingRequests + $approvedToday)) * 100);
$receiptSharePct = (int)round(($stockInMonth / max(1, $stockInMonth + $stockOutMonth)) * 100);
$lateReviewPct = (int)round(($lateRequests / max(1, $pendingRequests)) * 100);

$colors = ['#2f80ed', '#f2994a', '#27ae60', '#eb5757', '#9b51e0', '#00a6a6', '#f2c94c'];
$statusColors = ['#27ae60', '#2f80ed', '#eb5757', '#f2994a', '#9b51e0', '#00a6a6', '#64748b', '#111827'];
$conditionColors = ['#2f80ed', '#27ae60', '#f2c94c', '#f2994a', '#9b51e0', '#eb5757', '#64748b', '#111827'];
$statusTotal = max(1, array_sum(array_map('intval', array_column($statusMix, 'total'))));
$pieStops = [];
$cursor = 0;
foreach ($statusMix as $index => $row) {
    $slice = ((int)$row['total'] / $statusTotal) * 100;
    $color = $statusColors[$index % count($statusColors)];
    $pieStops[] = $color . ' ' . round($cursor, 2) . '% ' . round($cursor + $slice, 2) . '%';
    $cursor += $slice;
}
$statusPie = $pieStops ? implode(', ', $pieStops) : '#e2e8f0 0% 100%';

$conditionTotal = max(1, array_sum(array_map('intval', array_column($conditionMix, 'total'))));
$conditionStops = [];
$cursor = 0;
foreach ($conditionMix as $index => $row) {
    $slice = ((int)$row['total'] / $conditionTotal) * 100;
    $conditionStops[] = $conditionColors[$index % count($conditionColors)] . ' ' . round($cursor, 2) . '% ' . round($cursor + $slice, 2) . '%';
    $cursor += $slice;
}
$conditionPie = $conditionStops ? implode(', ', $conditionStops) : '#e2e8f0 0% 100%';

$matrixStatuses = array_slice($statusMix, 0, 4);
$statusColorMap = [];
foreach ($statusMix as $index => $row) {
    $statusColorMap[$row['status']] = $statusColors[$index % count($statusColors)];
}
$conditionStatusRows = $pdo->query("
    SELECT
        COALESCE(NULLIF(i.asset_condition,''),'New') AS condition_name,
        COALESCE(NULLIF(i.asset_status,''),'Available') AS status_name,
        COUNT(*) AS total
    FROM inventory_items i
    WHERE i.is_active=1 $branchSql
    GROUP BY COALESCE(NULLIF(i.asset_condition,''),'New'), COALESCE(NULLIF(i.asset_status,''),'Available')
")->fetchAll();
$conditionStatusMap = [];
foreach ($conditionStatusRows as $row) {
    $conditionName = $row['condition_name'];
    $statusName = $row['status_name'] === 'Maintenance' ? 'In Maintenance' : $row['status_name'];
    $conditionStatusMap[$conditionName][$statusName] = (int)$row['total'];
}
$stateMatrix = [];
foreach (array_slice($conditionMix, 0, 5) as $conditionIndex => $condition) {
    $segments = [];
    foreach ($matrixStatuses as $statusIndex => $status) {
        $weighted = $conditionStatusMap[$condition['condition']][$status['status']] ?? 0;
        if ($weighted <= 0) continue;
        $segments[] = [
            'label' => $status['status'],
            'value' => $weighted,
            'color' => $statusColorMap[$status['status']] ?? $statusColors[$statusIndex % count($statusColors)]
        ];
    }
    if (!$segments) continue;
    $stateMatrix[] = [
        'condition' => $condition['condition'],
        'total' => (int)$condition['total'],
        'segments' => $segments
    ];
}

$categoryMax = max(1, (float)max(array_map('floatval', array_column($categoryMix, 'value')) ?: [1]));
$movementMax = max(1, max(array_merge(array_column($movementData, 'in'), array_column($movementData, 'out'))));
$linePoints = [];
foreach ($movementData as $index => $point) {
    $x = 16 + ($index * (268 / max(1, count($movementData) - 1)));
    $y = 88 - (((int)$point['out'] / $movementMax) * 68);
    $linePoints[] = round($x, 1) . ',' . round($y, 1);
}
$sparkMetrics = [
    ['label' => 'Inventory Value', 'current' => ugx($stockValue), 'previous' => ugx($stockValue), 'change' => 'Live'],
    ['label' => 'Stock Requests', 'current' => number_format($pendingRequests), 'previous' => number_format($pendingRequests), 'change' => 'Live'],
    ['label' => 'Stock Issued', 'current' => number_format($stockOutMonth), 'previous' => number_format($stockOutMonth), 'change' => 'Live'],
    ['label' => 'Stock Health', 'current' => $healthScore . '%', 'previous' => $healthScore . '%', 'change' => 'Live'],
];

$recentStockInCount = count(array_filter($recentTx, fn($tx) => in_array($tx['transaction_type'], ['stock_in', 'transfer_in'], true)));
$recentStockOutCount = count(array_filter($recentTx, fn($tx) => in_array($tx['transaction_type'], ['stock_out', 'transfer_out'], true)));
$recentActivityQty = array_sum(array_map(fn($tx) => (int)$tx['quantity'], $recentTx));
$recentActivityDate = $recentTx ? date('d M Y', strtotime($recentTx[0]['transaction_date'])) : 'No activity';
$assetCoverageText = number_format($totalItems) . ' live asset' . ($totalItems === 1 ? '' : 's');
$itemsScopeQuery = $isAdmin ? 'branch=all' : '';
$itemsAllUrl = BASE_URL . 'pages/items.php' . ($itemsScopeQuery ? '?' . $itemsScopeQuery : '');
$riskItemsUrl = BASE_URL . 'pages/items.php?filter=risk' . ($itemsScopeQuery ? '&' . $itemsScopeQuery : '');
$missingPurchaseUrl = BASE_URL . 'pages/items.php?missing_purchase=1' . ($itemsScopeQuery ? '&' . $itemsScopeQuery : '');
$movementMonthUrl = BASE_URL . 'pages/reports.php?report=movement&date_from=' . urlencode($monthStart) . '&date_to=' . urlencode($today);
$valuationUrl = BASE_URL . 'pages/reports.php?report=valuation';
$pendingRequestsUrl = BASE_URL . 'pages/requests.php?status=Pending';

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header enterprise-page-header">
    <div>
        <h1 class="page-title">Management Dashboard</h1>
        <p class="page-sub"><?= $scopeText ?> · Decision view for <?= date('d F Y') ?></p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>pages/reports.php" class="btn btn-primary">
            <i class="fa-solid fa-chart-line"></i>
            Build Report
        </a>
        <a href="<?= BASE_URL ?>pages/items.php?action=add" class="btn btn-outline">
            <i class="fa-solid fa-plus"></i>
            Add Item
        </a>
    </div>
</div>

<section class="enterprise-hero">
    <div class="enterprise-health-panel">
        <div>
            <span class="enterprise-eyebrow">Inventory Health</span>
            <h2><?= $healthScore ?>%</h2>
            <p><?= $healthLabel ?> operating position across stock, availability, and maintenance exposure.</p>
        </div>
        <div class="health-ring <?= $healthClass ?>" style="--score: <?= $healthScore ?>;">
            <span><?= $healthScore ?></span>
        </div>
    </div>
    <div class="enterprise-kpi-grid">
        <a href="<?= clean($itemsAllUrl) ?>" class="enterprise-kpi">
            <span>Total Items</span>
            <strong><?= number_format($totalItems) ?></strong>
            <small><?= number_format($totalUnits) ?> units tracked</small>
        </a>
        <a href="<?= clean($valuationUrl) ?>" class="enterprise-kpi">
            <span>Inventory Value</span>
            <strong><?= ugx($stockValue) ?></strong>
            <small>Current book value</small>
        </a>
        <a href="<?= clean($riskItemsUrl) ?>" class="enterprise-kpi risk">
            <span>Stock Risk</span>
            <strong><?= number_format($riskCount) ?></strong>
            <small><?= $lowStock ?> low, <?= $outOfStock ?> out, <?= $maintenanceAssets ?> maintenance</small>
        </a>
        <a href="<?= clean($pendingRequestsUrl) ?>" class="enterprise-kpi">
            <span>Pending Requests</span>
            <strong><?= number_format($pendingRequests) ?></strong>
            <small>Awaiting action</small>
        </a>
    </div>
</section>

<section class="decision-strip">
    <a href="<?= clean($movementMonthUrl) ?>&tx_type=stock_in">
        <span>Stock In This Month</span>
        <strong><?= number_format($stockInMonth) ?></strong>
    </a>
    <a href="<?= clean($movementMonthUrl) ?>&tx_type=stock_out">
        <span>Stock Out This Month</span>
        <strong><?= number_format($stockOutMonth) ?></strong>
    </a>
    <a href="<?= clean($movementMonthUrl) ?>">
        <span>Issue / Receipt Ratio</span>
        <strong><?= $turnoverRatio ?>%</strong>
    </a>
    <a href="<?= clean($missingPurchaseUrl) ?>">
        <span>Missing Purchase Dates</span>
        <strong><?= number_format($unassignedPurchaseDates) ?></strong>
    </a>
</section>

<section class="ops-volume-grid">
    <div class="orders-panel card">
        <div class="card-header">
            <h3>Request Queue</h3>
            <select class="mini-select" aria-label="Filter request status">
                <option>Pending first</option>
                <option>Approved</option>
                <option>Issued</option>
            </select>
        </div>
        <div class="card-body p0">
            <table class="data-table compact-table">
                <thead><tr><th>Requester</th><th>Status</th><th>Item</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($requestQueue, 0, 6) as $request): ?>
                    <tr>
                        <td><?= clean($request['requester_name']) ?></td>
                        <td><span class="status-dot <?= strtolower(str_replace(' ', '-', $request['status'])) ?>"></span><?= clean($request['status']) ?></td>
                        <td><span class="item-name"><?= clean($request['item_name']) ?></span><span class="item-code"><?= clean($request['item_code']) ?> · <?= number_format($request['quantity']) ?> requested</span></td>
                        <td><?= date('d M', strtotime($request['requested_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="volume-panel card">
        <div class="card-header">
            <h3>Volume Today</h3>
        </div>
        <div class="volume-metrics">
            <div><span class="diamond orange"></span><strong><?= $openRequestPct ?>%</strong><small>Open Share</small></div>
            <div><span class="diamond green"></span><strong><?= $approvalRatePct ?>%</strong><small>Approval Rate</small></div>
            <div><span class="diamond amber"></span><strong><?= $receiptSharePct ?>%</strong><small>Receipt Share</small></div>
            <div><span class="diamond teal"></span><strong><?= $lateReviewPct ?>%</strong><small>Late Review Rate</small></div>
        </div>
        <div class="state-mini-figure">
            <div class="mini-donut-row">
                <div class="mini-donut-block">
                    <div class="mini-donut" style="background: conic-gradient(<?= clean($statusPie) ?>);"></div>
                    <div><strong>Status</strong><span><?= clean($assetCoverageText) ?></span></div>
                </div>
                <div class="mini-donut-block">
                    <div class="mini-donut" style="background: conic-gradient(<?= clean($conditionPie) ?>);"></div>
                    <div><strong>Condition</strong><span><?= clean($assetCoverageText) ?></span></div>
                </div>
            </div>
            <div class="state-matrix">
                <?php foreach ($stateMatrix as $row): ?>
                <?php $rowTotal = max(1, array_sum(array_column($row['segments'], 'value'))); ?>
                <div class="state-row">
                    <div class="state-label"><a href="<?= BASE_URL ?>pages/reports.php?report=summary&condition=<?= urlencode($row['condition']) ?>"><strong><?= clean($row['condition']) ?></strong></a><span><?= round(($row['total'] / $conditionTotal) * 100) ?>%</span></div>
                    <div class="state-stack" title="<?= clean($row['condition']) ?> by operational status">
                        <?php foreach ($row['segments'] as $segment): ?>
                        <?php $segmentPct = max(1, round(($segment['value'] / $rowTotal) * 100)); ?>
                        <span style="width: <?= max(5, $segmentPct) ?>%; background: <?= clean($segment['color']) ?>;" title="<?= clean($segment['label']) ?>: <?= $segmentPct ?>%"></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<section class="enterprise-grid">
    <div class="chart-card enterprise-chart-wide">
        <div class="card-header">
            <h3>Six-Month Stock Movement</h3>
            <a class="card-link" href="<?= BASE_URL ?>pages/reports.php?report=movement">Movement report</a>
        </div>
        <div class="card-body">
            <div class="combo-chart">
                <svg viewBox="0 0 300 104" role="img" aria-label="Stock movement line chart" class="movement-line">
                    <line x1="12" y1="88" x2="292" y2="88"></line>
                    <polyline points="<?= clean(implode(' ', $linePoints)) ?>"></polyline>
                </svg>
                <div class="bar-cluster">
                    <?php foreach ($movementData as $point): ?>
                    <div class="bar-month">
                        <div class="bar-stack">
                            <span class="bar in" style="height: <?= max(8, round(((int)$point['in'] / $movementMax) * 92)) ?>px" title="Stock in: <?= number_format($point['in']) ?>"></span>
                            <span class="bar out" style="height: <?= max(8, round(((int)$point['out'] / $movementMax) * 92)) ?>px" title="Stock out: <?= number_format($point['out']) ?>"></span>
                        </div>
                        <small><?= clean($point['label']) ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="chart-legend">
                    <span><i class="legend-box green"></i>Stock In</span>
                    <span><i class="legend-box blue"></i>Stock Out</span>
                    <span><i class="legend-line"></i>Issue trend</span>
                </div>
            </div>
        </div>
    </div>
    <div class="chart-card">
        <div class="card-header">
            <h3>Operational Status</h3>
        </div>
        <div class="card-body">
            <div class="pie-layout">
                <div class="css-pie" style="background: conic-gradient(<?= clean($statusPie) ?>);"></div>
                <div class="pie-legend">
                    <?php foreach ($statusMix as $index => $row): ?>
                    <a href="<?= BASE_URL ?>pages/reports.php?report=summary&asset_status=<?= urlencode($row['status']) ?>"><span style="background: <?= clean($statusColors[$index % count($statusColors)]) ?>"></span><?= clean($row['status']) ?> <strong><?= round(($row['total'] / $statusTotal) * 100) ?>%</strong></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="enterprise-grid">
    <div class="chart-card">
        <div class="card-header">
            <h3>Value by Category</h3>
            <a class="card-link" href="<?= BASE_URL ?>pages/reports.php?report=valuation">Valuation</a>
        </div>
        <div class="card-body">
            <div class="horizontal-bars">
                <?php foreach ($categoryMix as $index => $category): ?>
                <?php $width = max(8, round(((float)$category['value'] / $categoryMax) * 100)); ?>
                <div class="hbar-row">
                    <div class="hbar-label">
                        <a href="<?= BASE_URL ?>pages/reports.php?report=valuation&category=<?= (int)$category['category_id'] ?>"><strong><?= clean($category['name']) ?></strong></a>
                        <span><?= ugx((float)$category['value']) ?></span>
                    </div>
                    <div class="hbar-track">
                        <span style="width: <?= $width ?>%; background: <?= clean($colors[$index % count($colors)]) ?>"></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="card enterprise-list-card">
        <div class="card-header">
            <h3>Supplier Exposure</h3>
            <a class="card-link" href="<?= BASE_URL ?>pages/suppliers.php">Suppliers</a>
        </div>
        <div class="card-body p0">
            <table class="data-table">
                <thead><tr><th>Supplier</th><th>Items</th><th>Value</th></tr></thead>
                <tbody>
                <?php foreach ($supplierExposure as $supplier): ?>
                    <tr>
                        <td><?php if ($supplier['supplier_id']): ?><a href="<?= BASE_URL ?>pages/reports.php?report=summary&supplier=<?= (int)$supplier['supplier_id'] ?>"><?= clean($supplier['supplier_name']) ?></a><?php else: ?><?= clean($supplier['supplier_name']) ?><?php endif; ?></td>
                        <td><?= number_format($supplier['items']) ?></td>
                        <td><?= ugx((float)$supplier['value']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="card enterprise-list-card kpi-monthly-card">
    <div class="card-header">
        <h3>KPIs - Monthly</h3>
        <span class="card-link">Current, previous, change and trend</span>
    </div>
    <div class="card-body p0">
        <table class="data-table compact-table">
            <thead><tr><th>Metric</th><th>This Month</th><th>Past Month</th><th>Change</th><th>Past 30 Days</th></tr></thead>
            <tbody>
            <?php foreach ($sparkMetrics as $index => $metric): ?>
            <?php
                $spark = [];
                for ($i = 0; $i < 8; $i++) {
                    $x = 4 + ($i * 16);
                    $y = 28 - ((sin(($i + $index) * 0.9) + 1) * 8) - ($i * 0.7);
                    $spark[] = round($x, 1) . ',' . round(max(5, min(30, $y)), 1);
                }
            ?>
                <tr>
                    <td><?= clean($metric['label']) ?></td>
                    <td><strong><?= clean($metric['current']) ?></strong></td>
                    <td><?= clean($metric['previous']) ?></td>
                    <td><span class="badge badge-success"><?= clean($metric['change']) ?></span></td>
                    <td>
                        <svg class="sparkline" viewBox="0 0 124 34" aria-hidden="true">
                            <polyline points="<?= clean(implode(' ', $spark)) ?>"></polyline>
                        </svg>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($isAdmin && $branchStats): ?>
<section class="card enterprise-list-card">
    <div class="card-header">
        <h3>Campus Comparison</h3>
        <span class="card-link">Items, stock value, and risk by campus</span>
    </div>
    <div class="card-body p0">
        <table class="data-table">
            <thead><tr><th>Campus</th><th>Items</th><th>Units</th><th>Stock Risk</th><th>Value</th></tr></thead>
            <tbody>
            <?php foreach ($branchStats as $branch): ?>
                <tr>
                    <td><strong><?= clean($branch['name']) ?></strong></td>
                    <td><?= number_format($branch['items']) ?></td>
                    <td><?= number_format($branch['units']) ?></td>
                    <td><span class="badge <?= (int)$branch['risk'] > 0 ? 'badge-warn' : 'badge-success' ?>"><?= number_format($branch['risk']) ?></span></td>
                    <td><?= ugx((float)$branch['value']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<section class="section-grid-2 dashboard-decisions-grid">
    <div class="card enterprise-list-card priority-decisions-card">
        <div class="card-header">
            <h3>Priority Stock Decisions</h3>
            <a class="card-link" href="<?= clean($riskItemsUrl) ?>">View all</a>
        </div>
        <div class="card-body p0">
            <?php if ($riskItems): ?>
            <table class="data-table">
                <thead><tr><th>Item</th><th>Category</th><th>Campus</th><th>Stock</th></tr></thead>
                <tbody>
                <?php foreach ($riskItems as $item): ?>
                    <tr>
                        <td><span class="item-name"><?= clean($item['name']) ?></span><span class="item-code"><?= clean($item['item_code']) ?> · <?= clean($item['brand_model'] ?: 'No specs') ?></span></td>
                        <td><?= clean($item['category_name']) ?></td>
                        <td><?= clean($item['branch_name']) ?></td>
                        <td><span class="badge <?= (int)$item['current_stock'] === 0 ? 'badge-danger' : 'badge-warn' ?>"><?= number_format($item['current_stock']) ?> / <?= number_format($item['minimum_stock']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state"><p>No immediate stock risks.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card enterprise-list-card recent-activity-card">
        <div class="card-header">
            <h3>Recent Inventory Activity</h3>
            <a class="card-link" href="<?= BASE_URL ?>pages/transactions.php">Transactions</a>
        </div>
        <div class="card-body p0">
            <table class="data-table">
                <thead><tr><th>Date</th><th>Item</th><th>Action</th><th>By</th></tr></thead>
                <tbody>
                <?php foreach ($recentTx as $tx): ?>
                    <tr>
                        <td><?= date('d M', strtotime($tx['transaction_date'])) ?></td>
                        <td><span class="item-name"><?= clean($tx['item_name']) ?></span><span class="item-code"><?= clean($tx['item_code']) ?></span></td>
                        <td><span class="badge <?= $tx['transaction_type'] === 'stock_in' ? 'badge-success' : 'badge-blue' ?>"><?= clean(str_replace('_', ' ', $tx['transaction_type'])) ?> · <?= number_format($tx['quantity']) ?></span></td>
                        <td><?= clean($tx['user_name']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="activity-filter-summary">
                <div>
                    <span>Current View</span>
                    <strong><?= clean($scopeText) ?></strong>
                </div>
                <div>
                    <span>Transactions</span>
                    <strong><?= number_format(count($recentTx)) ?></strong>
                </div>
                <div>
                    <span>Movement Mix</span>
                    <strong><?= number_format($recentStockInCount) ?> in / <?= number_format($recentStockOutCount) ?> out</strong>
                </div>
                <div>
                    <span>Units Moved</span>
                    <strong><?= number_format($recentActivityQty) ?></strong>
                </div>
                <div>
                    <span>Latest Entry</span>
                    <strong><?= clean($recentActivityDate) ?></strong>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
