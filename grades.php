<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
$user = requireRole($pdo, 'student');
$schoolYear = setting($pdo, 'current_school_year');
$semester = setting($pdo, 'current_semester');
$stmt = $pdo->prepare("SELECT c.code, c.title, c.units, s.section_code, e.grade FROM enrollments e JOIN sections s ON s.id = e.section_id JOIN courses c ON c.id = s.course_id WHERE e.student_id = ? AND e.status = 'enrolled' AND e.grade_published = 1 AND s.school_year = ? AND s.semester = ? ORDER BY c.code");
$stmt->execute([$user['id'], $schoolYear, $semester]);
$grades = $stmt->fetchAll();
renderHeader('Grades', $user);
?>
<section class="page-heading"><div><p class="eyebrow">Academic record</p><h1>Published Grades</h1><p><?= e($semester) ?> &middot; <?= e($schoolYear) ?>. Only released grades appear here.</p></div></section>
<section class="card table-card"><div class="table-wrap"><table><thead><tr><th>Course code</th><th>Course title</th><th>Section</th><th>Units</th><th>Final grade</th></tr></thead><tbody>
<?php foreach ($grades as $grade): ?><tr><td><strong><?= e($grade['code']) ?></strong></td><td><?= e($grade['title']) ?></td><td><?= e($grade['section_code']) ?></td><td><?= e((string) $grade['units']) ?></td><td><span class="grade-chip"><?= e($grade['grade']) ?></span></td></tr><?php endforeach; ?>
<?php if (!$grades): ?><tr><td colspan="5" class="empty">No grades have been published yet.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php renderFooter(); ?>
