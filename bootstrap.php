<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

$databaseFile = __DIR__ . '/db.php';
if (!is_file($databaseFile)) {
    http_response_code(503);
    exit('Database configuration is unavailable. Add the private db.php file and try again.');
}
require_once $databaseFile;
require_once __DIR__ . '/initialize.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    throw new RuntimeException('db.php must provide a connected PDO instance in $pdo.');
}
if (!databaseIsInitialized($pdo)) {
    initializeDatabase($pdo);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Your session token expired. Please go back, refresh the page, and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function currentUser(PDO $pdo): ?array
{
    static $loaded = false;
    static $user = null;

    if ($loaded) {
        return $user;
    }
    $loaded = true;
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, email, role, first_name, last_name, student_number, phone, address, created_at FROM users WHERE id = ? AND is_active = 1');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    if (!$user) {
        unset($_SESSION['user_id']);
    }
    return $user;
}

function requireLogin(PDO $pdo): array
{
    $user = currentUser($pdo);
    if (!$user) {
        flash('error', 'Please sign in to continue.');
        redirect('index.php');
    }
    return $user;
}

function requireRole(PDO $pdo, string $role): array
{
    $user = requireLogin($pdo);
    if ($user['role'] !== $role) {
        http_response_code(403);
        exit('You do not have permission to view this page.');
    }
    return $user;
}

function setting(PDO $pdo, string $key, string $default = ''): string
{
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string) $value;
}

function fullName(array $user): string
{
    return trim($user['first_name'] . ' ' . $user['last_name']);
}

function dayOrderSql(string $column = 'day_of_week'): string
{
    return "FIELD($column, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')";
}

function renderHeader(string $title, ?array $user = null): void
{
    $flashes = flashes();
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'none'");
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> | Campus One</title>
        <link rel="stylesheet" href="assets/style.css">
    </head>
    <body>
    <?php if ($user): ?>
        <header class="topbar">
            <a class="brand" href="dashboard.php"><span>C1</span> Campus One</a>
            <nav>
                <?php if ($user['role'] === 'student'): ?>
                    <a href="dashboard.php">Dashboard</a><a href="enrollment.php">Enrollment</a><a href="schedule.php">Schedule</a><a href="grades.php">Grades</a><a href="balance.php">Balance</a><a href="announcements.php">Announcements</a>
                <?php else: ?>
                    <a href="admin_dashboard.php">Dashboard</a><a href="admin_students.php">Students</a><a href="admin_sections.php">Classes</a><a href="admin_balances.php">Balances</a><a href="admin_grades.php">Grades</a><a href="admin_announcements.php">Announcements</a>
                <?php endif; ?>
                <a href="profile.php">My Profile</a>
                <form class="nav-form" method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><button type="submit">Log out</button></form>
            </nav>
        </header>
    <?php endif; ?>
    <main class="container<?= $user ? '' : ' auth-container' ?>">
        <?php foreach ($flashes as $item): ?>
            <div class="alert alert-<?= e($item['type']) ?>"><?= e($item['message']) ?></div>
        <?php endforeach; ?>
        <?php
}

function renderFooter(): void
{
    ?>
    </main>
    <footer>Campus One Student Information Portal &middot; <?= date('Y') ?></footer>
    </body>
    </html>
    <?php
}
