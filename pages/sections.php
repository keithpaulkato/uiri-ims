<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole('Administrator', 'Campus Manager', 'Store Manager', 'Section Manager');
$pageTitle = 'Departments';
$activePage = 'sections';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $id = (int)($_POST['section_id'] ?? 0);
        $branchId = (int)($_POST['branch_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if (!$branchId || !$name) {
            setFlash('error', 'Campus and department name are required.');
        } else {
            if ($action === 'add') {
                $pdo->prepare("INSERT INTO sections (branch_id, name, code, description) VALUES (?, ?, ?, ?)")
                    ->execute([$branchId, $name, $code, $description]);
                auditLog('ADD_SECTION', 'sections', $pdo->lastInsertId(), "Added department: $name");
                setFlash('success', 'Department added successfully.');
            } else {
                $pdo->prepare("UPDATE sections SET branch_id = ?, name = ?, code = ?, description = ? WHERE id = ?")
                    ->execute([$branchId, $name, $code, $description, $id]);
                auditLog('EDIT_SECTION', 'sections', $id, "Updated department: $name");
                setFlash('success', 'Department updated successfully.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['section_id'] ?? 0);
        $pdo->prepare("DELETE FROM sections WHERE id = ?")->execute([$id]);
        auditLog('DELETE_SECTION', 'sections', $id, 'Deleted department');
        setFlash('success', 'Department deleted.');
    }
    header('Location: sections.php');
    exit;
}
$sections = $pdo->query(
        "SELECT s.*, b.name AS branch_name FROM sections s JOIN branches b ON s.branch_id = b.id WHERE s.is_active = 1 ORDER BY b.name, s.name"
)->fetchAll();
$branches = $pdo->query("SELECT * FROM branches ORDER BY is_headquarters DESC, name")->fetchAll();
$editSection = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM sections WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editSection = $stmt->fetch();
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Department Management</h1>
        <p class="page-sub">Manage UIRI departments/directorates under each campus.</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal('sectionModal')">Add Department</button>
    </div>
</div>

<div class="card">
    <div class="card-body p0">
        <table class="data-table">
            <thead>
                <tr><th>#</th><th>Department</th><th>Code</th><th>Campus</th><th>Description</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($sections as $i => $sec): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><strong><?= clean($sec['name']) ?></strong></td>
                <td><?= clean($sec['code'] ?: '—') ?></td>
                <td><?= clean($sec['branch_name']) ?></td>
                <td><?= clean($sec['description'] ?: '—') ?></td>
                <td>
                    <div class="action-btns">
                        <a href="sections.php?edit=<?= $sec['id'] ?>" class="btn-icon" title="Edit"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this department?')">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="section_id" value="<?= $sec['id'] ?>">
                            <button type="submit" class="btn-icon btn-icon-danger"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="sectionModal" <?= $editSection ? 'style="display:flex"' : '' ?>>
    <div class="modal">
        <div class="modal-header">
            <h3><?= $editSection ? 'Edit Department' : 'Add Department' ?></h3>
            <button class="modal-close" onclick="closeModal('sectionModal')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="<?= $editSection ? 'edit' : 'add' ?>">
            <?php if ($editSection): ?><input type="hidden" name="section_id" value="<?= $editSection['id'] ?>"><?php endif; ?>
            <div class="modal-body">
                <div class="form-grid-2">
                            <div class="form-group">
                        <label>Campus *</label>
                        <select name="branch_id" required>
                            <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= ($editSection['branch_id'] ?? 0) == $b['id'] ? 'selected' : '' ?>><?= clean($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Department Code</label>
                        <input type="text" name="code" value="<?= clean($editSection['code'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Department Name *</label>
                        <input type="text" name="name" required value="<?= clean($editSection['name'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Description</label>
                        <textarea name="description" rows="3"><?= clean($editSection['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('sectionModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><?= $editSection ? 'Update Department' : 'Add Department' ?></button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
