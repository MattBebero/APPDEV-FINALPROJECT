<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
$user = requireRole($pdo, 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    $tuition = filter_input(INPUT_POST, 'tuition', FILTER_VALIDATE_FLOAT);
    $misc = filter_input(INPUT_POST, 'miscellaneous', FILTER_VALIDATE_FLOAT);
    $payments = filter_input(INPUT_POST, 'payments', FILTER_VALIDATE_FLOAT);
    if (!$studentId || $tuition === false || $misc === false || $payments === false || !is_finite((float) $tuition) || !is_finite((float) $misc) || !is_finite((float) $payments) || min($tuition, $misc, $payments) < 0 || max($tuition, $misc, $payments) > 9999999999.99) {
        flash('error', 'Enter valid non-negative financial amounts.');
    } else {
        $studentCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'student' AND is_active = 1");
        $studentCheck->execute([$studentId]);
        if (!$studentCheck->fetchColumn()) {
            flash('error', 'The selected active student does not exist.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO balances (student_id, tuition, miscellaneous, payments) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE tuition = VALUES(tuition), miscellaneous = VALUES(miscellaneous), payments = VALUES(payments)');
            $stmt->execute([$studentId, $tuition, $misc, $payments]);
            flash('success', 'Student balance updated.');
        }
    }
    redirect('admin_balances.php');
}

$rows = $pdo->query("SELECT u.id, u.student_number, u.first_name, u.last_name, COALESCE(b.tuition,0) tuition, COALESCE(b.miscellaneous,0) miscellaneous, COALESCE(b.payments,0) payments, COALESCE(b.tuition + b.miscellaneous - b.payments,0) total, b.updated_at FROM users u LEFT JOIN balances b ON b.student_id = u.id WHERE u.role = 'student' AND u.is_active = 1 ORDER BY u.last_name, u.first_name")->fetchAll();
renderHeader('Manage Balances', $user);
?>
<section class="page-heading"><div><p class="eyebrow">Financial administration</p><h1>Student Balances</h1><p>Update assessed tuition, fees, and cumulative payments.</p></div></section>
<section class="card table-card"><div class="table-wrap"><table><thead><tr><th>Student</th><th>Tuition</th><th>Miscellaneous</th><th>Payments</th><th>Amount due</th><th>Update</th></tr></thead><tbody><?php foreach ($rows as $row): ?><?php $formId = 'balance-' . (int) $row['id']; ?><tr><td><strong><?= e($row['last_name'] . ', ' . $row['first_name']) ?></strong><br><small><?= e($row['student_number']) ?></small></td><td><input form="<?= e($formId) ?>" class="money-input" type="number" name="tuition" min="0" max="9999999999.99" step="0.01" required value="<?= e(number_format((float) $row['tuition'], 2, '.', '')) ?>"></td><td><input form="<?= e($formId) ?>" class="money-input" type="number" name="miscellaneous" min="0" max="9999999999.99" step="0.01" required value="<?= e(number_format((float) $row['miscellaneous'], 2, '.', '')) ?>"></td><td><input form="<?= e($formId) ?>" class="money-input" type="number" name="payments" min="0" max="9999999999.99" step="0.01" required value="<?= e(number_format((float) $row['payments'], 2, '.', '')) ?>"></td><td><strong>&#8369;<?= number_format((float) $row['total'], 2) ?></strong></td><td><form id="<?= e($formId) ?>" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="student_id" value="<?= (int) $row['id'] ?>"><button class="small">Save</button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php renderFooter(); ?>
