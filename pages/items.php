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
ensureInventoryDecisionColumns();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $postRedirectParams = [];

    if ($action === 'add' || $action === 'edit') {
        $itemId       = (int)($_POST['item_id'] ?? 0);
        $name         = trim($_POST['name'] ?? '');
        $itemCode     = trim($_POST['item_code'] ?? '');
        $assetCode    = trim($_POST['asset_code'] ?? '');
        $serialNumber = trim($_POST['serial_number'] ?? '');
        $qrCode       = trim($_POST['qr_code'] ?? '');
        $brandModel   = trim($_POST['brand_model'] ?? '');
        $categoryId   = (int)($_POST['category_id'] ?? 0);
        $supplierId   = (int)($_POST['supplier_id'] ?? 0) ?: null;
        $departmentId = (int)($_POST['department_id'] ?? 0) ?: null;
        $unit         = trim($_POST['unit'] ?? 'piece');
        $unitPrice    = (float)($_POST['unit_price'] ?? 0);
        $currentStock = max(0, (int)($_POST['current_stock'] ?? 0));
        $minStock     = (int)($_POST['minimum_stock'] ?? 5);
        $description  = trim($_POST['description'] ?? '');
        $assetType    = trim($_POST['inventory_type'] ?? 'Consumable');
        $purchaseDate = $_POST['purchase_date'] ?: null;
        $warrantyDate = $_POST['warranty_date'] ?: null;
        $assetStatus  = trim($_POST['asset_status'] ?? 'Available');
        $assetCondition = trim($_POST['asset_condition'] ?? 'New');
        $fundingSource = trim($_POST['funding_source'] ?? '');
        $storageLocation = trim($_POST['storage_location'] ?? '');
        $itemBranch   = $isAdmin ? (int)($_POST['branch_id'] ?? $branchId) : $branchId;

        if (!$name || !$categoryId) {
            setFlash('error', 'Item name and category are required.');
        } else {
            $imageName = $_POST['existing_image'] ?? null;
            if (!empty($_FILES['image']['name'])) {
                if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0775, true)) {
                    setFlash('error', 'Could not prepare the item image upload folder.');
                    header('Location: items.php'); exit;
                }
                $allowed = ['jpg','jpeg','png','gif','webp'];
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $isImage = is_uploaded_file($_FILES['image']['tmp_name']) && @getimagesize($_FILES['image']['tmp_name']);
                if (!in_array($ext, $allowed, true) || !$isImage) {
                    setFlash('error', 'Please upload a valid item image: JPG, PNG, GIF, or WEBP.');
                    header('Location: items.php'); exit;
                }
                if (($_FILES['image']['size'] ?? 0) > 5 * 1024 * 1024) {
                    setFlash('error', 'Item image must be 5MB or smaller.');
                    header('Location: items.php'); exit;
                }
                $imageName = 'item_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (!move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . $imageName)) {
                    setFlash('error', 'Could not save the item image. Please try again.');
                    header('Location: items.php'); exit;
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
            if ($serialNumber !== '') {
                $duplicateSerial = $pdo->prepare("SELECT id, name FROM inventory_items WHERE serial_number = ? AND is_active = 1 AND (? = 0 OR id <> ?) LIMIT 1");
                $duplicateSerial->execute([$serialNumber, $itemId, $itemId]);
                if ($duplicateSerial->fetch()) {
                    setFlash('error', 'That serial number is already assigned to another active item.');
                    header('Location: items.php' . ($action === 'edit' ? '?edit=' . $itemId : '?action=add')); exit;
                }
            }
            if ($action === 'add' && $itemCode === '') {
                $itemCode = generateItemCode($categoryId, $itemBranch);
            }

            if ($action === 'add') {
                try {
                    $stmt = $pdo->prepare("INSERT INTO inventory_items (branch_id,section_id,category_id,supplier_id,department_id,item_code,asset_code,serial_number,qr_code,name,brand_model,description,unit,unit_price,current_stock,minimum_stock,asset_type,purchase_date,warranty_date,asset_status,asset_condition,funding_source,storage_location,image,created_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
                    $stmt->execute([$itemBranch,$sectionId,$categoryId,$supplierId,$departmentId,$itemCode,$assetCode,$serialNumber,$qrCode,$name,$brandModel,$description,$unit,$unitPrice,$currentStock,$minStock,$assetType,$purchaseDate,$warrantyDate,$assetStatus,$assetCondition,$fundingSource,$storageLocation,$imageName,$user['id']]);
                    $savedItemId = (int)$pdo->lastInsertId();
                    auditLog('ADD_ITEM','inventory_items',$savedItemId,"Added: $name");
                    $postRedirectParams = ['saved' => $savedItemId];
                    $_SESSION['inventory_feedback'] = [
                        'type' => 'success',
                        'action' => 'added',
                        'item_id' => $savedItemId,
                        'name' => $name,
                        'code' => $itemCode,
                        'message' => "Item '$name' added successfully.",
                    ];
                    setFlash('success',"Item '$name' added successfully.");
                } catch (PDOException $e) {
                    if (($e->errorInfo[1] ?? null) === 1062) {
                        setFlash('error', 'That item code already exists. Leave Item Code blank so the system can generate a unique one.');
                    } else {
                        throw $e;
                    }
                }
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE inventory_items SET section_id=?,category_id=?,supplier_id=?,department_id=?,item_code=?,asset_code=?,serial_number=?,qr_code=?,name=?,brand_model=?,description=?,unit=?,unit_price=?,current_stock=?,minimum_stock=?,asset_type=?,purchase_date=?,warranty_date=?,asset_status=?,asset_condition=?,funding_source=?,storage_location=?,image=?,branch_id=?,updated_at=NOW() WHERE id=?");
                    $stmt->execute([$sectionId,$categoryId,$supplierId,$departmentId,$itemCode,$assetCode,$serialNumber,$qrCode,$name,$brandModel,$description,$unit,$unitPrice,$currentStock,$minStock,$assetType,$purchaseDate,$warrantyDate,$assetStatus,$assetCondition,$fundingSource,$storageLocation,$imageName,$itemBranch,$itemId]);
                    auditLog('EDIT_ITEM','inventory_items',$itemId,"Updated: $name");
                    $postRedirectParams = ['saved' => $itemId];
                    $_SESSION['inventory_feedback'] = [
                        'type' => 'success',
                        'action' => 'updated',
                        'item_id' => $itemId,
                        'name' => $name,
                        'code' => $itemCode,
                        'message' => "Item '$name' updated successfully.",
                    ];
                    setFlash('success',"Item '$name' updated successfully.");
                } catch (PDOException $e) {
                    if (($e->errorInfo[1] ?? null) === 1062) {
                        setFlash('error', 'That item code already exists. Please use another item code.');
                    } else {
                        throw $e;
                    }
                }
            }
        }
    }
    if ($action === 'delete') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $itemLookup = $pdo->prepare("SELECT id, branch_id, name FROM inventory_items WHERE id=? AND is_active=1");
        $itemLookup->execute([$itemId]);
        $itemToDelete = $itemLookup->fetch();

        if (!$itemToDelete) {
            setFlash('error', 'Item not found or already removed.');
        } elseif (!canAccessBranch((int)$itemToDelete['branch_id'])) {
            setFlash('error', 'You are not allowed to delete items from another campus.');
        } else {
            $pdo->prepare("UPDATE inventory_items SET is_active=0 WHERE id=?")->execute([$itemId]);
            auditLog('DELETE_ITEM','inventory_items',$itemId,"Deactivated: {$itemToDelete['name']}");
            $_SESSION['inventory_feedback'] = [
                'type' => 'success',
                'action' => 'removed',
                'item_id' => null,
                'name' => $itemToDelete['name'],
                'code' => '',
                'message' => "Item '{$itemToDelete['name']}' removed successfully.",
            ];
            setFlash('success',"Item '{$itemToDelete['name']}' removed successfully.");
        }
    }
    $redirectQuery = $postRedirectParams ? '?' . http_build_query($postRedirectParams) : '';
    header('Location: items.php' . $redirectQuery); exit;
}

$search         = trim($_GET['search'] ?? '');
$categoryParam  = trim($_GET['category'] ?? '');
$catFilter      = ctype_digit($categoryParam) ? (int)$categoryParam : 0;
$catNameFilter  = $catFilter ? '' : $categoryParam;
$supplierFilter = (int)($_GET['supplier'] ?? 0);
$branchFilter   = $isAdmin ? (int)($_GET['branch'] ?? 0) : (int)$branchId;
$deptFilter     = (int)($_GET['department'] ?? 0);
$sectionFilter  = (int)($_GET['section'] ?? 0);
$stockFilter    = $_GET['filter'] ?? '';
$legacyStockFilters = ['good' => 'available', 'low' => 'low_stock', 'out' => 'out_of_stock'];
$stockFilter = $legacyStockFilters[$stockFilter] ?? $stockFilter;
$assetStatusFilter = $_GET['asset_status'] ?? '';
$conditionFilter = $_GET['condition'] ?? '';
$missingPurchase = ($_GET['missing_purchase'] ?? '') === '1';
$inventoryFeedback = $_SESSION['inventory_feedback'] ?? null;
unset($_SESSION['inventory_feedback']);
$savedItemIdFromQuery = max(0, (int)($_GET['saved'] ?? 0));
$feedbackItemId = !empty($inventoryFeedback['item_id']) ? (int)$inventoryFeedback['item_id'] : $savedItemIdFromQuery;

$buildItemOptionWhere = function (array $keys) use (
    &$branchFilter,
    &$catFilter,
    &$catNameFilter,
    &$sectionFilter,
    &$deptFilter,
    &$supplierFilter,
    &$stockFilter,
    &$assetStatusFilter,
    &$conditionFilter,
    $isAdmin,
    $branchId
) {
    $where = ['i.is_active = 1'];
    $params = [];

    if (!$isAdmin) {
        $where[] = 'i.branch_id = ?';
        $params[] = $branchId;
    } elseif (in_array('branch', $keys, true) && $branchFilter) {
        $where[] = 'i.branch_id = ?';
        $params[] = $branchFilter;
    }
    if (in_array('category', $keys, true) && $catFilter) {
        $where[] = 'i.category_id = ?';
        $params[] = $catFilter;
    } elseif (in_array('category', $keys, true) && $catNameFilter !== '') {
        $where[] = 'i.category_id IN (SELECT id FROM categories WHERE name = ?)';
        $params[] = $catNameFilter;
    }
    if (in_array('section', $keys, true) && $sectionFilter) {
        $where[] = 'i.section_id = ?';
        $params[] = $sectionFilter;
    }
    if (in_array('department', $keys, true) && $deptFilter) {
        $where[] = 'i.department_id = ?';
        $params[] = $deptFilter;
    }
    if (in_array('supplier', $keys, true) && $supplierFilter) {
        $where[] = 'i.supplier_id = ?';
        $params[] = $supplierFilter;
    }
    if (in_array('stock', $keys, true) && $stockFilter) {
        if ($stockFilter === 'low_stock') {
            $where[] = 'i.current_stock <= i.minimum_stock AND i.current_stock > 0';
        } elseif ($stockFilter === 'out_of_stock') {
            $where[] = 'i.current_stock = 0';
        } elseif ($stockFilter === 'available') {
            $where[] = 'i.current_stock > i.minimum_stock';
        }
    }
    if (in_array('asset_status', $keys, true) && $assetStatusFilter !== '') {
        $where[] = 'i.asset_status = ?';
        $params[] = $assetStatusFilter;
    }
    if (in_array('condition', $keys, true) && $conditionFilter !== '') {
        $where[] = 'i.asset_condition = ?';
        $params[] = $conditionFilter;
    }

    return ['WHERE ' . implode(' AND ', $where), $params];
};

$fetchItemOptionRows = function (string $sql, array $keys) use ($pdo, $buildItemOptionWhere) {
    [$where, $params] = $buildItemOptionWhere($keys);
    $stmt = $pdo->prepare(str_replace('{WHERE}', $where, $sql));
    $stmt->execute($params);
    return $stmt->fetchAll();
};

$hasSelectedId = static function (array $rows, int $selected): bool {
    return $selected === 0 || in_array($selected, array_map('intval', array_column($rows, 'id')), true);
};

$branches = $pdo->query("SELECT * FROM branches ORDER BY is_headquarters DESC")->fetchAll();
$filterCategories = $fetchItemOptionRows("
    SELECT MIN(c.id) AS id, c.name, " . ($branchFilter ? "MIN(c.branch_id)" : "0") . " AS branch_id, COUNT(i.id) AS item_count
    FROM inventory_items i
    JOIN categories c ON c.id = i.category_id
    {WHERE}
    GROUP BY c.name
    ORDER BY c.name
", ['branch', 'section', 'department']);
if ($catFilter && !$hasSelectedId($filterCategories, $catFilter)) {
    $catFilter = 0;
    $catNameFilter = '';
}
if ($catNameFilter !== '' && !in_array($catNameFilter, array_column($filterCategories, 'name'), true)) {
    $catNameFilter = '';
}

$sections = $fetchItemOptionRows("
    SELECT sec.id, sec.name, sec.branch_id, b.name AS branch_name, COUNT(i.id) AS item_count
    FROM inventory_items i
    JOIN sections sec ON sec.id = i.section_id
    JOIN branches b ON b.id = sec.branch_id
    {WHERE}
    GROUP BY sec.id, sec.name, sec.branch_id, b.name
    ORDER BY b.name, sec.name
", ['branch']);
if (!$hasSelectedId($sections, $sectionFilter)) {
    $sectionFilter = 0;
    $deptFilter = 0;
}

$departments = $fetchItemOptionRows("
    SELECT d.id, d.name, d.section_id, sec.name AS section_name, sec.branch_id, b.name AS branch_name, COUNT(i.id) AS item_count
    FROM inventory_items i
    JOIN departments d ON d.id = i.department_id
    JOIN sections sec ON sec.id = d.section_id
    JOIN branches b ON b.id = sec.branch_id
    {WHERE}
    GROUP BY d.id, d.name, d.section_id, sec.name, sec.branch_id, b.name
    ORDER BY b.name, sec.name, d.name
", ['branch', 'section']);
if (!$hasSelectedId($departments, $deptFilter)) {
    $deptFilter = 0;
}

$suppliers = $fetchItemOptionRows("
    SELECT s.id, s.company_name, COUNT(i.id) AS item_count
    FROM inventory_items i
    JOIN suppliers s ON s.id = i.supplier_id
    {WHERE}
    GROUP BY s.id, s.company_name
    ORDER BY s.company_name
", ['branch', 'section', 'department', 'category']);
if (!$hasSelectedId($suppliers, $supplierFilter)) {
    $supplierFilter = 0;
}

$stockStatusOptions = [];
[$stockWhere, $stockParams] = $buildItemOptionWhere(['branch', 'category', 'section', 'department', 'supplier']);
$stockStmt = $pdo->prepare("
    SELECT
        SUM(CASE WHEN i.current_stock <= i.minimum_stock AND i.current_stock > 0 THEN 1 ELSE 0 END) AS low_stock,
        SUM(CASE WHEN i.current_stock = 0 THEN 1 ELSE 0 END) AS out_of_stock,
        SUM(CASE WHEN i.current_stock > i.minimum_stock THEN 1 ELSE 0 END) AS available
    FROM inventory_items i
    {$stockWhere}
");
$stockStmt->execute($stockParams);
$stockCounts = $stockStmt->fetch() ?: [];
foreach ([
    'available' => 'Available',
    'low_stock' => 'Low Stock',
    'out_of_stock' => 'Out of Stock',
] as $value => $label) {
    if ((int)($stockCounts[$value] ?? 0) > 0) {
        $stockStatusOptions[] = ['value' => $value, 'label' => $label, 'item_count' => (int)$stockCounts[$value]];
    }
}
if ($stockFilter && !in_array($stockFilter, array_column($stockStatusOptions, 'value'), true)) {
    $stockFilter = '';
}

$assetStatusOptions = $fetchItemOptionRows("
    SELECT i.asset_status AS value, i.asset_status AS label, COUNT(i.id) AS item_count
    FROM inventory_items i
    {WHERE}
      AND i.asset_status IS NOT NULL
      AND i.asset_status <> ''
    GROUP BY i.asset_status
    ORDER BY i.asset_status
", ['branch', 'category', 'section', 'department', 'supplier', 'stock']);
if ($assetStatusFilter && !in_array($assetStatusFilter, array_column($assetStatusOptions, 'value'), true)) {
    $assetStatusFilter = '';
}

$conditionOptions = $fetchItemOptionRows("
    SELECT i.asset_condition AS value, i.asset_condition AS label, COUNT(i.id) AS item_count
    FROM inventory_items i
    {WHERE}
      AND i.asset_condition IS NOT NULL
      AND i.asset_condition <> ''
    GROUP BY i.asset_condition
    ORDER BY i.asset_condition
", ['branch', 'category', 'section', 'department', 'supplier', 'stock', 'asset_status']);
if ($conditionFilter && !in_array($conditionFilter, array_column($conditionOptions, 'value'), true)) {
    $conditionFilter = '';
}

$filterRows = $pdo->query("
    SELECT i.branch_id, c.name AS category_name, i.section_id, i.department_id, i.supplier_id,
           i.asset_status, i.asset_condition,
           CASE
               WHEN i.current_stock = 0 THEN 'out_of_stock'
               WHEN i.current_stock <= i.minimum_stock THEN 'low_stock'
               ELSE 'available'
           END AS stock_status
    FROM inventory_items i
    JOIN categories c ON c.id = i.category_id
    WHERE i.is_active = 1
")->fetchAll();
if (!$isAdmin) {
    $filterRows = array_values(array_filter($filterRows, static fn($row) => (int)$row['branch_id'] === (int)$branchId));
}

$allCategories = $pdo->query("SELECT MIN(id) AS id, name, 0 AS branch_id FROM categories GROUP BY name ORDER BY name")->fetchAll();
$allSections = $pdo->query("SELECT id, name, branch_id FROM sections WHERE is_active=1 ORDER BY name")->fetchAll();
$allDepartments = $pdo->query("SELECT d.id, d.name, d.section_id, s.name AS section_name FROM departments d JOIN sections s ON d.section_id=s.id WHERE d.is_active=1 ORDER BY s.name, d.name")->fetchAll();
$allSuppliers = $pdo->query("SELECT id, company_name FROM suppliers WHERE is_active=1 ORDER BY company_name")->fetchAll();
$filterOptionLabels = [
    'categories' => array_map(static fn($row) => [
        'id' => $row['name'],
        'label' => $row['name'],
        'branch_id' => (int)$row['branch_id'],
    ], $allCategories),
    'sections' => array_map(static fn($row) => [
        'id' => (int)$row['id'],
        'label' => $row['name'],
        'branch_id' => (int)$row['branch_id'],
    ], $allSections),
    'departments' => array_map(static fn($row) => [
        'id' => (int)$row['id'],
        'label' => ($row['section_name'] ? $row['section_name'] . ' - ' : '') . $row['name'],
        'section_id' => (int)$row['section_id'],
    ], $allDepartments),
    'suppliers' => array_map(static fn($row) => [
        'id' => (int)$row['id'],
        'label' => $row['company_name'],
    ], $allSuppliers),
    'stock_statuses' => [
        ['id' => 'available', 'label' => 'Available'],
        ['id' => 'low_stock', 'label' => 'Low Stock'],
        ['id' => 'out_of_stock', 'label' => 'Out of Stock'],
    ],
];

$where  = ["i.is_active = 1"];
$params = [];
if (!$isAdmin) { $where[] = "i.branch_id = ?"; $params[] = $branchId; }
elseif ($branchFilter) { $where[] = "i.branch_id = ?"; $params[] = $branchFilter; }
if ($search) {
    $where[] = "(i.name LIKE ? OR i.item_code LIKE ? OR i.asset_code LIKE ? OR i.serial_number LIKE ? OR i.qr_code LIKE ? OR i.brand_model LIKE ? OR i.description LIKE ? OR c.name LIKE ? OR s.company_name LIKE ? OR b.name LIKE ? OR sec.name LIKE ? OR d.name LIKE ?)";
    $params = array_merge($params, ["%$search%","%$search%","%$search%","%$search%","%$search%","%$search%","%$search%","%$search%","%$search%","%$search%","%$search%","%$search%"]);
}
if ($catFilter) { $where[] = "i.category_id = ?"; $params[] = $catFilter; }
elseif ($catNameFilter !== '') { $where[] = "c.name = ?"; $params[] = $catNameFilter; }
if ($supplierFilter) { $where[] = "i.supplier_id = ?"; $params[] = $supplierFilter; }
if ($sectionFilter) { $where[] = "i.section_id = ?"; $params[] = $sectionFilter; }
if ($deptFilter) { $where[] = "i.department_id = ?"; $params[] = $deptFilter; }
if ($stockFilter === 'low_stock') $where[] = "i.current_stock <= i.minimum_stock AND i.current_stock > 0";
if ($stockFilter === 'out_of_stock') $where[] = "i.current_stock = 0";
if ($stockFilter === 'available') $where[] = "i.current_stock > i.minimum_stock";
if ($assetStatusFilter !== '') { $where[] = "i.asset_status = ?"; $params[] = $assetStatusFilter; }
if ($conditionFilter !== '') { $where[] = "i.asset_condition = ?"; $params[] = $conditionFilter; }
if ($missingPurchase) $where[] = "i.purchase_date IS NULL";
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

$inventoryItemSelect = "SELECT i.*, c.name AS category_name, s.company_name AS supplier_name, b.name AS branch_name, sec.name AS section_name, d.name AS department_name, recorder.full_name AS recorded_by_name, recorder_role.name AS recorded_by_role FROM inventory_items i JOIN categories c ON i.category_id=c.id LEFT JOIN suppliers s ON i.supplier_id=s.id JOIN branches b ON i.branch_id=b.id LEFT JOIN sections sec ON i.section_id=sec.id LEFT JOIN departments d ON i.department_id=d.id LEFT JOIN users recorder ON recorder.id=i.created_by LEFT JOIN roles recorder_role ON recorder_role.id=recorder.role_id";
$inventoryItemOrder = "COALESCE(i.updated_at, i.created_at) DESC, i.created_at DESC, i.id DESC";
$feedbackOrderSql = $feedbackItemId ? "CASE WHEN i.id = ? THEN 0 ELSE 1 END, " : "";
$itemParams = $params;
if ($feedbackItemId) {
    $itemParams[] = $feedbackItemId;
}
$stmt = $pdo->prepare("$inventoryItemSelect WHERE $whereSQL ORDER BY {$feedbackOrderSql}{$inventoryItemOrder} LIMIT $itemsPerPage OFFSET $offset");
$stmt->execute($itemParams);
$items = $stmt->fetchAll();

$printStmt = $pdo->prepare("$inventoryItemSelect WHERE $whereSQL ORDER BY $inventoryItemOrder");
$printStmt->execute($params);
$printItems = $printStmt->fetchAll();

$paginationParams = $_GET;
unset($paginationParams['page'], $paginationParams['edit'], $paginationParams['action'], $paginationParams['saved']);
$pageUrl = function (int $targetPage) use ($paginationParams): string {
    $query = http_build_query(array_merge($paginationParams, ['page' => $targetPage]));
    return 'items.php' . ($query ? '?' . $query : '');
};

$formSuppliers  = $pdo->query("SELECT * FROM suppliers WHERE is_active=1 ORDER BY company_name")->fetchAll();
if ($isAdmin) {
    $categories = $pdo->query("SELECT * FROM categories ORDER BY branch_id, name")->fetchAll();
} else {
    $categories = $pdo->prepare("SELECT * FROM categories WHERE branch_id = ? ORDER BY name");
    $categories->execute([$branchId]);
    $categories = $categories->fetchAll();
}
$formSections = $pdo->prepare("SELECT s.id, s.name, s.branch_id, b.name AS branch_name FROM sections s JOIN branches b ON s.branch_id=b.id WHERE s.is_active=1" . (!$isAdmin ? " AND s.branch_id = ?" : "") . " ORDER BY b.name, s.name");
$formSections->execute(!$isAdmin ? [$branchId] : []);
$formSections = $formSections->fetchAll();
$formDepartments = $pdo->prepare("SELECT d.id, d.name, d.section_id, s.branch_id, b.name AS branch_name, s.name AS section_name FROM departments d JOIN sections s ON d.section_id=s.id JOIN branches b ON s.branch_id=b.id WHERE d.is_active=1" . (!$isAdmin ? " AND b.id = ?" : "") . " ORDER BY b.name, s.name, d.name");
$formDepartments->execute(!$isAdmin ? [$branchId] : []);
$formDepartments = $formDepartments->fetchAll();

$editItem = null;
if (isset($_GET['edit'])) {
    $es = $pdo->prepare("$inventoryItemSelect WHERE i.id=? AND i.is_active=1");
    $es->execute([(int)$_GET['edit']]);
    $editItem = $es->fetch();
}
$showAddModal = $canManage && (($_GET['action'] ?? '') === 'add' || $editItem);

$branchNames = array_column($branches, 'name', 'id');
$categoryNames = array_column($filterCategories, 'name', 'name');
$sectionNames = array_column($sections, 'name', 'id');
$departmentNames = array_column($departments, 'name', 'id');
$supplierNames = array_column($suppliers, 'company_name', 'id');
$stockStatusNames = array_column($stockStatusOptions, 'label', 'value');
$assetStatusNames = array_column($assetStatusOptions, 'label', 'value');
$conditionNames = array_column($conditionOptions, 'label', 'value');
$recordingOfficer = $editItem
    ? trim(($editItem['recorded_by_name'] ?? '') . (($editItem['recorded_by_role'] ?? '') ? ', ' . $editItem['recorded_by_role'] : ''))
    : trim(($user['full_name'] ?? '') . (($user['role'] ?? '') ? ', ' . $user['role'] : ''));
$recordingOfficer = $recordingOfficer !== '' ? $recordingOfficer : 'Not recorded';
$printFilters = [
    'Campus' => $branchFilter ? ($branchNames[$branchFilter] ?? 'Selected campus') : ($isAdmin ? 'All Campuses' : ($branchNames[$branchId] ?? 'Current campus')),
    'Category' => ($catNameFilter !== '' || $catFilter) ? ($catNameFilter ?: ($categoryNames[$catFilter] ?? 'Selected category')) : 'All Categories',
    'Department' => $sectionFilter ? ($sectionNames[$sectionFilter] ?? 'Selected department') : 'All Departments',
    'Section / Unit' => $deptFilter ? ($departmentNames[$deptFilter] ?? 'Selected section/unit') : 'All Sections / Units',
    'Supplier' => $supplierFilter ? ($supplierNames[$supplierFilter] ?? 'Selected supplier') : 'All Suppliers',
    'Stock Level' => $stockFilter ? ($stockStatusNames[$stockFilter] ?? $stockFilter) : 'All Stock Levels',
    'Asset Status' => $assetStatusFilter ? ($assetStatusNames[$assetStatusFilter] ?? $assetStatusFilter) : 'All Asset Statuses',
    'Condition' => $conditionFilter ? ($conditionNames[$conditionFilter] ?? $conditionFilter) : 'All Conditions',
    'Search' => $search ?: 'None',
];

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
    <div class="page-actions">
        <button type="button" class="btn btn-outline" onclick="window.print()">
            <svg viewBox="0 0 24 24"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print Items
        </button>
        <?php if ($canManage): ?>
        <button class="btn btn-primary" onclick="openModal('addItemModal')">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Item
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($inventoryFeedback): ?>
<div class="inventory-feedback inventory-feedback-<?= clean($inventoryFeedback['type'] ?? 'success') ?>" id="inventoryFeedback" role="status" aria-live="polite">
    <div class="inventory-feedback-icon" aria-hidden="true">
        <?php if (($inventoryFeedback['type'] ?? 'success') === 'success'): ?>
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        <?php else: ?>
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?php endif; ?>
    </div>
    <div class="inventory-feedback-copy">
        <strong><?= clean($inventoryFeedback['message'] ?? 'Inventory change saved.') ?></strong>
        <span>
            <?= clean($inventoryFeedback['name'] ?? 'Inventory item') ?>
            <?php if (!empty($inventoryFeedback['code'])): ?>
                · Code <?= clean($inventoryFeedback['code']) ?>
            <?php endif; ?>
            <?php if (($inventoryFeedback['action'] ?? '') !== 'removed'): ?>
                · Visible in the highlighted row below.
            <?php endif; ?>
        </span>
    </div>
    <button type="button" class="inventory-feedback-close" aria-label="Dismiss inventory confirmation">×</button>
</div>
<?php endif; ?>

<div class="card filter-bar">
    <form method="GET" class="filter-form">
        <div class="filter-group">
            <div class="input-wrap">
                <svg class="input-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="search" placeholder="Search asset code, serial, QR code, name…" value="<?= clean($search) ?>">
            </div>
        </div>
        <?php if ($isAdmin): ?>
        <div class="filter-group">
            <select id="branchSelect" name="branch">
                <option value="">All Campuses</option>
                <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $b['id']==$branchFilter?'selected':'' ?>><?= clean($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="filter-group">
            <select id="sectionSelect" name="section">
                <option value="">All Departments</option>
                <?php foreach ($sections as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $s['id']==$sectionFilter?'selected':'' ?>><?= clean($s['branch_name']) ?> — <?= clean($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <select id="departmentSelect" name="department">
                <option value="">All Sections / Units</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $d['id']==$deptFilter?'selected':'' ?>><?= clean($d['branch_name']) ?> — <?= clean($d['section_name']) ?> — <?= clean($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <select id="categorySelect" name="category">
                <option value="">All Categories</option>
                <?php foreach ($filterCategories as $c): ?>
                <option value="<?= clean($c['name']) ?>" <?= ($catNameFilter === $c['name'] || ($catFilter && $c['id']==$catFilter)) ? 'selected' : '' ?>><?= clean($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <select id="supplierSelect" name="supplier">
                <option value="">All Suppliers</option>
                <?php foreach ($suppliers as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $s['id']==$supplierFilter?'selected':'' ?>><?= clean($s['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <select id="statusSelect" name="filter">
                <option value="">All Stock Levels</option>
                <?php foreach ($stockStatusOptions as $status): ?>
                <option value="<?= clean($status['value']) ?>" <?= $stockFilter===$status['value']?'selected':'' ?>><?= clean($status['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <select id="assetStatusSelect" name="asset_status">
                <option value="">All Asset Statuses</option>
                <?php foreach ($assetStatusOptions as $status): ?>
                <option value="<?= clean($status['value']) ?>" <?= $assetStatusFilter===$status['value']?'selected':'' ?>><?= clean($status['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <select id="conditionSelect" name="condition">
                <option value="">All Conditions</option>
                <?php foreach ($conditionOptions as $condition): ?>
                <option value="<?= clean($condition['value']) ?>" <?= $conditionFilter===$condition['value']?'selected':'' ?>><?= clean($condition['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($missingPurchase): ?>
        <input type="hidden" name="missing_purchase" value="1">
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="items.php" class="btn btn-outline">Reset</a>
    </form>
</div>

<div class="card inventory-print-area inventory-table-card">
    <div class="card-body p0">
        <?php if ($items): ?>
        <div class="print-header inventory-print-header">
            <div class="print-logo"><img src="<?= BASE_URL ?>assets/img/uiri-logo.webp" alt="UIRI"></div>
            <div>
                <h2>Inventory Items</h2>
                <p>Uganda Industrial Research Institute<?= $branchFilter ? ' — ' . clean($branchNames[$branchFilter] ?? '') : ' — All Campuses' ?></p>
                <p>Report generated by: <?= clean($user['full_name']) ?>, <?= clean($user['role']) ?> · <?= formatDateTime('now', true) ?></p>
            </div>
        </div>
        <div class="print-meta inventory-print-meta">
            <?php foreach ($printFilters as $label => $value): ?>
            <div><strong><?= clean($label) ?>:</strong> <?= clean((string)$value) ?></div>
            <?php endforeach; ?>
        </div>
        <div class="inventory-print-table-wrap">
        <table class="data-table inventory-items-table">
            <thead><tr>
                <th>#</th><th>Item</th><th>Category</th><th>Department</th><th>Section / Unit</th>
                <?php if ($isAdmin): ?><th>Branch</th><?php endif; ?>
                <th>Model / Specs</th><th>Serial No.</th><th>Purchased</th><th>Unit Price</th><th>Stock</th><th>Min</th><th>Status</th>
            </tr></thead>
            <tbody>
            <?php foreach ($printItems as $i => $item):
                $ss = $item['current_stock']==0 ? 'out' : ($item['current_stock']<=$item['minimum_stock'] ? 'low' : 'good');
                $sl = $ss==='out' ? 'Out of Stock' : ($ss==='low' ? 'Low Stock' : 'In Stock');
            ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td>
                    <span class="item-name"><?= clean($item['name']) ?></span>
                    <span class="item-code"><?= clean($item['item_code']) ?></span>
                    <span class="item-audit-line">Recorded by: <?= clean(trim((($item['recorded_by_name'] ?? '') ?: 'Not recorded') . (($item['recorded_by_role'] ?? '') ? ', ' . $item['recorded_by_role'] : ''))) ?></span>
                </td>
                <td><?= clean($item['category_name']) ?></td>
                <td><?= clean($item['section_name'] ?: '—') ?></td>
                <td><?= clean($item['department_name'] ?: '—') ?></td>
                <?php if ($isAdmin): ?><td><?= clean($item['branch_name']) ?></td><?php endif; ?>
                <td><?= $item['brand_model'] ? clean($item['brand_model']) : '<span class="table-muted-value">Not specified</span>' ?></td>
                <td><?= $item['serial_number'] ? clean($item['serial_number']) : '<span class="table-muted-value">—</span>' ?></td>
                <td><?= $item['purchase_date'] ? date('d M Y', strtotime($item['purchase_date'])) : '—' ?></td>
                <td><?= ugx($item['unit_price']) ?></td>
                <td><strong><?= number_format($item['current_stock']) ?> <?= clean($item['unit']) ?></strong></td>
                <td><?= $item['minimum_stock'] ?></td>
                <td><span class="badge badge-<?= $ss==='good'?'success':($ss==='low'?'warn':'danger') ?>"><?= $sl ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div class="inventory-table-scroll">
        <div class="inventory-table-canvas">
        <table class="data-table inventory-items-table">
            <thead><tr>
                <th>#</th><th>Item</th><th>Category</th><th>Department</th><th>Section / Unit</th>
                <?php if ($isAdmin): ?><th>Branch</th><?php endif; ?>
                <th>Model / Specs</th><th>Serial No.</th><th>Purchased</th><th>Unit Price</th><th>Stock</th><th>Min</th><th>Status</th>
                <?php if ($canManage): ?><th class="inventory-actions-col">Actions</th><?php endif; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($items as $i => $item):
                $ss = $item['current_stock']==0 ? 'out' : ($item['current_stock']<=$item['minimum_stock'] ? 'low' : 'good');
                $sl = $ss==='out' ? 'Out of Stock' : ($ss==='low' ? 'Low Stock' : 'In Stock');
                $isLatestChange = $page === 1 && $i === 0;
                $isFeedbackItem = $feedbackItemId && (int)$feedbackItemId === (int)$item['id'];
                $latestChangeDate = $item['updated_at'] ?: $item['created_at'];
                $rowClasses = array_filter([
                    $isLatestChange ? 'inventory-latest-row' : '',
                    $isFeedbackItem ? 'inventory-feedback-row' : '',
                ]);
                $latestMarkerLabel = $isFeedbackItem ? ($inventoryFeedback ? ('Just ' . ($inventoryFeedback['action'] ?? 'saved')) : 'Saved item') : 'Latest change';
            ?>
            <tr class="<?= clean(implode(' ', $rowClasses)) ?>" <?= $isFeedbackItem ? 'data-inventory-feedback-row="true"' : '' ?>>
                <td><?= $offset + $i + 1 ?></td>
                <td>
                    <div class="item-cell">
                        <div>
                            <span class="item-name">
                                <?= clean($item['name']) ?>
                            </span>
                            <?php if ($isLatestChange || $isFeedbackItem): ?>
                            <span class="latest-change-marker" title="Last changed <?= clean(date('d M Y, H:i', strtotime($latestChangeDate))) ?>"><?= clean($latestMarkerLabel) ?></span>
                            <?php endif; ?>
                            <span class="item-code"><?= clean($item['item_code']) ?></span>
                            <span class="item-audit-line">Recorded by: <?= clean(trim((($item['recorded_by_name'] ?? '') ?: 'Not recorded') . (($item['recorded_by_role'] ?? '') ? ', ' . $item['recorded_by_role'] : ''))) ?></span>
                        </div>
                    </div>
                </td>
                <td><?= clean($item['category_name']) ?></td>
                <td><?= clean($item['section_name'] ?: '—') ?></td>
                <td><?= clean($item['department_name'] ?: '—') ?></td>
                <?php if ($isAdmin): ?><td><?= clean($item['branch_name']) ?></td><?php endif; ?>
                <td><?= $item['brand_model'] ? clean($item['brand_model']) : '<span class="table-muted-value">Not specified</span>' ?></td>
                <td><?= $item['serial_number'] ? clean($item['serial_number']) : '<span class="table-muted-value">—</span>' ?></td>
                <td><?= $item['purchase_date'] ? date('d M Y', strtotime($item['purchase_date'])) : '—' ?></td>
                <td><?= ugx($item['unit_price']) ?></td>
                <td><strong><?= number_format($item['current_stock']) ?> <?= clean($item['unit']) ?></strong></td>
                <td><?= $item['minimum_stock'] ?></td>
                <td><span class="badge badge-<?= $ss==='good'?'success':($ss==='low'?'warn':'danger') ?>"><?= $sl ?></span></td>
                <?php if ($canManage): ?>
                <td class="inventory-actions-col">
                    <div class="action-btns">
                        <a href="items.php?edit=<?= $item['id'] ?>" class="btn-icon" title="Edit">
                            <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </a>
                        <button type="button" class="btn-icon btn-icon-danger js-delete-item" title="Delete" aria-label="Delete <?= clean($item['name']) ?>" data-item-id="<?= $item['id'] ?>" data-item-name="<?= clean($item['name']) ?>" data-item-code="<?= clean($item['item_code']) ?>" data-item-category="<?= clean($item['category_name']) ?>" data-item-stock="<?= (int)$item['current_stock'] ?>" data-item-unit="<?= clean($item['unit']) ?>">
                            <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                        </button>
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
        </div>
        </div>
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
<div class="modal-overlay" id="deleteItemModal" role="dialog" aria-modal="true" aria-labelledby="deleteItemTitle">
    <div class="modal delete-user-modal">
        <div class="delete-user-topline"></div>
        <div class="delete-user-body">
            <div class="delete-user-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
            </div>
            <div class="delete-user-copy">
                <span class="delete-user-kicker">Inventory item removal</span>
                <h3 id="deleteItemTitle">Confirm item deletion</h3>
                <p><strong id="deleteItemName">This item</strong> will be removed from active inventory records. Do you want to continue?</p>
            </div>
        </div>
        <div class="delete-user-warning" id="deleteItemWarning">
            Item code: <strong id="deleteItemCode">-</strong> · Category: <strong id="deleteItemCategory">-</strong> · Stock: <strong id="deleteItemStock">0 units</strong>
        </div>
        <form method="POST" id="deleteItemForm" class="delete-user-actions">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="item_id" id="deleteItemId" value="">
            <button type="button" class="btn btn-outline delete-user-cancel" id="cancelDeleteItem">No, keep item</button>
            <button type="submit" class="btn btn-danger delete-user-confirm" id="confirmDeleteItem">Yes, remove item</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="addItemModal" <?= $showAddModal?'style="display:flex"':'' ?>>
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3><?= $editItem?'Edit Item':'Add New Item' ?></h3>
            <button class="modal-close" onclick="closeModal('addItemModal')">×</button>
        </div>
        <form method="POST" enctype="multipart/form-data" id="inventoryItemForm" novalidate>
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
                                <button type="button" class="wizard-step-btn" data-step="location">2. Location</button>
                                <button type="button" class="wizard-step-btn" data-step="classification">3. Classification</button>
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
                                            <label>Item Code</label>
                                            <input type="text" name="item_code" placeholder="Auto-generated if left blank" value="<?= clean($editItem['item_code']??'') ?>">
                                            <small>Leave blank when adding a new item; the system will generate a unique code.</small>
                                        </div>
                                        <div class="form-group">
                                            <label>Model / Specs</label>
                                            <input type="text" name="brand_model" id="previewBrandModelInput" placeholder="e.g. Dell Latitude 7420, Core i5, 8GB RAM" value="<?= clean($editItem['brand_model']??'') ?>">
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
                                    <p>Classify the item after its campus and organizational assignment are clear.</p>
                                    <div class="form-grid-2">
                                        <div class="form-group">
                                            <label>Inventory Type</label>
                                            <select name="inventory_type">
                                                <?php foreach (['Fixed Asset','Consumable','Tool','Spare Part','Laboratory Equipment','Office Equipment'] as $type): ?>
                                                <option value="<?= $type ?>" <?= ($editItem['asset_type']??'Consumable')===$type?'selected':'' ?>><?= $type ?></option>
                                                <?php endforeach; ?>
                                            </select>
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
                                            <label>Supplier</label>
                                            <select name="supplier_id" id="previewSupplierInput">
                                                <option value="">Select supplier</option>
                                                <?php foreach ($formSuppliers as $s): ?>
                                                <option value="<?= $s['id'] ?>" <?= ($editItem['supplier_id']??0)==$s['id']?'selected':'' ?>><?= clean($s['company_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Asset Status</label>
                                            <select name="asset_status">
                                                <?php foreach (['Available','Working','Not Working','In Maintenance','In Use','Reserved','Issued','Decommissioned','Disposed'] as $status): ?>
                                                <option value="<?= $status ?>" <?= ($editItem['asset_status']??'Available')===$status?'selected':'' ?>><?= $status ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Condition</label>
                                            <select name="asset_condition">
                                                <?php foreach (['New','Good','Fair','Used','Refurbished','Needs Repair','Obsolete','Decommissioned'] as $condition): ?>
                                                <option value="<?= $condition ?>" <?= ($editItem['asset_condition']??'New')===$condition?'selected':'' ?>><?= $condition ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Funding Source</label>
                                            <input type="text" name="funding_source" placeholder="e.g. Government Grant" value="<?= clean($editItem['funding_source']??'') ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Date of Purchase</label>
                                            <input type="date" name="purchase_date" id="previewPurchaseDateInput" value="<?= clean($editItem['purchase_date']??'') ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="wizard-step-panel" data-step-panel="location">
                                <div class="form-section-card">
                                    <h4>Organizational Assignment</h4>
                                    <p>Start with campus, then narrow the item to the right department and section/unit.</p>
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
                                        <?php else: ?>
                                        <input type="hidden" name="branch_id" id="previewBranchInput" value="<?= (int)$branchId ?>" data-label="<?= clean($branchNames[$branchId] ?? 'Current campus') ?>">
                                        <div class="form-group">
                                            <label>Campus</label>
                                            <div class="readonly-field"><?= clean($branchNames[$branchId] ?? 'Current campus') ?></div>
                                            <small>This is fixed from your active branch.</small>
                                        </div>
                                        <?php endif; ?>
                                        <div class="form-group">
                                            <label>Department *</label>
                                            <select name="section_id" id="previewSectionInput" required>
                                                <option value="">Select department</option>
                                                <?php foreach ($formSections as $s): ?>
                                                <option value="<?= $s['id'] ?>" data-branch="<?= $s['branch_id'] ?>" <?= ($editItem['section_id']??0)==$s['id']?'selected':'' ?>><?= clean($s['branch_name']) ?> — <?= clean($s['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Section / Unit *</label>
                                            <select name="department_id" id="previewDeptInput" required>
                                                <option value="">Select section/unit</option>
                                                <?php foreach ($formDepartments as $d): ?>
                                                <option value="<?= $d['id'] ?>" data-section="<?= $d['section_id'] ?>" <?= ($editItem['department_id']??0)==$d['id']?'selected':'' ?>><?= clean($d['branch_name']) ?> — <?= clean($d['section_name']) ?> — <?= clean($d['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Physical Storage Location</label>
                                            <input type="text" name="storage_location" placeholder="e.g. Shelf B4" value="<?= clean($editItem['storage_location']??'') ?>">
                                            <small>Use this for room, shelf, cabinet, store, or lab placement after the organizational assignment.</small>
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
                                            <label>Asset Code</label>
                                            <input type="text" name="asset_code" id="previewAssetCodeInput" placeholder="e.g. AST-001" value="<?= clean($editItem['asset_code']??'') ?>">
                                        </div>
                                        <div class="form-group" id="serialNumberGroup">
                                            <label>Serial Number</label>
                                            <input type="text" name="serial_number" id="previewSerialNumberInput" placeholder="e.g. SN-4CE0460D0G" value="<?= clean($editItem['serial_number']??'') ?>">
                                            <small id="serialNumberHint">Use this for equipment, ICT assets, tools, and other uniquely identifiable items.</small>
                                        </div>
                                        <div class="form-group">
                                            <label>QR Code</label>
                                            <input type="text" name="qr_code" id="previewQrCodeInput" placeholder="e.g. QR-001" value="<?= clean($editItem['qr_code']??'') ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Warranty End</label>
                                            <input type="date" name="warranty_date" value="<?= clean($editItem['warranty_date']??'') ?>">
                                        </div>
                                    </div>
                                    <div class="form-grid-2">
                                        <div class="form-group">
                                            <label>Item Image</label>
                                            <input type="file" name="image" id="previewImageInput" accept="image/*">
                                            <?php if (!empty($editItem['image'])): ?><small>Current: <?= clean($editItem['image']) ?></small><?php endif; ?>
                                            <div class="inline-image-preview" id="inlineImagePreview" <?= empty($editItem['image']) ? 'hidden' : '' ?>>
                                                <img id="inlineImagePreviewImg" src="<?= !empty($editItem['image']) ? clean(UPLOAD_URL . $editItem['image']) : '' ?>" alt="Selected item image preview">
                                            </div>
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
                                    <div class="review-visual-card">
                                        <div class="review-image-frame" id="reviewImageFrame">
                                            <img id="reviewImagePreview" src="<?= !empty($editItem['image']) ? clean(UPLOAD_URL . $editItem['image']) : '' ?>" alt="Item image review" <?= empty($editItem['image']) ? 'hidden' : '' ?>>
                                            <span id="reviewImagePlaceholder" <?= !empty($editItem['image']) ? 'hidden' : '' ?>>No image selected</span>
                                        </div>
                                        <div>
                                            <strong id="reviewItemName">Untitled Item</strong>
                                            <span id="reviewItemContext">Choose campus, category, stock, and tracking details.</span>
                                        </div>
                                    </div>
                                    <div class="review-checklist">
                                        <div class="review-item"><strong>Core details</strong><span>Item name, category, and description will be saved.</span></div>
                                        <div class="review-item"><strong>Location</strong><span>Campus, department, and section/unit assignments are included in the record.</span></div>
                                        <div class="review-item"><strong>Stock & financials</strong><span>Minimum stock and price thresholds are tracked.</span></div>
                                        <div class="review-item"><strong>Tracking</strong><span>Asset and QR references are generated for easy identification.</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <aside class="wizard-preview">
                            <div class="preview-card">
                                <div class="preview-card-title-row">
                                    <div class="preview-card-title">Inventory Summary</div>
                                    <button type="button" class="preview-print-btn" id="printInventorySummaryBtn" title="Print inventory summary">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9V3h12v6"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v7H6z"/><path d="M8 17h8"/></svg>
                                        <span>Print</span>
                                    </button>
                                </div>
                                <div class="preview-image-frame">
                                    <img id="previewImage" src="<?= !empty($editItem['image']) ? clean(UPLOAD_URL . $editItem['image']) : '' ?>" alt="Item image preview" <?= empty($editItem['image']) ? 'hidden' : '' ?>>
                                    <span id="previewImagePlaceholder" <?= !empty($editItem['image']) ? 'hidden' : '' ?>>Image preview</span>
                                </div>
                                <div class="preview-item-name" id="previewItemName">Untitled Item</div>
                                <div class="preview-meta">
                                    <span class="preview-pill" id="previewCategory">Unassigned Category</span>
                                    <span class="preview-pill" id="previewBranch">Campus Not Set</span>
                                </div>
                                <dl class="preview-list">
                                    <div><dt>Supplier</dt><dd id="previewSupplier">Not assigned</dd></div>
                                    <div><dt>Department</dt><dd id="previewSection">Unassigned department</dd></div>
                                    <div><dt>Section / Unit</dt><dd id="previewDepartment">Unassigned section/unit</dd></div>
                                    <div><dt>Current Stock</dt><dd id="previewStock">0 units</dd></div>
                                    <div><dt>Estimated Value</dt><dd id="previewValue">UGX 0</dd></div>
                                    <div><dt>Model / Specs</dt><dd id="previewBrandModel">—</dd></div>
                                    <div><dt>Purchased</dt><dd id="previewPurchaseDate">—</dd></div>
                                    <div><dt>Asset Code</dt><dd id="previewAssetCode">—</dd></div>
                                    <div><dt>Serial No.</dt><dd id="previewSerialNumber">—</dd></div>
                                    <div><dt>QR Code</dt><dd id="previewQrCode">—</dd></div>
                                    <div><dt>Recorded by</dt><dd id="previewRecordedBy"><?= clean($recordingOfficer) ?></dd></div>
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
                <button type="button" class="btn btn-outline" id="inventorySaveContinue">Save & Continue</button>
                <button type="submit" class="btn btn-primary"><?= $editItem?'Update Item':'Submit Inventory' ?></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
const itemFilterRows = <?= json_encode(array_map(static fn($row) => [
    'branch' => (int)$row['branch_id'],
    'category' => (string)$row['category_name'],
    'section' => $row['section_id'] !== null ? (int)$row['section_id'] : null,
    'department' => $row['department_id'] !== null ? (int)$row['department_id'] : null,
    'supplier' => $row['supplier_id'] !== null ? (int)$row['supplier_id'] : null,
    'status' => (string)$row['stock_status'],
    'asset_status' => (string)$row['asset_status'],
    'condition' => (string)$row['asset_condition'],
], $filterRows), JSON_UNESCAPED_SLASHES) ?>;
const itemFilterLabels = <?= json_encode($filterOptionLabels, JSON_UNESCAPED_SLASHES) ?>;
const itemFilterFixedBranch = <?= $isAdmin ? 'null' : (int)$branchId ?>;
const inventoryFeedback = <?= json_encode($inventoryFeedback, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const inventoryPrintGeneratedBy = <?= json_encode(trim(($user['full_name'] ?? '') . (($user['role'] ?? '') ? ', ' . $user['role'] : '')), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

function initSmartItemFilters() {
    const fields = {
        branch: document.getElementById('branchSelect'),
        category: document.getElementById('categorySelect'),
        section: document.getElementById('sectionSelect'),
        department: document.getElementById('departmentSelect'),
        supplier: document.getElementById('supplierSelect'),
        status: document.getElementById('statusSelect'),
        asset_status: document.getElementById('assetStatusSelect'),
        condition: document.getElementById('conditionSelect'),
    };
    const order = ['branch', 'section', 'department', 'category', 'supplier', 'status', 'asset_status', 'condition'];
    const optionSources = {
        category: itemFilterLabels.categories,
        section: itemFilterLabels.sections,
        department: itemFilterLabels.departments,
        supplier: itemFilterLabels.suppliers,
        status: itemFilterLabels.stock_statuses,
    };
    const defaultLabels = {
        category: 'All Categories',
        section: 'All Departments',
        department: 'All Sections / Units',
        supplier: 'All Suppliers',
        status: 'All Stock Levels',
        asset_status: 'All Asset Statuses',
        condition: 'All Conditions',
    };
    const valueOf = (key) => {
        if (key === 'branch' && itemFilterFixedBranch !== null) {
            return String(itemFilterFixedBranch);
        }
        return fields[key] ? fields[key].value : '';
    };
    const matchesBefore = (row, targetKey) => {
        const targetIndex = order.indexOf(targetKey);
        return order.slice(0, targetIndex).every((key) => {
            const selected = valueOf(key);
            return !selected || String(row[key]) === selected;
        });
    };
    const uniqueValues = (key) => {
        const values = new Set();
        itemFilterRows.forEach((row) => {
            if (matchesBefore(row, key) && row[key] !== null && row[key] !== '') {
                values.add(String(row[key]));
            }
        });
        return values;
    };
    const labelFor = (key, value) => {
        if (key === 'asset_status' || key === 'condition') {
            return value;
        }
        const source = optionSources[key] || [];
        const option = source.find((item) => String(item.id) === String(value));
        return option ? option.label : value;
    };
    const renderSelect = (key) => {
        const select = fields[key];
        if (!select) {
            return;
        }
        const selected = select.value;
        const values = Array.from(uniqueValues(key)).sort((a, b) => labelFor(key, a).localeCompare(labelFor(key, b)));
        select.innerHTML = '';
        select.add(new Option(defaultLabels[key], ''));
        values.forEach((value) => select.add(new Option(labelFor(key, value), value)));
        select.value = selected && values.includes(String(selected)) ? selected : '';
        select.disabled = values.length === 0;
    };
    const refreshAfter = (changedKey = 'branch') => {
        const start = Math.max(1, order.indexOf(changedKey) + 1);
        order.slice(start).forEach(renderSelect);
    };
    order.forEach((key) => {
        if (fields[key]) {
            fields[key].addEventListener('change', () => refreshAfter(key));
        }
    });
    order.slice(1).forEach(renderSelect);
}

document.addEventListener('DOMContentLoaded', function () {
    initSmartItemFilters();

    function dismissInventoryFeedback() {
        const feedback = document.getElementById('inventoryFeedback');
        if (!feedback) {
            return;
        }
        feedback.classList.add('dismiss');
        window.setTimeout(() => feedback.remove(), 320);
    }

    document.querySelector('.inventory-feedback-close')?.addEventListener('click', dismissInventoryFeedback);

    if (inventoryFeedback) {
        const feedbackRow = document.querySelector('[data-inventory-feedback-row="true"]');
        if (feedbackRow) {
            feedbackRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        const toast = document.createElement('div');
        toast.className = `inventory-toast inventory-toast-${inventoryFeedback.type || 'success'}`;
        toast.setAttribute('role', 'status');
        const dot = document.createElement('span');
        dot.className = 'inventory-toast-dot';
        dot.setAttribute('aria-hidden', 'true');
        const copy = document.createElement('div');
        const title = document.createElement('strong');
        title.textContent = inventoryFeedback.message || 'Inventory change saved.';
        const detail = document.createElement('small');
        detail.textContent = inventoryFeedback.code ? `Code ${inventoryFeedback.code}` : 'Inventory list updated';
        const closeToast = document.createElement('button');
        closeToast.type = 'button';
        closeToast.setAttribute('aria-label', 'Dismiss notification');
        closeToast.textContent = '×';
        closeToast.addEventListener('click', () => toast.remove());
        copy.append(title, detail);
        toast.append(dot, copy, closeToast);
        document.body.appendChild(toast);
        window.setTimeout(() => toast.classList.add('show'), 30);
        window.setTimeout(() => toast.remove(), 5600);
        window.setTimeout(dismissInventoryFeedback, 5200);
    }

    const deleteItemId = document.getElementById('deleteItemId');
    const deleteItemName = document.getElementById('deleteItemName');
    const deleteItemCode = document.getElementById('deleteItemCode');
    const deleteItemCategory = document.getElementById('deleteItemCategory');
    const deleteItemStock = document.getElementById('deleteItemStock');
    const cancelDeleteItem = document.getElementById('cancelDeleteItem');
    const confirmDeleteItem = document.getElementById('confirmDeleteItem');
    const deleteItemForm = document.getElementById('deleteItemForm');

    document.querySelectorAll('.js-delete-item').forEach(button => {
        button.addEventListener('click', function () {
            if (!deleteItemId || !deleteItemName) return;
            const stock = parseInt(this.dataset.itemStock || '0', 10);
            const unit = this.dataset.itemUnit || 'unit';

            deleteItemId.value = this.dataset.itemId || '';
            deleteItemName.textContent = this.dataset.itemName || 'This item';
            if (deleteItemCode) deleteItemCode.textContent = this.dataset.itemCode || '-';
            if (deleteItemCategory) deleteItemCategory.textContent = this.dataset.itemCategory || '-';
            if (deleteItemStock) deleteItemStock.textContent = stock.toLocaleString() + ' ' + unit + (stock === 1 ? '' : 's');
            openModal('deleteItemModal');
            if (cancelDeleteItem) cancelDeleteItem.focus();
        });
    });

    if (cancelDeleteItem) {
        cancelDeleteItem.addEventListener('click', function () {
            closeModal('deleteItemModal');
        });
    }

    if (deleteItemForm && confirmDeleteItem) {
        deleteItemForm.addEventListener('submit', function () {
            confirmDeleteItem.disabled = true;
            confirmDeleteItem.textContent = 'Removing...';
        });
    }

    const stepButtons = Array.from(document.querySelectorAll('.wizard-step-btn'));
    const stepPanels = Array.from(document.querySelectorAll('.wizard-step-panel'));
    const stepOrder = stepButtons.map(btn => btn.dataset.step);
    const saveContinueBtn = document.getElementById('inventorySaveContinue');
    const inventoryForm = document.getElementById('inventoryItemForm');
    const submitBtn = inventoryForm?.querySelector('button[type="submit"]');
    const existingImageUrl = document.getElementById('reviewImagePreview')?.getAttribute('src') || '';
    let selectedImageUrl = '';

    function showStep(step) {
        stepButtons.forEach(btn => btn.classList.toggle('active', btn.dataset.step === step));
        stepPanels.forEach(panel => panel.classList.toggle('active', panel.dataset.stepPanel === step));
        if (saveContinueBtn) {
            saveContinueBtn.textContent = step === 'review' ? 'Back to Documents' : 'Save & Continue';
            saveContinueBtn.disabled = false;
        }
    }

    stepButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            showStep(this.dataset.step);
        });
    });

    if (saveContinueBtn) {
        saveContinueBtn.addEventListener('click', function () {
            const currentIndex = stepButtons.findIndex(btn => btn.classList.contains('active'));
            const currentStep = stepOrder[currentIndex] || stepOrder[0];
            const currentPanel = stepPanels.find(panel => panel.dataset.stepPanel === currentStep);
            if (currentStep === 'review') {
                showStep('documents');
                document.querySelector('#addItemModal .modal-body')?.scrollTo({ top: 0, behavior: 'smooth' });
                return;
            }
            validateHierarchy();
            const invalidField = currentPanel ? Array.from(currentPanel.querySelectorAll('input, select, textarea')).find(field => !field.checkValidity()) : null;

            if (invalidField) {
                invalidField.reportValidity();
                invalidField.focus();
                return;
            }

            const nextStep = stepOrder[Math.min(currentIndex + 1, stepOrder.length - 1)];
            showStep(nextStep);
            document.querySelector('#addItemModal .modal-body')?.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    function formatCurrency(value) {
        const num = parseFloat(value) || 0;
        return 'UGX ' + num.toLocaleString();
    }

    function updatePreview() {
        const name = document.getElementById('previewNameInput')?.value?.trim() || 'Untitled Item';
        const category = document.getElementById('previewCategoryInput')?.selectedOptions[0]?.text || 'Unassigned Category';
        const section = document.getElementById('previewSectionInput')?.selectedOptions[0]?.text || 'Unassigned department';
        const department = document.getElementById('previewDeptInput')?.selectedOptions[0]?.text || 'Unassigned section/unit';
        const branchInput = document.getElementById('previewBranchInput');
        const branch = branchInput?.selectedOptions?.[0]?.text || branchInput?.dataset?.label || 'Campus Not Set';
        const supplier = document.getElementById('previewSupplierInput')?.selectedOptions[0]?.text || 'Not assigned';
        const unit = document.getElementById('previewUnitInput')?.value || 'unit';
        const stock = parseInt(document.getElementById('previewCurrentStockInput')?.value || '0', 10);
        const price = parseFloat(document.getElementById('previewPriceInput')?.value || '0');
        const brandModel = document.getElementById('previewBrandModelInput')?.value?.trim() || '—';
        const purchaseDate = document.getElementById('previewPurchaseDateInput')?.value || '—';
        const assetCode = document.getElementById('previewAssetCodeInput')?.value?.trim() || '—';
        const serialNumber = document.getElementById('previewSerialNumberInput')?.value?.trim() || '—';
        const qrCode = document.getElementById('previewQrCodeInput')?.value?.trim() || '—';
        const serialHint = document.getElementById('serialNumberHint');
        const serializedCategory = /asset|computer|equipment|ict|instrument|laptop|machine|meter|motor|panel|printer|pump|robot|server|tool|vehicle/i.test(category);

        document.getElementById('previewItemName').textContent = name;
        document.getElementById('previewCategory').textContent = category;
        document.getElementById('previewSection').textContent = section;
        document.getElementById('previewDepartment').textContent = department;
        document.getElementById('previewBranch').textContent = branch;
        document.getElementById('previewSupplier').textContent = supplier;
        document.getElementById('previewStock').textContent = stock + ' ' + unit + (stock === 1 ? '' : 's');
        document.getElementById('previewValue').textContent = formatCurrency(price * Math.max(stock, 1));
        document.getElementById('previewBrandModel').textContent = brandModel;
        document.getElementById('previewPurchaseDate').textContent = purchaseDate;
        document.getElementById('previewAssetCode').textContent = assetCode;
        document.getElementById('previewSerialNumber').textContent = serialNumber;
        document.getElementById('previewQrCode').textContent = qrCode;
        if (serialHint) {
            serialHint.textContent = serializedCategory
                ? 'Recommended for this category when the item has a manufacturer or device serial.'
                : 'Optional. Use only when this item has a manufacturer or device serial.';
        }
        document.getElementById('reviewItemName').textContent = name;
        document.getElementById('reviewItemContext').textContent = `${branch} • ${category} • ${stock} ${unit}${stock === 1 ? '' : 's'}`;
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[char]));
    }

    function printInventorySummary() {
        updatePreview();
        const name = document.getElementById('previewItemName')?.textContent || 'Untitled Item';
        const category = document.getElementById('previewCategory')?.textContent || 'Unassigned Category';
        const campus = document.getElementById('previewBranch')?.textContent || 'Campus Not Set';
        const description = inventoryForm?.querySelector('textarea[name="description"]')?.value?.trim() || 'No description provided.';
        const itemCode = inventoryForm?.querySelector('input[name="item_code"]')?.value?.trim() || 'Auto-generated / not assigned';
        const generatedBy = inventoryPrintGeneratedBy;
        const image = document.getElementById('previewImage');
        const imageSrc = image && !image.hidden && image.getAttribute('src') ? image.getAttribute('src') : '';
        const rows = Array.from(document.querySelectorAll('.preview-list > div')).map((row) => {
            const label = row.querySelector('dt')?.textContent?.trim() || '';
            const value = row.querySelector('dd')?.textContent?.trim() || '—';
            return `<tr><th>${escapeHtml(label)}</th><td>${escapeHtml(value)}</td></tr>`;
        }).join('');
        const printWindow = window.open('', 'inventorySummaryPrint', 'width=900,height=720');

        if (!printWindow) {
            window.print();
            return;
        }

        printWindow.document.write(`<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>${escapeHtml(name)} - Inventory Summary</title>
<style>
@page { size: A4 portrait; margin: 14mm; }
* { box-sizing: border-box; }
body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #0A1628; background: #fff; font-size: 12px; }
.sheet { width: 100%; }
.header { display: flex; align-items: center; gap: 14px; padding-bottom: 12px; border-bottom: 2px solid #0A1628; margin-bottom: 16px; }
.brand { width: 64px; height: 64px; object-fit: contain; border: 1px solid #e2e8f0; border-radius: 8px; padding: 5px; }
.header h1 { margin: 0 0 4px; font-size: 21px; line-height: 1.15; }
.header p { margin: 2px 0; color: #475569; font-size: 11px; }
.summary { display: grid; grid-template-columns: 240px 1fr; gap: 18px; align-items: start; }
.image-frame { width: 100%; aspect-ratio: 4 / 3; border: 1px solid #cbd5e1; border-radius: 10px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #f8fafc; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; }
.image-frame img { width: 100%; height: 100%; object-fit: cover; display: block; }
.title { margin-bottom: 12px; }
.title h2 { margin: 0 0 6px; font-size: 18px; line-height: 1.2; }
.pills { display: flex; flex-wrap: wrap; gap: 6px; }
.pill { display: inline-flex; padding: 5px 8px; border-radius: 999px; background: #e0f2fe; color: #075985; font-size: 10px; font-weight: 800; }
.section-title { margin: 16px 0 8px; font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #334155; }
table { width: 100%; border-collapse: collapse; border: 1px solid #cbd5e1; }
th, td { padding: 8px 10px; border-top: 1px solid #e2e8f0; vertical-align: top; }
tr:first-child th, tr:first-child td { border-top: 0; }
th { width: 34%; background: #f8fafc; text-align: left; text-transform: uppercase; letter-spacing: .05em; font-size: 10px; color: #475569; }
td { font-weight: 700; }
.description { border: 1px solid #cbd5e1; border-radius: 10px; padding: 10px 12px; line-height: 1.45; white-space: pre-wrap; }
.footer { margin-top: 18px; padding-top: 10px; border-top: 1px solid #cbd5e1; color: #64748b; font-size: 10px; display: flex; justify-content: space-between; gap: 12px; }
@media print { .sheet { break-inside: avoid; } }
</style>
</head>
<body>
<main class="sheet">
    <header class="header">
        <img class="brand" src="<?= BASE_URL ?>assets/img/uiri-logo.webp" alt="UIRI">
        <div>
            <h1>Inventory Item Summary</h1>
            <p>Uganda Industrial Research Institute</p>
            <p>Summary generated by: ${escapeHtml(generatedBy || 'Current user')} · ${escapeHtml(new Date().toLocaleString())}</p>
        </div>
    </header>
    <section class="summary">
        <div class="image-frame">${imageSrc ? `<img src="${escapeHtml(imageSrc)}" alt="${escapeHtml(name)}">` : 'No image selected'}</div>
        <div>
            <div class="title">
                <h2>${escapeHtml(name)}</h2>
                <div class="pills">
                    <span class="pill">${escapeHtml(category)}</span>
                    <span class="pill">${escapeHtml(campus)}</span>
                    <span class="pill">${escapeHtml(itemCode)}</span>
                </div>
            </div>
            <div class="section-title">Inventory Details</div>
            <table>${rows}</table>
        </div>
    </section>
    <div class="section-title">Description</div>
    <div class="description">${escapeHtml(description)}</div>
    <footer class="footer">
        <span>Generated from UIRI IMS inventory add/edit preview.</span>
        <span>${escapeHtml(campus)}</span>
    </footer>
</main>
</body>
</html>`);
        printWindow.document.close();
        printWindow.focus();
        const printNow = () => {
            printWindow.print();
            printWindow.close();
        };
        const printImage = printWindow.document.querySelector('.image-frame img');
        if (printImage && !printImage.complete) {
            printImage.addEventListener('load', printNow, { once: true });
            printImage.addEventListener('error', printNow, { once: true });
        } else {
            window.setTimeout(printNow, 150);
        }
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

    function validateHierarchy() {
        const branchSelect = document.getElementById('previewBranchInput');
        const categorySelect = document.getElementById('previewCategoryInput');
        const sectionSelect = document.getElementById('previewSectionInput');
        const deptSelect = document.getElementById('previewDeptInput');
        const branchId = branchSelect?.value || '';
        const categoryOption = categorySelect?.selectedOptions?.[0];
        const sectionOption = sectionSelect?.selectedOptions?.[0];
        const deptOption = deptSelect?.selectedOptions?.[0];

        categorySelect?.setCustomValidity('');
        sectionSelect?.setCustomValidity('');
        deptSelect?.setCustomValidity('');

        if (categoryOption?.value && branchId && categoryOption.dataset.branch !== branchId) {
            categorySelect.setCustomValidity('Choose a category that belongs to the selected campus.');
        }
        if (sectionOption?.value && branchId && sectionOption.dataset.branch !== branchId) {
            sectionSelect.setCustomValidity('Choose a department that belongs to the selected campus.');
        }
        if (deptOption?.value && sectionSelect?.value && deptOption.dataset.section !== sectionSelect.value) {
            deptSelect.setCustomValidity('Choose a section/unit that belongs to the selected department.');
        }
    }

    function findInvalidField() {
        const fields = Array.from(inventoryForm?.querySelectorAll('input, select, textarea') || [])
            .filter(field => !field.disabled && field.type !== 'hidden');
        return fields.find(field => !field.checkValidity()) || null;
    }

    function showFieldStep(field) {
        const panel = field?.closest('.wizard-step-panel');
        if (panel?.dataset.stepPanel) {
            showStep(panel.dataset.stepPanel);
            document.querySelector('#addItemModal .modal-body')?.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    function showValidationMessage(field) {
        if (!field) return;
        showFieldStep(field);
        setTimeout(() => {
            field.reportValidity();
            field.focus();
        }, 0);
    }

    function updateImagePreview(url) {
        const targets = [
            ['previewImage', 'previewImagePlaceholder'],
            ['reviewImagePreview', 'reviewImagePlaceholder'],
            ['inlineImagePreviewImg', null],
        ];
        targets.forEach(([imageId, placeholderId]) => {
            const image = document.getElementById(imageId);
            const placeholder = placeholderId ? document.getElementById(placeholderId) : null;
            if (!image) return;
            if (url) {
                image.src = url;
                image.hidden = false;
                if (placeholder) placeholder.hidden = true;
            } else {
                image.removeAttribute('src');
                image.hidden = true;
                if (placeholder) placeholder.hidden = false;
            }
        });
        const inlineFrame = document.getElementById('inlineImagePreview');
        if (inlineFrame) inlineFrame.hidden = !url;
    }

    document.getElementById('previewImageInput')?.addEventListener('change', function () {
        if (selectedImageUrl) {
            URL.revokeObjectURL(selectedImageUrl);
            selectedImageUrl = '';
        }
        const file = this.files && this.files[0] ? this.files[0] : null;
        if (!file) {
            updateImagePreview(existingImageUrl);
            return;
        }
        selectedImageUrl = URL.createObjectURL(file);
        updateImagePreview(selectedImageUrl);
    });

    inventoryForm?.addEventListener('submit', function (event) {
        validateHierarchy();
        const invalidField = findInvalidField();
        if (invalidField) {
            event.preventDefault();
            showValidationMessage(invalidField);
            return;
        }
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';
        }
    });

    ['input', 'change'].forEach(eventName => {
        document.querySelectorAll('#previewNameInput, #previewCategoryInput, #previewSectionInput, #previewDeptInput, #previewBranchInput, #previewSupplierInput, #previewUnitInput, #previewCurrentStockInput, #previewMinStockInput, #previewPriceInput, #previewBrandModelInput, #previewPurchaseDateInput, #previewAssetCodeInput, #previewSerialNumberInput, #previewQrCodeInput').forEach(el => {
            el.addEventListener(eventName, updatePreview);
        });
    });

    document.getElementById('previewBranchInput')?.addEventListener('change', function () {
        filterSectionOptions();
        filterCategoryOptions();
        filterDepartmentOptions();
        validateHierarchy();
        updatePreview();
    });

    document.getElementById('previewSectionInput')?.addEventListener('change', function () {
        filterDepartmentOptions();
        validateHierarchy();
        updatePreview();
    });

    document.getElementById('previewCategoryInput')?.addEventListener('change', validateHierarchy);
    document.getElementById('previewDeptInput')?.addEventListener('change', validateHierarchy);
    document.getElementById('printInventorySummaryBtn')?.addEventListener('click', printInventorySummary);

    filterSectionOptions();
    filterCategoryOptions();
    filterDepartmentOptions();
    validateHierarchy();
    updatePreview();
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
