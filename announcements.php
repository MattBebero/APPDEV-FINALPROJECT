<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
$user = requireLogin($pdo);
$announcements = $pdo->query("SELECT a.title, a.body, a.created_at, u.first_name, u.last_name FROM announcements a JOIN users u ON u.id = a.posted_by WHERE a.is_published = 1 ORDER BY a.created_at DESC")->fetchAll();
renderHeader('Announcements', $user);
?>
<section class="page-heading"><div><p class="eyebrow">Campus updates</p><h1>Announcements</h1><p>Official notices from Campus One administrators.</p></div></section>
<section class="card"><?php foreach ($announcements as $announcement): ?><article class="announcement"><div><h2><?= e($announcement['title']) ?></h2><p><?= nl2br(e($announcement['body'])) ?></p></div><small><?= e(date('F j, Y · g:i A', strtotime($announcement['created_at']))) ?> &middot; <?= e($announcement['first_name'] . ' ' . $announcement['last_name']) ?></small></article><?php endforeach; ?><?php if (!$announcements): ?><p class="empty">No announcements have been published.</p><?php endif; ?></section>
<?php renderFooter(); ?>
