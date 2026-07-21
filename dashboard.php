<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
$user = requireLogin($pdo);
if ($user['role'] === 'admin') {
    redirect('admin_dashboard.php');
}

$schoolYear = setting($pdo, 'current_school_year');
$semester = setting($pdo, 'current_semester');
$stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments e JOIN sections s ON s.id = e.section_id WHERE e.student_id = ? AND e.status = 'enrolled' AND s.school_year = ? AND s.semester = ?");
$stmt->execute([$user['id'], $schoolYear, $semester]);
$classCount = (int) $stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT tuition + miscellaneous - payments FROM balances WHERE student_id = ?');
$stmt->execute([$user['id']]);
$balance = (float) ($stmt->fetchColumn() ?: 0);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments e JOIN sections s ON s.id = e.section_id WHERE e.student_id = ? AND e.status = 'enrolled' AND e.grade_published = 1 AND s.school_year = ? AND s.semester = ?");
$stmt->execute([$user['id'], $schoolYear, $semester]);
$gradeCount = (int) $stmt->fetchColumn();
$announcements = $pdo->query("SELECT a.title, a.body, a.created_at, u.first_name, u.last_name FROM announcements a JOIN users u ON u.id = a.posted_by WHERE a.is_published = 1 ORDER BY a.created_at DESC LIMIT 3")->fetchAll();
$enrollmentOpen = setting($pdo, 'enrollment_open', '0') === '1';

renderHeader('Student Dashboard', $user);
?>
<section class="page-heading"><div><p class="eyebrow">Student dashboard</p><h1>Hello, <?= e($user['first_name']) ?>!</h1><p>Here is your academic overview for <?= e($semester) ?>, <?= e($schoolYear) ?>.</p></div><span class="status <?= $enrollmentOpen ? 'open' : 'closed' ?>">Enrollment <?= $enrollmentOpen ? 'open' : 'closed' ?></span></section>
<section class="stats-grid">
    <a class="stat-card" href="schedule.php"><span>Enrolled classes</span><strong><?= $classCount ?></strong><small>View weekly schedule</small></a>
    <a class="stat-card" href="balance.php"><span>Outstanding balance</span><strong>&#8369;<?= number_format($balance, 2) ?></strong><small>View account details</small></a>
    <a class="stat-card" href="grades.php"><span>Published grades</span><strong><?= $gradeCount ?></strong><small>View academic results</small></a>
</section>
<section class="card"><div class="card-header"><div><p class="eyebrow">Latest updates</p><h2>Announcements</h2></div><a class="text-link" href="announcements.php">View all</a></div>
<?php if (!$announcements): ?><p class="empty">No announcements yet.</p><?php endif; ?>
<?php foreach ($announcements as $announcement): ?><article class="announcement"><div><h3><?= e($announcement['title']) ?></h3><p><?= nl2br(e($announcement['body'])) ?></p></div><small><?= e(date('M j, Y', strtotime($announcement['created_at']))) ?> &middot; <?= e($announcement['first_name'] . ' ' . $announcement['last_name']) ?></small></article><?php endforeach; ?>
</section>
<?php renderFooter(); ?>
