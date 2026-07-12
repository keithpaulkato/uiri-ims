<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
$pageTitle = 'Categories'; $activePage = 'categories';
$user = currentUser();
$branchId = $user['branch_id'];
$isAdmin = hasRole('Administrator');
$canManage = hasRole('Administrator','Store Manager');
$pdo = db();

$branches = $pdo->query("SELECT * FROM branches ORDER BY is_headquarters DESC, name")->fetchAll();
$branchFilter = $isAdmin ? (int)($_GET['branch'] ?? $branchId) : $branchId;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action']??'';
    $id = (int)($_POST['cat_id']??0);
    $name = trim($_POST['name']??'');
    $desc = trim($_POST['description']??'');
    $categoryBranch = $isAdmin ? (int)($_POST['branch_id'] ?? $branchFilter) : $branchId;
    if (!$categoryBranch) {
        $categoryBranch = $branchId;
    }

    if ($action==='add'||$action==='edit') {
        if (!$name) {
            setFlash('error','Category name is required.');
        } else {
            $duplicateCheck = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE branch_id = ? AND name = ?" . ($action==='edit' ? " AND id <> ?" : ""));
            $dupParams = [$categoryBranch, $name];
            if ($action==='edit') { $dupParams[] = $id; }
            $duplicateCheck->execute($dupParams);
            if ($duplicateCheck->fetchColumn() > 0) {
                setFlash('error', "Category '$name' already exists for the selected branch.");
            } else {
                if ($action==='add') {
                    $stmt = $pdo->prepare("INSERT INTO categories (branch_id, name, description) VALUES (?,?,?)");
                    $stmt->execute([$categoryBranch, $name, $desc]);
                    setFlash('success',"Category '$name' added.");
                } else {
                    $stmt = $pdo->prepare("UPDATE categories SET branch_id=?, name=?, description=? WHERE id=?");
                    $stmt->execute([$categoryBranch, $name, $desc, $id]);
                    setFlash('success',"Category '$name' updated.");
                }
            }
        }
    }
    if ($action==='delete') {
        $used = $pdo->prepare("SELECT COUNT(*) FROM inventory_items WHERE category_id=? AND is_active=1");
        $used->execute([$id]);
        if ($used->fetchColumn()>0) {
            setFlash('error','Cannot delete — category has active items.');
        } else {
            $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
            setFlash('success','Category deleted.');
        }
    }
    header('Location: categories.php' . ($isAdmin ? '?branch=' . $branchFilter : ''));
    exit;
}

$categoryWhere = "WHERE c.branch_id = ?";
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM categories c $categoryWhere");
$countStmt->execute([$branchFilter]);
$totalCategories = (int)$countStmt->fetchColumn();
$pagination = getPagination($totalCategories, 25);
$categoriesStmt = $pdo->prepare("SELECT c.*,(SELECT COUNT(*) FROM inventory_items WHERE category_id=c.id AND is_active=1) AS item_count FROM categories c $categoryWhere ORDER BY c.name LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$categoriesStmt->execute([$branchFilter]);
$categories = $categoriesStmt->fetchAll();
$editCat = null;
if (isset($_GET['edit'])) {
    $es = $pdo->prepare("SELECT * FROM categories WHERE id=?");
    $es->execute([(int)$_GET['edit']]);
    $editCat = $es->fetch();
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Categories</h1>
        <p class="page-sub"><?= number_format($totalCategories) ?> categories for <?= clean($branches[array_search($branchFilter, array_column($branches,'id'))]['name'] ?? ($_SESSION['user']['branch_name'] ?? 'Current Branch')) ?></p>
    </div>
    <?php if ($canManage): ?>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal('catModal')"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add Category</button>
    </div>
    <?php endif; ?>
</div>
<?php if ($isAdmin): ?>
<div class="card filter-bar">
    <form method="GET" class="filter-form">
        <div class="filter-group">
            <select name="branch" onchange="this.form.submit()">
                <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $b['id']==$branchFilter ? 'selected' : '' ?>><?= clean($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <button type="submit" class="btn btn-secondary">View Branch Categories</button>
        </div>
    </form>
</div>
<?php endif; ?>
<div class="card">
    <div class="card-body p0">
        <table class="data-table">
            <thead><tr><th>#</th><th>Name</th><th>Description</th><th>Items</th><?php if ($canManage): ?><th>Actions</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($categories as $i=>$cat): ?>
            <tr>
                <td><?= $pagination['offset'] + $i + 1 ?></td>
                <td><strong><?= clean($cat['name']) ?></strong></td>
                <td><?= clean($cat['description']?:'—') ?></td>
                <td><span class="badge badge-blue"><?= $cat['item_count'] ?></span></td>
                <?php if ($canManage): ?>
                <td>
                    <div class="action-btns">
                        <a href="categories.php?edit=<?= $cat['id'] ?>" class="btn-icon" title="Edit"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
                        <?php if ($cat['item_count']==0): ?>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this category?')">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
                            <button type="submit" class="btn-icon btn-icon-danger"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M9 6V4h6v2"/></svg></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?= renderPaginationBar($pagination, $totalCategories, ['edit']) ?>
    </div>
</div>

<?php if ($canManage): ?>
<div class="modal-overlay" id="catModal" <?= $editCat?'style="display:flex"':'' ?>>
    <div class="modal">
        <div class="modal-header"><h3><?= $editCat?'Edit Category':'Add Category' ?></h3><button class="modal-close" onclick="closeModal('catModal')">×</button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="<?= $editCat?'edit':'add' ?>">
            <?php if ($editCat): ?><input type="hidden" name="cat_id" value="<?= $editCat['id'] ?>"><?php endif; ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>Category Name *</label>
                    <input type="text" name="name" required value="<?= clean($editCat['name']??'') ?>" placeholder="e.g. ICT Equipment">
                </div>
                <?php if ($isAdmin): ?>
                <div class="form-group">
                    <label>Branch *</label>
                    <select name="branch_id" required>
                        <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= ($editCat['branch_id']??$branchFilter)==$b['id'] ? 'selected' : '' ?>><?= clean($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" name="branch_id" value="<?= $branchId ?>">
                <?php endif; ?>
                <div class="form-group"><label>Description</label><textarea name="description" rows="3" placeholder="Brief description…"><?= clean($editCat['description']??'') ?></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('catModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><?= $editCat?'Update':'Add Category' ?></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
