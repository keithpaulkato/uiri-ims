<?php
requireLogin();

// Set security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()");
// Note: Set Content-Security-Policy based on your needs
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:;");

$user  = currentUser();
$flash = getFlash();

// Branch info
$branchStmt = db()->prepare("SELECT * FROM branches WHERE id = ?");
$branchStmt->execute([$user['branch_id']]);
$currentBranch = $branchStmt->fetch();

$allBranches = db()->query("SELECT * FROM branches ORDER BY is_headquarters DESC")->fetchAll();

$notifCountStmt = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$notifCountStmt->execute([$user['id']]);
$notifCount = (int)$notifCountStmt->fetchColumn();

$notifStmt = db()->prepare(
    "SELECT id, title, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 6"
);
$notifStmt->execute([$user['id']]);
$notifications = $notifStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= clean($pageTitle ?? 'Dashboard') ?> — <?= SITE_SHORT ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@2.8.0/dist/css/tabler.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.4/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/1.7.0/flowbite.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar(false)"></div>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">
            <img src="<?= BASE_URL ?>assets/img/uiri-logo.webp" alt="UIRI Logo">
        </div>
        <div class="brand-text">
            <span class="brand-name">UIRI IMS</span>
            <span class="brand-sub">Inventory System</span>
        </div>
        <button class="sidebar-close" type="button" onclick="toggleSidebar(false)" aria-label="Close sidebar">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <div class="sidebar-resizer" id="sidebarResizer" aria-hidden="true" title="Drag to resize sidebar"></div>

    <!-- Branch Switcher -->
    <?php if (hasRole('Administrator', 'Executive')): ?>
    <div class="branch-switcher">
        <label>Active Branch</label>
        <form method="POST" action="<?= BASE_URL ?>includes/switch_branch.php">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
            <select name="branch_id" onchange="this.form.submit()">
                <?php foreach ($allBranches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $b['id'] == $user['branch_id'] ? 'selected' : '' ?>>
                        <?= clean($b['name']) ?> <?= $b['is_headquarters'] ? '(HQ)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php else: ?>
    <div class="branch-badge">
        <span class="branch-dot"></span>
        <?= clean($currentBranch['name']) ?>
        <?php if ($currentBranch['is_headquarters']): ?><em>(HQ)</em><?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <ul>
            <li class="nav-section">Public</li>
            <li class="<?= ($activePage ?? '') === 'landing' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>includes/logout.php?redirect=landing">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M3 10.5L12 3l9 7.5v9a1.5 1.5 0 01-1.5 1.5h-3v-7h-9v7h-3A1.5 1.5 0 013 19.5z"/></svg></span>
                    Landing Page
                </a>
            </li>

            <li class="nav-section">Main</li>
            <li class="<?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>pages/dashboard.php">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                    Dashboard
                </a>
            </li>

            <li class="nav-section">Inventory</li>
            <li class="<?= ($activePage ?? '') === 'items' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>pages/items.php">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg></span>
                    Inventory Items
                </a>
            </li>
            <li class="<?= ($activePage ?? '') === 'categories' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>pages/categories.php">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></span>
                    Categories
                </a>
            </li>

            <?php $stockPages = ['stock_in', 'stock_out', 'stock_adjustment', 'transactions']; $stockOpen = in_array($activePage ?? '', $stockPages, true); ?>
            <li class="nav-section">Stock</li>
            <li class="nav-dropdown <?= $stockOpen ? 'open' : '' ?>">
                <button type="button" class="nav-dropdown-toggle" onclick="this.closest('.nav-dropdown').classList.toggle('open')" aria-expanded="<?= $stockOpen ? 'true' : 'false' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><path d="M3.3 7L12 12l8.7-5"/><path d="M12 22V12"/></svg></span>
                    <span>Stock</span>
                    <svg viewBox="0 0 24 24" class="dropdown-chevron"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <ul class="nav-submenu">
                    <li class="<?= ($activePage ?? '') === 'stock_in' ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>pages/stock_in.php">
                            <span class="nav-icon"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg></span>
                            Stock In
                        </a>
                    </li>
                    <li class="<?= ($activePage ?? '') === 'stock_out' ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>pages/stock_out.php">
                            <span class="nav-icon"><svg viewBox="0 0 24 24"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg></span>
                            Stock Out
                        </a>
                    </li>
                    <li class="<?= ($activePage ?? '') === 'stock_adjustment' ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>pages/stock_adjustment.php">
                            <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M12 6v12m-6-6h12"/></svg></span>
                            Stock Adjustment
                        </a>
                    </li>
                    <li class="<?= ($activePage ?? '') === 'transactions' ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>pages/transactions.php">
                            <span class="nav-icon"><svg viewBox="0 0 24 24"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg></span>
                            Transactions
                        </a>
                    </li>
                </ul>
            </li>

            <li class="nav-section">Management</li>
            <li class="<?= ($activePage ?? '') === 'suppliers' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>pages/suppliers.php">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>
                    Suppliers
                </a>
            </li>
            <li class="<?= ($activePage ?? '') === 'requests' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>pages/requests.php">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h10"/><path d="M7 12h10"/><path d="M7 16h6"/></svg></span>
                    Requests
                </a>
            </li>
            <li class="<?= ($activePage ?? '') === 'procurement' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>pages/procurement.php">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h10"/><path d="M7 12h10"/><path d="M7 16h6"/></svg></span>
                    Procurement
                </a>
            </li>
            <li class="<?= ($activePage ?? '') === 'maintenance' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>pages/maintenance.php">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 13l2 2 4-4"/></svg></span>
                    Maintenance
                </a>
            </li>
            <li class="<?= ($activePage ?? '') === 'transfers' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>pages/transfers.php">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 014-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg></span>
                    Transfers
                </a>
            </li>
            <?php if (hasRole('Administrator')): ?>
            <li class="<?= ($activePage ?? '') === 'users' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>pages/users.php">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></span>
                    Users
                </a>
            </li>
            <?php endif; ?>
            <?php if (hasRole('Administrator', 'Campus Manager', 'Store Manager', 'Section Manager', 'Staff')): ?>
            <li class="<?= ($activePage ?? '') === 'sections' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>pages/sections.php">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M3 12h18"/><path d="M3 6h18"/><path d="M3 18h18"/></svg></span>
                    Departments
                </a>
            </li>
            <?php endif; ?>
            <?php if (hasRole('Administrator')): ?>
            <li class="<?= ($activePage ?? '') === 'departments' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>pages/departments.php">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4z"/></svg></span>
                    Sections / Units
                </a>
            </li>
            <li class="<?= ($activePage ?? '') === 'settings' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>pages/settings.php">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06A1.65 1.65 0 0015 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 009 15a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 8.6a1.65 1.65 0 00.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 0015 4.6a1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06A1.65 1.65 0 0019.4 15z"/></svg></span>
                    Settings
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-section">Reports</li>
            <li class="<?= ($activePage ?? '') === 'analytics' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>pages/analytics.php">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M7 15l3-3 3 2 5-7"/><circle cx="7" cy="15" r="1"/><circle cx="10" cy="12" r="1"/><circle cx="13" cy="14" r="1"/><circle cx="18" cy="7" r="1"/></svg></span>
                    Analytics
                </a>
            </li>
            <li class="<?= ($activePage ?? '') === 'reports' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>pages/reports.php">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span>
                    Reports
                </a>
            </li>
            <?php if (hasRole('Administrator')): ?>
            <li class="<?= ($activePage ?? '') === 'audit' ? 'active' : '' ?>">
                <a href="<?= BASE_URL ?>pages/audit.php">
                    <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></span>
                    Audit Trail
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
</aside>

<!-- TOP NAV -->
<div class="main-wrapper">
<header class="topnav">
    <button class="menu-toggle" id="menuToggle" type="button" onclick="toggleSidebar()" aria-label="Toggle menu" aria-controls="sidebar" aria-expanded="false">
        <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>

    <div class="topnav-branch">
        <span class="branch-indicator <?= $currentBranch['is_headquarters'] ? 'hq' : 'branch' ?>">
            <?= clean($currentBranch['name']) ?>
        </span>
    </div>

    <div class="topnav-right">
        <div class="notification-menu">
            <button class="icon-btn" id="notifBtn" type="button" aria-label="Notifications">
                <svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                <?php if ($notifCount > 0): ?>
                <span class="notif-badge"><?= $notifCount ?></span>
                <?php endif; ?>
            </button>
            <div class="notification-dropdown" id="notifDropdown">
                <div class="dropdown-header">Notifications</div>
                <?php if ($notifications): ?>
                    <?php foreach ($notifications as $n): ?>
                    <a href="<?= BASE_URL ?>pages/requests.php" class="notification-item <?= $n['is_read'] ? '' : 'unread' ?>">
                        <div>
                            <strong><?= clean($n['title']) ?></strong>
                            <p><?= clean($n['message']) ?></p>
                        </div>
                        <span><?= date('d M', strtotime($n['created_at'])) ?></span>
                    </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="notification-empty">No notifications yet.</div>
                <?php endif; ?>
            </div>
        </div>
        <button class="icon-btn" id="themeToggle" type="button" aria-label="Toggle dark mode">
            <svg viewBox="0 0 24 24"><path d="M12 3v2"/><path d="M12 19v2"/><path d="M4.22 4.22l1.42 1.42"/><path d="M18.36 18.36l1.42 1.42"/><path d="M1 12h2"/><path d="M21 12h2"/><path d="M4.22 19.78l1.42-1.42"/><path d="M18.36 5.64l1.42-1.42"/><circle cx="12" cy="12" r="3.5"/></svg>
        </button>
        <div class="user-menu" id="userMenu">
            <button class="user-btn" onclick="document.getElementById('userDropdown').classList.toggle('show')">
                <div class="user-avatar<?= profilePhotoUrl($user) ? ' has-photo' : '' ?>">
                    <?php if (profilePhotoUrl($user)): ?>
                        <img src="<?= clean(profilePhotoUrl($user)) ?>" alt="<?= clean($user['full_name']) ?> avatar">
                    <?php else: ?>
                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <span class="user-name"><?= clean(explode(' ', $user['full_name'])[0]) ?></span>
                    <span class="user-role"><?= clean($user['role']) ?></span>
                </div>
                <svg viewBox="0 0 24 24" class="chevron"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="user-dropdown" id="userDropdown">
                <a href="<?= BASE_URL ?>pages/profile.php">
                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    My Profile
                </a>
                <hr>
                <a href="<?= BASE_URL ?>includes/logout.php" class="logout">
                    <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Logout
                </a>
            </div>
        </div>
    </div>
</header>

<!-- FLASH MESSAGE -->
<?php if ($flash): ?>
<div class="flash flash-<?= $flash['type'] ?>" id="flashMsg">
    <svg viewBox="0 0 24 24">
        <?php if ($flash['type'] === 'success'): ?>
        <polyline points="20 6 9 17 4 12"/>
        <?php elseif ($flash['type'] === 'error'): ?>
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
        <?php else: ?>
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
        <?php endif; ?>
    </svg>
    <?= clean($flash['message']) ?>
    <button onclick="this.parentElement.remove()" class="flash-close">×</button>
</div>
<?php endif; ?>

<main class="page-content">
