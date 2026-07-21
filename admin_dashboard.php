<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
$user = requireRole($pdo, 'admin');
$schoolYear = setting($pdo, 'current_school_year');
$semester = setting($pdo, 'current_semester');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $open = isset($_POST['enrollment_open']) ? '1' : '0';
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('enrollment_open', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$open]);
    flash('success', 'Enrollment is now ' . ($open === '1' ? 'open.' : 'closed.'));
    redirect('admin_dashboard.php');
}

$termCount = $pdo->prepare('SELECT COUNT(*) FROM sections WHERE is_active = 1 AND school_year = ? AND semester = ?');
$termCount->execute([$schoolYear, $semester]);
$enrollmentCount = $pdo->prepare("SELECT COUNT(*) FROM enrollments e JOIN sections s ON s.id = e.section_id WHERE e.status = 'enrolled' AND s.school_year = ? AND s.semester = ?");
$enrollmentCount->execute([$schoolYear, $semester]);
$stats = [
    'students' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND is_active = 1")->fetchColumn(),
    'sections' => (int) $termCount->fetchColumn(),
    'enrollments' => (int) $enrollmentCount->fetchColumn(),
    'receivables' => (float) $pdo->query('SELECT COALESCE(SUM(tuition + miscellaneous - payments), 0) FROM balances')->fetchColumn()
];
$recentStmt = $pdo->prepare("SELECT u.first_name, u.last_name, u.student_number, c.code, s.section_code, e.enrolled_at FROM enrollments e JOIN users u ON u.id = e.student_id JOIN sections s ON s.id = e.section_id JOIN courses c ON c.id = s.course_id WHERE e.status = 'enrolled' AND s.school_year = ? AND s.semester = ? ORDER BY e.enrolled_at DESC LIMIT 8");
$recentStmt->execute([$schoolYear, $semester]);
$recent = $recentStmt->fetchAll();
$isOpen = setting($pdo, 'enrollment_open') === '1';
renderHeader('Admin Dashboard', $user);
?>
<section class="page-heading"><div><p class="eyebrow">Administration</p><h1>Operations Dashboard</h1><p><?= e($schoolYear) ?> &middot; <?= e($semester) ?></p></div></section>
<section class="stats-grid admin-stats"><a class="stat-card" href="admin_students.php"><span>Active students</span><strong><?= $stats['students'] ?></strong><small>Manage student records</small></a><a class="stat-card" href="admin_sections.php"><span>Active classes</span><strong><?= $stats['sections'] ?></strong><small>Manage class offerings</small></a><div class="stat-card"><span>Active enrollments</span><strong><?= $stats['enrollments'] ?></strong><small>Across all sections</small></div><a class="stat-card" href="admin_balances.php"><span>Total receivables</span><strong>&#8369;<?= number_format($stats['receivables'], 2) ?></strong><small>Manage balances</small></a></section>
<section class="two-column dashboard-columns"><form method="post" class="card setting-card"><div><p class="eyebrow">Global setting</p><h2>Enrollment access</h2><p>Students can add or drop classes only while this switch is on.</p></div><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><label class="switch-row"><span><strong><?= $isOpen ? 'Enrollment is open' : 'Enrollment is closed' ?></strong><small><?= $isOpen ? 'Students may change classes.' : 'Student enrollment pages are locked.' ?></small></span><input type="checkbox" name="enrollment_open" value="1" <?= $isOpen ? 'checked' : '' ?>></label><button>Save enrollment status</button></form>
<section class="card"><div class="card-header"><div><p class="eyebrow">Activity</p><h2>Recent enrollments</h2></div></div><?php foreach ($recent as $item): ?><div class="activity-row"><div><strong><?= e($item['first_name'] . ' ' . $item['last_name']) ?></strong><small><?= e($item['student_number']) ?></small></div><span><?= e($item['code'] . ' · ' . $item['section_code']) ?></span></div><?php endforeach; ?><?php if (!$recent): ?><p class="empty">No enrollment activity yet.</p><?php endif; ?></section></section>
<?php renderFooter(); ?>
