<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
$user       = currentUser();
$branchId   = $user['branch_id'];
$isAdmin    = hasRole('Administrator');

$pdo = db();

// ── Stats for current branch (or all branches for admin) ──────────────────────
$bFilter = $isAdmin ? "" : "AND i.branch_id = $branchId";

// Total items
$totalItems = $pdo->query("SELECT COUNT(*) FROM inventory_items i WHERE i.is_active=1 $bFilter")->fetchColumn();

// Total stock value
$stockValue = $pdo->query("SELECT SUM(i.current_stock * i.unit_price) FROM inventory_items i WHERE i.is_active=1 $bFilter")->fetchColumn() ?? 0;

// Low stock items
$lowStock   = $pdo->query("SELECT COUNT(*) FROM inventory_items i WHERE i.is_active=1 AND i.current_stock <= i.minimum_stock $bFilter")->fetchColumn();

// Out of stock
$outOfStock = $pdo->query("SELECT COUNT(*) FROM inventory_items i WHERE i.is_active=1 AND i.current_stock = 0 $bFilter")->fetchColumn();

// Total suppliers
$totalSuppliers = $pdo->query("SELECT COUNT(*) FROM suppliers WHERE is_active=1")->fetchColumn();

// Stock-in this month
$stockInMonth = $pdo->query("SELECT COALESCE(SUM(t.quantity),0) FROM stock_transactions t 
    JOIN inventory_items i ON t.item_id = i.id
    WHERE t.transaction_type='stock_in' AND MONTH(t.transaction_date)=MONTH(NOW()) AND YEAR(t.transaction_date)=YEAR(NOW()) AND i.branch_id = t.branch_id $bFilter")->fetchColumn();

// Stock-out this month
$stockOutMonth = $pdo->query("SELECT COALESCE(SUM(t.quantity),0) FROM stock_transactions t 
    JOIN inventory_items i ON t.item_id = i.id
    WHERE t.transaction_type='stock_out' AND MONTH(t.transaction_date)=MONTH(NOW()) AND YEAR(t.transaction_date)=YEAR(NOW()) AND i.branch_id = t.branch_id $bFilter")->fetchColumn();

// ── Low stock items list ──────────────────────────────────────────────────────
$lowStockItems = $pdo->query("
    SELECT i.*, c.name AS category_name, b.name AS branch_name
    FROM inventory_items i
    JOIN categories c ON i.category_id = c.id
    JOIN branches b ON i.branch_id = b.id
    WHERE i.is_active=1 AND i.current_stock <= i.minimum_stock $bFilter
    ORDER BY i.current_stock ASC LIMIT 8
")->fetchAll();

// ── Recently added items ─────────────────────────────────────────────────────
$recentItems = $pdo->query("
    SELECT i.*, c.name AS category_name, b.name AS branch_name
    FROM inventory_items i
    JOIN categories c ON i.category_id = c.id
    JOIN branches b ON i.branch_id = b.id
    WHERE i.is_active=1 $bFilter
    ORDER BY i.created_at DESC LIMIT 6
")->fetchAll();

// ── Recent transactions ───────────────────────────────────────────────────────
$recentTx = $pdo->query("
    SELECT t.*, i.name AS item_name, i.item_code, u.full_name AS user_name, b.name AS branch_name
    FROM stock_transactions t
    JOIN inventory_items i ON t.item_id = i.id
    JOIN users u ON t.user_id = u.id
    JOIN branches b ON t.branch_id = b.id
    WHERE 1=1 $bFilter
    ORDER BY t.created_at DESC LIMIT 8
")->fetchAll();

// ── Monthly stock chart data (last 6 months) ──────────────────────────────────
$chartData = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $label = date('M Y', strtotime("-$i months"));
    $in  = $pdo->query("SELECT COALESCE(SUM(t.quantity),0) FROM stock_transactions t JOIN inventory_items i ON t.item_id=i.id WHERE t.transaction_type='stock_in' AND DATE_FORMAT(t.transaction_date,'%Y-%m')='$m' AND i.branch_id = t.branch_id $bFilter")->fetchColumn();
    $out = $pdo->query("SELECT COALESCE(SUM(t.quantity),0) FROM stock_transactions t JOIN inventory_items i ON t.item_id=i.id WHERE t.transaction_type='stock_out' AND DATE_FORMAT(t.transaction_date,'%Y-%m')='$m' AND i.branch_id = t.branch_id $bFilter")->fetchColumn();
    $chartData[] = ['label' => $label, 'in' => (int)$in, 'out' => (int)$out];
}

// ── Category breakdown ────────────────────────────────────────────────────────
$catBreakdown = $pdo->query("
    SELECT c.name, COUNT(i.id) AS item_count, COALESCE(SUM(i.current_stock),0) AS total_stock
    FROM categories c
    LEFT JOIN inventory_items i ON i.category_id = c.id AND i.is_active=1 $bFilter
    GROUP BY c.id, c.name ORDER BY item_count DESC
")->fetchAll();

// ── Inventory growth (last 6 months) ────────────────────────────────────────
$growthData = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $label = date('M Y', strtotime("-$i months"));
    $count = $pdo->query("SELECT COUNT(*) FROM inventory_items i WHERE i.is_active=1 AND DATE_FORMAT(i.created_at,'%Y-%m')='$m' $bFilter")->fetchColumn();
    $growthData[] = ['label' => $label, 'count' => (int)$count];
}

// ── Campus breakdown ────────────────────────────────────────────────────────
$campusBreakdown = $pdo->query("
    SELECT b.name, COUNT(i.id) AS item_count, COALESCE(SUM(i.current_stock),0) AS total_stock
    FROM branches b
    LEFT JOIN inventory_items i ON i.branch_id = b.id AND i.is_active=1 $bFilter
    GROUP BY b.id, b.name ORDER BY item_count DESC
")->fetchAll();

// ── Supplier analysis ────────────────────────────────────────────────────────
$supplierBreakdown = $pdo->query("
    SELECT s.company_name AS name, COUNT(i.id) AS item_count
    FROM suppliers s
    LEFT JOIN inventory_items i ON i.supplier_id = s.id AND i.is_active=1 $bFilter
    GROUP BY s.id, s.company_name ORDER BY item_count DESC LIMIT 8
")->fetchAll();

// ── Asset status summary ─────────────────────────────────────────────────────
$assetStatusData = [
    ['label' => 'In Stock', 'value' => (int)$pdo->query("SELECT COUNT(*) FROM inventory_items i WHERE i.is_active=1 AND i.current_stock > i.minimum_stock $bFilter")->fetchColumn()],
    ['label' => 'Low Stock', 'value' => (int)$pdo->query("SELECT COUNT(*) FROM inventory_items i WHERE i.is_active=1 AND i.current_stock <= i.minimum_stock AND i.current_stock > 0 $bFilter")->fetchColumn()],
    ['label' => 'Out of Stock', 'value' => (int)$pdo->query("SELECT COUNT(*) FROM inventory_items i WHERE i.is_active=1 AND i.current_stock = 0 $bFilter")->fetchColumn()]
];

$pendingRequests = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0")->fetchColumn();
$pendingApprovals = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE type = 'approvals' AND is_read = 0")->fetchColumn();
$healthScore = max(0, min(100, round(100 - ($lowStock * 3) - ($outOfStock * 5))));
$healthLabel = $healthScore >= 90 ? 'Excellent' : ($healthScore >= 75 ? 'Stable' : ($healthScore >= 55 ? 'Needs attention' : 'Critical'));
$healthTone = $healthScore >= 90 ? 'good' : ($healthScore >= 75 ? 'warn' : 'critical');

$departmentPerformance = $pdo->query("SELECT d.name, COUNT(i.id) AS items, COUNT(CASE WHEN i.current_stock <= i.minimum_stock AND i.current_stock > 0 THEN 1 END) AS low_stock
    FROM departments d
    LEFT JOIN inventory_items i ON i.department_id = d.id AND i.is_active = 1 $bFilter
    GROUP BY d.id, d.name
    ORDER BY items DESC, low_stock ASC
    LIMIT 6")->fetchAll();

$noticeBoard = [
    'Inventory audit begins this Friday at 9:00 AM.',
    'Maintenance shutdown is scheduled for Saturday morning.',
    'Store closes promptly at 4:00 PM on weekdays.'
];

$smartAlerts = [];
if ($lowStock > 0) { $smartAlerts[] = "$lowStock items need attention before the next replenishment cycle."; }
if ($outOfStock > 0) { $smartAlerts[] = "$outOfStock items are currently out of stock."; }
if (empty($smartAlerts)) { $smartAlerts[] = 'Stock levels are healthy and replenishment looks on track.'; }

// ── Branch comparison (admin only) ────────────────────────────────────────────
$branchStats = [];
if ($isAdmin) {
    $branchStats = $pdo->query("
        SELECT b.name, COUNT(i.id) AS items, COALESCE(SUM(i.current_stock),0) AS stock,
               COALESCE(SUM(i.current_stock * i.unit_price),0) AS value
        FROM branches b
        LEFT JOIN inventory_items i ON i.branch_id = b.id AND i.is_active=1
        GROUP BY b.id, b.name
    ")->fetchAll();
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-sub">Overview for <?= $isAdmin ? 'All Branches' : clean($user['branch_name']) ?> — <?= date('l, d F Y') ?></p>
    </div>
    <?php if (hasRole('Administrator', 'Store Manager')): ?>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>pages/stock_in.php?action=add" class="btn btn-primary">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
            Stock In
        </a>
        <a href="<?= BASE_URL ?>pages/stock_out.php?action=add" class="btn btn-outline">
            <svg viewBox="0 0 24 24"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
            Stock Out
        </a>
    </div>
    <?php endif; ?>
</div>

<div class="dashboard-hero card">
    <div class="dashboard-hero-main">
        <div class="dashboard-user-summary">
            <div class="dashboard-avatar-large">
                <img src="<?= clean(profilePhotoUrl($user)) ?>" alt="<?= clean($user['full_name']) ?> avatar">
            </div>
            <div>
                <div class="hero-badge">Live operations</div>
                <h2><?= date('H') < 12 ? 'Good morning' : (date('H') < 18 ? 'Good afternoon' : 'Good evening') ?>, <?= clean($user['full_name']) ?> 👋</h2>
            </div>
        </div>
        <p>Welcome back. You have <?= number_format($pendingRequests) ?> active alerts, <?= number_format($lowStock) ?> low stock items, and <?= number_format($pendingApprovals) ?> approvals to review.</p>
        <div class="hero-highlights">
            <div class="hero-highlight"><svg viewBox="0 0 24 24"><path d="M12 3v18"/><path d="M3 12h18"/></svg> Stock overview</div>
            <div class="hero-highlight"><svg viewBox="0 0 24 24"><path d="M4 7h16"/><path d="M4 12h10"/><path d="M4 17h6"/></svg> Activity feed</div>
            <div class="hero-highlight"><svg viewBox="0 0 24 24"><path d="M12 2l7 4v6c0 5-3.5 8-7 10-3.5-2-7-5-7-10V6l7-4z"/></svg> Health score</div>
        </div>
        <div class="dashboard-pill-row">
            <span class="dashboard-pill"><svg viewBox="0 0 24 24"><path d="M4 7h16"/><path d="M7 12h10"/><path d="M9 17h6"/></svg> Favorites ready</span>
            <span class="dashboard-pill"><svg viewBox="0 0 24 24"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg> Quick actions</span>
        </div>
    </div>
    <div class="dashboard-hero-side">
        <div class="health-card">
            <div class="health-score" id="inventoryValueCounter" data-value="<?= (float)$stockValue ?>">UGX 0</div>
            <strong>Inventory Health</strong>
            <span><?= $healthLabel ?> · <?= $healthScore ?>%</span>
            <div class="health-pill <?= $healthTone ?>" style="margin-top:8px;"><?= $healthLabel ?></div>
        </div>
        <div class="theme-switcher">
            <button type="button" class="theme-chip active" data-theme-scheme="corporate">Corporate</button>
            <button type="button" class="theme-chip" data-theme-scheme="blue">Blue</button>
            <button type="button" class="theme-chip" data-theme-scheme="contrast">High Contrast</button>
        </div>
    </div>
</div>

<div class="notification-summary-grid">
    <div class="notification-summary-card accent-gold">
        <div class="notification-summary-icon">!</div>
        <div>
            <strong><?= number_format($pendingRequests) ?> pending</strong>
            <span>Active alerts waiting review</span>
        </div>
    </div>
    <div class="notification-summary-card accent-sky">
        <div class="notification-summary-icon">⚠</div>
        <div>
            <strong><?= number_format($lowStock) ?> low stock</strong>
            <span>Items need restocking</span>
        </div>
    </div>
    <div class="notification-summary-card accent-emerald">
        <div class="notification-summary-icon">✓</div>
        <div>
            <strong><?= number_format($pendingApprovals) ?> approvals</strong>
            <span>Pending approvals in queue</span>
        </div>
    </div>
    <div class="notification-summary-card accent-violet">
        <div class="notification-summary-icon">₵</div>
        <div>
            <strong><?= ugx((float)$stockValue) ?></strong>
            <span>Current inventory valuation</span>
        </div>
    </div>
</div>

<div class="dashboard-widget-grid">
    <div class="dashboard-widget">
        <h4>Daily Activity Feed</h4>
        <div class="activity-feed">
            <?php foreach (array_slice($recentTx, 0, 4) as $tx): ?>
            <div class="feed-item">
                <div class="feed-time"><?= date('h:i A', strtotime($tx['transaction_date'])) ?></div>
                <div class="feed-copy"><strong><?= clean($tx['item_name']) ?></strong> <?= str_replace('_',' ',$tx['transaction_type']) ?> by <?= clean($tx['user_name']) ?>.</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="dashboard-widget">
        <h4>Smart Alerts</h4>
        <div class="notice-list">
            <?php foreach ($smartAlerts as $alert): ?>
            <div class="notice-item">⚠ <?= clean($alert) ?></div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="dashboard-widget">
        <h4>Institution Notice Board</h4>
        <div class="notice-list">
            <?php foreach ($noticeBoard as $notice): ?>
            <div class="notice-item">📌 <?= clean($notice) ?></div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="dashboard-widget">
        <h4>Smart Search</h4>
        <div class="dashboard-search">
            <input type="text" id="dashboardSearch" placeholder="Search quick links..." />
            <button type="button">Go</button>
        </div>
        <div id="searchResults" class="quick-link-list" style="margin-top:10px;"></div>
    </div>
</div>

<div class="dashboard-widget-grid">
    <div class="dashboard-widget">
        <h4>Department Performance</h4>
        <div class="activity-feed">
            <?php foreach ($departmentPerformance as $index => $dept): ?>
            <div class="feed-item">
                <div class="feed-time">#<?= $index + 1 ?></div>
                <div class="feed-copy"><strong><?= clean($dept['name']) ?></strong><br><?= number_format($dept['items']) ?> items · <?= $dept['low_stock'] ?> low stock alerts</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="dashboard-widget">
        <h4>Inventory Heat Map</h4>
        <div class="heat-map">
            <div class="heat-cell healthy">Healthy</div>
            <div class="heat-cell moderate">Moderate</div>
            <div class="heat-cell critical">Critical</div>
        </div>
    </div>

    <div class="dashboard-widget">
        <h4>Favorites</h4>
        <div class="quick-link-list">
            <a href="<?= BASE_URL ?>pages/items.php" class="js-track-view" data-label="Inventory"><span>Inventory</span><span>↗</span></a>
            <a href="<?= BASE_URL ?>pages/reports.php" class="js-track-view" data-label="Reports"><span>Reports</span><span>↗</span></a>
            <a href="<?= BASE_URL ?>pages/suppliers.php" class="js-track-view" data-label="Suppliers"><span>Suppliers</span><span>↗</span></a>
        </div>
    </div>

    <div class="dashboard-widget">
        <h4>Recently Viewed</h4>
        <div id="recentlyViewedList" class="quick-link-list"></div>
    </div>
</div>

<!-- KPI CARDS -->
<div class="stats-grid">
    <div class="stat-card" data-href="<?= BASE_URL ?>pages/items.php">
        <div class="stat-icon blue">
            <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
        </div>
        <div class="stat-body">
            <span class="stat-label">Total Items</span>
            <span class="stat-value"><?= number_format($totalItems) ?></span>
            <span class="stat-sub"><?= $isAdmin ? 'All branches' : clean($user['branch_name']) ?></span>
        </div>
    </div>
    <div class="stat-card" data-href="<?= BASE_URL ?>pages/reports.php">
        <div class="stat-icon gold">
            <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
        </div>
        <div class="stat-body">
            <span class="stat-label">Total Stock Value</span>
            <span class="stat-value" style="font-size:1.3rem"><?= ugx((float)$stockValue) ?></span>
            <span class="stat-sub">Current valuation</span>
        </div>
    </div>
    <div class="stat-card" data-href="<?= BASE_URL ?>pages/stock_in.php?action=add">
        <div class="stat-icon green">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
        </div>
        <div class="stat-body">
            <span class="stat-label">Stock In (This Month)</span>
            <span class="stat-value"><?= number_format($stockInMonth) ?></span>
            <span class="stat-sub">Units received</span>
        </div>
    </div>
    <div class="stat-card" data-href="<?= BASE_URL ?>pages/stock_out.php?action=add">
        <div class="stat-icon purple">
            <svg viewBox="0 0 24 24"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
        </div>
        <div class="stat-body">
            <span class="stat-label">Stock Out (This Month)</span>
            <span class="stat-value"><?= number_format($stockOutMonth) ?></span>
            <span class="stat-sub">Units issued</span>
        </div>
    </div>
    <div class="stat-card <?= $lowStock > 0 ? 'stat-warn' : '' ?>" data-href="<?= BASE_URL ?>pages/items.php?filter=low">
        <div class="stat-icon <?= $lowStock > 0 ? 'orange' : 'green' ?>">
            <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div class="stat-body">
            <span class="stat-label">Low Stock Alerts</span>
            <span class="stat-value"><?= number_format($lowStock) ?></span>
            <span class="stat-sub">Items need restocking</span>
        </div>
    </div>
    <div class="stat-card <?= $outOfStock > 0 ? 'stat-danger' : '' ?>" data-href="<?= BASE_URL ?>pages/stock_out.php?action=add">
        <div class="stat-icon <?= $outOfStock > 0 ? 'red' : 'green' ?>">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
        </div>
        <div class="stat-body">
            <span class="stat-label">Out of Stock</span>
            <span class="stat-value"><?= number_format($outOfStock) ?></span>
            <span class="stat-sub">Items with zero stock</span>
        </div>
    </div>
</div>

<!-- Branch Comparison (Admin) -->
<?php if ($isAdmin && $branchStats): ?>
<div class="section-grid-2">
    <?php foreach ($branchStats as $bs): ?>
    <div class="branch-overview-card">
        <div class="branch-overview-header">
            <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
            <h3><?= clean($bs['name']) ?></h3>
        </div>
        <div class="branch-overview-stats">
            <div><span><?= number_format($bs['items']) ?></span><label>Items</label></div>
            <div><span><?= number_format($bs['stock']) ?></span><label>Total Units</label></div>
            <div><span style="font-size:.85rem"><?= ugx($bs['value']) ?></span><label>Value</label></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Charts Row -->
<div class="dashboard-grid">
    <div class="chart-card">
        <div class="card-header">
            <h3>Inventory Growth</h3>
        </div>
        <div class="card-body">
            <canvas id="growthChart" height="220"></canvas>
        </div>
    </div>

    <div class="chart-card">
        <div class="card-header">
            <h3>Monthly Stock In / Out</h3>
        </div>
        <div class="card-body">
            <canvas id="movementChart" height="220"></canvas>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="chart-card">
        <div class="card-header">
            <h3>Inventory by Campus</h3>
        </div>
        <div class="card-body">
            <canvas id="campusChart" height="220"></canvas>
        </div>
    </div>

    <div class="chart-card">
        <div class="card-header">
            <h3>Inventory by Category</h3>
        </div>
        <div class="card-body">
            <canvas id="categoryChart" height="220"></canvas>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="chart-card">
        <div class="card-header">
            <h3>Supplier Analysis</h3>
        </div>
        <div class="card-body">
            <canvas id="supplierChart" height="220"></canvas>
        </div>
    </div>

    <div class="chart-card">
        <div class="card-header">
            <h3>Asset Status</h3>
        </div>
        <div class="card-body">
            <canvas id="assetStatusChart" height="220"></canvas>
        </div>
    </div>
</div>

<!-- Low Stock, Recent Items & Transactions -->
<div class="section-grid-2">
    <!-- Low Stock Items -->
    <div class="card">
        <div class="card-header">
            <h3>Low Stock Alerts</h3>
            <a href="<?= BASE_URL ?>pages/items.php?filter=low" class="card-link">View all</a>
        </div>
        <div class="card-body p0">
            <?php if ($lowStockItems): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Branch</th>
                        <th>Stock</th>
                        <th>Min</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($lowStockItems as $item): ?>
                <tr>
                    <td>
                        <span class="item-name"><?= clean($item['name']) ?></span>
                        <span class="item-code"><?= clean($item['item_code']) ?></span>
                    </td>
                    <td><?= clean($item['branch_name']) ?></td>
                    <td><span class="badge <?= $item['current_stock'] == 0 ? 'badge-danger' : 'badge-warn' ?>"><?= $item['current_stock'] ?></span></td>
                    <td><?= $item['minimum_stock'] ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                <p>All stock levels are healthy!</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Items -->
    <div class="card">
        <div class="card-header">
            <h3>Recently Added Items</h3>
            <a href="<?= BASE_URL ?>pages/items.php" class="card-link">View all</a>
        </div>
        <div class="card-body p0">
            <?php if ($recentItems): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Added</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentItems as $item): ?>
                <tr>
                    <td>
                        <span class="item-name"><?= clean($item['name']) ?></span>
                        <span class="item-code"><?= clean($item['item_code']) ?></span>
                    </td>
                    <td><?= clean($item['category_name']) ?></td>
                    <td><?= date('d M Y', strtotime($item['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state"><p>No recent items found.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="section-grid-2">
    <!-- Recent Transactions -->
    <div class="card">
        <div class="card-header">
            <h3>Recent Transactions</h3>
            <a href="<?= BASE_URL ?>pages/transactions.php" class="card-link">View all</a>
        </div>
        <div class="card-body p0">
            <?php if ($recentTx): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentTx as $tx): ?>
                <tr>
                    <td>
                        <span class="item-name"><?= clean($tx['item_name']) ?></span>
                        <span class="item-code"><?= clean($tx['branch_name']) ?></span>
                    </td>
                    <td>
                        <span class="badge <?= $tx['transaction_type'] === 'stock_in' ? 'badge-success' : ($tx['transaction_type'] === 'stock_out' ? 'badge-blue' : 'badge-purple') ?>">
                            <?= str_replace('_', ' ', $tx['transaction_type']) ?>
                        </span>
                    </td>
                    <td><?= number_format($tx['quantity']) ?></td>
                    <td><?= date('d M', strtotime($tx['transaction_date'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state"><p>No recent transactions found.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
const chartLabels = <?= json_encode(array_column($chartData, 'label')) ?>;
const stockInData = <?= json_encode(array_column($chartData, 'in')) ?>;
const stockOutData= <?= json_encode(array_column($chartData, 'out')) ?>;
const growthLabels = <?= json_encode(array_column($growthData, 'label')) ?>;
const growthCounts = <?= json_encode(array_column($growthData, 'count')) ?>;
const campusLabels = <?= json_encode(array_column($campusBreakdown, 'name')) ?>;
const campusCounts = <?= json_encode(array_column($campusBreakdown, 'item_count')) ?>;
const catLabels    = <?= json_encode(array_column($catBreakdown, 'name')) ?>;
const catCounts    = <?= json_encode(array_column($catBreakdown, 'item_count')) ?>;
const supplierLabels = <?= json_encode(array_column($supplierBreakdown, 'name')) ?>;
const supplierCounts = <?= json_encode(array_column($supplierBreakdown, 'item_count')) ?>;
const assetLabels = <?= json_encode(array_column($assetStatusData, 'label')) ?>;
const assetValues = <?= json_encode(array_column($assetStatusData, 'value')) ?>;

new Chart(document.getElementById('growthChart'), {
    type: 'line',
    data: {
        labels: growthLabels,
        datasets: [{
            label: 'Items Added',
            data: growthCounts,
            borderColor: '#C9A227',
            backgroundColor: 'rgba(201,162,39,0.16)',
            fill: true,
            tension: 0.35,
            pointRadius: 3
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
});

new Chart(document.getElementById('movementChart'), {
    type: 'bar',
    data: {
        labels: chartLabels,
        datasets: [
            { label: 'Stock In', data: stockInData, backgroundColor: '#22c55e', borderRadius: 4 },
            { label: 'Stock Out', data: stockOutData, backgroundColor: '#3b82f6', borderRadius: 4 }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
});

new Chart(document.getElementById('campusChart'), {
    type: 'bar',
    data: {
        labels: campusLabels,
        datasets: [{ label: 'Items', data: campusCounts, backgroundColor: '#0A1628', borderRadius: 4 }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
});

new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: catLabels,
        datasets: [{
            data: catCounts,
            backgroundColor: ['#0A1628','#C9A227','#3b82f6','#22c55e','#a855f7','#f97316','#ef4444'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'right' } }
    }
});

new Chart(document.getElementById('supplierChart'), {
    type: 'bar',
    data: {
        labels: supplierLabels,
        datasets: [{ label: 'Linked Items', data: supplierCounts, backgroundColor: '#29ABE2', borderRadius: 4 }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
});

new Chart(document.getElementById('assetStatusChart'), {
    type: 'doughnut',
    data: {
        labels: assetLabels,
        datasets: [{
            data: assetValues,
            backgroundColor: ['#22c55e','#f59e0b','#ef4444'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
    }
});
</script>

<div class="fab" id="dashboardFab">
    <div class="fab-menu">
        <a href="<?= BASE_URL ?>pages/items.php?action=add" class="js-track-view" data-label="Add Inventory">＋ Add Inventory</a>
        <a href="<?= BASE_URL ?>pages/stock_out.php?action=add" class="js-track-view" data-label="Issue Stock">⇣ Issue Stock</a>
        <a href="<?= BASE_URL ?>pages/stock_in.php?action=add" class="js-track-view" data-label="Receive Stock">⇡ Receive Stock</a>
        <a href="<?= BASE_URL ?>pages/reports.php" class="js-track-view" data-label="Generate Report">📊 Generate Report</a>
        <a href="<?= BASE_URL ?>pages/transfers.php" class="js-track-view" data-label="Transfer Asset">⇄ Transfer Asset</a>
    </div>
    <button type="button" class="fab-toggle" aria-label="Quick actions">＋</button>
</div>

<button type="button" id="fullscreenDashboard" class="btn btn-outline" style="position:fixed; right:24px; bottom:96px; z-index:300;">⤢ Full Screen</button>

<?php include __DIR__ . '/../includes/footer.php'; ?>
