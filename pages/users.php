<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole('Administrator');
$pageTitle = 'Users'; $activePage = 'users'; $pdo = db();

function defaultUserPassword(string $fullName): string {
    $base = strtolower(preg_replace('/[^a-z0-9]+/i', '', $fullName));
    if ($base === '') {
        $base = 'user';
    }
    if (strlen($base) < 4) {
        $base = str_pad($base, 4, 'x');
    }
    return $base . '@123';
}

function usernameExists(PDO $pdo, string $username, int $excludeUserId = 0): bool {
    if ($username === '') {
        return false;
    }

    if ($excludeUserId > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $excludeUserId]);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
    }

    return (int)$stmt->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action']??'';
    $id = (int)($_POST['user_id']??0);
    if ($action==='add'||$action==='edit') {
        $fullName = trim($_POST['full_name']??''); $emailInput = trim($_POST['email']??'');
        $email = $emailInput !== '' ? $emailInput : null;
        $username = trim($_POST['username']??''); $phone = normalizeUgandanPhone($_POST['phone']??'');
        $existingPhoto = null;
        $hasProfilePhotoUpload = !empty($_FILES['profile_photo']['name']);
        if ($action === 'edit' && $id) {
            $existingUserStmt = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
            $existingUserStmt->execute([$id]);
            $existingPhoto = $existingUserStmt->fetchColumn();
        }
        $roleId = (int)($_POST['role_id']??0); $branchId = (int)($_POST['branch_id']??0);
        $departmentId = (int)($_POST['department_id']??0); $sectionId = (int)($_POST['section_id']??0);
        $status = (int)($_POST['is_active']??1);
        $pass = $_POST['password']??'';
        if (!$fullName||!$roleId||!$branchId) { setFlash('error','Full name, role, and campus are required.'); }
        else {
            if ($sectionId) {
                $sectionCheck = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE id = ? AND branch_id = ? AND is_active = 1");
                $sectionCheck->execute([$sectionId, $branchId]);
                if (!$sectionCheck->fetchColumn()) {
                    setFlash('error', 'Selected department does not belong to the selected campus.');
                    header('Location: users.php'); exit;
                }
            }
            if ($departmentId) {
                $deptCheck = $pdo->prepare("SELECT COUNT(*) FROM departments d JOIN sections s ON d.section_id = s.id WHERE d.id = ? AND d.section_id = ? AND s.branch_id = ? AND d.is_active = 1");
                $deptCheck->execute([$departmentId, $sectionId, $branchId]);
                if (!$deptCheck->fetchColumn()) {
                    setFlash('error', 'Selected section/unit does not belong to the selected department and campus.');
                    header('Location: users.php'); exit;
                }
            }
            if ($hasProfilePhotoUpload) {
                $uploadError = '';
                if (!validateProfilePhotoUpload($_FILES['profile_photo'], $uploadError)) {
                    setFlash('error', $uploadError);
                    header('Location: users.php'); exit;
                }
            }
            if ($action==='add') {
                // Auto-generate username/password when left blank
                if (!$username || usernameExists($pdo, $username)) {
                    $username = generateUsername($fullName, $branchId);
                }
                if (!$pass) {
                    $pass = defaultUserPassword($fullName);
                }

                if (strlen($pass) < 8) { setFlash('error','Password must be at least 8 characters.'); }
                else {
                    $hash = password_hash($pass, PASSWORD_BCRYPT);
                    try {
                        $pdo->prepare("INSERT INTO users (branch_id,section_id,department_id,role_id,full_name,email,username,password,phone,is_active,profile_photo) VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute([$branchId,$sectionId?:null,$departmentId?:null,$roleId,$fullName,$email,$username,$hash,$phone,$status,null]);
                    } catch (PDOException $e) {
                        if (($e->errorInfo[1] ?? null) !== 1062) {
                            throw $e;
                        }
                        $username = generateUsername($fullName, $branchId);
                        $pdo->prepare("INSERT INTO users (branch_id,section_id,department_id,role_id,full_name,email,username,password,phone,is_active,profile_photo) VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute([$branchId,$sectionId?:null,$departmentId?:null,$roleId,$fullName,$email,$username,$hash,$phone,$status,null]);
                    }
                    $newId = $pdo->lastInsertId();
                    if ($hasProfilePhotoUpload) {
                        $profilePhoto = saveProfilePhotoUpload($_FILES['profile_photo'], (int)$newId);
                        if ($profilePhoto) {
                            $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?")->execute([$profilePhoto, $newId]);
                        }
                    }
                    auditLog('ADD_USER','users',$newId,"Added user: $username");

                    $sent = false;
                    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $subject = SITE_SHORT . ' — Account created';
                        $body = "Hello {$fullName},\n\nAn account has been created for you on " . SITE_NAME . ".\n\nUsername: {$username}\nPassword: {$pass}\n\nPlease sign in and change your password after your first login.";
                        $sent = sendMail($email, $subject, $body);
                    }

                    if ($sent) {
                        setFlash('success',"User '$fullName' added successfully.");
                    } else {
                        setFlash('success',"User '$fullName' added successfully.");
                    }
                }
            } else {
                if (!$username) {
                    $username = generateUsername($fullName, $branchId);
                } elseif (usernameExists($pdo, $username, $id)) {
                    setFlash('error', "Username '$username' is already in use. Please choose another username.");
                    header('Location: users.php'); exit;
                }
                $profilePhoto = $existingPhoto;
                if ($hasProfilePhotoUpload) {
                    $uploadedPhoto = saveProfilePhotoUpload($_FILES['profile_photo'], $id);
                    if (!$uploadedPhoto) {
                        setFlash('error','The user details are valid, but the profile photo could not be saved. Please try another image.');
                        header('Location: users.php'); exit;
                    }
                    $profilePhoto = $uploadedPhoto;
                }
                $pdo->prepare("UPDATE users SET branch_id=?,section_id=?,department_id=?,role_id=?,full_name=?,email=?,username=?,phone=?,is_active=?,profile_photo=? WHERE id=?")->execute([$branchId,$sectionId?:null,$departmentId?:null,$roleId,$fullName,$email,$username,$phone,$status,$profilePhoto,$id]);
                if ($pass && strlen($pass)>=8) {
                    $hash=password_hash($pass,PASSWORD_BCRYPT);
                    $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash,$id]);
                }
                auditLog('EDIT_USER','users',$id,"Updated user: $username");
                setFlash('success',"User '$fullName' updated.");
            }
        }
    }
    if ($action==='toggle') {
        $pdo->prepare("UPDATE users SET is_active=1-is_active WHERE id=? AND id!=1")->execute([$id]);
        setFlash('success','User status updated.');
    }
    if ($action==='delete') {
        if ($id === 1) {
            setFlash('error','The administrator account cannot be removed.');
        } elseif ($id === (int)($_SESSION['user_id'] ?? 0)) {
            setFlash('error','You cannot delete the account you are currently using.');
        } else {
            $deleteInfoStmt = $pdo->prepare("SELECT u.full_name, u.profile_photo, b.name AS branch_name FROM users u JOIN branches b ON u.branch_id = b.id WHERE u.id = ? AND u.id != 1");
            $deleteInfoStmt->execute([$id]);
            $deleteInfo = $deleteInfoStmt->fetch();
            if (!$deleteInfo) {
                setFlash('error','Unable to find the selected user.');
            } else {
                $replacementUserId = (int)($_SESSION['user_id'] ?? 1);
                $runOptionalCleanup = function (string $sql, array $params = []) use ($pdo): void {
                    try {
                        $pdo->prepare($sql)->execute($params);
                    } catch (Throwable $ignored) {
                    }
                };
                try {
                    $pdo->beginTransaction();
                    $runOptionalCleanup("DELETE FROM user_permissions WHERE user_id = ?", [$id]);
                    $runOptionalCleanup("DELETE FROM notifications WHERE user_id = ?", [$id]);
                    $runOptionalCleanup("UPDATE departments SET section_manager_id = NULL WHERE section_manager_id = ?", [$id]);
                    $runOptionalCleanup("UPDATE departments SET department_manager_id = NULL WHERE department_manager_id = ?", [$id]);
                    $runOptionalCleanup("UPDATE inventory_items SET created_by = NULL WHERE created_by = ?", [$id]);
                    $runOptionalCleanup("UPDATE login_history SET user_id = NULL WHERE user_id = ?", [$id]);
                    $runOptionalCleanup("UPDATE audit_log SET user_id = NULL WHERE user_id = ?", [$id]);
                    $runOptionalCleanup("UPDATE settings SET updated_by = NULL WHERE updated_by = ?", [$id]);
                    $runOptionalCleanup("UPDATE reports SET generated_by = NULL WHERE generated_by = ?", [$id]);
                    $runOptionalCleanup("UPDATE inventory_requests SET approved_by = NULL WHERE approved_by = ?", [$id]);
                    $runOptionalCleanup("UPDATE inventory_requests SET processed_by = NULL WHERE processed_by = ?", [$id]);
                    $runOptionalCleanup("UPDATE inventory_requests SET user_id = ? WHERE user_id = ?", [$replacementUserId, $id]);
                    $runOptionalCleanup("UPDATE stock_transactions SET user_id = ? WHERE user_id = ?", [$replacementUserId, $id]);
                    $runOptionalCleanup("UPDATE transfers SET approved_by = NULL WHERE approved_by = ?", [$id]);
                    $runOptionalCleanup("UPDATE transfers SET requested_by = ? WHERE requested_by = ?", [$replacementUserId, $id]);
                    $runOptionalCleanup("UPDATE equipment_maintenance SET assigned_to = NULL WHERE assigned_to = ?", [$id]);

                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND id != 1");
                    $stmt->execute([$id]);
                    if ($stmt->rowCount()) {
                        auditLog('DELETE_USER','users',$id,"Deleted user: {$deleteInfo['full_name']} from {$deleteInfo['branch_name']}");
                        $pdo->commit();
                        $photo = $deleteInfo['profile_photo'] ?? '';
                        if ($photo && strpos($photo, 'uploads/profiles/') === 0) {
                            $photoPath = __DIR__ . '/../' . $photo;
                            if (is_file($photoPath)) {
                                @unlink($photoPath);
                            }
                        }
                        setFlash('success', $deleteInfo['full_name'] . ' was permanently deleted from ' . $deleteInfo['branch_name'] . ' campus.');
                    } else {
                        $pdo->rollBack();
                        setFlash('error','Unable to remove the selected user.');
                    }
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    setFlash('error','Unable to permanently delete this user because related records still depend on the account.');
                }
            }
        }
    }
    header('Location: users.php'); exit;
}

$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$pagination = getPagination($totalUsers, 10);
$users = $pdo->query("SELECT u.*,r.name AS role_name,b.name AS branch_name,s.name AS section_name,d.name AS department_name FROM users u JOIN roles r ON u.role_id=r.id JOIN branches b ON u.branch_id=b.id LEFT JOIN sections s ON u.section_id=s.id LEFT JOIN departments d ON u.department_id=d.id ORDER BY u.full_name LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}")->fetchAll();
$roles = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll();
$branches = $pdo->query("SELECT * FROM branches ORDER BY is_headquarters DESC")->fetchAll();
$departments = $pdo->query("SELECT d.*, s.name AS section_name, b.id AS branch_id, b.name AS branch_name FROM departments d JOIN sections s ON d.section_id = s.id JOIN branches b ON s.branch_id = b.id WHERE d.is_active = 1 ORDER BY b.name, s.name, d.name")->fetchAll();
$sections = $pdo->query("SELECT s.*, b.name AS branch_name FROM sections s JOIN branches b ON s.branch_id = b.id WHERE s.is_active = 1 ORDER BY b.name, s.name")->fetchAll();
$editUser = null;
if (isset($_GET['edit'])) { $es=$pdo->prepare("SELECT * FROM users WHERE id=?"); $es->execute([(int)$_GET['edit']]); $editUser=$es->fetch(); }

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div><h1 class="page-title">User Management</h1><p class="page-sub"><?= number_format($totalUsers) ?> users registered</p></div>
    <div class="page-actions"><button class="btn btn-primary" onclick="openModal('userModal')"><svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add User</button></div>
</div>
<div class="card users-table-card">
    <div class="card-body p0">
        <div class="table-responsive users-table-wrap">
        <table class="data-table users-table">
            <thead><tr><th>#</th><th>Name</th><th>Username</th><th>Role</th><th>Branch</th><th>Department</th><th>Section / Unit</th><th>Phone</th><th>Last Login</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $i=>$u): ?>
            <tr>
                <td><?= $pagination['offset'] + $i + 1 ?></td>
                <td><div class="item-cell"><div class="user-avatar-sm">
                    <img src="<?= clean(profilePhotoUrl($u)) ?>" alt="<?= clean($u['full_name']) ?> avatar">
                </div><div><span class="item-name"><?= clean($u['full_name']) ?></span><span class="item-code"><?= clean($u['email']) ?></span></div></div></td>
                <td><code><?= clean($u['username']) ?></code></td>
                <td><span class="badge badge-purple"><?= clean($u['role_name']) ?></span></td>
                <td><?= clean($u['branch_name']) ?></td>
                <td><?= clean($u['section_name']?:'—') ?></td>
                <td><?= clean($u['department_name']?:'—') ?></td>
                <td><?= clean($u['phone']?:'—') ?></td>
                <td><?= $u['last_login'] ? formatDateTime($u['last_login'], true) : 'Never' ?></td>
                <td><span class="badge <?= $u['is_active']?'badge-success':'badge-danger' ?>"><?= $u['is_active']?'Active':'Inactive' ?></span></td>
                <td>
                    <div class="action-btns">
                        <a href="users.php?edit=<?= $u['id'] ?>" class="btn-icon" title="Edit"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
                        <?php if ($u['id']!=1): ?>
                        <button
                            type="button"
                            class="btn-icon btn-icon-danger js-delete-user"
                            title="Delete permanently"
                            aria-label="Delete <?= clean($u['full_name']) ?> permanently"
                            data-user-id="<?= $u['id'] ?>"
                            data-user-name="<?= clean($u['full_name']) ?>"
                            data-user-campus="<?= clean($u['branch_name']) ?>">
                            <svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                        </button>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn-icon" title="<?= $u['is_active'] ? 'Deactivate user' : 'Activate user' ?>" aria-label="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?> <?= clean($u['full_name']) ?>"><svg viewBox="0 0 24 24"><path d="M12 2v10"/><path d="M18.4 6.6a9 9 0 11-12.8 0"/></svg></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?= renderPaginationBar($pagination, $totalUsers, ['edit']) ?>
    </div>
</div>

<div class="modal-overlay" id="deleteUserModal" role="dialog" aria-modal="true" aria-labelledby="deleteUserTitle">
    <div class="modal delete-user-modal">
        <div class="delete-user-topline"></div>
        <div class="delete-user-body">
            <div class="delete-user-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
            </div>
            <div class="delete-user-copy">
                <span class="delete-user-kicker">Permanent account deletion</span>
                <h3 id="deleteUserTitle">Confirm user deletion</h3>
                <p><strong id="deleteUserName">This user</strong> will be permanently deleted from <strong id="deleteUserCampus">this campus</strong> campus. Do you want to continue?</p>
            </div>
        </div>
        <div class="delete-user-warning">
            This removes the account immediately. The user will no longer be able to sign in.
        </div>
        <form method="POST" id="deleteUserForm" class="delete-user-actions">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" id="deleteUserId" value="">
            <button type="button" class="btn btn-outline delete-user-cancel" id="cancelDeleteUser">No, keep account</button>
            <button type="submit" class="btn btn-danger delete-user-confirm" id="confirmDeleteUser">Yes, delete permanently</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="userModal" <?= $editUser?'style="display:flex"':'' ?>>
    <div class="modal modal-lg">
        <div class="modal-header"><h3><?= $editUser?'Edit User':'Add User' ?></h3><button class="modal-close" onclick="closeModal('userModal')">×</button></div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="<?= $editUser?'edit':'add' ?>">
            <?php if ($editUser): ?><input type="hidden" name="user_id" value="<?= $editUser['id'] ?>"><?php endif; ?>
            <div class="modal-body">
                <div class="form-grid-2">
                    <div class="form-group"><label>Full Name *</label><input type="text" id="modal_full_name" name="full_name" required value="<?= clean($editUser['full_name']??'') ?>"></div>
                    <div class="form-group"><label>Username *</label><input type="text" id="modal_username" name="username" value="<?= clean($editUser['username']??'') ?>"><small class="form-note">Leave blank to auto-generate.</small></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= clean($editUser['email']??'') ?>"><small class="form-note">Optional.</small></div>
                    <div class="form-group"><label>Phone</label><input type="tel" class="js-ug-phone" name="phone" value="<?= clean($editUser['phone']??'') ?>" placeholder="+256 700000000" inputmode="numeric" autocomplete="tel" maxlength="14"></div>
                    <div class="form-group"><label>Role *</label>
                        <select name="role_id" required>
                            <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= ($editUser['role_id']??0)==$r['id']?'selected':'' ?>><?= clean($r['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Campus *</label>
                        <select id="modal_branch_id" name="branch_id" required>
                            <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= ($editUser['branch_id']??$user['branch_id'])==$b['id']?'selected':'' ?>><?= clean($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Department</label>
                        <select id="modal_section_id" name="section_id">
                            <option value="0">-- None --</option>
                            <?php foreach ($sections as $s): ?>
                            <option value="<?= $s['id'] ?>" data-branch="<?= $s['branch_id'] ?>" <?= ($editUser['section_id']??0)==$s['id']?'selected':'' ?>><?= clean($s['name']) ?> (<?= clean($s['branch_name']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Section / Unit</label>
                        <select id="modal_department_id" name="department_id">
                            <option value="0">-- None --</option>
                            <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>" data-section="<?= $d['section_id'] ?>" data-branch="<?= $d['branch_id'] ?>" <?= ($editUser['department_id']??0)==$d['id']?'selected':'' ?>><?= clean($d['name']) ?> (<?= clean($d['section_name']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Status</label>
                        <select name="is_active">
                            <option value="1" <?= (($editUser['is_active']??1)==1?'selected':'') ?>>Active</option>
                            <option value="0" <?= (($editUser['is_active']??1)==0?'selected':'') ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Profile Photo</label>
                        <input type="file" name="profile_photo" accept="image/*">
                        <small class="form-note">Leave blank to keep the current photo or use the default avatar.</small>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Password <?= $editUser?'(leave blank to keep current)':'(leave blank to auto-generate)' ?></label>
                        <input type="text" id="modal_password" name="password" minlength="8" placeholder="Min 8 characters">
                        <small class="form-note"><?= $editUser ? 'Leave blank to keep the current password.' : 'Auto-generates as fullname@123, for example joelmugole@123.' ?></small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('userModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><?= $editUser?'Update User':'Add User' ?></button>
            </div>
        </form>
    </div>
</div>
<script>
function slugify(value) {
    return value.toLowerCase().trim().replace(/[^a-z0-9]+/g, '.').replace(/(^\.|\.$)/g, '');
}
function branchCode(name) {
    if (!name) return 'xx';
    const code = name.replace(/[^A-Z]/g, '').slice(0, 2);
    return code || name.slice(0, 2).toUpperCase();
}
function defaultUserPassword(fullName) {
    let base = fullName.toLowerCase().replace(/[^a-z0-9]+/g, '');
    if (!base) base = 'user';
    if (base.length < 4) base = base.padEnd(4, 'x');
    return base + '@123';
}
function updateModalCredentials() {
    const full = document.getElementById('modal_full_name');
    const username = document.getElementById('modal_username');
    const branch = document.getElementById('modal_branch_id');
    const pwd = document.getElementById('modal_password');
    if (!full || !username || !branch) return;
    const fullName = full.value.trim();
    const action = document.querySelector('#userModal input[name="action"]')?.value || 'add';
    if (action === 'add' && pwd && fullName && (!pwd.value || pwd.dataset.autoGenerated === '1')) {
        pwd.value = defaultUserPassword(fullName);
        pwd.dataset.autoGenerated = '1';
    }
}
function filterUserOrgOptions() {
    const branch = document.getElementById('modal_branch_id');
    const department = document.getElementById('modal_section_id');
    const sectionUnit = document.getElementById('modal_department_id');
    const branchId = branch?.value || '';

    if (department) {
        Array.from(department.options).forEach(option => {
            if (!option.value || option.value === '0') {
                option.hidden = false;
                return;
            }
            option.hidden = branchId && option.dataset.branch !== branchId;
        });
        if (department.selectedOptions[0]?.hidden) {
            department.value = '0';
        }
    }

    const departmentId = department?.value || '';
    if (sectionUnit) {
        Array.from(sectionUnit.options).forEach(option => {
            if (!option.value || option.value === '0') {
                option.hidden = false;
                return;
            }
            const matchesBranch = !branchId || option.dataset.branch === branchId;
            const matchesDepartment = departmentId && departmentId !== '0' && option.dataset.section === departmentId;
            option.hidden = !(matchesBranch && matchesDepartment);
        });
        if (sectionUnit.selectedOptions[0]?.hidden || !departmentId || departmentId === '0') {
            sectionUnit.value = '0';
        }
    }
}
document.addEventListener('DOMContentLoaded', function(){
    const f = document.getElementById('modal_full_name');
    const b = document.getElementById('modal_branch_id');
    const department = document.getElementById('modal_section_id');
    if (f) f.addEventListener('input', updateModalCredentials);
    const pwd = document.getElementById('modal_password');
    if (pwd) {
        pwd.addEventListener('input', function () {
            this.dataset.autoGenerated = '0';
        });
    }
    if (b) b.addEventListener('change', function () {
        updateModalCredentials();
        filterUserOrgOptions();
    });
    if (department) department.addEventListener('change', filterUserOrgOptions);
    filterUserOrgOptions();

    const deleteModal = document.getElementById('deleteUserModal');
    const deleteUserId = document.getElementById('deleteUserId');
    const deleteUserName = document.getElementById('deleteUserName');
    const deleteUserCampus = document.getElementById('deleteUserCampus');
    const cancelDeleteUser = document.getElementById('cancelDeleteUser');
    const confirmDeleteUser = document.getElementById('confirmDeleteUser');
    document.querySelectorAll('.js-delete-user').forEach(button => {
        button.addEventListener('click', function () {
            if (!deleteModal || !deleteUserId || !deleteUserName || !deleteUserCampus) return;
            deleteUserId.value = this.dataset.userId || '';
            deleteUserName.textContent = this.dataset.userName || 'This user';
            deleteUserCampus.textContent = this.dataset.userCampus || 'this';
            openModal('deleteUserModal');
            if (cancelDeleteUser) cancelDeleteUser.focus();
        });
    });
    if (cancelDeleteUser) {
        cancelDeleteUser.addEventListener('click', function () {
            closeModal('deleteUserModal');
        });
    }
    const deleteUserForm = document.getElementById('deleteUserForm');
    if (deleteUserForm && confirmDeleteUser) {
        deleteUserForm.addEventListener('submit', function () {
            confirmDeleteUser.disabled = true;
            confirmDeleteUser.textContent = 'Deleting...';
        });
    }
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
