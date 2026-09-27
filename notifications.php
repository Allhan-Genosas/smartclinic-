<?php
require_once __DIR__ . '/includes/functions.php';

require_login();

$u = current_user();
$pdo = db();

if (isset($_GET['read'])) {
    $id = (int) $_GET['read'];

    $pdo->prepare(
        'UPDATE notifications
         SET is_read = 1, read_at = NOW()
         WHERE id = ? AND user_id = ?'
    )->execute([
        $id,
        $u['id']
    ]);

    redirect('notifications.php');
}

if (isset($_GET['read_all'])) {
    $pdo->prepare(
        'UPDATE notifications
         SET is_read = 1, read_at = NOW()
         WHERE user_id = ?'
    )->execute([
        $u['id']
    ]);

    redirect('notifications.php');
}

$s = $pdo->prepare(
    'SELECT *
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 100'
);

$s->execute([
    $u['id']
]);

$rows = $s->fetchAll();

$pageTitle = 'Notifications';

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <span class="kicker">Automated communication</span>
        <h1>Notifications</h1>
        <p>Appointment, prescription, lab and task updates are collected in one secure inbox.</p>
    </div>
    <a class="btn btn-ghost" href="notifications.php?read_all=1">Mark all as read</a>
</div>

<div class="card" style="padding: 0">
    <?php if (!$rows): ?>
        <div class="empty" style="margin: 20px">No notifications yet.</div>
    <?php else: ?>
        <?php foreach ($rows as $n): ?>
            <a href="notifications.php?read=<?= $n['id'] ?>" class="notif <?= $n['is_read'] ? 'read' : 'unread' ?>">
                <span class="notif-dot"></span>
                <span>
                    <b><?= e($n['title']) ?></b>
                    <p class="muted small"><?= e($n['message']) ?></p>
                    <small class="muted"><?= date('d M Y, g:i A', strtotime($n['created_at'])) ?></small>
                </span>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>