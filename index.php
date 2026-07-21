<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

if (currentUser($pdo)) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    $account = false;
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare('SELECT id, password_hash, role FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $account = $stmt->fetch();
    }

    if ($account && password_verify($password, $account['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $account['id'];
        unset($_SESSION['csrf_token']);
        redirect($account['role'] === 'admin' ? 'admin_dashboard.php' : 'dashboard.php');
    }
    flash('error', 'Invalid email or password.');
    redirect('index.php');
}

renderHeader('Sign in');
?>
<section class="auth-card">
    <div class="auth-intro">
        <div class="brand-mark">C1</div>
        <p class="eyebrow">Student information system</p>
        <h1>Welcome to Campus One</h1>
        <p>Enrollment, schedules, grades, balances, and campus updates in one secure place.</p>
    </div>
    <form method="post" class="auth-form">
        <h2>Sign in</h2>
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <label>Email address<input type="email" name="email" required autocomplete="username"></label>
        <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
        <button type="submit">Sign in</button>
    </form>
</section>
<?php renderFooter(); ?>
