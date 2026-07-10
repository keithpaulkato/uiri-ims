<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pageTitle  = 'Inventory Items';
$activePage = 'items';
$user       = currentUser();
$branchId   = $user['branch_id'];
$isAdmin    = hasRole('Administrator');
$canManage  = hasRole('Administrator', 'Store Manager', 'Staff');
$pdo        = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $itemId       = (int)($_POST['item_id'] ?? 0);
        $name         = trim($_POST['name'] ?? '');
        $itemCode     = trim($_POST['item_code'] ?? '');
        $assetCode    = trim($_POST['asset_code'] ?? '');
        $qrCode       = trim($_POST['qr_code'] ?? '');
        $categoryId   = (int)($_POST['category_id'] ?? 0);
        $supplierId   = (int)($_POST['supplier_id'] ?? 0) ?: null;
        $departmentId = (int)($_POST['department_id'] ?? 0) ?: null;
        $unit         = trim($_POST['unit'] ?? 'piece');
        $unitPrice    = (float)($_POST['unit_price'] ?? 0);
        $currentStock = max(0, (int)($_POST['current_stock'] ?? 0));
        $minStock     = (int)($_POST['minimum_stock'] ?? 5);
        $description  = trim($_POST['description'] ?? '');
        $itemBranch   = $isAdmin ? (int)($_POST['branch_id'] ?? $branchId) : $branchId;

        if (!$name || !$itemCode || !$categoryId) {
            setFlash('error', 'Name, item code and category are required.');
        } else {
            $imageName = $_POST['existing_image'] ?? null;
            if (!empty($_FILES['image']['name'])) {
                $allowed = ['jpg','jpeg','png','gif','webp'];
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowed)) {
                    $imageName = 'item_' . time() . '_' . rand(100,999) . '.' . $ext;
                    move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . $imageName);
                }
            }
            $sectionId = (int)($_POST['section_id'] ?? 0) ?: null;
            // Current hierarchy: branch -> section -> department
            if ($sectionId) {
                $ps = $pdo->prepare("SELECT branch_id FROM sections WHERE id=? AND is_active=1");
                $ps->execute([$sectionId]);
                $secRow = $ps->fetch();
                if (!$secRow) {
                    setFlash('error', 'Invalid section selected.');
                    header('Location: items.php'); exit;
                }
                if ($secRow['branch_id'] != $itemBranch) {
                    setFlash('error', 'Selected section does not belong to the chosen campus.');
                    header('Location: items.php'); exit;
                }
            }

            if ($departmentId) {
                $pd = $pdo->prepare("SELECT section_id FROM departments WHERE id=? AND is_active=1");
                $pd->execute([$departmentId]);
                $depRow = $pd->fetch();
                if (!$depRow) {
                    setFlash('error', 'Invalid department selected.');
                    header('Location: items.php'); exit;
                }
                if ($sectionId && $depRow['section_id'] != $sectionId) {
                    setFlash('error', 'Selected department does not belong to the selected section.');
                    header('Location: items.php'); exit;
                }
                if (!$sectionId) {
                    $sectionId = $depRow['section_id'];
                }
            }

            if ($categoryId) {
                $pc = $pdo->prepare("SELECT branch_id FROM categories WHERE id=?");
                $pc->execute([$categoryId]);
                $catRow = $pc->fetch();
                if (!$catRow) {
                    setFlash('error', 'Invalid category selected.');
                    header('Location: items.php'); exit;
                }
                if ($catRow['branch_id'] != $itemBranch) {
                    setFlash('error', 'Selected category does not belong to the chosen campus.');
                    header('Location: items.php'); exit;
                }
            }
        if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO inventory_items (branch_id,section_id,category_id,supplier_id,department_id,item_code,asset_code,qr_code,name,description,unit,unit_price,current_stock,minimum_stock,image,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$itemBranch,$sectionId,$categoryId,$supplierId,$departmentId,$itemCode,$assetCode,$qrCode,$name,$description,$unit,$unitPrice,$currentStock,$minStock,$imageName,$user['id']]);
                auditLog('ADD_ITEM','inventory_items',$pdo->lastInsertId(),"Added: $name");
                setFlash('success',"Item '$name' added successfully.");
            } else {
                $stmt = $pdo->prepare("UPDATE inventory_items SET section_id=?,category_id=?,supplier_id=?,department_id=?,item_code=?,asset_code=?,qr_code=?,name=?,description=?,unit=?,unit_price=?,current_stock=?,minimum_stock=?,image=?,branch_id=? WHERE id=?");
                $stmt->execute([$sectionId,$categoryId,$supplierId,$departmentId,$itemCode,$assetCode,$qrCode,$name,$description,$unit,$unitPrice,$currentStock,$minStock,$imageName,$itemBranch,$itemId]);
                auditLog('EDIT_ITEM','inventory_items',$itemId,"Updated: $name");
                setFlash('success',"Item '$name' updated successfully.");
            }
        }
    }
    if ($action === 'delete') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $pdo->prepare("UPDATE inventory_items SET is_active=0 WHERE id=?")->execute([$itemId]);
        auditLog('DELETE_ITEM','inventory_items',$itemId,"Deactivated item ID $itemId");
        setFlash('success','Item removed successfully.');
    }
    header('Location: items.php'); exit;
}

$search         = trim($_GET['search'] ?? '');
$catFilter      = (int)($_GET['category'] ?? 0);
$supplierFilter = (int)($_GET['supplier'] ?? 0);
$branchFilter   = $isAdmin ? (int)($_GET['branch'] ?? 0) : $branchId;
$deptFilter     = (int)($_GET['department'] ?? 0);
$sectionFilter  = (int)($_GET['section'] ?? 0);
$stockFilter    = $_GET['filter'] ?? '';

$where  = ["i.is_active = 1"];
$params = [];
if (!$isAdmin) { $where[] = "i.branch_id = ?"; $params[] = $branchId; }
elseif ($branchFilter) { $where[] = "i.branch_id = ?"; $params[] = $branchFilter; }
if ($search) {
    $where[] = "(i.name LIKE ? OR i.item_code LIKE ? OR i.asset_code LIKE ? OR i.qr_code LIKE ? OR i.description LIKE ? OR c.name LIKE ? OR s.company_name LIKE ? OR b.name LIKE ? OR sec.name LIKE ? OR d.name LIKE ?)";
    $params = array_merge($params, ["%$search%","%$search%","%$search%","%$search%","%$search%","%$search%","%$search%","%$search%","%$search%","%$search%"]);
}
if ($catFilter) { $where[] = "i.category_id = ?"; $params[] = $catFilter; }
if ($supplierFilter) { $where[] = "i.supplier_id = ?"; $params[] = $supplierFilter; }
if ($sectionFilter) { $where[] = "i.section_id = ?"; $params[] = $sectionFilter; }
if ($deptFilter) { $where[] = "i.department_id = ?"; $params[] = $deptFilter; }
if ($stockFilter === 'low') $where[] = "i.current_stock <= i.minimum_stock AND i.current_stock > 0";
if ($stockFilter === 'out') $where[] = "i.current_stock = 0";
if ($stockFilter === 'good') $where[] = "i.current_stock > i.minimum_stock";
$whereSQL = implode(' AND ', $where);

$itemsPerPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM inventory_items i JOIN categories c ON i.category_id=c.id LEFT JOIN suppliers s ON i.supplier_id=s.id JOIN branches b ON i.branch_id=b.id LEFT JOIN sections sec ON i.section_id=sec.id LEFT JOIN departments d ON i.department_id=d.id WHERE $whereSQL");
$countStmt->execute($params);
$totalItems = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalItems / $itemsPerPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $itemsPerPage;
$pageStart = $totalItems ? $offset + 1 : 0;
$pageEnd = min($offset + $itemsPerPage, $totalItems);

$stmt = $pdo->prepare("SELECT i.*, c.name AS category_name, s.company_name AS supplier_name, b.name AS branch_name, sec.name AS section_name, d.name AS department_name FROM inventory_items i JOIN categories c ON i.category_id=c.id LEFT JOIN suppliers s ON i.supplier_id=s.id JOIN branches b ON i.branch_id=b.id LEFT JOIN sections sec ON i.section_id=sec.id LEFT JOIN departments d ON i.department_id=d.id WHERE $whereSQL ORDER BY i.created_at DESC LIMIT $itemsPerPage OFFSET $offset");
$stmt->execute($params);
$items = $stmt->fetchAll();

$paginationParams = $_GET;
unset($paginationParams['page'], $paginationParams['edit'], $paginationParams['action']);
$pageUrl = function (int $targetPage) use ($paginationParams): string {
    $query = http_build_query(array_merge($paginationParams, ['page' => $targetPage]));
    return 'items.php' . ($query ? '?' . $query : '');
};

$suppliers  = $pdo->query("SELECT * FROM suppliers WHERE is_active=1 ORDER BY company_name")->fetchAll();
$branches   = $pdo->query("SELECT * FROM branches ORDER BY is_headquarters DESC")->fetchAll();
$filterCategoriesStmt = $pdo->prepare("SELECT * FROM categories" . ($isAdmin && $branchFilter ? " WHERE branch_id = ?" : "") . " ORDER BY name");
if ($isAdmin && $branchFilter) {
    $filterCategoriesStmt->execute([$branchFilter]);
} else {
    $filterCategoriesStmt->execute();
}
$filterCategories = $filterCategoriesStmt->fetchAll();
if ($isAdmin) {
    $categories = $pdo->query("SELECT * FROM categories ORDER BY branch_id, name")->fetchAll();
} else {
    $categories = $pdo->prepare("SELECT * FROM categories WHERE branch_id = ? ORDER BY name");
    $categories->execute([$branchId]);
    $categories = $categories->fetchAll();
}
$sections = $pdo->prepare("SELECT s.id, s.name, s.branch_id, b.name AS branch_name FROM sections s JOIN branches b ON s.branch_id=b.id WHERE s.is_active=1" . (!$isAdmin ? " AND s.branch_id = ?" : "") . " ORDER BY b.name, s.name");
$sections->execute(!$isAdmin ? [$branchId] : []);
$sections = $sections->fetchAll();
$departments = $pdo->prepare("SELECT d.id, d.name, d.section_id, s.branch_id, b.name AS branch_name, s.name AS section_name FROM departments d JOIN sections s ON d.section_id=s.id JOIN branches b ON s.branch_id=b.id WHERE d.is_active=1" . (!$isAdmin ? " AND b.id = ?" : "") . " ORDER BY b.name, s.name, d.name");
$departments->execute(!$isAdmin ? [$branchId] : []);
$departments = $departments->fetchAll();

$editItem = null;
if (isset($_GET['edit'])) {
    $es = $pdo->prepare("SELECT * FROM inventory_items WHERE id=? AND is_active=1");
    $es->execute([(int)$_GET['edit']]);
    $editItem = $es->fetch();
}
$showAddModal = $canManage && (($_GET['action'] ?? '') === 'add' || $editItem);

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Inventory Items</h1>
        <p class="page-sub">
            <?= number_format($totalItems) ?> item(s) found
            <?php if ($totalItems): ?>
                · Showing <?= number_format($pageStart) ?>-<?= number_format($pageEnd) ?>
            <?php endif; ?>
        </p>
    </div>
    <?php if ($canManage): ?>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal('addItemModal')">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Item
        </button>
    </div>
    <?php endif; ?>
</div>

<div class="card filter-bar">
    <form method="GET" class="filter-form">
        <div class="filter-group">
            <div class="input-wrap">
                <svg class="input-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="search" placeholder="Search asset code, QR code, name…" value="<?= clean($search) ?>">
            </div>
        </div>
        <div class="filter-group">
            <select name="category">
                <option value="">All Categories</option>
                <?php foreach ($filterCategories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $c['id']==$catFilter?'selected':'' ?>><?= clean($c['name']) ?><?= $isAdmin ? ' — ' . clean($branches[array_search($c['branch_id'], array_column($branches,'id'))]['name'] ?? '') : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <select name="supplier">
                <option value="">All Suppliers</option>
                <?php foreach ($suppliers as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $s['id']==$supplierFilter?'selected':'' ?>><?= clean($s['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($isAdmin): ?>
        <div class="filter-group">
            <select name="branch">
                <option value="">All Campuses</option>
                <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $b['id']==$branchFilter?'selected':'' ?>><?= clean($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="filter-group">
            <select name="section">
                <option value="">All Sections</option>
                <?php foreach ($sections as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $s['id']==$sectionFilter?'selected':'' ?>><?= clean($s['branch_name']) ?> — <?= clean($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <select name="department">
                <option value="">All Departments</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $d['id']==$deptFilter?'selected':'' ?>><?= clean($d['branch_name']) ?> — <?= clean($d['section_name']) ?> — <?= clean($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <select name="filter">
                <option value="">All Stock Levels</option>
                <option value="good" <?= $stockFilter==='good'?'selected':'' ?>>Good Stock</option>
                <option value="low"  <?= $stockFilter==='low'?'selected':'' ?>>Low Stock</option>
                <option value="out"  <?= $stockFilter==='out'?'selected':'' ?>>Out of Stock</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="items.php" class="btn btn-outline">Reset</a>
    </form>
</div>

<div class="card">
    <div class="card-body p0">
        <?php if ($items): ?>
        <table class="data-table">
            <thead><tr>
                <th>#</th><th>Item</th><th>Category</th><th>Section</th><th>Department</th>
                <?php if ($isAdmin): ?><th>Branch</th><?php endif; ?>
                <th>Unit Price</th><th>Stock</th><th>Min</th><th>Status</th>
                <?php if ($canManage): ?><th>Actions</th><?php endif; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($items as $i => $item):
                $ss = $item['current_stock']==0 ? 'out' : ($item['current_stock']<=$item['minimum_stock'] ? 'low' : 'good');
                $sl = $ss==='out' ? 'Out of Stock' : ($ss==='low' ? 'Low Stock' : 'In Stock');
            ?>
            <tr>
                <td><?= $offset + $i + 1 ?></td>
                <td>
                    <div class="item-cell">
                        <div class="item-thumb-placeholder"><svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg></div>
                        <div><span class="item-name"><?= clean($item['name']) ?></span><span class="item-code"><?= clean($item['item_code']) ?></span></div>
                    </div>
                </td>
                <td><?= clean($item['category_name']) ?></td>
                <td><?= clean($item['section_name'] ?: '—') ?></td>
                <td><?= clean($item['department_name'] ?: '—') ?></td>
                <?php if ($isAdmin): ?><td><?= clean($item['branch_name']) ?></td><?php endif; ?>
                <td><?= ugx($item['unit_price']) ?></td>
                <td><strong><?= number_format($item['current_stock']) ?> <?= clean($item['unit']) ?></strong></td>
                <td><?= $item['minimum_stock'] ?></td>
                <td><span class="badge badge-<?= $ss==='good'?'success':($ss==='low'?'warn':'danger') ?>"><?= $sl ?></span></td>
                <?php if ($canManage): ?>
                <td>
                    <div class="action-btns">
                        <a href="items.php?edit=<?= $item['id'] ?>" class="btn-icon" title="Edit">
                            <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </a>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this item?')">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                            <button type="submit" class="btn-icon btn-icon-danger" title="Delete">
                                <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                            </button>
                        </form>
                    </div>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($totalPages > 1): ?>
        <div class="pagination-bar">
            <nav class="pagination-nav pagination-nav-left" aria-label="Previous inventory item pages">
                <a class="pagination-link pagination-direction <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $page > 1 ? clean($pageUrl($page - 1)) : '#' ?>" aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>">&lt;&lt;Previous</a>
                <?php
                    $windowStart = max(1, $page - 2);
                    $windowEnd = min($totalPages, $page + 2);
                    if ($windowEnd - $windowStart < 4) {
                        $windowStart = max(1, min($windowStart, $windowEnd - 4));
                        $windowEnd = min($totalPages, max($windowEnd, $windowStart + 4));
                    }
                ?>
                <?php if ($windowStart > 1): ?>
                    <a class="pagination-link" href="<?= clean($pageUrl(1)) ?>">1</a>
                    <?php if ($windowStart > 2): ?><span class="pagination-ellipsis">...</span><?php endif; ?>
                <?php endif; ?>
                <?php for ($p = $windowStart; $p <= $windowEnd; $p++): ?>
                    <a class="pagination-link <?= $p === $page ? 'active' : '' ?>" href="<?= clean($pageUrl($p)) ?>" aria-current="<?= $p === $page ? 'page' : 'false' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($windowEnd < $totalPages): ?>
                    <?php if ($windowEnd < $totalPages - 1): ?><span class="pagination-ellipsis">...</span><?php endif; ?>
                    <a class="pagination-link" href="<?= clean($pageUrl($totalPages)) ?>"><?= number_format($totalPages) ?></a>
                <?php endif; ?>
            </nav>
            <div class="pagination-summary">
                Page <?= number_format($page) ?> of <?= number_format($totalPages) ?>
            </div>
            <nav class="pagination-nav pagination-nav-right" aria-label="Next inventory item page">
                <a class="pagination-link pagination-direction <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= $page < $totalPages ? clean($pageUrl($page + 1)) : '#' ?>" aria-disabled="<?= $page >= $totalPages ? 'true' : 'false' ?>">Next&gt;&gt;</a>
            </nav>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="empty-state">
            <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
            <h3>No items found</h3><p>Try adjusting your filters or add a new item.</p>
            <?php if ($canManage): ?><button class="btn btn-primary" onclick="openModal('addItemModal')">Add Item</button><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canManage): ?>
<div class="modal-overlay" id="addItemModal" <?= $showAddModal?'style="display:flex"':'' ?>>
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3><?= $editItem?'Edit Item':'Add New Item' ?></h3>
            <button class="modal-close" onclick="closeModal('addItemModal')">×</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="<?= $editItem?'edit':'add' ?>">
            <?php if ($editItem): ?>
            <input type="hidden" name="item_id" value="<?= $editItem['id'] ?>">
            <input type="hidden" name="existing_image" value="<?= clean($editItem['image']??'') ?>">
            <?php endif; ?>
            <div class="modal-body">
                <div class="inventory-wizard">
                    <div class="wizard-intro">
                        <div class="wizard-breadcrumb">Dashboard / Inventory / Add Inventory</div>
                        <div class="wizard-title-row">
                            <div>
                                <h4><?= $editItem?'Edit Inventory Item':'Add New Inventory Item' ?></h4>
                                <p>Register a new asset or consumable into the UIRI Inventory Management System.</p>
                            </div>
                            <div class="wizard-badges">
                                <span class="badge badge-blue">Enterprise Flow</span>
                                <span class="badge badge-success">Live Preview</span>
                            </div>
                        </div>
                    </div>

                    <div class="wizard-content">
                        <div class="wizard-form-column">
                            <div class="wizard-step-nav">
                                <button type="button" class="wizard-step-btn active" data-step="basic">1. Basic Info</button>
                                <button type="button" class="wizard-step-btn" data-step="classification">2. Classification</button>
                                <button type="button" class="wizard-step-btn" data-step="location">3. Location</button>
                                <button type="button" class="wizard-step-btn" data-step="stock">4. Stock</button>
                                <button type="button" class="wizard-step-btn" data-step="documents">5. Documents</button>
                                <button type="button" class="wizard-step-btn" data-step="review">6. Review</button>
                            </div>

                            <div class="wizard-step-panel active" data-step-panel="basic">
                                <div class="form-section-card">
                                    <h4>Basic Information</h4>
                                    <p>Capture the core identity of the inventory item.</p>
                                    <div class="form-grid-2">
                                        <div class="form-group">
                                            <label>Item Name *</label>
                                            <input type="text" name="name" id="previewNameInput" required placeholder="e.g. Dell Latitude Laptop" value="<?= clean($editItem['name']??'') ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Item Code *</label>
                                            <input type="text" name="item_code" required placeholder="e.g. NK-ICT-001" value="<?= clean($editItem['item_code']??'') ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Category *</label>
                                            <select name="category_id" id="previewCategoryInput" required>
                                                <option value="">Select category</option>
                                                <?php foreach ($categories as $c): ?>
                                                <option value="<?= $c['id'] ?>" data-branch="<?= $c['branch_id'] ?>" <?= ($editItem['category_id']??0)==$c['id']?'selected':'' ?>><?= clean($c['name']) ?><?= $isAdmin ? ' — ' . clean($branches[array_search($c['branch_id'], array_column($branches,'id'))]['name'] ?? '') : '' ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Brand / Model</label>
                                            <input type="text" name="brand_model" placeholder="e.g. Dell Latitude 7420" value="">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Description</label>
                                        <textarea name="description" rows="3" placeholder="Item description…"><?= clean($editItem['description']??'') ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="wizard-step-panel" data-step-panel="classification">
                                <div class="form-section-card">
                                    <h4>Classification</h4>
                                    <p>Define how the item should be categorized in the system.</p>
                                    <div class="form-grid-2">
                                        <div class="form-group">
                                            <label>Inventory Type</label>
                                            <select name="inventory_type">
                                                <option value="Fixed Asset">Fixed Asset</option>
                                                <option value="Consumable">Consumable</option>
                                                <option value="Tool">Tool</option>
                                                <option value="Spare Part">Spare Part</option>
                                                <option value="Laboratory Equipment">Laboratory Equipment</option>
                                                <option value="Office Equipment">Office Equipment</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Asset Status</label>
                                            <select name="asset_status">
                                                <option value="Available">Available</option>
                                                <option value="In Use">In Use</option>
                                                <option value="Maintenance">Maintenance</option>
                                                <option value="Disposed">Disposed</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Condition</label>
                                            <select name="asset_condition">
                                                <option value="New">New</option>
                                                <option value="Used">Used</option>
                                                <option value="Refurbished">Refurbished</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Funding Source</label>
                                            <input type="text" name="funding_source" placeholder="e.g. Government Grant">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="wizard-step-panel" data-step-panel="location">
                                <div class="form-section-card">
                                    <h4>Organizational Assignment</h4>
                                    <p>Assign the item to the right campus, section, and department.</p>
                                    <div class="form-grid-2">
                                                <?php if ($isAdmin): ?>
                                        <div class="form-group">
                                            <label>Campus *</label>
                                            <select name="branch_id" id="previewBranchInput" required>
                                                <?php foreach ($branches as $b): ?>
                                                <option value="<?= $b['id'] ?>" <?= ($editItem['branch_id']??$branchId)==$b['id']?'selected':'' ?> data-branch="<?= $b['id'] ?>"><?= clean($b['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <?php endif; ?>
                                        <div class="form-group">
                                            <label>Section</label>
                                            <select name="section_id" id="previewSectionInput" <?= $isAdmin ? '' : 'required' ?>>
                                                <option value="">Select section</option>
                                                <?php foreach ($sections as $s): ?>
                                                <option value="<?= $s['id'] ?>" data-branch="<?= $s['branch_id'] ?>" <?= ($editItem['section_id']??0)==$s['id']?'selected':'' ?>><?= clean($s['branch_name']) ?> — <?= clean($s['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Department</label>
                                            <select name="department_id" id="previewDeptInput">
                                                <option value="">Select department</option>
                                                <?php foreach ($departments as $d): ?>
                                                <option value="<?= $d['id'] ?>" data-section="<?= $d['section_id'] ?>" <?= ($editItem['department_id']??0)==$d['id']?'selected':'' ?>><?= clean($d['branch_name']) ?> — <?= clean($d['section_name']) ?> — <?= clean($d['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Storage Location</label>
                                            <input type="text" name="storage_location" placeholder="e.g. Shelf B4">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="wizard-step-panel" data-step-panel="stock">
                                <div class="form-section-card">
                                    <h4>Stock & Financial Information</h4>
                                    <p>Track stock levels, pricing, and estimated value.</p>
                                    <div class="form-grid-2">
                                        <div class="form-group">
                                            <label>Unit of Measure</label>
                                            <select name="unit" id="previewUnitInput">
                                                <?php foreach (['piece','ream','box','set','litre','kg','metre','carton','dozen','pair'] as $u): ?>
                                                <option value="<?= $u ?>" <?= ($editItem['unit']??'piece')===$u?'selected':'' ?>><?= ucfirst($u) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Unit Price (UGX)</label>
                                            <input type="number" name="unit_price" id="previewPriceInput" min="0" step="100" value="<?= $editItem['unit_price']??0 ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Current Stock</label>
                                            <input type="number" name="current_stock" id="previewCurrentStockInput" min="0" value="<?= $editItem['current_stock']??0 ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Minimum Stock Level</label>
                                            <input type="number" name="minimum_stock" id="previewMinStockInput" min="0" value="<?= $editItem['minimum_stock']??5 ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Estimated Value (UGX)</label>
                                            <input type="number" name="estimated_value" min="0" step="100" placeholder="0">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="wizard-step-panel" data-step-panel="documents">
                                <div class="form-section-card">
                                    <h4>Documents & Tracking</h4>
                                    <p>Add supporting files and internal tracking references.</p>
                                    <div class="form-grid-2">
                                        <div class="form-group">
                                            <label>Supplier</label>
                                            <select name="supplier_id" id="previewSupplierInput">
                                                <option value="">Select supplier</option>
                                                <?php foreach ($suppliers as $s): ?>
                                                <option value="<?= $s['id'] ?>" <?= ($editItem['supplier_id']??0)==$s['id']?'selected':'' ?>><?= clean($s['company_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Asset Code</label>
                                            <input type="text" name="asset_code" id="previewAssetCodeInput" placeholder="e.g. AST-001" value="<?= clean($editItem['asset_code']??'') ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>QR Code</label>
                                            <input type="text" name="qr_code" id="previewQrCodeInput" placeholder="e.g. QR-001" value="<?= clean($editItem['qr_code']??'') ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Warranty End</label>
                                            <input type="date" name="warranty_end">
                                        </div>
                                    </div>
                                    <div class="form-grid-2">
                                        <div class="form-group">
                                            <label>Item Image</label>
                                            <input type="file" name="image" accept="image/*">
                                            <?php if (!empty($editItem['image'])): ?><small>Current: <?= clean($editItem['image']) ?></small><?php endif; ?>
                                        </div>
                                        <div class="form-group">
                                            <label>Invoice / Warranty File</label>
                                            <input type="file" name="supporting_file" accept=".pdf,.jpg,.jpeg,.png">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="wizard-step-panel" data-step-panel="review">
                                <div class="form-section-card">
                                    <h4>Review & Submit</h4>
                                    <p>Confirm the inventory details before saving.</p>
                                    <div class="review-checklist">
                                        <div class="review-item"><strong>Core details</strong><span>Item name, category, and description will be saved.</span></div>
                                        <div class="review-item"><strong>Location</strong><span>Campus and department assignments are included in the record.</span></div>
                                        <div class="review-item"><strong>Stock & financials</strong><span>Minimum stock and price thresholds are tracked.</span></div>
                                        <div class="review-item"><strong>Tracking</strong><span>Asset and QR references are generated for easy identification.</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <aside class="wizard-preview">
                            <div class="preview-card">
                                <div class="preview-card-title">Inventory Summary</div>
                                <div class="preview-item-name" id="previewItemName">Untitled Item</div>
                                <div class="preview-meta">
                                    <span class="preview-pill" id="previewCategory">Unassigned Category</span>
                                    <span class="preview-pill" id="previewBranch">Campus Not Set</span>
                                </div>
                                <dl class="preview-list">
                                    <div><dt>Supplier</dt><dd id="previewSupplier">Not assigned</dd></div>
                                    <div><dt>Section</dt><dd id="previewSection">Unassigned section</dd></div>
                                    <div><dt>Department</dt><dd id="previewDepartment">Unassigned department</dd></div>
                                    <div><dt>Current Stock</dt><dd id="previewStock">0 units</dd></div>
                                    <div><dt>Estimated Value</dt><dd id="previewValue">UGX 0</dd></div>
                                    <div><dt>Asset Code</dt><dd id="previewAssetCode">—</dd></div>
                                    <div><dt>QR Code</dt><dd id="previewQrCode">—</dd></div>
                                </dl>
                            </div>
                            <div class="preview-card">
                                <div class="preview-card-title">Inventory Tips</div>
                                <ul class="preview-tips">
                                    <li>Item codes are usually generated automatically.</li>
                                    <li>Required fields are marked with an asterisk.</li>
                                    <li>Duplicate serials and codes should be avoided.</li>
                                </ul>
                            </div>
                        </aside>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addItemModal')">Cancel</button>
                <button type="button" class="btn btn-outline">Save Draft</button>
                <button type="button" class="btn btn-outline">Save & Continue</button>
                <button type="submit" class="btn btn-primary"><?= $editItem?'Update Item':'Submit Inventory' ?></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const stepButtons = Array.from(document.querySelectorAll('.wizard-step-btn'));
    const stepPanels = Array.from(document.querySelectorAll('.wizard-step-panel'));

    function showStep(step) {
        stepButtons.forEach(btn => btn.classList.toggle('active', btn.dataset.step === step));
        stepPanels.forEach(panel => panel.classList.toggle('active', panel.dataset.stepPanel === step));
    }

    stepButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            showStep(this.dataset.step);
        });
    });

    function formatCurrency(value) {
        const num = parseFloat(value) || 0;
        return 'UGX ' + num.toLocaleString();
    }

    function updatePreview() {
        const name = document.getElementById('previewNameInput')?.value?.trim() || 'Untitled Item';
        const category = document.getElementById('previewCategoryInput')?.selectedOptions[0]?.text || 'Unassigned Category';
        const section = document.getElementById('previewSectionInput')?.selectedOptions[0]?.text || 'Unassigned section';
        const department = document.getElementById('previewDeptInput')?.selectedOptions[0]?.text || 'Unassigned department';
        const branch = document.getElementById('previewBranchInput')?.selectedOptions[0]?.text || 'Campus Not Set';
        const supplier = document.getElementById('previewSupplierInput')?.selectedOptions[0]?.text || 'Not assigned';
        const unit = document.getElementById('previewUnitInput')?.value || 'unit';
        const stock = parseInt(document.getElementById('previewCurrentStockInput')?.value || '0', 10);
        const price = parseFloat(document.getElementById('previewPriceInput')?.value || '0');
        const assetCode = document.getElementById('previewAssetCodeInput')?.value?.trim() || '—';
        const qrCode = document.getElementById('previewQrCodeInput')?.value?.trim() || '—';

        document.getElementById('previewItemName').textContent = name;
        document.getElementById('previewCategory').textContent = category;
        document.getElementById('previewSection').textContent = section;
        document.getElementById('previewDepartment').textContent = department;
        document.getElementById('previewBranch').textContent = branch;
        document.getElementById('previewSupplier').textContent = supplier;
        document.getElementById('previewStock').textContent = stock + ' ' + unit + (stock === 1 ? '' : 's');
        document.getElementById('previewValue').textContent = formatCurrency(price * Math.max(stock, 1));
        document.getElementById('previewAssetCode').textContent = assetCode;
        document.getElementById('previewQrCode').textContent = qrCode;
    }

    function filterSectionOptions() {
        const branchSelect = document.getElementById('previewBranchInput');
        const sectionSelect = document.getElementById('previewSectionInput');
        if (!sectionSelect) return;
        const branchId = branchSelect?.value || '';
        Array.from(sectionSelect.options).forEach(option => {
            if (!option.value) { option.style.display = ''; return; }
            const matchesBranch = !branchId || option.dataset.branch === branchId;
            option.style.display = matchesBranch ? '' : 'none';
        });
        if (sectionSelect.selectedOptions[0] && sectionSelect.selectedOptions[0].style.display === 'none') {
            sectionSelect.value = '';
        }
    }

    function filterDepartmentOptions() {
        const sectionSelect = document.getElementById('previewSectionInput');
        const deptSelect = document.getElementById('previewDeptInput');
        if (!deptSelect) return;
        const sectionId = sectionSelect?.value || '';
        Array.from(deptSelect.options).forEach(option => {
            if (!option.value) { option.style.display = ''; return; }
            const matchesSection = !sectionId || option.dataset.section === sectionId;
            option.style.display = matchesSection ? '' : 'none';
        });
        if (deptSelect.selectedOptions[0] && deptSelect.selectedOptions[0].style.display === 'none') {
            deptSelect.value = '';
        }
    }

    function filterCategoryOptions() {
        const branchSelect = document.getElementById('previewBranchInput');
        const categorySelect = document.getElementById('previewCategoryInput');
        if (!categorySelect) return;
        const branchId = branchSelect?.value || '';
        Array.from(categorySelect.options).forEach(option => {
            if (!option.value) { option.style.display = ''; return; }
            option.style.display = !branchId || option.dataset.branch === branchId ? '' : 'none';
        });
        if (categorySelect.selectedOptions[0] && categorySelect.selectedOptions[0].style.display === 'none') {
            categorySelect.value = '';
        }
    }

    ['input', 'change'].forEach(eventName => {
        document.querySelectorAll('#previewNameInput, #previewCategoryInput, #previewSectionInput, #previewDeptInput, #previewBranchInput, #previewSupplierInput, #previewUnitInput, #previewCurrentStockInput, #previewMinStockInput, #previewPriceInput, #previewAssetCodeInput, #previewQrCodeInput').forEach(el => {
            el.addEventListener(eventName, updatePreview);
        });
    });

    document.getElementById('previewBranchInput')?.addEventListener('change', function () {
        filterSectionOptions();
        filterCategoryOptions();
        filterDepartmentOptions();
        updatePreview();
    });

    document.getElementById('previewSectionInput')?.addEventListener('change', function () {
        filterDepartmentOptions();
        updatePreview();
    });

    filterSectionOptions();
    filterCategoryOptions();
    filterDepartmentOptions();
    updatePreview();
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
