<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
$user = requireRole($pdo, 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? 'create';
    if ($action === 'toggle') {
        $id = filter_input(INPUT_POST, 'announcement_id', FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = $pdo->prepare('UPDATE announcements SET is_published = 1 - is_published WHERE id = ?');
            $stmt->execute([$id]);
            flash('success', 'Announcement visibility updated.');
        }
    } else {
        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($title === '' || $body === '' || mb_strlen($title) > 180) {
            flash('error', 'A title of up to 180 characters and a message are required.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO announcements (title, body, posted_by, is_published) VALUES (?, ?, ?, ?)');
            $stmt->execute([$title, $body, $user['id'], isset($_POST['is_published']) ? 1 : 0]);
            flash('success', 'Announcement created.');
        }
    }
    redirect('admin_announcements.php');
}

$rows = $pdo->query('SELECT a.id, a.title, a.body, a.is_published, a.created_at, u.first_name, u.last_name FROM announcements a JOIN users u ON u.id=a.posted_by ORDER BY a.created_at DESC')->fetchAll();
renderHeader('Manage Announcements', $user);
?>
<section class="page-heading"><div><p class="eyebrow">Communications</p><h1>Announcements</h1><p>Publish official updates visible throughout the portal.</p></div></section>
<section class="two-column announcement-admin"><form method="post" class="card form-card"><h2>New announcement</h2><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="create"><label>Title<input name="title" required maxlength="180"></label><label>Message<textarea name="body" required rows="8"></textarea></label><label class="check-label"><input type="checkbox" name="is_published" value="1" checked> Publish immediately</label><button>Post announcement</button></form><section class="card"><h2>Announcement history</h2><?php foreach ($rows as $row): ?><article class="announcement"><div><div class="title-line"><h3><?= e($row['title']) ?></h3><span class="status <?= $row['is_published'] ? 'open' : 'closed' ?>"><?= $row['is_published'] ? 'Published' : 'Hidden' ?></span></div><p><?= nl2br(e($row['body'])) ?></p></div><div class="announcement-actions"><small><?= e(date('M j, Y g:i A', strtotime($row['created_at']))) ?></small><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="announcement_id" value="<?= (int) $row['id'] ?>"><button class="small secondary"><?= $row['is_published'] ? 'Hide' : 'Publish' ?></button></form></div></article><?php endforeach; ?></section></section>
<?php renderFooter(); ?>
