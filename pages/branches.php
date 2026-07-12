<?php
require_once __DIR__ . '/../includes/config.php';

requireLogin();
requireRole('Administrator');

$pdo = db();
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

// Handle delete
if ($action === 'delete' && $id) {
    verifyCsrf();
    try {
        $pdo->prepare("DELETE FROM branches WHERE id = ?")->execute([$id]);
        auditLog('DELETE', 'branches', $id, 'Deleted branch');
        setFlash('success', 'Branch deleted successfully.');
        header('Location: branches.php');
        exit;
    } catch (Exception $e) {
        setFlash('error', 'Cannot delete branch: ' . $e->getMessage());
        header('Location: branches.php');
        exit;
    }
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = normalizeUgandanPhone($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $isHq = isset($_POST['is_headquarters']) ? 1 : 0;
    
    if (!$name || !$location) {
        $error = 'Please enter branch name and location.';
    } else {
        try {
            if ($action === 'edit' && $id) {
                $pdo->prepare("UPDATE branches SET name=?, location=?, address=?, phone=?, email=?, is_headquarters=? WHERE id=?")
                    ->execute([$name, $location, $address, $phone, $email, $isHq, $id]);
                auditLog('UPDATE', 'branches', $id, "Updated branch: $name");
                setFlash('success', 'Branch updated successfully.');
            } else {
                $pdo->prepare("INSERT INTO branches (name, location, address, phone, email, is_headquarters) VALUES (?,?,?,?,?,?)")
                    ->execute([$name, $location, $address, $phone, $email, $isHq]);
                auditLog('CREATE', 'branches', $pdo->lastInsertId(), "Created branch: $name");
                setFlash('success', 'Branch created successfully.');
            }
            header('Location: branches.php');
            exit;
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Fetch data
$totalBranches = (int)$pdo->query("SELECT COUNT(*) FROM branches")->fetchColumn();
$pagination = getPagination($totalBranches, 10);
$branches = $pdo->query("SELECT * FROM branches ORDER BY is_headquarters DESC, name LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}")->fetchAll();
$branch = null;

if (($action === 'edit' || $action === 'view') && $id) {
    $stmt = $pdo->prepare("SELECT * FROM branches WHERE id = ?");
    $stmt->execute([$id]);
    $branch = $stmt->fetch();
    if (!$branch) {
        header('Location: branches.php');
        exit;
    }
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branches — UIRI IMS</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="container">
    <div class="page-header">
        <h1><?= $action === 'list' ? 'Branches' : ($action === 'edit' ? 'Edit Branch' : 'Add Branch') ?></h1>
    </div>

    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?>">
        <?= clean($flash['message']) ?>
    </div>
    <?php endif; ?>

    <?php if ($action === 'list'): ?>
    <!-- List View -->
    <div class="card">
        <div class="card-header">
            <h3>All Branches</h3>
            <a href="?action=add" class="btn btn-primary">+ Add Branch</a>
        </div>
        <div class="card-body">
            <p class="page-sub"><?= number_format($totalBranches) ?> branch(es)</p>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Location</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($branches as $b): ?>
                    <tr>
                        <td><strong><?= clean($b['name']) ?></strong></td>
                        <td><?= clean($b['location']) ?></td>
                        <td><?= clean($b['phone'] ?? '') ?></td>
                        <td><?= clean($b['email'] ?? '') ?></td>
                        <td><span class="badge <?= $b['is_headquarters'] ? 'badge-primary' : 'badge-secondary' ?>"><?= $b['is_headquarters'] ? 'HQ' : 'Branch' ?></span></td>
                        <td>
                            <a href="?action=edit&id=<?= $b['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                            <a href="?action=delete&id=<?= $b['id'] ?>&csrf_token=<?= csrfToken() ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this branch?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?= renderPaginationBar($pagination, $totalBranches, ['action', 'id']) ?>
        </div>
    </div>

    <?php else: ?>
    <!-- Add/Edit Form -->
    <div class="card">
        <div class="card-body">
            <?php if ($error): ?>
            <div class="alert alert-error"><?= clean($error) ?></div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <div class="form-grid-2">
                    <div class="form-group" style="grid-column:1/-1">
                        <label for="name">Branch Name *</label>
                        <input type="text" id="name" name="name" required value="<?= clean($branch['name'] ?? $_POST['name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="location">Location *</label>
                        <input type="text" id="location" name="location" required value="<?= clean($branch['location'] ?? $_POST['location'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" class="js-ug-phone" id="phone" name="phone" value="<?= clean($branch['phone'] ?? $_POST['phone'] ?? '') ?>" placeholder="+256 700000000" inputmode="numeric" autocomplete="tel" maxlength="14">
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="3"><?= clean($branch['address'] ?? $_POST['address'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?= clean($branch['email'] ?? $_POST['email'] ?? '') ?>">
                    </div>
                    <div class="form-group checkbox-group">
                        <label>
                            <input type="checkbox" name="is_headquarters" value="1" <?= ($branch['is_headquarters'] ?? $_POST['is_headquarters'] ?? 0) ? 'checked' : '' ?>>
                            This is the headquarters
                        </label>
                    </div>
                </div>
                <div style="margin-top:20px;">
                    <button type="submit" class="btn btn-primary">Save Branch</button>
                    <a href="branches.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
