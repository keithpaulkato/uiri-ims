<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole('Administrator');
$pageTitle = 'Users'; $activePage = 'users'; $pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action']??'';
    $id = (int)($_POST['user_id']??0);
    if ($action==='add'||$action==='edit') {
        $fullName = trim($_POST['full_name']??''); $emailInput = trim($_POST['email']??'');
        $email = $emailInput !== '' ? $emailInput : null;
        $username = trim($_POST['username']??''); $phone = trim($_POST['phone']??'');
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
                if (!$username) {
                    $username = generateUsername($fullName, $branchId);
                }
                if (!$pass) {
                    $pass = generatePassword(10);
                }

                if (strlen($pass) < 8) { setFlash('error','Password must be at least 8 characters.'); }
                else {
                    $hash = password_hash($pass, PASSWORD_BCRYPT);
                    $pdo->prepare("INSERT INTO users (branch_id,section_id,department_id,role_id,full_name,email,username,password,phone,is_active,profile_photo) VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute([$branchId,$sectionId?:null,$departmentId?:null,$roleId,$fullName,$email,$username,$hash,$phone,$status,null]);
                    $newId = $pdo->lastInsertId();
                    if ($hasProfilePhotoUpload) {
                        $profilePhoto = saveProfilePhotoUpload($_FILES['profile_photo'], (int)$newId);
                        if ($profilePhoto) {
                            $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?")->execute([$profilePhoto, $newId]);
                        }
                    }
                    auditLog('ADD_USER','users',$newId,"Added user: $username");

                    // Create password setup token and expiry
                    $token = bin2hex(random_bytes(32));
                    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    $pdo->prepare("UPDATE users SET password_reset_token = ?, token_expiry = ? WHERE id = ?")->execute([$token, $expiry, $newId]);
                    $resetLink = BASE_URL . 'reset-password.php?token=' . urlencode($token);

                    $sent = false;
                    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $subject = SITE_SHORT . ' — Set your password';
                        $body = "Hello {$fullName},\n\nAn account has been created for you on " . SITE_NAME . ".\nPlease set your password using the link below (valid for 1 hour):\n\n" . $resetLink . "\n\nIf you did not request this, contact your administrator.";
                        $sent = sendMail($email, $subject, $body, SMTP_FROM_NAME, SMTP_FROM_EMAIL, false);
                    }

                    if ($sent) {
                        setFlash('success',"User '$fullName' added. A password setup link has been emailed to the user.");
                    } else {
                        setFlash('success',"User '$fullName' added. Email delivery is not available, so share this password setup link with the user within 1 hour: " . $resetLink);
                    }
                }
            } else {
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
        if ($id !== 1) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND id != 1");
            $stmt->execute([$id]);
            if ($stmt->rowCount()) {
                auditLog('DELETE_USER','users',$id,"Deleted user ID: $id");
                setFlash('success','User removed.');
            } else {
                setFlash('error','Unable to remove the selected user.');
            }
        } else {
            setFlash('error','The administrator account cannot be removed.');
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
                <td><?= $u['last_login'] ? date('d M Y H:i',strtotime($u['last_login'])) : 'Never' ?></td>
                <td><span class="badge <?= $u['is_active']?'badge-success':'badge-danger' ?>"><?= $u['is_active']?'Active':'Inactive' ?></span></td>
                <td>
                    <div class="action-btns">
                        <a href="users.php?edit=<?= $u['id'] ?>" class="btn-icon" title="Edit"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
                        <?php if ($u['id']!=1): ?>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this user?')">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn-icon" title="Delete"><svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg></button>
                        </form>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn-icon" title="Toggle"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
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
                    <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?= clean($editUser['phone']??'') ?>"></div>
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
                        <small class="form-note">If left blank a secure password will be generated.</small>
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
function updateModalCredentials() {
    const full = document.getElementById('modal_full_name');
    const username = document.getElementById('modal_username');
    const branch = document.getElementById('modal_branch_id');
    const pwd = document.getElementById('modal_password');
    if (!full || !username || !branch) return;
    const fullName = full.value.trim();
    const branchName = branch.options[branch.selectedIndex]?.text || '';
    if (fullName && branchName && !username.value) {
        const parts = fullName.toLowerCase().split(/\s+/).filter(Boolean);
        let uname = 'user';
        if (parts.length === 1) uname = slugify(parts[0]);
        else uname = slugify(parts[0].charAt(0) + parts[parts.length - 1]);
        uname += '.' + branchCode(branchName).toLowerCase();
        username.value = uname;
    }
    if (pwd && !pwd.value) {
        const lower = 'abcdefghijklmnopqrstuvwxyz';
        const upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        const digits = '0123456789';
        const symbols = '!@#$%*?';
        const all = lower + upper + digits + symbols;
        let p = '';
        p += upper[Math.floor(Math.random() * upper.length)];
        p += lower[Math.floor(Math.random() * lower.length)];
        p += digits[Math.floor(Math.random() * digits.length)];
        p += symbols[Math.floor(Math.random() * symbols.length)];
        for (let i = 4; i < 10; i++) p += all[Math.floor(Math.random() * all.length)];
        pwd.value = p;
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
    if (b) b.addEventListener('change', function () {
        updateModalCredentials();
        filterUserOrgOptions();
    });
    if (department) department.addEventListener('change', filterUserOrgOptions);
    filterUserOrgOptions();
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
