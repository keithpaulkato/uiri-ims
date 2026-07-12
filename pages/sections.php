<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole('Administrator', 'Campus Manager', 'Store Manager', 'Section Manager');
$pageTitle = 'Departments';
$activePage = 'sections';
$pdo = db();
$user = currentUser();
$isAdmin = hasRole('Administrator');
$currentBranchId = (int)$user['branch_id'];
$branches = $pdo->query("SELECT * FROM branches ORDER BY is_headquarters DESC, name")->fetchAll();
$branchNamesById = array_column($branches, 'name', 'id');
$currentBranchName = $branchNamesById[$currentBranchId] ?? ($user['branch_name'] ?? 'Current Campus');

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
$totalSections = (int)$pdo->query("SELECT COUNT(*) FROM sections s WHERE s.is_active = 1")->fetchColumn();
$pagination = getPagination($totalSections, 10);
$sections = $pdo->query(
        "SELECT s.*, b.name AS branch_name FROM sections s JOIN branches b ON s.branch_id = b.id WHERE s.is_active = 1 ORDER BY b.name, s.name LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}"
)->fetchAll();
$printSectionsStmt = $isAdmin
    ? $pdo->query("SELECT s.id, s.name, s.code, s.description, s.branch_id, b.name AS branch_name FROM sections s JOIN branches b ON s.branch_id = b.id WHERE s.is_active = 1 ORDER BY b.name, s.name")
    : null;
if ($isAdmin) {
    $printSections = $printSectionsStmt->fetchAll();
} else {
    $printSectionsStmt = $pdo->prepare("SELECT s.id, s.name, s.code, s.description, s.branch_id, b.name AS branch_name FROM sections s JOIN branches b ON s.branch_id = b.id WHERE s.is_active = 1 AND s.branch_id = ? ORDER BY s.name");
    $printSectionsStmt->execute([$currentBranchId]);
    $printSections = $printSectionsStmt->fetchAll();
}
$printDepartmentData = array_map(static function ($section) {
    return [
        'name' => $section['name'],
        'code' => $section['code'] ?: '—',
        'branch_id' => (int)$section['branch_id'],
        'branch_name' => $section['branch_name'],
        'description' => $section['description'] ?: '—',
    ];
}, $printSections);
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
        <p class="page-sub"><?= number_format($totalSections) ?> UIRI departments/directorates under each campus.</p>
    </div>
    <div class="page-actions">
        <?php if ($isAdmin): ?>
        <button type="button" class="btn btn-outline" onclick="openModal('printDepartmentsModal')">
            <svg viewBox="0 0 24 24"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print Departments
        </button>
        <?php else: ?>
        <button type="button" class="btn btn-outline" onclick="printDepartments('<?= $currentBranchId ?>')">
            <svg viewBox="0 0 24 24"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print Departments
        </button>
        <?php endif; ?>
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
                <td><?= $pagination['offset'] + $i + 1 ?></td>
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
        <?= renderPaginationBar($pagination, $totalSections, ['edit']) ?>
    </div>
</div>

<?php if ($isAdmin): ?>
<div class="modal-overlay" id="printDepartmentsModal" role="dialog" aria-modal="true" aria-labelledby="printDepartmentsTitle">
    <div class="modal">
        <div class="modal-header">
            <h3 id="printDepartmentsTitle">Print Departments</h3>
            <button class="modal-close" onclick="closeModal('printDepartmentsModal')">×</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Print Scope</label>
                <select id="printDepartmentScope">
                    <option value="<?= $currentBranchId ?>">Active campus: <?= clean($currentBranchName) ?></option>
                    <option value="all">All campuses</option>
                    <?php foreach ($branches as $branch): ?>
                    <option value="<?= $branch['id'] ?>"><?= clean($branch['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="form-note">Choose whether to print the active campus, all campuses, or a specific campus.</small>
            </div>
            <div class="print-option-summary">
                The report includes all matching departments, not only the current paginated rows.
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('printDepartmentsModal')">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="printSelectedDepartments()">Print Report</button>
        </div>
    </div>
</div>
<?php endif; ?>

<section class="departments-print-area" id="departmentsPrintArea" aria-hidden="true">
    <div class="departments-print-header">
        <div class="departments-print-logo">
            <img src="<?= BASE_URL ?>assets/img/uiri-logo.webp" alt="UIRI">
        </div>
        <div>
            <span>Uganda Industrial Research Institute</span>
            <h1>Department Register</h1>
            <p id="printDepartmentScopeLabel"><?= clean($currentBranchName) ?></p>
        </div>
    </div>
    <div class="departments-print-meta">
        <div><strong>Scope</strong><span id="printDepartmentMetaScope"><?= clean($currentBranchName) ?></span></div>
        <div><strong>Total Departments</strong><span id="printDepartmentMetaCount">0</span></div>
        <div><strong>Generated By</strong><span><?= clean($user['full_name']) ?></span><em><?= clean($user['role'] ?? 'User') ?></em></div>
        <div><strong>Generated On</strong><span><?= formatDateTime('now', true) ?></span></div>
    </div>
    <table class="departments-print-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Department</th>
                <th>Code</th>
                <th>Campus</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody id="printDepartmentsRows"></tbody>
    </table>
</section>

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

<script>
const departmentPrintData = <?= json_encode($printDepartmentData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const departmentBranchNames = <?= json_encode($branchNamesById, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const activeDepartmentBranchId = '<?= $currentBranchId ?>';

function departmentPrintText(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (character) {
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[character];
    });
}

function printDepartments(scope) {
    const selectedScope = scope || activeDepartmentBranchId;
    const rows = selectedScope === 'all'
        ? departmentPrintData
        : departmentPrintData.filter(row => String(row.branch_id) === String(selectedScope));
    const scopeLabel = selectedScope === 'all' ? 'All UIRI Campuses' : (departmentBranchNames[selectedScope] || 'Selected Campus');
    const tbody = document.getElementById('printDepartmentsRows');
    const scopeTitle = document.getElementById('printDepartmentScopeLabel');
    const metaScope = document.getElementById('printDepartmentMetaScope');
    const metaCount = document.getElementById('printDepartmentMetaCount');

    if (!tbody || !scopeTitle || !metaScope || !metaCount) return;

    tbody.innerHTML = rows.length
        ? rows.map((row, index) => `
            <tr>
                <td>${index + 1}</td>
                <td><strong>${departmentPrintText(row.name)}</strong></td>
                <td>${departmentPrintText(row.code || '—')}</td>
                <td>${departmentPrintText(row.branch_name)}</td>
                <td>${departmentPrintText(row.description || '—')}</td>
            </tr>
        `).join('')
        : '<tr><td colspan="5" class="departments-print-empty">No departments found for this scope.</td></tr>';

    scopeTitle.textContent = scopeLabel;
    metaScope.textContent = scopeLabel;
    metaCount.textContent = rows.length.toLocaleString();
    document.body.classList.add('department-printing');
    window.setTimeout(function () {
        window.print();
    }, 50);
}

function printSelectedDepartments() {
    const scope = document.getElementById('printDepartmentScope')?.value || activeDepartmentBranchId;
    closeModal('printDepartmentsModal');
    printDepartments(scope);
}

window.addEventListener('afterprint', function () {
    document.body.classList.remove('department-printing');
});
</script>
<style>
.print-option-summary {
    border: 1px solid #dbeafe;
    background: #eff6ff;
    color: #1e3a8a;
    border-radius: 8px;
    padding: 12px 14px;
    font-size: .84rem;
    font-weight: 650;
}
.departments-print-area {
    display: none;
}
.departments-print-header {
    display: flex;
    align-items: center;
    gap: 18px;
    padding-bottom: 18px;
    margin-bottom: 16px;
    border-bottom: 3px solid #0A1628;
}
.departments-print-logo {
    width: 72px;
    height: 72px;
    border: 1px solid #d8e1ec;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.departments-print-logo img {
    max-width: 58px;
    max-height: 58px;
}
.departments-print-header span {
    display: block;
    font-size: 11px;
    font-weight: 800;
    color: #607086;
    text-transform: uppercase;
    letter-spacing: .08em;
}
.departments-print-header h1 {
    margin: 3px 0;
    color: #0A1628;
    font-size: 24px;
    font-weight: 900;
}
.departments-print-header p {
    margin: 0;
    color: #34445a;
    font-size: 13px;
    font-weight: 700;
}
.departments-print-meta {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
    margin-bottom: 16px;
}
.departments-print-meta div {
    border: 1px solid #d8e1ec;
    border-radius: 8px;
    padding: 9px 10px;
    background: #f8fafc;
}
.departments-print-meta strong,
.departments-print-meta span,
.departments-print-meta em {
    display: block;
}
.departments-print-meta strong {
    color: #607086;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .06em;
    margin-bottom: 4px;
}
.departments-print-meta span {
    color: #0A1628;
    font-size: 12px;
    font-weight: 800;
}
.departments-print-meta em {
    color: #607086;
    font-size: 10px;
    font-style: normal;
    font-weight: 750;
    margin-top: 2px;
}
.departments-print-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
}
.departments-print-table th {
    background: #d8edf8;
    color: #12314f;
    text-align: left;
    padding: 8px;
    border: 1px solid #a8bed3;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .05em;
}
.departments-print-table td {
    padding: 8px;
    border: 1px solid #cbd7e4;
    color: #102033;
    vertical-align: top;
}
.departments-print-empty {
    text-align: center;
    font-weight: 800;
    color: #607086;
}
@media print {
    body.department-printing .sidebar,
    body.department-printing .topnav,
    body.department-printing .flash,
    body.department-printing .page-content > :not(.departments-print-area) {
        display: none !important;
    }
    body.department-printing .main-wrapper {
        margin: 0 !important;
    }
    body.department-printing .page-content {
        padding: 0 !important;
    }
    body.department-printing .departments-print-area {
        display: block !important;
        padding: 18mm 14mm;
        background: #fff;
    }
    body.department-printing .departments-print-meta {
        grid-template-columns: repeat(4, 1fr);
        break-inside: avoid;
    }
    body.department-printing .departments-print-header {
        break-inside: avoid;
    }
}
</style>
<?php include __DIR__ . '/../includes/footer.php'; ?>
