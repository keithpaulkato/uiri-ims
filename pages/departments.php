<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole('Administrator');
$pageTitle = 'Sections / Units';
$activePage = 'departments';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $id = (int)($_POST['department_id'] ?? 0);
        $sectionId = (int)($_POST['section_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $managerName = trim($_POST['manager_name'] ?? '');
        $contactEmail = trim($_POST['contact_email'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if (!$sectionId || !$name) {
            setFlash('error', 'Parent department and section/unit name are required.');
        } else {
            if ($action === 'add') {
                $pdo->prepare("INSERT INTO departments (section_id, name, code, manager_name, contact_email, description) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$sectionId, $name, $code, $managerName, $contactEmail, $description]);
                auditLog('ADD_DEPARTMENT', 'departments', $pdo->lastInsertId(), "Added section/unit: $name");
                setFlash('success', 'Section/unit added successfully.');
            } else {
                $pdo->prepare("UPDATE departments SET section_id = ?, name = ?, code = ?, manager_name = ?, contact_email = ?, description = ? WHERE id = ?")
                    ->execute([$sectionId, $name, $code, $managerName, $contactEmail, $description, $id]);
                auditLog('EDIT_DEPARTMENT', 'departments', $id, "Updated section/unit: $name");
                setFlash('success', 'Section/unit updated successfully.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['department_id'] ?? 0);
        $pdo->prepare("DELETE FROM departments WHERE id = ?")->execute([$id]);
        auditLog('DELETE_DEPARTMENT', 'departments', $id, 'Deleted section/unit');
        setFlash('success', 'Section/unit deleted.');
    }
    header('Location: departments.php');
    exit;
}

$totalDepartments = (int)$pdo->query("SELECT COUNT(*) FROM departments d WHERE d.is_active = 1")->fetchColumn();
$pagination = getPagination($totalDepartments, 10);
$departments = $pdo->query(
    "SELECT d.*, s.name AS section_name, b.name AS branch_name FROM departments d JOIN sections s ON d.section_id = s.id JOIN branches b ON s.branch_id = b.id WHERE d.is_active = 1 ORDER BY b.name, s.name, d.name LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}"
)->fetchAll();
$sections = $pdo->query("SELECT s.id, s.name, s.branch_id, b.name AS branch_name FROM sections s JOIN branches b ON s.branch_id = b.id WHERE s.is_active = 1 ORDER BY b.name, s.name")->fetchAll();
$editDepartment = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editDepartment = $stmt->fetch();
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Section / Unit Management</h1>
        <p class="page-sub"><?= number_format($totalDepartments) ?> sections and units under their parent departments/directorates.</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openModal('departmentModal')">Add Section / Unit</button>
    </div>
</div>

<div class="card">
    <div class="card-body p0">
        <table class="data-table">
            <thead>
                <tr><th>#</th><th>Section / Unit</th><th>Code</th><th>Parent Department</th><th>Campus</th><th>Manager</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($departments as $i => $dept): ?>
            <tr>
                <td><?= $pagination['offset'] + $i + 1 ?></td>
                <td><strong><?= clean($dept['name']) ?></strong></td>
                <td><?= clean($dept['code'] ?: '—') ?></td>
                <td><?= clean($dept['section_name']) ?></td>
                <td><?= clean($dept['branch_name']) ?></td>
                <td><?= clean($dept['manager_name'] ?: '—') ?></td>
                <td>
                    <div class="action-btns">
                        <a href="departments.php?edit=<?= $dept['id'] ?>" class="btn-icon" title="Edit"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this section/unit?')">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="department_id" value="<?= $dept['id'] ?>">
                            <button type="submit" class="btn-icon btn-icon-danger"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?= renderPaginationBar($pagination, $totalDepartments, ['edit']) ?>
    </div>
</div>

<div class="modal-overlay" id="departmentModal" <?= $editDepartment ? 'style="display:flex"' : '' ?>>
    <div class="modal">
        <div class="modal-header">
            <h3><?= $editDepartment ? 'Edit Section / Unit' : 'Add Section / Unit' ?></h3>
            <button class="modal-close" onclick="closeModal('departmentModal')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="<?= $editDepartment ? 'edit' : 'add' ?>">
            <?php if ($editDepartment): ?><input type="hidden" name="department_id" value="<?= $editDepartment['id'] ?>"><?php endif; ?>
            <div class="modal-body">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Parent Department *</label>
                        <select name="section_id" required>
                            <?php foreach ($sections as $section): ?>
                            <option value="<?= $section['id'] ?>" <?= ($editDepartment['section_id'] ?? 0) == $section['id'] ? 'selected' : '' ?>><?= clean($section['branch_name'] . ' / ' . $section['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Section / Unit Code</label>
                        <input type="text" name="code" value="<?= clean($editDepartment['code'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Section / Unit Name *</label>
                        <input type="text" name="name" required value="<?= clean($editDepartment['name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Manager Name</label>
                        <input type="text" name="manager_name" value="<?= clean($editDepartment['manager_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Contact Email</label>
                        <input type="email" name="contact_email" value="<?= clean($editDepartment['contact_email'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Description</label>
                        <textarea name="description" rows="3"><?= clean($editDepartment['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('departmentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><?= $editDepartment ? 'Update Section / Unit' : 'Add Section / Unit' ?></button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
