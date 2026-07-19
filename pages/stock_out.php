<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole('Administrator', 'Store Manager', 'Staff');

$pageTitle = 'Stock Out';
$activePage = 'stock_out';
$user = currentUser();
$branchId = (int)$user['branch_id'];
$isAdmin = hasRole('Administrator');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $itemId = (int)($_POST['item_id'] ?? 0);
    $qty = (int)($_POST['quantity'] ?? 0);
    $refNo = trim($_POST['reference_number'] ?? '');
    $issuedTo = trim($_POST['issued_to'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $txDate = $_POST['transaction_date'] ?? date('Y-m-d');

    $errors = [];
    if (!$itemId) $errors[] = 'Please select an item.';
    if ($qty <= 0) $errors[] = 'Quantity must be greater than zero.';
    if ($txDate === '') $errors[] = 'Transaction date is required.';

    if ($errors) {
        setFlash('error', implode(' ', $errors));
        header('Location: stock_out.php');
        exit;
    }

    $pdo->beginTransaction();
    try {
        $itemStmt = $pdo->prepare("
            SELECT i.id, i.branch_id, i.current_stock, i.name, i.item_code, i.brand_model, i.description, i.unit, i.asset_type, i.unit_price, c.name AS category_name
            FROM inventory_items i
            JOIN categories c ON c.id = i.category_id
            WHERE i.id = ? AND i.is_active = 1
            FOR UPDATE
        ");
        $itemStmt->execute([$itemId]);
        $itemData = $itemStmt->fetch();

        if (!$itemData) {
            throw new Exception('Selected item was not found.');
        }
        if (!$isAdmin && (int)$itemData['branch_id'] !== $branchId) {
            throw new Exception('Selected item is outside your active branch.');
        }
        $displayUnit = inventoryDisplayUnitForRow($itemData);
        if ((int)$itemData['current_stock'] < $qty) {
            throw new Exception("Insufficient stock. Available: {$itemData['current_stock']} {$displayUnit}.");
        }

        $detailParts = [];
        if ($issuedTo !== '') $detailParts[] = "Issued to: $issuedTo";
        if ($purpose !== '') $detailParts[] = "Purpose: $purpose";
        if ($remarks !== '') $detailParts[] = $remarks;
        $fullRemarks = implode(' | ', $detailParts);

        $txBranch = (int)$itemData['branch_id'];
        $unitPrice = (float)($itemData['unit_price'] ?? 0);

        $insert = $pdo->prepare("
            INSERT INTO stock_transactions
                (item_id, branch_id, user_id, transaction_type, quantity, unit_price, reference_number, remarks, transaction_date)
            VALUES (?, ?, ?, 'stock_out', ?, ?, ?, ?, ?)
        ");
        $insert->execute([$itemId, $txBranch, $user['id'], $qty, $unitPrice, $refNo, $fullRemarks, $txDate]);
        $newTransactionId = (int)$pdo->lastInsertId();

        $pdo->prepare("UPDATE inventory_items SET current_stock = current_stock - ? WHERE id = ?")
            ->execute([$qty, $itemId]);

        $pdo->commit();
        auditLog('STOCK_OUT', 'stock_transactions', $newTransactionId, "Stock out: $qty {$displayUnit} x {$itemData['name']}");
        setFlash('success', "Stock out recorded - $qty {$displayUnit} issued.");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlash('error', $e->getMessage() ?: 'Transaction failed.');
    }

    header('Location: stock_out.php');
    exit;
}

$branchFilter = $isAdmin ? (int)($_GET['branch'] ?? 0) : $branchId;
$branchClause = $isAdmin ? ($branchFilter ? "AND i.branch_id = $branchFilter" : '') : "AND i.branch_id = $branchId";
$txClause = $isAdmin ? ($branchFilter ? "AND t.branch_id = $branchFilter" : '') : "AND t.branch_id = $branchId";

$items = $pdo->query("
    SELECT i.id, i.name, i.item_code, i.brand_model, i.description, i.unit, i.unit_price, i.current_stock, i.minimum_stock,
           i.asset_type, i.branch_id, c.name AS category_name, b.name AS branch_name, b.location AS branch_location
    FROM inventory_items i
    JOIN categories c ON i.category_id = c.id
    JOIN branches b ON i.branch_id = b.id
    WHERE i.is_active = 1 AND i.current_stock > 0 $branchClause
    ORDER BY i.name
")->fetchAll();
foreach ($items as &$stockOutItem) {
    $stockOutItem['display_unit'] = inventoryDisplayUnitForRow($stockOutItem);
}
unset($stockOutItem);

$branches = $pdo->query("SELECT * FROM branches ORDER BY is_headquarters DESC")->fetchAll();
$recent = $pdo->query("
    SELECT t.*, i.name AS item_name, i.item_code, i.brand_model, i.description, i.unit, i.asset_type, c.name AS category_name, b.name AS branch_name, u.full_name AS issued_by
    FROM stock_transactions t
    JOIN inventory_items i ON t.item_id = i.id
    JOIN categories c ON c.id = i.category_id
    JOIN branches b ON t.branch_id = b.id
    JOIN users u ON t.user_id = u.id
    WHERE t.transaction_type = 'stock_out' $txClause
    ORDER BY t.transaction_date DESC, t.created_at DESC
    LIMIT 24
")->fetchAll();
foreach ($recent as &$recentStockOut) {
    $recentStockOut['display_unit'] = inventoryDisplayUnitForRow($recentStockOut);
}
unset($recentStockOut);

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header stock-page-header">
    <div>
        <h1 class="page-title">Stock Out</h1>
        <p class="page-sub">Record issued inventory and deduct quantities from the correct branch stock.</p>
    </div>
    <div class="stock-page-actions">
        <a class="btn btn-outline" href="transactions.php?type=stock_out">View Transactions</a>
        <button type="button" class="btn btn-primary" onclick="document.getElementById('stockOutForm').scrollIntoView({behavior:'smooth'})">New Stock Out</button>
    </div>
</div>

<div class="stock-workspace stock-workspace-out">
    <section class="card stock-form-card">
        <div class="card-header">
            <h3>Issue Stock</h3>
            <span class="badge badge-blue">Stock decreases</span>
        </div>
        <div class="card-body">
            <form method="POST" id="stockOutForm">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="item_id" id="stockOutItemId" value="">

                <div class="inventory-wizard stock-in-wizard">
                    <div class="wizard-intro">
                        <div>
                            <div class="wizard-breadcrumb">
                                <span class="breadcrumb-tag tag-navy"><i class="fa-solid fa-layer-group me-1"></i>Stock</span>
                                <i class="fa-solid fa-chevron-right breadcrumb-sep"></i>
                                <span class="breadcrumb-tag tag-blue"><i class="fa-solid fa-arrow-up-long me-1"></i>Stock Out</span>
                                <i class="fa-solid fa-chevron-right breadcrumb-sep"></i>
                                <span class="breadcrumb-tag tag-amber"><i class="fa-solid fa-dolly me-1"></i>Issue Inventory</span>
                            </div>
                            <div class="wizard-title-row">
                                <h3>Record Stock Out</h3>
                                <div class="wizard-badges">
                                    <span class="wizard-badge badge-live"><i class="fa-solid fa-bolt me-1"></i>Live preview</span>
                                    <span class="wizard-badge badge-guarded"><i class="fa-solid fa-shield-halved me-1"></i>Stock guarded</span>
                                </div>
                            </div>
                            <p class="wizard-desc">Issue inventory in focused steps, then review the stock impact cleanly before issuing.</p>
                        </div>
                    </div>

                    <div class="wizard-step-nav" aria-label="Stock out steps">
                        <button type="button" class="wizard-step-btn active" data-step-target="product">1. Product</button>
                        <button type="button" class="wizard-step-btn" data-step-target="issue">2. Issue</button>
                        <button type="button" class="wizard-step-btn" data-step-target="reference">3. Reference</button>
                        <button type="button" class="wizard-step-btn" data-step-target="review">4. Review</button>
                    </div>

                    <div class="stock-wizard-panels">
                        <div class="wizard-step-panel active" data-step="product">
                <?php if ($isAdmin): ?>
                <div class="form-group">
                    <label>Branch Filter</label>
                    <select class="form-control" onchange="window.location='stock_out.php?branch='+this.value">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $b): ?>
                        <option value="<?= (int)$b['id'] ?>" <?= (int)$b['id'] === $branchFilter ? 'selected' : '' ?>><?= clean($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-note">The transaction branch is taken from the selected item.</small>
                </div>
                <?php endif; ?>

                <div class="form-section-card">
                    <h4>Product Information</h4>
                    <p>Search by SKU, name or scan a barcode. The system prevents issuing more than current stock.</p>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Product Search</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                                <input list="stockOutProductList" id="stockOutProductSearch" class="form-control" placeholder="Search product..." autocomplete="off" oninput="onStockOutProductSearch(this.value)">
                            </div>
                            <datalist id="stockOutProductList">
                            <?php foreach ($items as $item): ?>
                                <option value="<?= clean($item['item_code'] . ' - ' . $item['name']) ?>"></option>
                            <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="form-group">
                            <label>Barcode Scanner</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-barcode"></i></span>
                                <input type="text" id="stockOutBarcode" class="form-control" placeholder="Scan or type SKU" onblur="onStockOutBarcode(this.value)">
                            </div>
                        </div>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group"><label>SKU</label><input type="text" id="stockOutSku" class="form-control" readonly></div>
                        <div class="form-group"><label>Category</label><input type="text" id="stockOutCategory" class="form-control" readonly></div>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group"><label>Available Stock</label><input type="text" id="stockOutAvailableField" class="form-control" readonly></div>
                        <div class="form-group"><label>Unit of Measure</label><input type="text" id="stockOutUnitField" class="form-control" readonly></div>
                    </div>
                </div>
                        </div>

                        <div class="wizard-step-panel" data-step="issue">
                <div class="form-section-card">
                    <h4>Issue Details</h4>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Quantity *</label>
                            <input type="number" name="quantity" id="stockOutQty" min="1" required placeholder="0" oninput="updateStockOutPreview()">
                            <small class="form-note" id="stockOutLimit">Select an item to see available stock.</small>
                        </div>
                        <div class="form-group">
                            <label>Issued To</label>
                            <input type="text" name="issued_to" id="stockOutIssuedTo" class="form-control" placeholder="Person, department, or unit" oninput="updateStockOutPreview()">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Purpose</label>
                        <input type="text" name="purpose" id="stockOutPurpose" class="form-control" placeholder="e.g. Production use, field work, maintenance" oninput="updateStockOutPreview()">
                    </div>
                </div>
                        </div>

                        <div class="wizard-step-panel" data-step="reference">
                <div class="form-section-card">
                    <h4>Reference</h4>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Issue Reference</label>
                            <input type="text" name="reference_number" id="stockOutReference" class="form-control" placeholder="e.g. ISS-2026-010" oninput="updateStockOutPreview()">
                        </div>
                        <div class="form-group">
                            <label>Transaction Date</label>
                            <input type="date" name="transaction_date" id="stockOutDate" class="form-control" value="<?= date('Y-m-d') ?>" required oninput="updateStockOutPreview()">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="remarks" id="stockOutRemarks" rows="3" class="form-control" placeholder="Additional issue notes"></textarea>
                    </div>
                </div>
                        </div>

                        <div class="wizard-step-panel" data-step="review">
                            <div class="form-section-card">
                                <h4>Review Stock Out</h4>
                                <p>Confirm the item, issue quantity and remaining stock before recording.</p>
                                <div class="review-checklist stock-review-list">
                                    <div class="review-item"><span>Product</span><strong id="outReviewProduct">Select product</strong></div>
                                    <div class="review-item"><span>Quantity issued</span><strong id="outReviewQty">0</strong></div>
                                    <div class="review-item"><span>Stock after issue</span><strong id="outReviewAfter">-</strong></div>
                                    <div class="review-item"><span>Issued to</span><strong id="outReviewIssuedTo">-</strong></div>
                                    <div class="review-item"><span>Transaction date</span><strong id="outReviewDate"><?= date('Y-m-d') ?></strong></div>
                                    <div class="review-item"><span>Total value</span><strong id="outReviewTotal">-</strong></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="stock-wizard-footer">
                        <button type="button" class="btn btn-outline-secondary" id="stockOutWizardBack">
                            <i class="fa-solid fa-arrow-left me-1"></i> Back
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="stockOutWizardSaveDraft">
                            <i class="fa-regular fa-floppy-disk me-1"></i> Save Draft
                        </button>
                        <button type="button" class="btn btn-primary" id="stockOutWizardNext">
                            Save & Continue <i class="fa-solid fa-arrow-right ms-1"></i>
                        </button>
                        <button type="submit" class="btn btn-primary" id="stockOutSubmit">
                            <i class="fa-solid fa-check me-2"></i> Record Stock Out
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <aside class="stock-side-panel">
        <div class="card">
            <div class="card-header"><h3>Issue Preview</h3></div>
            <div class="card-body preview-card">
                <div class="preview-header">
                    <div>
                        <h4 id="outPreviewName">Select an item</h4>
                        <p id="outPreviewCode" class="text-muted">SKU: -</p>
                    </div>
                </div>
                <div class="preview-grid">
                    <div><strong>Available</strong><span id="outAvailable">-</span></div>
                    <div><strong>After issue</strong><span id="outAfter">-</span></div>
                    <div><strong>Minimum</strong><span id="outMinimum">-</span></div>
                    <div><strong>Quantity out</strong><span id="outQtyPreview">0</span></div>
                    <div><strong>Unit value</strong><span id="outUnitValue">-</span></div>
                    <div><strong>Total value</strong><span id="outTotalValue">-</span></div>
                    <div><strong>Branch</strong><span id="outBranch">-</span></div>
                    <div><strong>Status</strong><span id="outStatus">-</span></div>
                </div>
            </div>
        </div>

        <div class="card recent-stock-card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h3>Recent Stock Out</h3>
                <a href="transactions.php?type=stock_out" class="card-link">View all</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table" id="recentOutTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Issued By</th>
                                <th>Qty</th>
                                <th>Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="recentOutTableBody"></tbody>
                    </table>
                </div>
                <div class="pagination-bar recent-pagination-bar">
                    <nav class="pagination-nav pagination-nav-left" aria-label="Recent stock out pages">
                        <button type="button" class="pagination-link pagination-direction" id="recentOutPrevPage" onclick="changeOutPage(-1)">Prev</button>
                        <span id="recentOutPageLinks" class="recent-page-links"></span>
                    </nav>
                    <div class="pagination-summary" id="recentOutPaginationInfo">Page 1 of 1</div>
                    <nav class="pagination-nav pagination-nav-right" aria-label="Next recent stock out page">
                        <button type="button" class="pagination-link pagination-direction" id="recentOutNextPage" onclick="changeOutPage(1)">Next</button>
                    </nav>
                </div>
            </div>
        </div>
    </aside>
</div>

<script>
const stockOutItems = <?= json_encode(array_values($items)) ?>;
const recentOutData = <?= json_encode(array_values($recent)) ?>;
let selectedStockOutItem = null;
let filteredRecentOut = [...recentOutData];
let recentOutPage = 1;
const recentOutPageSize = 4;
const stockOutSteps = ['product', 'issue', 'reference', 'review'];
let currentStockOutStep = 'product';

function formatOutCurrency(value) {
    if (value === null || value === undefined || isNaN(value)) return '-';
    return 'UGX ' + Number(value).toLocaleString('en-US', { maximumFractionDigits: 0 });
}

function findStockOutItem(value) {
    if (!value) return null;
    const normalized = value.trim().toLowerCase();
    let item = stockOutItems.find(row => `${row.item_code} - ${row.name}`.toLowerCase() === normalized);
    if (!item) item = stockOutItems.find(row => row.item_code.toLowerCase() === normalized || row.name.toLowerCase() === normalized);
    if (!item) {
        const code = normalized.split(' - ')[0];
        item = stockOutItems.find(row => row.item_code.toLowerCase() === code);
    }
    return item || null;
}

function setStockOutItem(item) {
    const qty = document.getElementById('stockOutQty');
    if (!item) {
        selectedStockOutItem = null;
        document.getElementById('stockOutItemId').value = '';
        qty.removeAttribute('max');
        document.getElementById('stockOutLimit').textContent = 'Select an item to see available stock.';
        updateStockOutPreview();
        return;
    }
    selectedStockOutItem = item;
    const displayUnit = item.display_unit || item.unit || 'EA';
    document.getElementById('stockOutItemId').value = item.id;
    document.getElementById('stockOutProductSearch').value = `${item.item_code} - ${item.name}`;
    document.getElementById('stockOutBarcode').value = item.item_code || '';
    document.getElementById('stockOutSku').value = item.item_code || '';
    document.getElementById('stockOutCategory').value = item.category_name || '';
    document.getElementById('stockOutAvailableField').value = `${item.current_stock || 0} ${displayUnit}`;
    document.getElementById('stockOutUnitField').value = displayUnit;
    qty.max = item.current_stock || '';
    document.getElementById('stockOutLimit').textContent = `${item.current_stock || 0} ${displayUnit} available`;
    updateStockOutPreview();
}

function onStockOutProductSearch(value) {
    const item = findStockOutItem(value);
    if (item) setStockOutItem(item);
}

function onStockOutBarcode(value) {
    const item = findStockOutItem(value);
    if (item) {
        setStockOutItem(item);
    } else if (value.trim()) {
        Swal.fire({ icon: 'warning', title: 'Product not found', text: 'No available item matches that SKU.', toast: true, position: 'top-end', timer: 2600, showConfirmButton: false });
    }
}

function updateStockOutPreview() {
    const item = selectedStockOutItem;
    const qty = parseInt(document.getElementById('stockOutQty').value, 10) || 0;
    if (!item) {
        ['outPreviewName','outPreviewCode','outAvailable','outAfter','outMinimum','outUnitValue','outTotalValue','outBranch','outStatus'].forEach(id => {
            document.getElementById(id).textContent = id === 'outPreviewName' ? 'Select an item' : '-';
        });
        document.getElementById('outQtyPreview').textContent = '0';
        updateStockOutReview();
        return;
    }

    const stock = parseInt(item.current_stock, 10) || 0;
    const min = parseInt(item.minimum_stock, 10) || 0;
    const price = parseFloat(item.unit_price) || 0;
    const after = stock - qty;
    const displayUnit = item.display_unit || item.unit || 'EA';
    document.getElementById('outPreviewName').textContent = item.name || 'Selected item';
    document.getElementById('outPreviewCode').textContent = `SKU: ${item.item_code || '-'}`;
    document.getElementById('outAvailable').textContent = `${stock} ${displayUnit}`;
    document.getElementById('outAfter').textContent = qty ? `${after} ${displayUnit}` : '-';
    document.getElementById('outMinimum').textContent = `${min} ${displayUnit}`;
    document.getElementById('outQtyPreview').textContent = qty ? `${qty} ${displayUnit}` : `0 ${displayUnit}`;
    document.getElementById('outUnitValue').textContent = formatOutCurrency(price);
    document.getElementById('outTotalValue').textContent = qty ? formatOutCurrency(price * qty) : '-';
    document.getElementById('outBranch').textContent = item.branch_name || '-';
    document.getElementById('outStatus').textContent = qty > stock ? 'Insufficient' : (qty ? 'Ready' : '-');
    document.getElementById('stockOutSubmit').disabled = qty > stock;
    document.getElementById('stockOutLimit').textContent = qty > stock
        ? `Only ${stock} ${displayUnit} available`
        : `${stock} ${displayUnit} available`;
    updateStockOutReview();
}

function updateStockOutReview() {
    const qty = parseInt(document.getElementById('stockOutQty').value, 10) || 0;
    const stock = selectedStockOutItem ? (parseInt(selectedStockOutItem.current_stock, 10) || 0) : null;
    const price = selectedStockOutItem ? (parseFloat(selectedStockOutItem.unit_price) || 0) : 0;
    const displayUnit = selectedStockOutItem ? (selectedStockOutItem.display_unit || selectedStockOutItem.unit || 'EA') : 'EA';
    document.getElementById('outReviewProduct').textContent = selectedStockOutItem ? `${selectedStockOutItem.item_code} - ${selectedStockOutItem.name}` : 'Select product';
    document.getElementById('outReviewQty').textContent = qty ? `${qty} ${displayUnit}` : `0 ${displayUnit}`;
    document.getElementById('outReviewAfter').textContent = stock === null || !qty ? '-' : `${stock - qty} ${displayUnit}`;
    document.getElementById('outReviewIssuedTo').textContent = document.getElementById('stockOutIssuedTo').value || '-';
    document.getElementById('outReviewDate').textContent = document.getElementById('stockOutDate').value || new Date().toISOString().slice(0, 10);
    document.getElementById('outReviewTotal').textContent = qty ? formatOutCurrency(price * qty) : '-';
}

function showStockOutStep(step) {
    if (!stockOutSteps.includes(step)) return;
    currentStockOutStep = step;
    document.querySelectorAll('#stockOutForm .wizard-step-panel').forEach(panel => panel.classList.toggle('active', panel.dataset.step === step));
    document.querySelectorAll('#stockOutForm .wizard-step-btn').forEach(button => button.classList.toggle('active', button.dataset.stepTarget === step));
    const stepIndex = stockOutSteps.indexOf(step);
    document.getElementById('stockOutWizardBack').disabled = stepIndex === 0;
    document.getElementById('stockOutWizardNext').style.display = step === 'review' ? 'none' : 'inline-flex';
    document.getElementById('stockOutSubmit').style.display = step === 'review' ? 'inline-flex' : 'none';
    updateStockOutReview();
}

function moveStockOutStep(direction) {
    const currentIndex = stockOutSteps.indexOf(currentStockOutStep);
    const nextIndex = Math.min(stockOutSteps.length - 1, Math.max(0, currentIndex + direction));
    showStockOutStep(stockOutSteps[nextIndex]);
}

function buildRecentOutTable() {
    const tbody = document.getElementById('recentOutTableBody');
    const start = (recentOutPage - 1) * recentOutPageSize;
    const visible = filteredRecentOut.slice(start, start + recentOutPageSize);
    if (!visible.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center">No recent stock-out records yet.</td></tr>';
    } else {
        tbody.innerHTML = visible.map(tx => `
            <tr>
                <td>${new Date(tx.transaction_date).toLocaleDateString('en-GB')}</td>
                <td><strong>${tx.item_code}</strong> <small>${tx.item_name}</small></td>
                <td>${tx.issued_by || '-'}</td>
                <td>${tx.quantity} ${tx.display_unit || ''}</td>
                <td>${formatOutCurrency(tx.unit_price * tx.quantity)}</td>
                <td class="table-actions">
                    <button type="button" class="btn btn-outline-secondary btn-sm action-icon-btn action-view" title="View stock-out" aria-label="View stock-out" onclick="viewRecentOut(${tx.id})">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>
                    </button>
                </td>
            </tr>
        `).join('');
    }
    renderRecentOutPagination();
}

function renderRecentOutPagination() {
    const maxPage = Math.max(1, Math.ceil(filteredRecentOut.length / recentOutPageSize));
    recentOutPage = Math.min(maxPage, Math.max(1, recentOutPage));
    const links = document.getElementById('recentOutPageLinks');
    const pageItems = Array.from({ length: maxPage }, (_, index) => index + 1);
    links.innerHTML = pageItems.map(page => {
        return `<button type="button" class="pagination-link ${page === recentOutPage ? 'active' : ''}" onclick="goRecentOutPage(${page})">${page}</button>`;
    }).join('');
    document.getElementById('recentOutPaginationInfo').textContent = `Page ${recentOutPage} of ${maxPage}`;
    document.getElementById('recentOutPrevPage').classList.toggle('disabled', recentOutPage <= 1);
    document.getElementById('recentOutPrevPage').disabled = recentOutPage <= 1;
    document.getElementById('recentOutNextPage').classList.toggle('disabled', recentOutPage >= maxPage);
    document.getElementById('recentOutNextPage').disabled = recentOutPage >= maxPage;
}

function changeOutPage(direction) {
    const maxPage = Math.max(1, Math.ceil(filteredRecentOut.length / recentOutPageSize));
    recentOutPage = Math.min(maxPage, Math.max(1, recentOutPage + direction));
    buildRecentOutTable();
}

function goRecentOutPage(page) {
    const maxPage = Math.max(1, Math.ceil(filteredRecentOut.length / recentOutPageSize));
    recentOutPage = Math.min(maxPage, Math.max(1, page));
    buildRecentOutTable();
}

function viewRecentOut(id) {
    const tx = recentOutData.find(row => row.id === id);
    if (!tx) return;
    Swal.fire({
        title: 'Stock-Out Details',
        html: `
            <strong>Product:</strong> ${tx.item_code} - ${tx.item_name}<br>
            <strong>Quantity:</strong> ${tx.quantity} ${tx.display_unit || ''}<br>
            <strong>Total Value:</strong> ${formatOutCurrency(tx.unit_price * tx.quantity)}<br>
            <strong>Issued By:</strong> ${tx.issued_by || '-'}<br>
            <strong>Branch:</strong> ${tx.branch_name || '-'}<br>
            <strong>Date:</strong> ${new Date(tx.transaction_date).toLocaleDateString('en-GB')}<br>
            <strong>Reference:</strong> ${tx.reference_number || '-'}
        `,
        width: 650,
        confirmButtonText: 'Close'
    });
}

window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#stockOutForm .wizard-step-btn').forEach(button => {
        button.addEventListener('click', () => showStockOutStep(button.dataset.stepTarget));
    });
    document.getElementById('stockOutWizardBack').addEventListener('click', () => moveStockOutStep(-1));
    document.getElementById('stockOutWizardNext').addEventListener('click', () => moveStockOutStep(1));
    document.getElementById('stockOutWizardSaveDraft').addEventListener('click', () => {
        Swal.fire({ icon: 'info', title: 'Draft kept on screen', text: 'Finish the review step when ready to issue stock.', toast: true, position: 'top-end', timer: 2600, showConfirmButton: false });
    });
    ['stockOutQty','stockOutIssuedTo','stockOutPurpose','stockOutReference','stockOutDate'].forEach(id => {
        document.getElementById(id).addEventListener('input', updateStockOutPreview);
    });
    showStockOutStep('product');
    buildRecentOutTable();
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
