<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
$user = requireRole($pdo, 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $first = trim((string) ($_POST['first_name'] ?? ''));
    $last = trim((string) ($_POST['last_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $number = trim((string) ($_POST['student_number'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $validInstitutionEmail = filter_var($email, FILTER_VALIDATE_EMAIL)
        && str_ends_with($email, '@campusone.edu.ph')
        && mb_strlen($email) <= 190;
    if ($first === '' || mb_strlen($first) > 80 || $last === '' || mb_strlen($last) > 80 || !$validInstitutionEmail || $number === '' || mb_strlen($number) > 40 || strlen($password) < 8 || strlen($password) > 4096) {
        flash('error', 'Complete all fields using a @campusone.edu.ph email and a password of at least 8 characters.');
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, role, first_name, last_name, student_number) VALUES (?, ?, 'student', ?, ?, ?)");
            $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), $first, $last, $number]);
            $studentId = (int) $pdo->lastInsertId();
            $stmt = $pdo->prepare('INSERT INTO balances (student_id) VALUES (?)');
            $stmt->execute([$studentId]);
            $pdo->commit();
            flash('success', 'Student account created successfully.');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            flash('error', $e->getCode() === '23000' ? 'That email or student number already exists.' : 'The student could not be created.');
        }
    }
    redirect('admin_students.php');
}

// password_hash is deliberately excluded from this administrative listing.
$students = $pdo->query("SELECT u.id, u.student_number, u.first_name, u.last_name, u.email, u.phone, u.created_at, u.is_active, COALESCE(b.tuition + b.miscellaneous - b.payments, 0) balance, (SELECT COUNT(*) FROM enrollments e WHERE e.student_id = u.id AND e.status = 'enrolled') class_count FROM users u LEFT JOIN balances b ON b.student_id = u.id WHERE u.role = 'student' ORDER BY u.last_name, u.first_name")->fetchAll();
renderHeader('Manage Students', $user);
?>
<section class="page-heading"><div><p class="eyebrow">Administration</p><h1>Students</h1><p>Create accounts and review student records.</p></div></section>
<details class="card create-panel"><summary>Add a student account</summary><form method="post" class="form-card compact-form"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><div class="form-grid"><label>First name<input name="first_name" required maxlength="80"></label><label>Last name<input name="last_name" required maxlength="80"></label><label>Campus email<input type="email" name="email" required maxlength="190" pattern=".+@campusone\.edu\.ph"></label><label>Student number<input name="student_number" required maxlength="40"></label><label>Initial password<input type="password" name="password" required minlength="8" maxlength="4096"></label></div><button>Create student</button></form></details>
<section class="card table-card"><div class="table-wrap"><table><thead><tr><th>Student</th><th>Student no.</th><th>Contact</th><th>Classes</th><th>Balance</th><th>Joined</th></tr></thead><tbody><?php foreach ($students as $student): ?><tr><td><strong><?= e($student['last_name'] . ', ' . $student['first_name']) ?></strong></td><td><?= e($student['student_number']) ?></td><td><?= e($student['email']) ?><br><small><?= e($student['phone'] ?: 'No phone') ?></small></td><td><?= (int) $student['class_count'] ?></td><td>&#8369;<?= number_format((float) $student['balance'], 2) ?></td><td><?= e(date('M j, Y', strtotime($student['created_at']))) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php renderFooter(); ?>
