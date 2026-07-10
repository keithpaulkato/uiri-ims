<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
$pageTitle = 'My Profile'; $activePage = '';
$user = currentUser(); $pdo = db();
$userId = (int)($_SESSION['user_id'] ?? ($user['id'] ?? 0));

$profileStmt = $pdo->prepare("SELECT u.*,r.name AS role_name,b.name AS branch_name FROM users u JOIN roles r ON u.role_id=r.id JOIN branches b ON u.branch_id=b.id WHERE u.id=?");
$profileStmt->execute([$userId]);
$profile = $profileStmt->fetch();
if (!$profile) {
    setFlash('error', 'Profile could not be found. Please sign in again.');
    header('Location: ' . BASE_URL . 'includes/logout.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $fullName = trim($_POST['full_name']??''); $phone = trim($_POST['phone']??'');
    $oldPass = $_POST['old_password']??''; $newPass = $_POST['new_password']??''; $confirmPass = $_POST['confirm_password']??'';
    $profilePhoto = $profile['profile_photo'] ?? null;
    $photoChanged = false;
    if (!empty($_FILES['profile_photo']['name'])) {
        $uploadedPhoto = saveProfilePhotoUpload($_FILES['profile_photo'], $userId);
        if ($uploadedPhoto) {
            $profilePhoto = $uploadedPhoto;
            $photoChanged = true;
        } else {
            setFlash('error','Please upload a valid image file (jpg, png, webp, gif) under 2MB.');
            header('Location: profile.php'); exit;
        }
    }
    if (!$fullName) { setFlash('error','Full name is required.'); }
    else {
        $passwordRequested = $oldPass !== '' || $newPass !== '' || $confirmPass !== '';
        $passwordOk = true;
        $passwordHash = null;

        if ($passwordRequested) {
            $passError = '';
            if ($oldPass === '' || $newPass === '' || $confirmPass === '') {
                setFlash('error', 'To change your password, please fill in current password, new password, and confirmation.');
                $passwordOk = false;
            } elseif ($newPass !== $confirmPass) {
                setFlash('error','New passwords do not match.');
                $passwordOk = false;
            } elseif (!validatePassword($newPass, $passError)) {
                setFlash('error',$passError);
                $passwordOk = false;
            } else {
                $row = $pdo->prepare("SELECT password FROM users WHERE id=?"); $row->execute([$userId]); $row=$row->fetch();
                if (!$row || !password_verify($oldPass,$row['password'])) {
                    setFlash('error','Current password is incorrect.');
                    $passwordOk = false;
                } else {
                    $passwordHash = password_hash($newPass,PASSWORD_BCRYPT);
                }
            }
        }

        if ($passwordOk) {
            $pdo->prepare("UPDATE users SET full_name=?,phone=?,profile_photo=? WHERE id=?")->execute([$fullName,$phone,$profilePhoto,$userId]);
            $_SESSION['user']['full_name'] = $fullName;
            $_SESSION['user']['profile_photo'] = $profilePhoto;

            if ($passwordHash) {
                $pdo->prepare("UPDATE users SET password=?, failed_login_attempts=0 WHERE id=?")->execute([$passwordHash,$userId]);
                setFlash('success','Profile and password updated successfully.');
            } elseif ($photoChanged) {
                setFlash('success','Profile photo updated successfully.');
            } else {
                setFlash('success','Profile updated successfully.');
            }
        }
    }
    header('Location: profile.php'); exit;
}

include __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div><h1 class="page-title">My Profile</h1><p class="page-sub">Manage your account settings</p></div></div>
<div class="section-grid-2" style="align-items:start">
<div class="card">
    <div class="card-header"><h3>Account Information</h3></div>
    <div class="card-body">
        <div style="text-align:center;padding:20px 0">
            <div style="width:80px;height:80px;border-radius:50%;background:#0A1628;color:#C9A227;font-size:2rem;font-weight:700;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;overflow:hidden">
                <?php if (profilePhotoUrl($profile)): ?>
                    <img src="<?= clean(profilePhotoUrl($profile)) ?>" alt="<?= clean($profile['full_name']) ?> avatar" style="width:100%;height:100%;object-fit:cover;display:block">
                <?php else: ?>
                    <?= strtoupper(substr($profile['full_name'],0,1)) ?>
                <?php endif; ?>
            </div>
            <h3><?= clean($profile['full_name']) ?></h3>
            <span class="badge badge-purple"><?= clean($profile['role_name']) ?></span>
        </div>
        <table class="data-table"><tbody>
            <tr><td><strong>Username</strong></td><td><code><?= clean($profile['username']) ?></code></td></tr>
            <tr><td><strong>Email</strong></td><td><?= clean($profile['email']) ?></td></tr>
            <tr><td><strong>Branch</strong></td><td><?= clean($profile['branch_name']) ?></td></tr>
            <tr><td><strong>Last Login</strong></td><td><?= $profile['last_login']?date('d M Y H:i',strtotime($profile['last_login'])):'Never' ?></td></tr>
            <tr><td><strong>Member Since</strong></td><td><?= date('d M Y',strtotime($profile['created_at'])) ?></td></tr>
        </tbody></table>
    </div>
</div>
<div class="card">
    <div class="card-header"><h3>Edit Profile</h3></div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div class="form-group"><label>Profile Photo</label><input type="file" name="profile_photo" accept="image/*"></div>
            <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" required value="<?= clean($profile['full_name']) ?>"></div>
            <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?= clean($profile['phone']??'') ?>"></div>
            <hr style="margin:20px 0;border:none;border-top:1px solid #e2e8f0">
            <h4 style="margin-bottom:16px;color:#64748b">Change Password</h4>
            <div class="form-group"><label>Current Password</label><input type="password" name="old_password" placeholder="Enter current password"></div>
            <div class="form-group"><label>New Password</label><input type="password" name="new_password" placeholder="Min 8 characters"></div>
            <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" placeholder="Repeat new password"></div>
            <button type="submit" class="btn btn-primary btn-block">Save Changes</button>
        </form>
    </div>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
