<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
$user = requireRole($pdo, 'student');
$schoolYear = setting($pdo, 'current_school_year');
$semester = setting($pdo, 'current_semester');
$stmt = $pdo->prepare("SELECT c.code, c.title, s.section_code, s.instructor, s.room, ss.day_of_week, ss.start_time, ss.end_time
    FROM enrollments e JOIN sections s ON s.id = e.section_id JOIN courses c ON c.id = s.course_id JOIN section_schedules ss ON ss.section_id = s.id
    WHERE e.student_id = ? AND e.status = 'enrolled' AND s.school_year = ? AND s.semester = ? ORDER BY " . dayOrderSql('ss.day_of_week') . ', ss.start_time');
$stmt->execute([$user['id'], $schoolYear, $semester]);
$meetings = $stmt->fetchAll();
$byDay = [];
foreach ($meetings as $meeting) { $byDay[$meeting['day_of_week']][] = $meeting; }
renderHeader('Weekly Schedule', $user);
?>
<section class="page-heading"><div><p class="eyebrow">Academics</p><h1>Weekly Schedule</h1><p><?= e($semester) ?> &middot; <?= e($schoolYear) ?></p></div><a class="button secondary" href="enrollment.php">Manage enrollment</a></section>
<section class="schedule-grid">
<?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day): ?><div class="day-card"><h2><?= $day ?></h2>
<?php if (empty($byDay[$day])): ?><p class="empty">No classes</p><?php endif; ?>
<?php foreach ($byDay[$day] ?? [] as $meeting): ?><article><time><?= e(date('g:i A', strtotime($meeting['start_time']))) ?>–<?= e(date('g:i A', strtotime($meeting['end_time']))) ?></time><strong><?= e($meeting['code']) ?> · <?= e($meeting['section_code']) ?></strong><span><?= e($meeting['room']) ?> · <?= e($meeting['instructor']) ?></span></article><?php endforeach; ?></div><?php endforeach; ?>
</section>
<?php renderFooter(); ?>
