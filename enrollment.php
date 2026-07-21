<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
$user = requireRole($pdo, 'student');
$schoolYear = setting($pdo, 'current_school_year');
$semester = setting($pdo, 'current_semester');
$isOpen = setting($pdo, 'enrollment_open', '0') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $sectionId = filter_input(INPUT_POST, 'section_id', FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';
    if (!$sectionId || !in_array($action, ['enroll', 'drop'], true)) {
        flash('error', 'Invalid enrollment request.');
        redirect('enrollment.php');
    }

    try {
        $pdo->beginTransaction();

        // Re-check and lock the global flag during the write. A stale page cannot
        // enroll after an administrator has closed enrollment.
        $settingLock = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'enrollment_open' FOR UPDATE");
        $settingLock->execute();
        if ((string) $settingLock->fetchColumn() !== '1') {
            throw new RuntimeException('Enrollment is not yet opened.');
        }

        // The term check prevents a crafted request from enrolling in an old or
        // future section that is not shown on this page.
        $lock = $pdo->prepare('SELECT id, course_id, capacity, is_active FROM sections WHERE id = ? AND school_year = ? AND semester = ? FOR UPDATE');
        $lock->execute([$sectionId, $schoolYear, $semester]);
        $section = $lock->fetch();
        if (!$section) {
            throw new RuntimeException('This class is not available for the current term.');
        }

        if ($action === 'drop') {
            $stmt = $pdo->prepare("UPDATE enrollments SET status = 'dropped', grade = NULL, grade_published = 0 WHERE student_id = ? AND section_id = ? AND status = 'enrolled'");
            $stmt->execute([$user['id'], $sectionId]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('You are not enrolled in that class.');
            }
            $pdo->commit();
            flash('success', 'Class dropped successfully.');
            redirect('enrollment.php');
        }

        if (!(bool) $section['is_active']) {
            throw new RuntimeException('This class is no longer available.');
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE section_id = ? AND status = 'enrolled'");
        $countStmt->execute([$sectionId]);
        if ((int) $countStmt->fetchColumn() >= (int) $section['capacity']) {
            throw new RuntimeException('This class has reached its capacity.');
        }

        $duplicate = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND section_id = ? AND status = 'enrolled'");
        $duplicate->execute([$user['id'], $sectionId]);
        if ((int) $duplicate->fetchColumn() > 0) {
            throw new RuntimeException('You are already enrolled in this class.');
        }

        $sameCourse = $pdo->prepare("SELECT s.section_code FROM enrollments e JOIN sections s ON s.id = e.section_id WHERE e.student_id = ? AND e.status = 'enrolled' AND s.course_id = ? AND s.school_year = ? AND s.semester = ? LIMIT 1");
        $sameCourse->execute([$user['id'], $section['course_id'], $schoolYear, $semester]);
        $existingSection = $sameCourse->fetchColumn();
        if ($existingSection !== false) {
            throw new RuntimeException('You are already enrolled in another section of this course (' . $existingSection . ').');
        }

        $conflict = $pdo->prepare("SELECT c.code, s.section_code, ss.day_of_week, ss.start_time, ss.end_time
            FROM section_schedules candidate
            JOIN section_schedules ss ON ss.day_of_week = candidate.day_of_week
                AND candidate.start_time < ss.end_time AND candidate.end_time > ss.start_time
            JOIN sections s ON s.id = ss.section_id
            JOIN courses c ON c.id = s.course_id
            JOIN enrollments e ON e.section_id = s.id
            WHERE candidate.section_id = ? AND e.student_id = ? AND e.status = 'enrolled'
                AND s.school_year = ? AND s.semester = ? LIMIT 1");
        $conflict->execute([$sectionId, $user['id'], $schoolYear, $semester]);
        $overlap = $conflict->fetch();
        if ($overlap) {
            throw new RuntimeException('Schedule conflict with ' . $overlap['code'] . ' ' . $overlap['section_code'] . ' on ' . $overlap['day_of_week'] . '.');
        }

        $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, section_id, status) VALUES (?, ?, 'enrolled') ON DUPLICATE KEY UPDATE status = 'enrolled', grade = NULL, grade_published = 0, enrolled_at = CURRENT_TIMESTAMP");
        $stmt->execute([$user['id'], $sectionId]);
        $pdo->commit();
        flash('success', 'You are now enrolled in the selected class.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'Enrollment could not be completed. Please try again.');
    }
    redirect('enrollment.php');
}

$sql = "SELECT s.id, c.code, c.title, c.units, s.section_code, s.instructor, s.room, s.capacity,
        (SELECT COUNT(*) FROM enrollments ec WHERE ec.section_id = s.id AND ec.status = 'enrolled') enrolled_count,
        GROUP_CONCAT(CONCAT(ss.day_of_week, ' ', TIME_FORMAT(ss.start_time, '%h:%i %p'), '-', TIME_FORMAT(ss.end_time, '%h:%i %p')) ORDER BY " . dayOrderSql('ss.day_of_week') . " SEPARATOR '|||') schedule,
        MAX(CASE WHEN e.student_id = ? AND e.status = 'enrolled' THEN 1 ELSE 0 END) is_enrolled
    FROM sections s JOIN courses c ON c.id = s.course_id JOIN section_schedules ss ON ss.section_id = s.id
    LEFT JOIN enrollments e ON e.section_id = s.id
    WHERE s.is_active = 1 AND s.school_year = ? AND s.semester = ?
    GROUP BY s.id, c.code, c.title, c.units, s.section_code, s.instructor, s.room, s.capacity ORDER BY c.code";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user['id'], $schoolYear, $semester]);
$sections = $stmt->fetchAll();

renderHeader('Enrollment', $user);
?>
<section class="page-heading"><div><p class="eyebrow">Academics</p><h1>Class Enrollment</h1><p><?= e($schoolYear) ?> &middot; <?= e($semester) ?></p></div></section>
<?php if (!$isOpen): ?>
<section class="lock-card"><div class="lock-icon">&#128274;</div><h2>Enrollment is not yet opened.</h2><p>You can review the available class offerings below, but changes are currently locked.</p></section>
<?php else: ?><div class="alert alert-success">Enrollment is open. Capacity and schedule conflicts are checked before every enrollment.</div><?php endif; ?>
<section class="card table-card"><div class="table-wrap"><table><thead><tr><th>Course</th><th>Section</th><th>Schedule</th><th>Instructor / Room</th><th>Slots</th><th>Action</th></tr></thead><tbody>
<?php foreach ($sections as $section): ?><tr><td><strong><?= e($section['code']) ?></strong><br><small><?= e($section['title']) ?> &middot; <?= e((string) $section['units']) ?> units</small></td><td><?= e($section['section_code']) ?></td><td><?= implode('<br>', array_map('e', explode('|||', (string) $section['schedule']))) ?></td><td><?= e($section['instructor']) ?><br><small><?= e($section['room']) ?></small></td><td><?= (int) $section['enrolled_count'] ?> / <?= (int) $section['capacity'] ?></td><td>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="section_id" value="<?= (int) $section['id'] ?>">
<?php if ($section['is_enrolled']): ?><button class="button danger small" name="action" value="drop" <?= !$isOpen ? 'disabled' : '' ?>>Drop</button><?php else: ?><button class="button small" name="action" value="enroll" <?= !$isOpen || $section['enrolled_count'] >= $section['capacity'] ? 'disabled' : '' ?>>Enroll</button><?php endif; ?></form></td></tr><?php endforeach; ?>
<?php if (!$sections): ?><tr><td colspan="6" class="empty">No classes are available for this term.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php renderFooter(); ?>
