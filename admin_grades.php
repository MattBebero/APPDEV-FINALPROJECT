<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
$user = requireRole($pdo, 'admin');
$schoolYear = setting($pdo, 'current_school_year');
$semester = setting($pdo, 'current_semester');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $enrollmentId = filter_input(INPUT_POST, 'enrollment_id', FILTER_VALIDATE_INT);
    $grade = strtoupper(trim((string) ($_POST['grade'] ?? '')));
    $published = isset($_POST['grade_published']) ? 1 : 0;
    if (!$enrollmentId || mb_strlen($grade) > 10) {
        flash('error', 'Invalid grade update.');
    } elseif ($published && $grade === '') {
        flash('error', 'Enter a grade before publishing it.');
    } else {
        $eligible = $pdo->prepare("SELECT e.id FROM enrollments e JOIN sections s ON s.id = e.section_id WHERE e.id = ? AND e.status = 'enrolled' AND s.school_year = ? AND s.semester = ?");
        $eligible->execute([$enrollmentId, $schoolYear, $semester]);
        if (!$eligible->fetchColumn()) {
            flash('error', 'No current-term grade record was found.');
        } else {
            $stmt = $pdo->prepare('UPDATE enrollments SET grade = ?, grade_published = ? WHERE id = ?');
            $stmt->execute([$grade ?: null, $published, $enrollmentId]);
            flash('success', 'Grade record updated.');
        }
    }
    redirect('admin_grades.php');
}

$rowsStmt = $pdo->prepare("SELECT e.id, e.grade, e.grade_published, u.student_number, u.first_name, u.last_name, c.code, s.section_code FROM enrollments e JOIN users u ON u.id=e.student_id JOIN sections s ON s.id=e.section_id JOIN courses c ON c.id=s.course_id WHERE e.status='enrolled' AND s.school_year = ? AND s.semester = ? ORDER BY c.code,s.section_code,u.last_name,u.first_name");
$rowsStmt->execute([$schoolYear, $semester]);
$rows = $rowsStmt->fetchAll();
renderHeader('Manage Grades', $user);
?>
<section class="page-heading"><div><p class="eyebrow">Academic administration</p><h1>Grade Publishing</h1><p>Save grades privately or release them to students.</p></div></section>
<section class="card table-card"><div class="table-wrap"><table><thead><tr><th>Student</th><th>Course</th><th>Grade</th><th>Published</th><th>Action</th></tr></thead><tbody><?php foreach ($rows as $row): ?><?php $formId = 'grade-' . (int) $row['id']; ?><tr><td><strong><?= e($row['last_name'] . ', ' . $row['first_name']) ?></strong><br><small><?= e($row['student_number']) ?></small></td><td><?= e($row['code'] . ' · ' . $row['section_code']) ?></td><td><input form="<?= e($formId) ?>" class="grade-input" name="grade" maxlength="10" value="<?= e($row['grade']) ?>"></td><td><label class="check-label"><input form="<?= e($formId) ?>" type="checkbox" name="grade_published" value="1" <?= $row['grade_published'] ? 'checked' : '' ?>> Visible</label></td><td><form id="<?= e($formId) ?>" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="enrollment_id" value="<?= (int) $row['id'] ?>"><button class="small">Save</button></form></td></tr><?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="5" class="empty">No enrolled students to grade.</td></tr><?php endif; ?></tbody></table></div></section>
<?php renderFooter(); ?>
