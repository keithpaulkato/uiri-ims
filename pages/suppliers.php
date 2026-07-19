<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
$pageTitle = 'Suppliers'; $activePage = 'suppliers';
$user = currentUser(); $canManage = hasRole('Administrator','Store Manager'); $pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action']??'';
    if ($action==='add'||$action==='edit') {
        $id = (int)($_POST['supplier_id']??0);
        $company = trim($_POST['company_name']??''); $contact = trim($_POST['contact_person']??'');
        $email = trim($_POST['email']??''); $phone = normalizeUgandanPhone($_POST['phone']??'');
        $address = trim($_POST['address']??''); $tin = trim($_POST['tin_number']??'');
        if (!$company) { setFlash('error','Company name is required.'); }
        else {
            if ($action==='add') {
                $pdo->prepare("INSERT INTO suppliers (company_name,contact_person,email,phone,address,tin_number,is_active) VALUES (?,?,?,?,?,?,1)")->execute([$company,$contact,$email,$phone,$address,$tin]);
                auditLog('ADD_SUPPLIER','suppliers',$pdo->lastInsertId(),"Added supplier: $company");
                setFlash('success',"Supplier '$company' added.");
            } else {
                $pdo->prepare("UPDATE suppliers SET company_name=?,contact_person=?,email=?,phone=?,address=?,tin_number=? WHERE id=?")->execute([$company,$contact,$email,$phone,$address,$tin,$id]);
                auditLog('EDIT_SUPPLIER','suppliers',$id,"Updated supplier: $company");
                setFlash('success',"Supplier '$company' updated.");
            }
        }
    }
    if ($action==='toggle') {
        $id=(int)($_POST['supplier_id']??0);
        $pdo->prepare("UPDATE suppliers SET is_active=1-is_active WHERE id=?")->execute([$id]);
        auditLog('TOGGLE_SUPPLIER','suppliers',$id,'Toggled supplier status');
        setFlash('success','Supplier status updated.');
    }
    if ($action==='delete') {
        $id=(int)($_POST['supplier_id']??0);
        if ($id) {
            $pdo->prepare("UPDATE inventory_items SET supplier_id = NULL WHERE supplier_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM suppliers WHERE id = ?")->execute([$id]);
            auditLog('DELETE_SUPPLIER','suppliers',$id,'Deleted supplier');
            setFlash('success','Supplier deleted.');
        }
    }
    header('Location: suppliers.php'); exit;
}

$search = trim($_GET['search']??'');
$where = []; $params = [];
if ($search) { $where[] = "(company_name LIKE ? OR contact_person LIKE ? OR email LIKE ?)"; $params=array_merge($params,["%$search%","%$search%","%$search%"]); }
$whereSQL = $where ? 'WHERE '.implode(' AND ',$where) : '';
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM suppliers $whereSQL");
$countStmt->execute($params);
$totalSuppliers = (int)$countStmt->fetchColumn();
$pagination = getPagination($totalSuppliers, 10);
$suppliers = $pdo->prepare("SELECT * FROM suppliers $whereSQL ORDER BY company_name LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$suppliers->execute($params); $suppliers = $suppliers->fetchAll();

$printStmt = $pdo->prepare("SELECT * FROM suppliers $whereSQL ORDER BY company_name");
$printStmt->execute($params);
$printSuppliers = $printStmt->fetchAll();
$printActiveCount = count(array_filter($printSuppliers, static fn($supplier) => (int)$supplier['is_active'] === 1));
$printInactiveCount = count($printSuppliers) - $printActiveCount;
$supplierPrintFilters = [
    'Search' => $search !== '' ? $search : 'Not applied',
    'Total suppliers' => number_format(count($printSuppliers)),
    'Active' => number_format($printActiveCount),
    'Inactive' => number_format($printInactiveCount),
];

$selectedSupplierHistory = null;
$supplierStats = null;
if (isset($_GET['history'])) {
    $supplierId = (int)$_GET['history'];
    $selectedSupplier = $pdo->prepare("SELECT * FROM suppliers WHERE id=?");
    $selectedSupplier->execute([$supplierId]);
    $selectedSupplierHistory = $selectedSupplier->fetch();

    if ($selectedSupplierHistory) {
        $statsStmt = $pdo->prepare(
            "SELECT COUNT(*) AS transaction_count,
                    COALESCE(SUM(CASE WHEN t.transaction_type = 'stock_in' THEN t.quantity ELSE 0 END),0) AS total_received,
                    COALESCE(SUM(CASE WHEN t.transaction_type = 'stock_out' THEN t.quantity ELSE 0 END),0) AS total_issued,
                    MAX(t.transaction_date) AS last_transaction
             FROM stock_transactions t
             JOIN inventory_items i ON t.item_id = i.id
             WHERE i.supplier_id = ?"
        );
        $statsStmt->execute([$supplierId]);
        $supplierStats = $statsStmt->fetch();

        $historyCountStmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM stock_transactions t
             JOIN inventory_items i ON t.item_id = i.id
             WHERE i.supplier_id = ?"
        );
        $historyCountStmt->execute([$supplierId]);
        $totalSupplierHistory = (int)$historyCountStmt->fetchColumn();
        $historyPagination = getPagination($totalSupplierHistory, 10, 'history_page');

        $historyStmt = $pdo->prepare(
            "SELECT t.transaction_date, t.transaction_type, t.quantity, t.reference_number, t.remarks,
                    i.item_code, i.name AS item_name, u.full_name AS user_name
             FROM stock_transactions t
             JOIN inventory_items i ON t.item_id = i.id
             LEFT JOIN users u ON t.user_id = u.id
             WHERE i.supplier_id = ?
             ORDER BY t.transaction_date DESC, t.created_at DESC
             LIMIT {$historyPagination['per_page']} OFFSET {$historyPagination['offset']}"
        );
        $historyStmt->execute([$supplierId]);
        $supplierHistory = $historyStmt->fetchAll();
    }
}

$editSupplier = null;
if (isset($_GET['edit'])) { $es=$pdo->prepare("SELECT * FROM suppliers WHERE id=?"); $es->execute([(int)$_GET['edit']]); $editSupplier=$es->fetch(); }

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div><h1 class="page-title">Suppliers</h1><p class="page-sub"><?= number_format($totalSuppliers) ?> supplier(s)</p></div>
    <div class="page-actions">
        <button type="button" class="btn btn-outline" onclick="printSuppliersReport()">
            <svg viewBox="0 0 24 24"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print Suppliers
        </button>
        <?php if ($canManage): ?>
        <button class="btn btn-primary" onclick="openModal('supplierModal')"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add Supplier</button>
        <?php endif; ?>
    </div>
</div>
<div class="card filter-bar">
    <form method="GET" class="filter-form">
        <div class="filter-group"><div class="input-wrap"><svg class="input-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" name="search" placeholder="Search suppliers…" value="<?= clean($search) ?>"></div></div>
        <button type="submit" class="btn btn-primary">Search</button>
        <a href="suppliers.php" class="btn btn-outline">Reset</a>
    </form>
</div>

<section class="suppliers-print-area" id="suppliersPrintArea" aria-hidden="true">
    <div class="suppliers-print-header">
        <div class="suppliers-print-logo"><img src="<?= BASE_URL ?>assets/img/uiri-logo.webp" alt="UIRI"></div>
        <div>
            <span>Uganda Industrial Research Institute</span>
            <h1>Supplier Register</h1>
            <p>Printed: <?= date('d M Y, h:i A') ?> by <?= clean($user['full_name'] ?? 'Current user') ?><?= !empty($user['role']) ? ' (' . clean($user['role']) . ')' : '' ?></p>
        </div>
    </div>
    <div class="suppliers-print-meta">
        <?php foreach ($supplierPrintFilters as $label => $value): ?>
        <div><strong><?= clean($label) ?></strong><span><?= clean($value) ?></span></div>
        <?php endforeach; ?>
    </div>
    <table class="suppliers-print-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Company</th>
                <th>Contact Person</th>
                <th>Email</th>
                <th>Phone</th>
                <th>TIN</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($printSuppliers): ?>
            <?php foreach ($printSuppliers as $i => $supplier): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><strong><?= clean($supplier['company_name']) ?></strong></td>
                <td><?= clean($supplier['contact_person'] ?: 'â€”') ?></td>
                <td><?= clean($supplier['email'] ?: 'â€”') ?></td>
                <td><?= clean($supplier['phone'] ?: 'â€”') ?></td>
                <td><?= clean($supplier['tin_number'] ?: 'â€”') ?></td>
                <td><?= (int)$supplier['is_active'] === 1 ? 'Active' : 'Inactive' ?></td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="7" class="suppliers-print-empty">No suppliers found for this print scope.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <div class="suppliers-print-footer">
        <span>Generated from UIRI IMS supplier management.</span>
        <span><?= clean($user['branch_name'] ?? 'UIRI') ?></span>
    </div>
</section>
<div class="card">
    <div class="card-body p0">
        <?php if ($suppliers): ?>
        <div class="table-responsive suppliers-table-wrap">
        <table class="data-table suppliers-table">
            <thead><tr><th>#</th><th>Company</th><th>Contact Person</th><th>Email</th><th>Phone</th><th>TIN</th><th>Status</th><?php if ($canManage): ?><th>Actions</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($suppliers as $i=>$s): ?>
            <tr>
                <td><?= $pagination['offset'] + $i + 1 ?></td>
                <td><strong><?= clean($s['company_name']) ?></strong></td>
                <td><?= clean($s['contact_person']?:'—') ?></td>
                <td><?= clean($s['email']?:'—') ?></td>
                <td><?= clean($s['phone']?:'—') ?></td>
                <td><?= clean($s['tin_number']?:'—') ?></td>
                <td><span class="badge <?= $s['is_active']?'badge-success':'badge-danger' ?>"><?= $s['is_active']?'Active':'Inactive' ?></span></td>
                <?php if ($canManage): ?>
                <td>
                    <div class="action-btns">
                        <a href="suppliers.php?history=<?= $s['id'] ?>" class="btn-icon" title="View history"><svg viewBox="0 0 24 24"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="9"/></svg></a>
                        <a href="suppliers.php?edit=<?= $s['id'] ?>" class="btn-icon" title="Edit"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="supplier_id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn-icon" title="Toggle status"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
                        </form>
                        <form method="POST" class="js-delete-confirm" style="display:inline"
                              data-delete-title="Delete supplier?"
                              data-delete-text="<?= clean(($s['company_name'] ?: 'This supplier') . ' will be removed. Related inventory item supplier links will be cleared.') ?>"
                              data-delete-confirm="Yes, delete supplier">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="supplier_id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn-icon btn-icon-danger" title="Delete"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg></button>
                        </form>
                    </div>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?= renderPaginationBar($pagination, $totalSuppliers, ['edit', 'history']) ?>
        <?php else: ?>
        <div class="empty-state"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/></svg><h3>No suppliers found</h3><p>Add your first supplier to get started.</p></div>
        <?php endif; ?>
    </div>
</div>

<?php if ($selectedSupplierHistory && !empty($supplierHistory)): ?>
<div class="card">
    <div class="card-header">
        <h3>Supplier Performance & Purchase History — <?= clean($selectedSupplierHistory['company_name']) ?></h3>
        <a href="suppliers.php" class="card-link">Close</a>
    </div>
    <div class="card-body">
        <div class="form-grid-2" style="margin-bottom:20px;">
            <div class="card" style="padding:16px;">
                <h4 style="margin:0 0 8px;">Performance Tracking</h4>
                <p><strong>Transactions:</strong> <?= (int)($supplierStats['transaction_count'] ?? 0) ?></p>
                <p><strong>Total Received:</strong> <?= (int)($supplierStats['total_received'] ?? 0) ?></p>
                <p><strong>Total Issued:</strong> <?= (int)($supplierStats['total_issued'] ?? 0) ?></p>
                <p><strong>Last Transaction:</strong> <?= clean($supplierStats['last_transaction'] ? date('d M Y', strtotime($supplierStats['last_transaction'])) : '—') ?></p>
            </div>
            <div class="card" style="padding:16px;">
                <h4 style="margin:0 0 8px;">Supplier Details</h4>
                <p><strong>Contact:</strong> <?= clean($selectedSupplierHistory['contact_person'] ?: '—') ?></p>
                <p><strong>Phone:</strong> <?= clean($selectedSupplierHistory['phone'] ?: '—') ?></p>
                <p><strong>Email:</strong> <?= clean($selectedSupplierHistory['email'] ?: '—') ?></p>
                <p><strong>TIN:</strong> <?= clean($selectedSupplierHistory['tin_number'] ?: '—') ?></p>
            </div>
        </div>
        <div class="table-responsive suppliers-history-wrap">
        <table class="data-table suppliers-history-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Item</th>
                    <th>Type</th>
                    <th>Qty</th>
                    <th>Reference</th>
                    <th>Recorded By</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($supplierHistory as $tx): ?>
            <tr>
                <td><?= date('d M Y', strtotime($tx['transaction_date'])) ?></td>
                <td><span class="item-name"><?= clean($tx['item_name']) ?></span><span class="item-code"><?= clean($tx['item_code']) ?></span></td>
                <td><span class="badge <?= $tx['transaction_type'] === 'stock_in' ? 'badge-success' : 'badge-blue' ?>"><?= str_replace('_', ' ', ucfirst($tx['transaction_type'])) ?></span></td>
                <td><?= number_format($tx['quantity']) ?></td>
                <td><?= clean($tx['reference_number'] ?: '—') ?></td>
                <td><?= clean($tx['user_name'] ?: 'System') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?= renderPaginationBar($historyPagination, $totalSupplierHistory, ['edit']) ?>
    </div>
</div>
<?php endif; ?>

<?php if ($canManage): ?>
<div class="modal-overlay" id="supplierModal" <?= $editSupplier?'style="display:flex"':'' ?>>
    <div class="modal">
        <div class="modal-header"><h3><?= $editSupplier?'Edit Supplier':'Add Supplier' ?></h3><button class="modal-close" onclick="closeModal('supplierModal')">×</button></div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="<?= $editSupplier?'edit':'add' ?>">
            <?php if ($editSupplier): ?><input type="hidden" name="supplier_id" value="<?= $editSupplier['id'] ?>"><?php endif; ?>
            <div class="modal-body">
                <div class="form-grid-2">
                    <div class="form-group" style="grid-column:1/-1"><label>Company Name *</label><input type="text" name="company_name" required value="<?= clean($editSupplier['company_name']??'') ?>" placeholder="e.g. CompuTech Uganda Ltd"></div>
                    <div class="form-group"><label>Contact Person</label><input type="text" name="contact_person" value="<?= clean($editSupplier['contact_person']??'') ?>" placeholder="Full name"></div>
                    <div class="form-group"><label>Phone</label><input type="tel" class="js-ug-phone" name="phone" value="<?= clean($editSupplier['phone']??'') ?>" placeholder="+256 700000000" inputmode="numeric" autocomplete="tel" maxlength="14"></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= clean($editSupplier['email']??'') ?>" placeholder="supplier@example.com"></div>
                    <div class="form-group"><label>TIN Number</label><input type="text" name="tin_number" value="<?= clean($editSupplier['tin_number']??'') ?>" placeholder="URA TIN"></div>
                    <div class="form-group" style="grid-column:1/-1"><label>Address</label><textarea name="address" rows="2" placeholder="Physical address…"><?= clean($editSupplier['address']??'') ?></textarea></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('supplierModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><?= $editSupplier?'Update':'Add Supplier' ?></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<script>
function printSuppliersReport() {
    const printArea = document.getElementById('suppliersPrintArea');
    if (!printArea) {
        window.print();
        return;
    }

    const cleanup = () => {
        document.body.classList.remove('supplier-printing');
        printArea.setAttribute('aria-hidden', 'true');
        window.removeEventListener('afterprint', cleanup);
    };

    printArea.setAttribute('aria-hidden', 'false');
    document.body.classList.add('supplier-printing');
    window.addEventListener('afterprint', cleanup, { once: true });
    window.setTimeout(() => window.print(), 80);
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
