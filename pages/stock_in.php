<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole('Administrator', 'Store Manager', 'Staff');
$pageTitle = 'Stock In'; $activePage = 'stock_in';
$user = currentUser(); $branchId = $user['branch_id'];
$isAdmin = hasRole('Administrator'); $pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (isset($_POST['delete_transaction_id'])) {
        $deleteId = (int)$_POST['delete_transaction_id'];
        $stmt = $pdo->prepare("SELECT item_id, quantity, branch_id FROM stock_transactions WHERE id = ? AND transaction_type = 'stock_in'");
        $stmt->execute([$deleteId]);
        $tx = $stmt->fetch();

        if (!$tx) {
            setFlash('error', 'Stock-in transaction not found.');
        } elseif (!$isAdmin && (int)$tx['branch_id'] !== (int)$branchId) {
            setFlash('error', 'You cannot delete stock-in records outside your branch.');
        } else {
            $stockStmt = $pdo->prepare("SELECT current_stock FROM inventory_items WHERE id = ?");
            $stockStmt->execute([$tx['item_id']]);
            $currentStock = (int)$stockStmt->fetchColumn();
            if ($currentStock < $tx['quantity']) {
                setFlash('error', 'Cannot delete transaction because current stock is insufficient.');
            } else {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("DELETE FROM stock_transactions WHERE id = ?")->execute([$deleteId]);
                    $pdo->prepare("UPDATE inventory_items SET current_stock=current_stock-? WHERE id=?")->execute([$tx['quantity'], $tx['item_id']]);
                    $pdo->commit();
                    setFlash('success', 'Stock-in transaction deleted successfully.');
                } catch (Exception $e) {
                    $pdo->rollBack();
                    setFlash('error', 'Failed to delete stock-in transaction.');
                }
            }
        }
        header('Location: stock_in.php');
        exit;
    }

    $transactionId = (int)($_POST['transaction_id'] ?? 0);

    $itemId = (int)($_POST['item_id'] ?? 0);
    $qty = (int)($_POST['quantity'] ?? 0);
    $unitPrice = (float)($_POST['unit_price'] ?? 0);
    $sellingPrice = (float)($_POST['selling_price'] ?? 0);
    $supplierId = (int)($_POST['supplier_id'] ?? 0) ?: null;
    $referenceNumber = trim($_POST['reference_number'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $transactionDate = $_POST['transaction_date'] ?? date('Y-m-d');
    $batchNumber = trim($_POST['batch_number'] ?? '');
    $serialNumber = trim($_POST['serial_number'] ?? '');
    $manufacturingDate = $_POST['manufacturing_date'] ?? '';
    $expiryDate = $_POST['expiry_date'] ?? '';
    $warehouse = trim($_POST['warehouse'] ?? '');
    $shelfLocation = trim($_POST['shelf_location'] ?? '');
    $purchaseOrderNumber = trim($_POST['purchase_order_number'] ?? '');
    $invoiceNumber = trim($_POST['invoice_number'] ?? '');
    $currency = trim($_POST['currency'] ?? 'UGX');
    $taxPercent = max(0, min(100, (float)($_POST['tax'] ?? 0)));
    $discountPercent = max(0, min(100, (float)($_POST['discount'] ?? 0)));

    $errors = [];
    if (!$itemId) { $errors[] = 'Please select a product.'; }
    if ($qty <= 0) { $errors[] = 'Quantity must be greater than zero.'; }
    if ($unitPrice < 0) { $errors[] = 'Unit cost must be zero or greater.'; }
    if ($sellingPrice < 0) { $errors[] = 'Selling price must be zero or greater.'; }

    if ($errors) {
        setFlash('error', implode(' ', $errors));
    } else {
        $pdo->beginTransaction();
        try {
            $itemStmt = $pdo->prepare("SELECT id, branch_id, current_stock, name FROM inventory_items WHERE id = ? AND is_active = 1 FOR UPDATE");
            $itemStmt->execute([$itemId]);
            $item = $itemStmt->fetch();
            if (!$item) {
                throw new Exception('Selected product was not found.');
            }
            if (!$isAdmin && (int)$item['branch_id'] !== (int)$branchId) {
                throw new Exception('Selected product is outside your active branch.');
            }
            $txBranch = (int)$item['branch_id'];

            if ($supplierId) {
                $pdo->prepare("UPDATE inventory_items SET supplier_id = ? WHERE id = ?")->execute([$supplierId, $itemId]);
            }

            if ($transactionId) {
                $existingStmt = $pdo->prepare("SELECT * FROM stock_transactions WHERE id = ? AND transaction_type = 'stock_in' FOR UPDATE");
                $existingStmt->execute([$transactionId]);
                $existing = $existingStmt->fetch();
                if (!$existing) {
                    throw new Exception('Stock-in record not found.');
                }
                if (!$isAdmin && (int)$existing['branch_id'] !== (int)$branchId) {
                    throw new Exception('You cannot edit stock-in records outside your branch.');
                }

                $oldItemId = (int)$existing['item_id'];
                $oldQty = (int)$existing['quantity'];
                $stockDelta = $qty - $oldQty;

                if ($oldItemId === $itemId) {
                    if ($stockDelta < 0) {
                        $currentStock = (int)$item['current_stock'];
                        if ($currentStock + $stockDelta < 0) {
                            throw new Exception('Cannot reduce quantity: insufficient current stock.');
                        }
                    }
                    $pdo->prepare("UPDATE inventory_items SET current_stock=current_stock+? WHERE id=?")->execute([$stockDelta, $itemId]);
                } else {
                    $oldItemStmt = $pdo->prepare("SELECT current_stock FROM inventory_items WHERE id = ? FOR UPDATE");
                    $oldItemStmt->execute([$oldItemId]);
                    $currentOldStock = (int)$oldItemStmt->fetchColumn();
                    if ($currentOldStock < $oldQty) {
                        throw new Exception('Cannot change item: original stock cannot be removed safely.');
                    }
                    $pdo->prepare("UPDATE inventory_items SET current_stock=current_stock-? WHERE id=?")->execute([$oldQty, $oldItemId]);
                    $pdo->prepare("UPDATE inventory_items SET current_stock=current_stock+? WHERE id=?")->execute([$qty, $itemId]);
                }

                $computedTotal = round($qty * $unitPrice, 2);
                $taxAmount = round($computedTotal * $taxPercent / 100, 2);
                $discountAmount = round($computedTotal * $discountPercent / 100, 2);
                $grandTotal = round($computedTotal + $taxAmount - $discountAmount, 2);

                $details = [];
                if ($batchNumber) { $details[] = "Batch: $batchNumber"; }
                if ($serialNumber) { $details[] = "Serial: $serialNumber"; }
                if ($manufacturingDate) { $details[] = "Mfg: $manufacturingDate"; }
                if ($expiryDate) { $details[] = "Expiry: $expiryDate"; }
                if ($warehouse) { $details[] = "Warehouse: $warehouse"; }
                if ($shelfLocation) { $details[] = "Shelf: $shelfLocation"; }
                if ($purchaseOrderNumber) { $details[] = "PO#: $purchaseOrderNumber"; }
                if ($invoiceNumber) { $details[] = "Invoice: $invoiceNumber"; }
                if ($currency && strtoupper($currency) !== 'UGX') { $details[] = "Currency: $currency"; }
                if ($taxPercent) { $details[] = "Tax: {$taxPercent}%"; }
                if ($discountPercent) { $details[] = "Discount: {$discountPercent}%"; }
                if ($sellingPrice) { $details[] = "Selling Price: UGX " . number_format($sellingPrice, 2); }
                if ($grandTotal) { $details[] = "Total Cost: UGX " . number_format($grandTotal, 2); }

                if ($details) {
                    $remarks = trim($remarks . ($remarks ? ' | ' : '') . implode(' | ', $details));
                }

                $pdo->prepare("UPDATE stock_transactions SET item_id = ?, branch_id = ?, quantity = ?, unit_price = ?, reference_number = ?, remarks = ?, transaction_date = ? WHERE id = ?")
                    ->execute([$itemId, $txBranch, $qty, $unitPrice, $referenceNumber, $remarks, $transactionDate, $transactionId]);
                $pdo->commit();
                auditLog('EDIT_STOCK_IN', 'stock_transactions', $transactionId, "Updated stock in: $qty x {$item['name']}");
                setFlash('success', 'Stock-in transaction updated successfully.');
            } else {
                $computedTotal = round($qty * $unitPrice, 2);
                $taxAmount = round($computedTotal * $taxPercent / 100, 2);
                $discountAmount = round($computedTotal * $discountPercent / 100, 2);
                $grandTotal = round($computedTotal + $taxAmount - $discountAmount, 2);

                $details = [];
                if ($batchNumber) { $details[] = "Batch: $batchNumber"; }
                if ($serialNumber) { $details[] = "Serial: $serialNumber"; }
                if ($manufacturingDate) { $details[] = "Mfg: $manufacturingDate"; }
                if ($expiryDate) { $details[] = "Expiry: $expiryDate"; }
                if ($warehouse) { $details[] = "Warehouse: $warehouse"; }
                if ($shelfLocation) { $details[] = "Shelf: $shelfLocation"; }
                if ($purchaseOrderNumber) { $details[] = "PO#: $purchaseOrderNumber"; }
                if ($invoiceNumber) { $details[] = "Invoice: $invoiceNumber"; }
                if ($currency && strtoupper($currency) !== 'UGX') { $details[] = "Currency: $currency"; }
                if ($taxPercent) { $details[] = "Tax: {$taxPercent}%"; }
                if ($discountPercent) { $details[] = "Discount: {$discountPercent}%"; }
                if ($sellingPrice) { $details[] = "Selling Price: UGX " . number_format($sellingPrice, 2); }
                if ($grandTotal) { $details[] = "Total Cost: UGX " . number_format($grandTotal, 2); }

                if ($details) {
                    $remarks = trim($remarks . ($remarks ? ' | ' : '') . implode(' | ', $details));
                }

                $pdo->prepare("INSERT INTO stock_transactions (item_id,branch_id,user_id,transaction_type,quantity,unit_price,reference_number,remarks,transaction_date) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([$itemId, $txBranch, $user['id'], 'stock_in', $qty, $unitPrice, $referenceNumber, $remarks, $transactionDate]);
                $newTransactionId = (int)$pdo->lastInsertId();
                $pdo->prepare("UPDATE inventory_items SET current_stock=current_stock+? WHERE id=?")->execute([$qty, $itemId]);
                $pdo->commit();
                auditLog('STOCK_IN', 'stock_transactions', $newTransactionId, "Stock in: $qty x {$item['name']}");
                setFlash('success', "Stock in recorded — $qty unit(s) added.");
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('error', $e->getMessage() ?: 'Transaction failed.');
        }
    }
    header('Location: stock_in.php');
    exit;
}

$branchFilter = $isAdmin ? (int)($_GET['branch'] ?? 0) : $branchId;
$bWhere = $isAdmin ? ($branchFilter ? "AND i.branch_id=$branchFilter" : '') : "AND i.branch_id=$branchId";
$items = $pdo->query("SELECT i.id,i.name,i.item_code,i.unit,i.unit_price,i.current_stock,i.minimum_stock,i.purchase_date,i.image,i.asset_type,i.branch_id,i.supplier_id,c.name AS category_name,s.company_name AS supplier_name,s.phone AS supplier_phone,s.email AS supplier_email,s.address AS supplier_address,b.name AS branch_name,b.location AS branch_location FROM inventory_items i JOIN categories c ON i.category_id=c.id LEFT JOIN suppliers s ON i.supplier_id=s.id JOIN branches b ON i.branch_id=b.id WHERE i.is_active=1 $bWhere ORDER BY i.name")->fetchAll();
$branches = $pdo->query("SELECT * FROM branches ORDER BY is_headquarters DESC")->fetchAll();
$suppliers = $pdo->query("SELECT * FROM suppliers WHERE is_active=1 ORDER BY company_name")->fetchAll();
$tWhere = $isAdmin ? ($branchFilter ? "AND t.branch_id=$branchFilter" : '') : "AND t.branch_id=$branchId";
$recent = $pdo->query("SELECT t.*, i.name AS item_name, i.item_code, i.supplier_id, b.name AS branch_name, u.full_name AS received_by, s.company_name AS supplier_name FROM stock_transactions t JOIN inventory_items i ON t.item_id=i.id JOIN branches b ON t.branch_id=b.id JOIN users u ON t.user_id=u.id LEFT JOIN suppliers s ON i.supplier_id=s.id WHERE t.transaction_type='stock_in' $tWhere ORDER BY t.transaction_date DESC, t.created_at DESC LIMIT 8")->fetchAll();
$itemStatsRaw = $pdo->query("SELECT item_id, MAX(transaction_date) AS last_stock_in_date, AVG(unit_price) AS avg_purchase_cost FROM stock_transactions WHERE transaction_type='stock_in' GROUP BY item_id")->fetchAll(PDO::FETCH_ASSOC);
$itemStats = [];
foreach ($itemStatsRaw as $stat) {
    $itemStats[$stat['item_id']] = $stat;
}
$lastPurchasesRaw = $pdo->query("SELECT item_id, unit_price AS last_purchase_price, transaction_date AS last_stock_in_date FROM stock_transactions WHERE transaction_type='stock_in' ORDER BY transaction_date DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
$latestPurchase = [];
foreach ($lastPurchasesRaw as $row) {
    if (!isset($latestPurchase[$row['item_id']])) {
        $latestPurchase[$row['item_id']] = $row;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-start" data-aos="fade-down">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-box-circle-check"></i> Stock In</h1>
        <p class="page-sub">Record received inventory with product, supplier, purchase and warehouse details.</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <button type="button" class="btn btn-outline-secondary" onclick="printRecentStock()">
            <i class="fa-solid fa-print me-1"></i> Print
        </button>
        <button type="button" class="btn btn-outline-secondary" onclick="exportRecentStock()">
            <i class="fa-solid fa-file-csv me-1"></i> Export CSV
        </button>
        <button type="button" class="btn btn-primary" onclick="document.getElementById('stockInForm').scrollIntoView({behavior:'smooth'})">
            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> New Stock In
        </button>
    </div>
</div>
<div class="stock-workspace">
    <section class="card stock-form-card" data-aos="fade-right">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h3>Stock In Form</h3>
            <span class="badge badge-primary">Live calculations</span>
        </div>
        <div class="card-body">
            <form method="POST" id="stockInForm">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="item_id" id="itemIdInput" value="">
                <input type="hidden" name="transaction_id" id="transactionIdInput" value="">

                <div class="inventory-wizard stock-in-wizard">
                    <div class="wizard-intro">
                        <div>
                            <div class="wizard-breadcrumb">Stock / Stock In / Receive Inventory</div>
                            <div class="wizard-title-row">
                                <h3>Record Stock In</h3>
                                <div class="wizard-badges">
                                    <span>Live preview</span>
                                    <span>Branch aware</span>
                                </div>
                            </div>
                            <p>Receive inventory in focused steps, then review the stock impact before saving.</p>
                        </div>
                    </div>

                    <div class="wizard-step-nav" aria-label="Stock in steps">
                        <button type="button" class="wizard-step-btn active" data-step-target="product">1. Product</button>
                        <button type="button" class="wizard-step-btn" data-step-target="supplier">2. Supplier</button>
                        <button type="button" class="wizard-step-btn" data-step-target="reference">3. Reference</button>
                        <button type="button" class="wizard-step-btn" data-step-target="stock">4. Stock</button>
                        <button type="button" class="wizard-step-btn" data-step-target="review">5. Review</button>
                    </div>

                    <div class="stock-wizard-panels">
                        <div class="wizard-step-panel active" data-step="product">
                <?php if ($isAdmin): ?>
                <div class="form-group">
                    <label>Branch Filter</label>
                    <select id="branchSelect" class="form-control" onchange="window.location='stock_in.php?branch='+this.value">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $b['id'] == $branchFilter ? 'selected' : '' ?>><?= clean($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="form-section-card">
                    <h4>Product Information</h4>
                    <p>Search by SKU, name or scan a barcode to populate product details automatically.</p>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Product Search</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                                <input list="productList" id="productSearch" class="form-control" placeholder="Search product..." autocomplete="off" oninput="onProductSearch(this.value)">
                            </div>
                            <datalist id="productList">
                                <?php foreach ($items as $item): ?>
                                <option value="<?= clean($item['item_code'] . ' - ' . $item['name']) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="form-group">
                            <label>Barcode Scanner</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-barcode"></i></span>
                                <input type="text" id="barcodeInput" class="form-control" placeholder="Scan or type SKU" onblur="onBarcodeInput(this.value)">
                            </div>
                        </div>
                    </div>

                    <div class="form-grid-2" style="gap:16px;">
                        <div class="form-group"><label>SKU</label><input type="text" id="productSku" class="form-control" readonly></div>
                        <div class="form-group"><label>Category</label><input type="text" id="productCategory" class="form-control" readonly></div>
                    </div>
                    <div class="form-grid-2" style="gap:16px;">
                        <div class="form-group"><label>Brand</label><input type="text" id="productBrand" class="form-control" readonly></div>
                        <div class="form-group"><label>Unit of Measure</label><input type="text" id="productUnit" class="form-control" readonly></div>
                    </div>
                </div>
                        </div>

                        <div class="wizard-step-panel" data-step="supplier">
                <div class="form-section-card">
                    <h4>Supplier Information</h4>
                    <div class="d-flex gap-2 align-items-center mb-3">
                        <select name="supplier_id" id="supplierSelect" class="form-control">
                            <option value="">Choose supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= $supplier['id'] ?>"><?= clean($supplier['company_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.location.href='suppliers.php'">
                            <i class="fa-solid fa-plus"></i> Add New Supplier
                        </button>
                    </div>
                    <div class="supplier-details" id="supplierDetails">
                        <div><strong>Contact:</strong> <span id="supplierContact">–</span></div>
                        <div><strong>Email:</strong> <span id="supplierEmail">–</span></div>
                        <div><strong>Address:</strong> <span id="supplierAddress">–</span></div>
                    </div>
                    <div class="purchase-history mt-3" id="purchaseHistory">
                        <div class="history-title">Recent purchase history</div>
                        <div class="history-list" id="historyList">Select a product to view its last purchases.</div>
                    </div>
                </div>
                        </div>

                        <div class="wizard-step-panel" data-step="reference">
                <div class="form-section-card">
                    <h4>Purchase Reference</h4>
                    <div class="form-grid-2">
                        <div class="form-group"><label>Reference Number</label><input type="text" id="referenceNumber" name="reference_number" placeholder="e.g. PO-2026-001" class="form-control"></div>
                        <div class="form-group"><label>Transaction Date</label><input type="date" id="transactionDate" name="transaction_date" value="<?= date('Y-m-d') ?>" class="form-control"></div>
                    </div>
                    <div class="form-group"><label>Remarks / Internal Notes</label><textarea id="remarks" name="remarks" rows="3" class="form-control" placeholder="Enter additional notes or order details"></textarea></div>
                </div>
                        </div>

                        <div class="wizard-step-panel" data-step="stock">
                <div class="form-section-card">
                    <h4>Stock Details</h4>
                    <div class="form-grid-2">
                        <div class="form-group"><label>Quantity Received</label><input type="number" name="quantity" id="qty" min="1" class="form-control" required></div>
                        <div class="form-group"><label>Unit Cost</label><input type="number" name="unit_price" id="unitPrice" min="0" step="0.01" class="form-control" required></div>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group"><label>Selling Price</label><input type="number" name="selling_price" id="sellingPrice" min="0" step="0.01" class="form-control"></div>
                        <div class="form-group"><label>Batch Number</label><input type="text" name="batch_number" id="batchNumber" class="form-control"></div>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group"><label>Serial Number</label><input type="text" name="serial_number" id="serialNumber" class="form-control"></div>
                        <div class="form-group"><label>Manufacturing Date</label><input type="date" name="manufacturing_date" id="manufacturingDate" class="form-control"></div>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group"><label>Expiry Date</label><input type="date" name="expiry_date" id="expiryDate" class="form-control"></div>
                        <div class="form-group"><label>Warehouse</label><input type="text" name="warehouse" id="warehouse" class="form-control" placeholder="Warehouse name"></div>
                    </div>
                    <div class="form-group"><label>Shelf Location</label><input type="text" name="shelf_location" id="shelfLocation" class="form-control" placeholder="e.g. Shelf B4"></div>
                </div>

                <div class="form-section-card">
                    <h4>Purchase Details</h4>
                    <div class="form-grid-2">
                        <div class="form-group"><label>Purchase Order Number</label><input type="text" name="purchase_order_number" id="purchaseOrderNumber" class="form-control"></div>
                        <div class="form-group"><label>Invoice Number</label><input type="text" name="invoice_number" id="invoiceNumber" class="form-control"></div>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group"><label>Currency</label><select name="currency" id="currencySelect" class="form-control"><option>UGX</option><option>USD</option><option>EUR</option></select></div>
                        <div class="form-group"><label>Tax (%)</label><input type="number" name="tax" id="taxPercent" min="0" max="100" step="0.01" class="form-control"></div>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group"><label>Discount (%)</label><input type="number" name="discount" id="discountPercent" min="0" max="100" step="0.01" class="form-control"></div>
                        <div class="form-group"><label>Total Cost</label><div class="computed-field" id="calcTotalCost">UGX 0</div></div>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group"><label>Tax Amount</label><div class="computed-field" id="calcTaxAmount">UGX 0</div></div>
                        <div class="form-group"><label>Discount Amount</label><div class="computed-field" id="calcDiscountAmount">UGX 0</div></div>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group"><label>Grand Total</label><div class="computed-field" id="calcGrandTotal">UGX 0</div></div>
                        <div class="form-group"><label>Expected Revenue</label><div class="computed-field" id="calcExpectedRevenue">UGX 0</div></div>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group"><label>Profit Margin</label><div class="computed-field" id="calcProfitMargin">0%</div></div>
                    </div>
                </div>
                        </div>

                        <div class="wizard-step-panel" data-step="review">
                            <div class="form-section-card">
                                <h4>Review Stock In</h4>
                                <p>Confirm the received item, quantity and projected stock before recording.</p>
                                <div class="review-checklist stock-review-list">
                                    <div class="review-item"><span>Product</span><strong id="reviewProduct">Select product</strong></div>
                                    <div class="review-item"><span>Supplier</span><strong id="reviewSupplier">Select supplier</strong></div>
                                    <div class="review-item"><span>Quantity received</span><strong id="reviewQty">0</strong></div>
                                    <div class="review-item"><span>Projected stock</span><strong id="reviewProjectedStock">-</strong></div>
                                    <div class="review-item"><span>Transaction date</span><strong id="reviewDate"><?= date('Y-m-d') ?></strong></div>
                                    <div class="review-item"><span>Grand total</span><strong id="reviewGrandTotal">UGX 0</strong></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="stock-wizard-footer">
                        <button type="button" class="btn btn-outline-secondary" id="stockWizardBack">
                            <i class="fa-solid fa-arrow-left me-1"></i> Back
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="stockWizardSaveDraft">
                            <i class="fa-regular fa-floppy-disk me-1"></i> Save Draft
                        </button>
                        <button type="button" class="btn btn-primary" id="stockWizardNext">
                            Save & Continue <i class="fa-solid fa-arrow-right ms-1"></i>
                        </button>
                        <button type="submit" class="btn btn-primary" id="stockInSubmitButton">
                            <i class="fa-solid fa-check me-2"></i> Record Stock In
                        </button>
                    </div>
                </div>
            </form>
            <form id="deleteForm" method="POST" style="display:none">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="delete_transaction_id" id="deleteTransactionIdInput" value="">
            </form>
        </div>
    </section>

    <aside class="stock-side-panel">
        <div class="card mb-4" data-aos="fade-left">
            <div class="card-header"><h3>Product Preview</h3></div>
            <div class="card-body preview-card" id="productPreview">
                <div class="preview-header">
                    <img id="previewImage" src="https://via.placeholder.com/140?text=No+Image" alt="Product image" onerror="this.onerror=null;this.src='https://via.placeholder.com/140?text=No+Image'">
                    <div>
                        <h4 id="previewName">Select a product</h4>
                        <p id="previewSku" class="text-muted">SKU: —</p>
                    </div>
                </div>
                <div class="preview-grid">
                    <div><strong>Current stock</strong><span id="previewCurrentStock">—</span></div>
                    <div><strong>Min stock</strong><span id="previewMinStock">—</span></div>
                    <div><strong>Quantity in</strong><span id="previewQtyIn">0</span></div>
                    <div><strong>Projected stock</strong><span id="previewProjectedStock">—</span></div>
                    <div><strong>Last purchase</strong><span id="previewLastPurchase">—</span></div>
                    <div><strong>Last supplier</strong><span id="previewLastSupplier">—</span></div>
                    <div><strong>Last stock-in</strong><span id="previewLastStockIn">—</span></div>
                    <div><strong>Avg cost</strong><span id="previewAvgCost">—</span></div>
                    <div><strong>Warehouse</strong><span id="previewWarehouse">—</span></div>
                    <div><strong>Grand total</strong><span id="previewGrandTotal">UGX 0</span></div>
                </div>
            </div>
        </div>

        <div class="card recent-stock-card" data-aos="fade-left">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h3>Recent Stock In</h3>
                <div class="d-flex gap-2">
                    <a href="transactions.php?type=stock_in" class="btn btn-outline-secondary btn-sm">View all</a>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportRecentStock()"><i class="fa-solid fa-file-csv"></i></button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="printRecentStock()"><i class="fa-solid fa-print"></i></button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-filters">
                    <div class="filter-row">
                        <input type="text" id="recentSearch" class="form-control" placeholder="Search recent stock-in..." oninput="updateRecentFilters()">
                        <select id="recentSupplier" class="form-control" onchange="updateRecentFilters()">
                            <option value="">All suppliers</option>
                            <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= clean($supplier['company_name']) ?>"><?= clean($supplier['company_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-row">
                        <select id="recentBranch" class="form-control" onchange="updateRecentFilters()">
                            <option value="">All branches</option>
                            <?php foreach ($branches as $b): ?>
                            <option value="<?= clean($b['name']) ?>"><?= clean($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="date-range">
                            <input type="date" id="recentDateFrom" class="form-control" onchange="updateRecentFilters()">
                            <input type="date" id="recentDateTo" class="form-control" onchange="updateRecentFilters()">
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="data-table" id="recentTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Supplier</th>
                                <th>Qty</th>
                                <th>Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="recentTableBody"></tbody>
                    </table>
                </div>
                <div class="pagination-bar recent-pagination-bar">
                    <nav class="pagination-nav pagination-nav-left" aria-label="Recent stock in pages">
                        <button type="button" class="pagination-link pagination-direction" id="recentPrevPage" onclick="changePage(-1)">&lt;&lt;Previous</button>
                        <span id="recentPageLinks" class="recent-page-links"></span>
                    </nav>
                    <div class="pagination-summary" id="paginationInfo">Page 1 of 1</div>
                    <nav class="pagination-nav pagination-nav-right" aria-label="Next recent stock in page">
                        <button type="button" class="pagination-link pagination-direction" id="recentNextPage" onclick="changePage(1)">Next&gt;&gt;</button>
                    </nav>
                </div>
            </div>
        </div>
    </aside>
</div>

<script>
const itemsData = <?= json_encode(array_values($items)) ?>;
const recentData = <?= json_encode(array_values($recent)) ?>;
const itemStats = <?= json_encode($itemStats) ?>;
const latestPurchase = <?= json_encode($latestPurchase) ?>;
let filteredRecent = [...recentData];
let recentPage = 1;
const pageSize = 1;
const stockSteps = ['product', 'supplier', 'reference', 'stock', 'review'];
let currentStockStep = 'product';
let selectedStockInItem = null;

function formatCurrency(value, symbol = 'UGX') {
    if (value === null || value === undefined || isNaN(value)) return '—';
    return symbol + ' ' + Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function setProductPreview(item) {
    if (!item) return;
    selectedStockInItem = item;
    document.getElementById('productSku').value = item.item_code || '';
    document.getElementById('productCategory').value = item.category_name || '';
    document.getElementById('productBrand').value = item.asset_type || 'N/A';
    document.getElementById('productUnit').value = item.unit || '';
    document.getElementById('itemIdInput').value = item.id;
    document.getElementById('productSearch').value = `${item.item_code} - ${item.name}`;
    document.getElementById('barcodeInput').value = item.item_code || '';
    document.getElementById('unitPrice').value = item.unit_price || 0;
    document.getElementById('supplierSelect').value = item.supplier_id || '';
    document.getElementById('supplierContact').textContent = item.supplier_phone || '—';
    document.getElementById('supplierEmail').textContent = item.supplier_email || '—';
    document.getElementById('supplierAddress').textContent = item.supplier_address || '—';

    document.getElementById('previewImage').src = item.image || 'https://via.placeholder.com/140?text=No+Image';
    document.getElementById('previewName').textContent = item.name || 'Selected product';
    document.getElementById('previewSku').textContent = `SKU: ${item.item_code || '—'}`;
    document.getElementById('previewCurrentStock').textContent = item.current_stock !== null ? item.current_stock : '—';
    document.getElementById('previewMinStock').textContent = item.minimum_stock !== null ? item.minimum_stock : '—';
    document.getElementById('previewWarehouse').textContent = item.branch_location || item.branch_name || '—';

    const stats = itemStats[item.id] || {};
    const latest = latestPurchase[item.id] || {};
    document.getElementById('previewAvgCost').textContent = stats.avg_purchase_cost ? formatCurrency(stats.avg_purchase_cost) : '—';
    document.getElementById('previewLastPurchase').textContent = latest.last_purchase_price ? formatCurrency(latest.last_purchase_price) : '—';
    document.getElementById('previewLastSupplier').textContent = item.supplier_name || '—';
    document.getElementById('previewLastStockIn').textContent = latest.last_stock_in_date || stats.last_stock_in_date || '—';

    renderPurchaseHistory(item.id);
    calculateTotals();
}

function findItemByInput(value) {
    if (!value) return null;
    const normalized = value.trim().toLowerCase();
    let item = itemsData.find(i => `${i.item_code} - ${i.name}`.toLowerCase() === normalized);
    if (!item) item = itemsData.find(i => i.item_code.toLowerCase() === normalized || i.name.toLowerCase() === normalized);
    if (!item) {
        const code = normalized.split(' - ')[0];
        item = itemsData.find(i => i.item_code.toLowerCase() === code);
    }
    return item || null;
}

function onProductSearch(value) {
    const item = findItemByInput(value);
    if (item) setProductPreview(item);
}

function onBarcodeInput(value) {
    const item = itemsData.find(i => i.item_code.toLowerCase() === value.trim().toLowerCase());
    if (item) {
        setProductPreview(item);
    } else if (value.trim()) {
        Swal.fire({ icon: 'warning', title: 'Product not found', text: 'No item matches the scanned barcode.', toast: true, position: 'top-end', timer: 2800, showConfirmButton: false });
    }
}

function calculateTotals() {
    const qty = parseFloat(document.getElementById('qty').value) || 0;
    const cost = parseFloat(document.getElementById('unitPrice').value) || 0;
    const sell = parseFloat(document.getElementById('sellingPrice').value) || 0;
    const tax = parseFloat(document.getElementById('taxPercent').value) || 0;
    const discount = parseFloat(document.getElementById('discountPercent').value) || 0;

    const totalCost = qty * cost;
    const taxAmount = totalCost * (tax / 100);
    const discountAmount = totalCost * (discount / 100);
    const grandTotal = totalCost + taxAmount - discountAmount;
    const expectedRevenue = qty * sell;
    const margin = cost > 0 ? ((sell - cost) / cost) * 100 : 0;
    const currentStock = selectedStockInItem ? (parseInt(selectedStockInItem.current_stock, 10) || 0) : null;
    const projectedStock = currentStock === null ? null : currentStock + qty;

    document.getElementById('calcTotalCost').textContent = formatCurrency(totalCost);
    document.getElementById('calcTaxAmount').textContent = formatCurrency(taxAmount);
    document.getElementById('calcDiscountAmount').textContent = formatCurrency(discountAmount);
    document.getElementById('calcGrandTotal').textContent = formatCurrency(grandTotal);
    document.getElementById('calcExpectedRevenue').textContent = formatCurrency(expectedRevenue);
    document.getElementById('calcProfitMargin').textContent = `${margin.toFixed(2)}%`;
    document.getElementById('previewQtyIn').textContent = qty ? qty : '0';
    document.getElementById('previewProjectedStock').textContent = projectedStock === null ? '—' : projectedStock;
    document.getElementById('previewGrandTotal').textContent = formatCurrency(grandTotal);
    updateStockReview(grandTotal, projectedStock);
}

function updateStockReview(grandTotal = null, projectedStock = null) {
    const qty = parseFloat(document.getElementById('qty').value) || 0;
    const supplierSelect = document.getElementById('supplierSelect');
    const selectedSupplier = supplierSelect.options[supplierSelect.selectedIndex];
    const dateValue = document.getElementById('transactionDate').value || new Date().toISOString().slice(0, 10);
    if (projectedStock === null && selectedStockInItem) {
        projectedStock = (parseInt(selectedStockInItem.current_stock, 10) || 0) + qty;
    }
    const totalText = grandTotal === null ? document.getElementById('calcGrandTotal').textContent : formatCurrency(grandTotal);

    document.getElementById('reviewProduct').textContent = selectedStockInItem ? `${selectedStockInItem.item_code} - ${selectedStockInItem.name}` : 'Select product';
    document.getElementById('reviewSupplier').textContent = selectedSupplier && selectedSupplier.value ? selectedSupplier.textContent : 'Select supplier';
    document.getElementById('reviewQty').textContent = qty ? qty : '0';
    document.getElementById('reviewProjectedStock').textContent = projectedStock === null ? '—' : projectedStock;
    document.getElementById('reviewDate').textContent = dateValue;
    document.getElementById('reviewGrandTotal').textContent = totalText;
}

function showStockInStep(step) {
    if (!stockSteps.includes(step)) return;
    currentStockStep = step;
    document.querySelectorAll('#stockInForm .wizard-step-panel').forEach(panel => {
        panel.classList.toggle('active', panel.dataset.step === step);
    });
    document.querySelectorAll('#stockInForm .wizard-step-btn').forEach(button => {
        button.classList.toggle('active', button.dataset.stepTarget === step);
    });
    const stepIndex = stockSteps.indexOf(step);
    document.getElementById('stockWizardBack').disabled = stepIndex === 0;
    document.getElementById('stockWizardNext').style.display = step === 'review' ? 'none' : 'inline-flex';
    document.getElementById('stockInSubmitButton').style.display = step === 'review' ? 'inline-flex' : 'none';
    updateStockReview();
}

function moveStockStep(direction) {
    const currentIndex = stockSteps.indexOf(currentStockStep);
    const nextIndex = Math.min(stockSteps.length - 1, Math.max(0, currentIndex + direction));
    showStockInStep(stockSteps[nextIndex]);
}

function renderPurchaseHistory(itemId) {
    const history = recentData.filter(tx => tx.item_id === itemId).slice(0, 5);
    const container = document.getElementById('historyList');
    if (!history.length) {
        container.innerHTML = '<div class="history-empty">No purchase history available for this product.</div>';
        return;
    }
    container.innerHTML = history.map(tx => `
        <div class="history-item">
            <span>${new Date(tx.transaction_date).toLocaleDateString('en-GB')}</span>
            <span>${tx.reference_number || 'No ref'}</span>
            <span>${formatCurrency(tx.unit_price)}</span>
            <span>${tx.supplier_name || 'Supplier'}</span>
        </div>
    `).join('');
}

function buildRecentTable() {
    const tbody = document.getElementById('recentTableBody');
    const start = (recentPage - 1) * pageSize;
    const visible = filteredRecent.slice(start, start + pageSize);

    if (!visible.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center">No recent stock-in records match your filters.</td></tr>';
    } else {
        tbody.innerHTML = visible.map(tx => `
            <tr>
                <td>${new Date(tx.transaction_date).toLocaleDateString('en-GB')}</td>
                <td><strong>${tx.item_code}</strong><br><small>${tx.item_name}</small></td>
                <td>${tx.supplier_name || '—'}</td>
                <td>${tx.quantity}</td>
                <td>${formatCurrency(tx.unit_price * tx.quantity)}</td>
                <td class="table-actions">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="viewRecent(${tx.id})"><i class="fa-solid fa-eye"></i></button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="editRecent(${tx.id})"><i class="fa-solid fa-pen"></i></button>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteRecent(${tx.id})"><i class="fa-solid fa-trash"></i></button>
                </td>
            </tr>
        `).join('');
    }
    renderRecentPagination();
}

function renderRecentPagination() {
    const maxPage = Math.max(1, Math.ceil(filteredRecent.length / pageSize));
    recentPage = Math.min(maxPage, Math.max(1, recentPage));
    const links = document.getElementById('recentPageLinks');
    links.innerHTML = Array.from({ length: maxPage }, (_, index) => {
        const page = index + 1;
        return `<button type="button" class="pagination-link ${page === recentPage ? 'active' : ''}" onclick="goRecentPage(${page})">${page}</button>`;
    }).join('');
    document.getElementById('paginationInfo').textContent = `Page ${recentPage} of ${maxPage}`;
    document.getElementById('recentPrevPage').classList.toggle('disabled', recentPage <= 1);
    document.getElementById('recentPrevPage').disabled = recentPage <= 1;
    document.getElementById('recentNextPage').classList.toggle('disabled', recentPage >= maxPage);
    document.getElementById('recentNextPage').disabled = recentPage >= maxPage;
}

function updateRecentFilters() {
    const search = document.getElementById('recentSearch').value.trim().toLowerCase();
    const supplier = document.getElementById('recentSupplier').value;
    const branch = document.getElementById('recentBranch').value;
    const from = document.getElementById('recentDateFrom').value;
    const to = document.getElementById('recentDateTo').value;

    filteredRecent = recentData.filter(tx => {
        const matchesSearch = search === '' || [tx.item_name, tx.item_code, tx.supplier_name, tx.reference_number, tx.branch_name, tx.received_by].some(field => field && field.toLowerCase().includes(search));
        const matchesSupplier = !supplier || tx.supplier_name === supplier;
        const matchesBranch = !branch || tx.branch_name === branch;
        const txDate = new Date(tx.transaction_date);
        const afterFrom = !from || txDate >= new Date(from);
        const beforeTo = !to || txDate <= new Date(to);
        return matchesSearch && matchesSupplier && matchesBranch && afterFrom && beforeTo;
    });
    recentPage = 1;
    buildRecentTable();
}

function changePage(direction) {
    const maxPage = Math.max(1, Math.ceil(filteredRecent.length / pageSize));
    recentPage = Math.min(maxPage, Math.max(1, recentPage + direction));
    buildRecentTable();
}

function goRecentPage(page) {
    const maxPage = Math.max(1, Math.ceil(filteredRecent.length / pageSize));
    recentPage = Math.min(maxPage, Math.max(1, page));
    buildRecentTable();
}

function viewRecent(id) {
    const tx = recentData.find(row => row.id === id);
    if (!tx) return;
    Swal.fire({
        title: 'Stock-In Details',
        html: `
            <strong>Product:</strong> ${tx.item_code} - ${tx.item_name}<br>
            <strong>Supplier:</strong> ${tx.supplier_name || '—'}<br>
            <strong>Quantity:</strong> ${tx.quantity}<br>
            <strong>Unit Cost:</strong> ${formatCurrency(tx.unit_price)}<br>
            <strong>Total Cost:</strong> ${formatCurrency(tx.unit_price * tx.quantity)}<br>
            <strong>Warehouse:</strong> ${tx.branch_name || '—'}<br>
            <strong>Received By:</strong> ${tx.received_by || '—'}<br>
            <strong>Date:</strong> ${new Date(tx.transaction_date).toLocaleDateString('en-GB')}<br>
            <strong>Reference:</strong> ${tx.reference_number || '—'}
        `,
        width: 650,
        confirmButtonText: 'Close'
    });
}

function editRecent(id) {
    const tx = recentData.find(row => row.id === id);
    if (!tx) return;
    const item = itemsData.find(row => row.id === tx.item_id);
    if (item) {
        setProductPreview(item);
        document.getElementById('productSearch').value = `${item.item_code} - ${item.name}`;
    }
    document.getElementById('itemIdInput').value = tx.item_id;
    document.getElementById('transactionIdInput').value = tx.id;
    document.getElementById('qty').value = tx.quantity;
    document.getElementById('unitPrice').value = tx.unit_price;
    document.getElementById('sellingPrice').value = tx.selling_price || '';
    document.getElementById('supplierSelect').value = tx.supplier_id || '';
    document.getElementById('referenceNumber').value = tx.reference_number || '';
    document.getElementById('remarks').value = tx.remarks || '';
    document.getElementById('transactionDate').value = tx.transaction_date ? tx.transaction_date.substr(0, 10) : '';
    document.getElementById('stockInSubmitButton').innerHTML = '<i class="fa-solid fa-pen me-2"></i> Update Stock In';
    document.getElementById('stockInForm').scrollIntoView({behavior: 'smooth'});
    calculateTotals();
    showStockInStep('review');
}

function deleteRecent(id) {
    Swal.fire({
        title: 'Delete stock-in',
        text: 'Deleting this stock-in transaction will deduct the received quantity from inventory. Proceed?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel'
    }).then(result => {
        if (result.isConfirmed) {
            document.getElementById('deleteTransactionIdInput').value = id;
            document.getElementById('deleteForm').submit();
        }
    });
}

function exportRecentStock() {
    const header = ['Date','Product','Supplier','Qty','Unit Cost','Total Cost','Warehouse','Received By','Status'];
    const rows = filteredRecent.map(tx => [
        tx.transaction_date,
        `${tx.item_code} - ${tx.item_name}`,
        tx.supplier_name || '',
        tx.quantity,
        tx.unit_price,
        tx.unit_price * tx.quantity,
        tx.branch_name,
        tx.received_by,
        'Received'
    ]);
    const csv = [header, ...rows].map(r => r.map(cell => `"${String(cell).replace(/"/g,'""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'stock_in_history.csv';
    link.click();
    Swal.fire({ icon: 'success', title: 'Export started', toast: true, position: 'top-end', timer: 2400, showConfirmButton: false });
}

function printRecentStock() {
    window.print();
}

window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#stockInForm .wizard-step-btn').forEach(button => {
        button.addEventListener('click', () => showStockInStep(button.dataset.stepTarget));
    });
    document.getElementById('stockWizardBack').addEventListener('click', () => moveStockStep(-1));
    document.getElementById('stockWizardNext').addEventListener('click', () => moveStockStep(1));
    document.getElementById('stockWizardSaveDraft').addEventListener('click', () => {
        Swal.fire({ icon: 'info', title: 'Draft kept on screen', text: 'Finish the review step when you are ready to record stock.', toast: true, position: 'top-end', timer: 2600, showConfirmButton: false });
    });
    document.getElementById('qty').addEventListener('input', calculateTotals);
    document.getElementById('unitPrice').addEventListener('input', calculateTotals);
    document.getElementById('sellingPrice').addEventListener('input', calculateTotals);
    document.getElementById('taxPercent').addEventListener('input', calculateTotals);
    document.getElementById('discountPercent').addEventListener('input', calculateTotals);
    document.getElementById('transactionDate').addEventListener('input', updateStockReview);
    document.getElementById('referenceNumber').addEventListener('input', updateStockReview);
    document.getElementById('remarks').addEventListener('input', updateStockReview);
    document.getElementById('supplierSelect').addEventListener('change', () => {
        const supplierId = parseInt(document.getElementById('supplierSelect').value, 10);
        const selectedSupplier = <?= json_encode(array_column($suppliers, null, 'id')) ?>;
        const supplier = selectedSupplier[supplierId] || {};
        document.getElementById('supplierContact').textContent = supplier.phone || '—';
        document.getElementById('supplierEmail').textContent = supplier.email || '—';
        document.getElementById('supplierAddress').textContent = supplier.address || '—';
        updateStockReview();
    });
    showStockInStep('product');
    calculateTotals();
    buildRecentTable();
});
</script>
<style>
.product-preview-card .preview-header{display:flex;gap:16px;align-items:center;margin-bottom:18px;}
.preview-card img{width:140px;height:140px;object-fit:cover;border-radius:18px;border:1px solid #e2e8f0;}
.preview-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;}
.preview-grid div{background:#f8fbff;padding:12px 14px;border-radius:12px;border:1px solid #e2e8f0;}
.supplier-details div, .history-item{display:flex;justify-content:space-between;gap:10px;padding:.5rem 0;border-bottom:1px solid #eef2f7;}
.history-title{font-weight:700;color:#344054;margin-bottom:10px;}
.history-list{display:grid;gap:8px;}
.history-empty{color:#6b7280;font-size:.9rem;}
.table-filters{display:grid;gap:14px;margin-bottom:14px;}
.filter-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.date-range{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.table-actions button{margin-right:4px;}
.recent-pagination-bar{margin-top:12px;}
@media (max-width: 1024px){.section-grid-2{grid-template-columns:1fr;}.filter-row,.date-range{grid-template-columns:1fr;}}
@media print {body *{visibility:hidden;}#recentTable, #recentTable *,.page-header, .card-header, .card-body, .table-actions, .btn, .table-filters, .pagination-bar{visibility:visible;}#stockInForm, .preview-card, .supplier-details, .history-list{visibility:visible;} .page-header{position:relative;} .card{border:none;box-shadow:none;}} 
</style>
<?php include __DIR__ . '/../includes/footer.php'; ?>
