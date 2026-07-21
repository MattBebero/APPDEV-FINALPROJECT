<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
$user = requireLogin($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'profile') {
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));
        if ($firstName === '' || $lastName === '' || mb_strlen($firstName) > 80 || mb_strlen($lastName) > 80 || mb_strlen($phone) > 30 || mb_strlen($address) > 255) {
            flash('error', 'Check the profile fields and their maximum lengths.');
        } else {
            $stmt = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, phone = ?, address = ? WHERE id = ?');
            $stmt->execute([$firstName, $lastName, $phone ?: null, $address ?: null, $user['id']]);
            flash('success', 'Profile updated successfully.');
        }
    } elseif ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $hash = (string) $stmt->fetchColumn();
        if (!password_verify($current, $hash)) {
            flash('error', 'Your current password is incorrect.');
        } elseif (strlen($new) < 8) {
            flash('error', 'New password must contain at least 8 characters.');
        } elseif ($new !== $confirm) {
            flash('error', 'New password and confirmation do not match.');
        } else {
            $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            flash('success', 'Password changed successfully.');
        }
    }
    redirect('profile.php');
}

renderHeader('My Profile', $user);
?>
<section class="page-heading"><div><p class="eyebrow">Account settings</p><h1>My Profile</h1><p>Manage your personal details and account password.</p></div></section>
<section class="two-column">
<form method="post" class="card form-card"><h2>Personal information</h2><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="profile"><div class="form-grid"><label>First name<input name="first_name" required maxlength="80" value="<?= e($user['first_name']) ?>"></label><label>Last name<input name="last_name" required maxlength="80" value="<?= e($user['last_name']) ?>"></label></div><label>Email<input value="<?= e($user['email']) ?>" disabled><small>Email changes require administrator support.</small></label><?php if ($user['role'] === 'student'): ?><label>Student number<input value="<?= e($user['student_number']) ?>" disabled></label><?php endif; ?><label>Phone<input name="phone" maxlength="30" value="<?= e($user['phone']) ?>"></label><label>Address<textarea name="address" maxlength="255"><?= e($user['address']) ?></textarea></label><button>Save profile</button></form>
<form method="post" class="card form-card"><h2>Change password</h2><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="password"><label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label><label>New password<input type="password" name="new_password" required minlength="8" autocomplete="new-password"></label><label>Confirm new password<input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></label><button>Change password</button></form>
</section>
<?php renderFooter(); ?>
