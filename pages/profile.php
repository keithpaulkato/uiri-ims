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
    $fullName = trim($_POST['full_name']??''); $phone = normalizeUgandanPhone($_POST['phone']??'');
    $oldPass = $_POST['old_password']??''; $newPass = $_POST['new_password']??''; $confirmPass = $_POST['confirm_password']??'';
    $profilePhoto = $profile['profile_photo'] ?? null;
    $photoChanged = false;
    $hasProfilePhotoUpload = !empty($_FILES['profile_photo']['name']);
    if ($hasProfilePhotoUpload) {
        $uploadError = '';
        if (!validateProfilePhotoUpload($_FILES['profile_photo'], $uploadError)) {
            setFlash('error', $uploadError);
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
            if ($hasProfilePhotoUpload) {
                $uploadedPhoto = saveProfilePhotoUpload($_FILES['profile_photo'], $userId);
                if ($uploadedPhoto) {
                    $profilePhoto = $uploadedPhoto;
                    $photoChanged = true;
                } else {
                    setFlash('error','The profile details are valid, but the photo could not be saved. Please try another image.');
                    header('Location: profile.php'); exit;
                }
            }
            $pdo->prepare("UPDATE users SET full_name=?,phone=?,profile_photo=? WHERE id=?")->execute([$fullName,$phone,$profilePhoto,$userId]);
            $_SESSION['user']['full_name'] = $fullName;
            $_SESSION['user']['profile_photo'] = $profilePhoto;

            if ($passwordHash) {
                $pdo->prepare("UPDATE users SET password=?, must_change_password=0, password_changed_at=NOW(), failed_login_attempts=0 WHERE id=?")->execute([$passwordHash,$userId]);
                $_SESSION['user']['must_change_password'] = 0;
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
<div class="profile-header-card" style="position: relative; margin-bottom: 28px; border-radius: 16px; box-shadow: var(--shadow); border: 1px solid var(--border); background: var(--white); overflow: hidden;">
    <!-- Cover Image -->
    <div style="height: 160px; background: linear-gradient(135deg, var(--navy-3) 0%, var(--corp-blue) 100%); position: relative;">
        <div style="position: absolute; inset: 0; opacity: 0.15; background-image: radial-gradient(circle at 30% 20%, #ffffff 0%, transparent 40%), radial-gradient(circle at 70% 80%, #ffffff 0%, transparent 40%);"></div>
    </div>
    <!-- Profile Info Area -->
    <div style="padding: 0 24px 16px; display: flex; flex-direction: column; position: relative;">
        <div style="display: flex; align-items: flex-end; gap: 20px; margin-top: -45px; margin-bottom: 16px; flex-wrap: wrap;">
            <!-- Avatar Frame -->
            <div id="headerAvatarContainer" style="width: 96px; height: 96px; border-radius: 12px; background: var(--white); overflow: hidden; display: flex; align-items: center; justify-content: center; border: 4px solid var(--white); box-shadow: 0 4px 12px rgba(0,0,0,0.08); flex-shrink: 0; position: relative; z-index: 5;">
                <?php if (profilePhotoUrl($profile)): ?>
                    <img src="<?= clean(profilePhotoUrl($profile)) ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <span style="font-size: 2.5rem; font-weight: 800; color: var(--navy);"><?= strtoupper(substr($profile['full_name'],0,1)) ?></span>
                <?php endif; ?>
            </div>
            <!-- Name & Role -->
            <div style="flex: 1; padding-top: 45px; min-width: 200px;">
                <h2 style="font-size: 1.4rem; font-weight: 850; color: var(--text); margin: 0 0 4px;"><?= clean($profile['full_name']) ?></h2>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="badge badge-purple" style="font-weight: 800; border-radius: 4px; font-size: 0.68rem; padding: 2px 8px; text-transform: uppercase; letter-spacing: 0.5px;"><?= clean($profile['role_name']) ?></span>
                    <span style="font-size: 0.8rem; color: var(--sub); font-weight: 600;"><?= clean($profile['branch_name']) ?> Branch</span>
                </div>
            </div>
        </div>
        <!-- Profile Tabs -->
        <div style="border-top: 1px solid var(--border); display: flex; gap: 8px; padding-top: 8px; margin-top: 8px;">
            <button type="button" class="tab-btn active" data-target="overview-panel" style="padding: 10px 16px; font-size: 0.84rem; font-weight: 800; color: var(--navy); border: none; background: transparent; border-bottom: 2px solid var(--navy); cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; gap: 6px;">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                Overview
            </button>
            <button type="button" class="tab-btn" data-target="edit-panel" style="padding: 10px 16px; font-size: 0.84rem; font-weight: 700; color: var(--sub); border: none; background: transparent; border-bottom: 2px solid transparent; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; gap: 6px;">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                Edit Account
            </button>
            <button type="button" class="tab-btn" data-target="security-panel" style="padding: 10px 16px; font-size: 0.84rem; font-weight: 700; color: var(--sub); border: none; background: transparent; border-bottom: 2px solid transparent; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; gap: 6px;">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                Security
            </button>
        </div>
    </div>
</div>

<!-- Tab Panels Container -->
<div style="margin-bottom: 30px;">
    <!-- Panel 1: Overview -->
    <div id="overview-panel" class="tab-panel section-grid-2" style="align-items: start; gap: 24px;">
        <!-- Profile Info Card -->
        <div class="card" style="border-radius: 12px; box-shadow: var(--shadow); border: 1px solid var(--border); background: var(--white); overflow: hidden;">
            <div class="card-header" style="border-bottom: 1px solid var(--border); padding: 18px 24px; background: var(--surface);">
                <h3 style="font-size: 0.95rem; font-weight: 800; color: var(--navy); margin: 0; text-transform: uppercase; letter-spacing: 0.5px;">Profile Description</h3>
            </div>
            <div class="card-body" style="padding: 24px;">
                <div style="margin-bottom: 24px;">
                    <h4 style="font-size: 0.84rem; font-weight: 800; color: var(--navy); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px;">About Me</h4>
                    <p style="font-size: 0.88rem; line-height: 1.6; color: var(--text); font-weight: 500; margin: 0;">
                        Inventory team member at UIRI. Dedicated to tracking physical assets, coordinating campus allocations, and maintaining stock catalog configurations across branch offices.
                    </p>
                </div>

                <div class="profile-details-list" style="display: flex; flex-direction: column; gap: 16px; border-top: 1px solid var(--border); padding-top: 20px;">
                    <div class="profile-detail-item" style="display: flex; align-items: center; gap: 14px;">
                        <div style="color: var(--sub); display: flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 50%; background: var(--surface); border: 1px solid var(--border); flex-shrink: 0;">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        </div>
                        <div>
                            <span style="display: block; font-size: 0.72rem; color: var(--sub); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Username</span>
                            <code style="font-size: 0.85rem; color: var(--text); font-weight: 700; background: var(--surface); padding: 2px 6px; border-radius: 6px; border: 1px solid var(--border);"><?= clean($profile['username']) ?></code>
                        </div>
                    </div>
                    <div class="profile-detail-item" style="display: flex; align-items: center; gap: 14px;">
                        <div style="color: var(--sub); display: flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 50%; background: var(--surface); border: 1px solid var(--border); flex-shrink: 0;">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        </div>
                        <div>
                            <span style="display: block; font-size: 0.72rem; color: var(--sub); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Email Address</span>
                            <span style="font-size: 0.88rem; color: var(--text); font-weight: 600;"><?= clean($profile['email']) ?></span>
                        </div>
                    </div>
                    <div class="profile-detail-item" style="display: flex; align-items: center; gap: 14px;">
                        <div style="color: var(--sub); display: flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 50%; background: var(--surface); border: 1px solid var(--border); flex-shrink: 0;">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        </div>
                        <div>
                            <span style="display: block; font-size: 0.72rem; color: var(--sub); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Last Account Login</span>
                            <span style="font-size: 0.88rem; color: var(--text); font-weight: 600;"><?= $profile['last_login'] ? formatDateTime($profile['last_login'], true) : 'Never' ?></span>
                        </div>
                    </div>
                    <div class="profile-detail-item" style="display: flex; align-items: center; gap: 14px;">
                        <div style="color: var(--sub); display: flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 50%; background: var(--surface); border: 1px solid var(--border); flex-shrink: 0;">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        </div>
                        <div>
                            <span style="display: block; font-size: 0.72rem; color: var(--sub); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Member Since</span>
                            <span style="font-size: 0.88rem; color: var(--text); font-weight: 600;"><?= formatDate($profile['created_at']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Platform Settings Card -->
        <div class="card" style="border-radius: 12px; box-shadow: var(--shadow); border: 1px solid var(--border); background: var(--white); overflow: hidden;">
            <div class="card-header" style="border-bottom: 1px solid var(--border); padding: 18px 24px; background: var(--surface);">
                <h3 style="font-size: 0.95rem; font-weight: 800; color: var(--navy); margin: 0; text-transform: uppercase; letter-spacing: 0.5px;">Platform Settings</h3>
            </div>
            <div class="card-body" style="padding: 24px;">
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <div>
                        <h4 style="font-size: 0.75rem; font-weight: 800; color: var(--sub); text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 12px;">Alerts & Reminders</h4>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.86rem; color: var(--text); font-weight: 500;">
                                <input type="checkbox" checked style="width: 18px; height: 18px; border-radius: 4px; accent-color: var(--navy);">
                                Email notifications on stock level threshold alerts
                            </label>
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.86rem; color: var(--text); font-weight: 500;">
                                <input type="checkbox" checked style="width: 18px; height: 18px; border-radius: 4px; accent-color: var(--navy);">
                                Notify me when an item assignment changes
                            </label>
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.86rem; color: var(--text); font-weight: 500;">
                                <input type="checkbox" style="width: 18px; height: 18px; border-radius: 4px; accent-color: var(--navy);">
                                Email alerts for new categories setup
                            </label>
                        </div>
                    </div>

                    <div style="border-top: 1px solid var(--border); padding-top: 18px;">
                        <h4 style="font-size: 0.75rem; font-weight: 800; color: var(--sub); text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 12px;">Security Settings</h4>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.86rem; color: var(--text); font-weight: 500;">
                                <input type="checkbox" checked style="width: 18px; height: 18px; border-radius: 4px; accent-color: var(--navy);">
                                Send email warnings on failed login attempts
                            </label>
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.86rem; color: var(--text); font-weight: 500;">
                                <input type="checkbox" checked style="width: 18px; height: 18px; border-radius: 4px; accent-color: var(--navy);">
                                Require double token confirmation for deletions
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel 2: Edit Account -->
    <div id="edit-panel" class="tab-panel" style="display: none;">
        <div class="card" style="border-radius: 12px; box-shadow: var(--shadow); border: 1px solid var(--border); background: var(--white); overflow: hidden; max-width: 720px; margin: 0 auto;">
            <div class="card-header" style="border-bottom: 1px solid var(--border); padding: 18px 24px; background: var(--surface);">
                <h3 style="font-size: 0.95rem; font-weight: 800; color: var(--navy); margin: 0; text-transform: uppercase; letter-spacing: 0.5px;">Account Details</h3>
            </div>
            <div class="card-body" style="padding: 24px;">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    
                    <!-- Avatar Upload Zone -->
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--sub); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Upload Avatar</label>
                        <div class="profile-upload-zone" style="position: relative; display: flex; align-items: center; gap: 16px; padding: 16px; border: 2px dashed var(--border); border-radius: 12px; background: var(--surface); transition: border-color 0.2s ease;">
                            <div id="uploadPreviewContainer" style="width: 58px; height: 58px; border-radius: 50%; overflow: hidden; background: var(--white); display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid var(--border); box-shadow: 0 2px 6px rgba(0,0,0,0.04);">
                                <?php if (profilePhotoUrl($profile)): ?>
                                    <img src="<?= clean(profilePhotoUrl($profile)) ?>" alt="Avatar Preview" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <span style="font-size: 1.4rem; font-weight: 800; color: var(--navy);" id="uploadPreviewLetter"><?= strtoupper(substr($profile['full_name'],0,1)) ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <input type="file" name="profile_photo" id="profilePhotoInput" accept="image/*" style="position: absolute; inset: 0; opacity: 0; cursor: pointer; z-index: 2;">
                                <button type="button" class="btn btn-outline" style="pointer-events: none; font-size: 0.8rem; padding: 6px 14px; border-radius: 8px;">Change Photo</button>
                                <span style="display: block; font-size: 0.72rem; color: var(--sub); margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" id="uploadFileInfo">PNG, JPG up to 2MB</span>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Information inputs -->
                    <div class="form-group" style="margin-bottom: 18px;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--sub); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Full Name *</label>
                        <input type="text" name="full_name" required value="<?= clean($profile['full_name']) ?>" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--white); color: var(--text); font-weight: 500;">
                    </div>

                    <div class="form-group" style="margin-bottom: 24px;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--sub); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Phone Number</label>
                        <input type="tel" class="js-ug-phone" name="phone" value="<?= clean($profile['phone']??'') ?>" placeholder="+256 700000000" inputmode="numeric" autocomplete="tel" maxlength="14" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--white); color: var(--text); font-weight: 500;">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px; font-weight: 700; border-radius: 8px;">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                        Update Account Information
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Panel 3: Security -->
    <div id="security-panel" class="tab-panel" style="display: none;">
        <div class="card" style="border-radius: 12px; box-shadow: var(--shadow); border: 1px solid var(--border); background: var(--white); overflow: hidden; max-width: 720px; margin: 0 auto;">
            <div class="card-header" style="border-bottom: 1px solid var(--border); padding: 18px 24px; background: var(--surface);">
                <h3 style="font-size: 0.95rem; font-weight: 800; color: var(--navy); margin: 0; text-transform: uppercase; letter-spacing: 0.5px;">Update Password</h3>
            </div>
            <div class="card-body" style="padding: 24px;">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <!-- Hidden field to satisfy validation updates -->
                    <input type="hidden" name="full_name" value="<?= clean($profile['full_name']) ?>">
                    <input type="hidden" name="phone" value="<?= clean($profile['phone']??'') ?>">

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--sub); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Current Password</label>
                        <input type="password" name="old_password" required placeholder="Verify current credentials" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--white); color: var(--text); font-weight: 500;">
                    </div>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--sub); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">New Password</label>
                        <input type="password" name="new_password" required placeholder="At least 8 characters" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--white); color: var(--text); font-weight: 500;">
                    </div>

                    <div class="form-group" style="margin-bottom: 24px;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--sub); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Confirm New Password</label>
                        <input type="password" name="confirm_password" required placeholder="Verify new password" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--white); color: var(--text); font-weight: 500;">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px; font-weight: 700; border-radius: 8px;">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const photoInput = document.getElementById('profilePhotoInput');
    const previewContainer = document.getElementById('uploadPreviewContainer');
    const headerAvatarContainer = document.getElementById('headerAvatarContainer');
    const fileInfo = document.getElementById('uploadFileInfo');

    // Tab switcher logic
    const tabs = document.querySelectorAll('.tab-btn');
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active classes
            tabs.forEach(t => {
                t.classList.remove('active');
                t.style.borderBottomColor = 'transparent';
                t.style.color = 'var(--sub)';
                t.style.fontWeight = '700';
            });
            
            // Add active styles to clicked tab
            this.classList.add('active');
            this.style.borderBottomColor = 'var(--navy)';
            this.style.color = 'var(--navy)';
            this.style.fontWeight = '800';
            
            // Hide panels
            document.querySelectorAll('.tab-panel').forEach(panel => {
                panel.style.display = 'none';
            });
            
            // Show target panel
            const targetId = this.dataset.target;
            const targetPanel = document.getElementById(targetId);
            if (targetId === 'overview-panel') {
                targetPanel.style.display = 'grid';
            } else {
                targetPanel.style.display = 'block';
            }
        });
    });

    if (photoInput) {
        photoInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                // Update file info label
                if (fileInfo) {
                    fileInfo.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
                    fileInfo.style.color = 'var(--corp-blue)';
                    fileInfo.style.fontWeight = '600';
                }

                // Update preview images
                const reader = new FileReader();
                reader.onload = function (e) {
                    const imgHtml = `<img src="${e.target.result}" alt="New Avatar" style="width:100%;height:100%;object-fit:cover;">`;
                    if (previewContainer) previewContainer.innerHTML = imgHtml;
                    if (headerAvatarContainer) headerAvatarContainer.innerHTML = imgHtml;
                };
                reader.readAsDataURL(file);
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
