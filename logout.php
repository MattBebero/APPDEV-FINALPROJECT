<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
$user = requireLogin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($user['role'] === 'admin' ? 'admin_dashboard.php' : 'dashboard.php');
}
verifyCsrf();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
session_start();
flash('success', 'You have been signed out.');
redirect('index.php');
