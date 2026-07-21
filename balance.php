<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
$user = requireRole($pdo, 'student');
$stmt = $pdo->prepare('SELECT tuition, miscellaneous, payments, tuition + miscellaneous - payments AS total, updated_at FROM balances WHERE student_id = ?');
$stmt->execute([$user['id']]);
$balance = $stmt->fetch() ?: ['tuition'=>0,'miscellaneous'=>0,'payments'=>0,'total'=>0,'updated_at'=>null];
renderHeader('Account Balance', $user);
?>
<section class="page-heading"><div><p class="eyebrow">Student financials</p><h1>Account Balance</h1><p>Student no. <?= e($user['student_number']) ?></p></div></section>
<section class="balance-layout"><div class="balance-hero"><span>Outstanding balance</span><strong>&#8369;<?= number_format((float) $balance['total'], 2) ?></strong><small>Last updated <?= $balance['updated_at'] ? e(date('M j, Y g:i A', strtotime($balance['updated_at']))) : '—' ?></small></div>
<div class="card"><h2>Statement summary</h2><div class="money-row"><span>Tuition charges</span><strong>&#8369;<?= number_format((float) $balance['tuition'], 2) ?></strong></div><div class="money-row"><span>Miscellaneous fees</span><strong>&#8369;<?= number_format((float) $balance['miscellaneous'], 2) ?></strong></div><div class="money-row credit"><span>Payments / credits</span><strong>− &#8369;<?= number_format((float) $balance['payments'], 2) ?></strong></div><div class="money-row total"><span>Amount due</span><strong>&#8369;<?= number_format((float) $balance['total'], 2) ?></strong></div></div></section>
<?php renderFooter(); ?>
