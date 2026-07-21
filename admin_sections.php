<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
$user = requireRole($pdo, 'admin');
$schoolYear = setting($pdo, 'current_school_year');
$semester = setting($pdo, 'current_semester');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? 'create';
    if ($action === 'toggle') {
        $id = filter_input(INPUT_POST, 'section_id', FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = $pdo->prepare('UPDATE sections SET is_active = 1 - is_active WHERE id = ? AND school_year = ? AND semester = ?');
            $stmt->execute([$id, $schoolYear, $semester]);
            flash($stmt->rowCount() === 1 ? 'success' : 'error', $stmt->rowCount() === 1 ? 'Class availability updated.' : 'That class is not part of the current term.');
        }
        redirect('admin_sections.php');
    }
    if ($action === 'delete_section') {
        $sectionId = filter_input(INPUT_POST, 'section_id', FILTER_VALIDATE_INT);
        if (!$sectionId) {
            flash('error', 'Invalid class offering.');
            redirect('admin_sections.php');
        }
        try {
            $pdo->beginTransaction();
            $sectionLock = $pdo->prepare('SELECT id FROM sections WHERE id = ? AND school_year = ? AND semester = ? FOR UPDATE');
            $sectionLock->execute([$sectionId, $schoolYear, $semester]);
            if (!$sectionLock->fetchColumn()) {
                throw new RuntimeException('That class offering is not part of the current term.');
            }
            $history = $pdo->prepare('SELECT COUNT(*) FROM enrollments WHERE section_id = ?');
            $history->execute([$sectionId]);
            if ((int) $history->fetchColumn() > 0) {
                throw new RuntimeException('This class cannot be removed because it has enrollment history. Deactivate it instead.');
            }
            $delete = $pdo->prepare('DELETE FROM sections WHERE id = ?');
            $delete->execute([$sectionId]);
            $pdo->commit();
            flash('success', 'Class offering removed.');
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            flash('error', $e->getMessage());
        } catch (PDOException) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            flash('error', 'The class offering could not be removed.');
        }
        redirect('admin_sections.php');
    }
    if ($action === 'delete_course') {
        $courseId = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
        if (!$courseId) {
            flash('error', 'Invalid course.');
            redirect('admin_sections.php');
        }
        try {
            $pdo->beginTransaction();
            $courseLock = $pdo->prepare('SELECT code FROM courses WHERE id = ? FOR UPDATE');
            $courseLock->execute([$courseId]);
            $courseCode = $courseLock->fetchColumn();
            if ($courseCode === false) {
                throw new RuntimeException('That course no longer exists.');
            }
            $offerings = $pdo->prepare('SELECT COUNT(*) FROM sections WHERE course_id = ?');
            $offerings->execute([$courseId]);
            if ((int) $offerings->fetchColumn() > 0) {
                throw new RuntimeException('Remove all class offerings for ' . $courseCode . ' before deleting the course.');
            }
            $delete = $pdo->prepare('DELETE FROM courses WHERE id = ?');
            $delete->execute([$courseId]);
            $pdo->commit();
            flash('success', 'Course ' . $courseCode . ' removed from the catalog.');
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            flash('error', $e->getMessage());
        } catch (PDOException) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            flash('error', 'The course could not be removed.');
        }
        redirect('admin_sections.php');
    }
    if ($action === 'create_course') {
        $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
        $title = trim((string) ($_POST['title'] ?? ''));
        $units = filter_input(INPUT_POST, 'units', FILTER_VALIDATE_FLOAT);
        $description = trim((string) ($_POST['description'] ?? ''));
        $validCode = $code !== '' && mb_strlen($code) <= 30 && preg_match('/^[A-Z0-9-]+$/', $code);
        $validUnits = $units !== false && is_finite((float) $units) && $units > 0 && $units <= 99.9 && round((float) $units, 1) === (float) $units;
        if (!$validCode || $title === '' || mb_strlen($title) > 150 || !$validUnits || mb_strlen($description) > 10000) {
            flash('error', 'Enter a valid course code, title, and units with at most one decimal place.');
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO courses (code, title, units, description) VALUES (?, ?, ?, ?)');
                $stmt->execute([$code, $title, $units, $description ?: null]);
                flash('success', 'Course ' . $code . ' added to the catalog.');
            } catch (PDOException $e) {
                flash('error', $e->getCode() === '23000' ? 'That course code already exists.' : 'The course could not be added.');
            }
        }
        redirect('admin_sections.php');
    }
    $courseId = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
    $sectionCode = strtoupper(trim((string) ($_POST['section_code'] ?? '')));
    $instructor = trim((string) ($_POST['instructor'] ?? ''));
    $room = trim((string) ($_POST['room'] ?? ''));
    $capacity = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT);
    $day = (string) ($_POST['day_of_week'] ?? '');
    $start = (string) ($_POST['start_time'] ?? '');
    $end = (string) ($_POST['end_time'] ?? '');
    $validDays = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    if (!$courseId || $sectionCode === '' || mb_strlen($sectionCode) > 30 || $instructor === '' || mb_strlen($instructor) > 150 || $room === '' || mb_strlen($room) > 50 || !$capacity || $capacity < 1 || $capacity > 500 || !in_array($day, $validDays, true) || !preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end) || $start >= $end) {
        flash('error', 'Complete all class fields with a valid capacity and meeting time.');
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO sections (course_id, section_code, instructor, room, capacity, school_year, semester) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$courseId, $sectionCode, $instructor, $room, $capacity, $schoolYear, $semester]);
            $sectionId = (int) $pdo->lastInsertId();
            $stmt = $pdo->prepare('INSERT INTO section_schedules (section_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)');
            $stmt->execute([$sectionId, $day, $start, $end]);
            $pdo->commit();
            flash('success', 'Class offering created.');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            flash('error', $e->getCode() === '23000' ? 'That course and section already exists for this term.' : 'The class could not be created.');
        }
    }
    redirect('admin_sections.php');
}

$courses = $pdo->query('SELECT c.id, c.code, c.title, c.units, COUNT(s.id) AS section_count FROM courses c LEFT JOIN sections s ON s.course_id = c.id GROUP BY c.id, c.code, c.title, c.units ORDER BY c.code')->fetchAll();
$sectionsStmt = $pdo->prepare("SELECT s.id, s.section_code, s.instructor, s.room, s.capacity, s.is_active, c.code, c.title, (SELECT COUNT(*) FROM enrollments e WHERE e.section_id=s.id AND e.status='enrolled') enrolled_count, GROUP_CONCAT(CONCAT(ss.day_of_week, ' ', TIME_FORMAT(ss.start_time, '%h:%i %p'), '-', TIME_FORMAT(ss.end_time, '%h:%i %p')) ORDER BY " . dayOrderSql('ss.day_of_week') . " SEPARATOR ', ') schedule FROM sections s JOIN courses c ON c.id=s.course_id JOIN section_schedules ss ON ss.section_id=s.id WHERE s.school_year = ? AND s.semester = ? GROUP BY s.id,c.code,c.title,s.section_code,s.instructor,s.room,s.capacity,s.is_active ORDER BY c.code,s.section_code");
$sectionsStmt->execute([$schoolYear, $semester]);
$sections = $sectionsStmt->fetchAll();
renderHeader('Manage Classes', $user);
?>
<section class="page-heading"><div><p class="eyebrow">Academic administration</p><h1>Class Offerings</h1><p>Create and control sections for the current term.</p></div></section>
<details class="card create-panel"><summary>Add a class offering</summary><form method="post" class="form-card compact-form"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="create"><div class="form-grid"><label>Course<select name="course_id" required><option value="">Select a course</option><?php foreach ($courses as $course): ?><option value="<?= (int) $course['id'] ?>"><?= e($course['code'] . ' — ' . $course['title']) ?></option><?php endforeach; ?></select></label><label>Section code<input name="section_code" required maxlength="30"></label><label>Instructor<input name="instructor" required maxlength="150"></label><label>Room<input name="room" required maxlength="50"></label><label>Capacity<input type="number" name="capacity" required min="1" max="500" value="30"></label><label>Meeting day<select name="day_of_week" required><?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day): ?><option><?= $day ?></option><?php endforeach; ?></select></label><label>Start time<input type="time" name="start_time" required></label><label>End time<input type="time" name="end_time" required></label></div><button>Create class</button></form></details>
<section class="card table-card"><div class="table-wrap"><table><thead><tr><th>Course / section</th><th>Schedule</th><th>Instructor / room</th><th>Enrollment</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach ($sections as $section): ?><tr><td><strong><?= e($section['code'] . ' · ' . $section['section_code']) ?></strong><br><small><?= e($section['title']) ?></small></td><td><?= e($section['schedule']) ?></td><td><?= e($section['instructor']) ?><br><small><?= e($section['room']) ?></small></td><td><?= (int) $section['enrolled_count'] ?> / <?= (int) $section['capacity'] ?></td><td><span class="status <?= $section['is_active'] ? 'open' : 'closed' ?>"><?= $section['is_active'] ? 'Active' : 'Inactive' ?></span></td><td><div class="action-stack"><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="section_id" value="<?= (int) $section['id'] ?>"><button class="small secondary"><?= $section['is_active'] ? 'Deactivate' : 'Activate' ?></button></form><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="delete_section"><input type="hidden" name="section_id" value="<?= (int) $section['id'] ?>"><button class="small danger">Remove</button></form></div></td></tr><?php endforeach; ?><?php if (!$sections): ?><tr><td colspan="6" class="empty">No class offerings exist for the current term.</td></tr><?php endif; ?></tbody></table></div></section>
<details class="card create-panel catalog-panel"><summary>Manage course catalog</summary><form method="post" class="form-card compact-form catalog-form"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="create_course"><div class="form-grid"><label>Course code<input name="code" required maxlength="30" pattern="[A-Za-z0-9-]+"></label><label>Course title<input name="title" required maxlength="150"></label><label>Units<input type="number" name="units" required min="0.1" max="99.9" step="0.1" value="3.0"></label><label>Description (optional)<textarea name="description" maxlength="10000" rows="3"></textarea></label></div><button>Add course</button></form><p class="helper-text">A course can be deleted after all of its class offerings have been removed. Offerings with enrollment history must be retained.</p><div class="table-wrap"><table><thead><tr><th>Code</th><th>Course title</th><th>Units</th><th>Offerings</th><th>Action</th></tr></thead><tbody><?php foreach ($courses as $course): ?><tr><td><strong><?= e($course['code']) ?></strong></td><td><?= e($course['title']) ?></td><td><?= e((string) $course['units']) ?></td><td><?= (int) $course['section_count'] ?></td><td><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="delete_course"><input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>"><button class="small danger">Delete course</button></form></td></tr><?php endforeach; ?></tbody></table></div></details>
<?php renderFooter(); ?>
