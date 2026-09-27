<?php
require_once __DIR__ . '/functions.php';

$u = current_user();
$pageTitle = $pageTitle ?? APP_NAME;
$current = basename($_SERVER['PHP_SELF']);
$unread = 0;

if ($u) {
    $q = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $q->execute([$u['id']]);
    $unread = (int)$q->fetchColumn();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php if ($u): ?>
    <div class="app-shell">
        <aside class="sidebar">
            <a class="brand" href="dashboard.php">
                <span class="brand-mark">S+</span>
                <span><strong>SmartClinic+</strong><small>Healthcare Platform</small></span>
            </a>
            <nav class="nav">
                <a class="<?= $current === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php"><span>⌂</span> Dashboard</a>
                <?php if ($u['role'] === 'patient'): ?>
                    <a class="<?= $current === 'appointments.php' ? 'active' : '' ?>" href="appointments.php"><span>◫</span> Appointments</a>
                    <a class="<?= $current === 'queue.php' ? 'active' : '' ?>" href="queue.php"><span>≡</span> My Queue</a>
                    <a class="<?= $current === 'ehr.php' ? 'active' : '' ?>" href="ehr.php"><span>♡</span> Health Record</a>
                    <a class="<?= $current === 'prescriptions.php' ? 'active' : '' ?>" href="prescriptions.php"><span>Rx</span> Prescriptions</a>
                    <a class="<?= $current === 'labs.php' ? 'active' : '' ?>" href="labs.php"><span>⌁</span> Lab Results</a>
                <?php elseif ($u['role'] === 'doctor'): ?>
                    <a class="<?= $current === 'appointments.php' ? 'active' : '' ?>" href="appointments.php"><span>◫</span> Schedule</a>
                    <a class="<?= $current === 'queue.php' ? 'active' : '' ?>" href="queue.php"><span>≡</span> Queue</a>
                    <a class="<?= $current === 'ehr.php' ? 'active' : '' ?>" href="ehr.php"><span>♡</span> Patient EHR</a>
                    <a class="<?= $current === 'prescriptions.php' ? 'active' : '' ?>" href="prescriptions.php"><span>Rx</span> E-Prescriptions</a>
                    <a class="<?= $current === 'labs.php' ? 'active' : '' ?>" href="labs.php"><span>⌁</span> Lab Results</a>
                    <a class="<?= $current === 'tasks.php' ? 'active' : '' ?>" href="tasks.php"><span>✓</span> Staff Tasks</a>
                <?php elseif ($u['role'] === 'nurse'): ?>
                    <a class="<?= $current === 'queue.php' ? 'active' : '' ?>" href="queue.php"><span>≡</span> Smart Queue</a>
                    <a class="<?= $current === 'tasks.php' ? 'active' : '' ?>" href="tasks.php"><span>✓</span> Assigned Tasks</a>
                    <a class="<?= $current === 'labs.php' ? 'active' : '' ?>" href="labs.php"><span>⌁</span> Lab Tracking</a>
                <?php else: ?>
                    <a class="<?= $current === 'appointments.php' ? 'active' : '' ?>" href="appointments.php"><span>◫</span> Appointments</a>
                    <a class="<?= $current === 'queue.php' ? 'active' : '' ?>" href="queue.php"><span>≡</span> Operations</a>
                    <a class="<?= $current === 'analytics.php' ? 'active' : '' ?>" href="analytics.php"><span>▥</span> Analytics</a>
                    <a class="<?= $current === 'tasks.php' ? 'active' : '' ?>" href="tasks.php"><span>✓</span> Staff Tasks</a>
                <?php endif; ?>
                <a class="<?= $current === 'notifications.php' ? 'active' : '' ?>" href="notifications.php">
                    <span>◉</span> Notifications 
                    <?php if ($unread): ?>
                        <b class="nav-count"><?= $unread ?></b>
                    <?php endif; ?>
                </a>
            </nav>
            <div class="sidebar-foot">
                <div class="privacy-chip">🔒 Privacy-first prototype</div>
                <a href="logout.php">↪ Sign out</a>
            </div>
        </aside>

        <main class="main">
            <header class="topbar">
                <div>
                    <button class="menu-btn" id="menuBtn" aria-label="Toggle navigation">☰</button>
                    <span class="eyebrow">SmartClinic+ Workspace</span>
                </div>
                <div class="user-chip">
                    <span class="avatar"><?= e(strtoupper(substr($u['first_name'], 0, 1) . substr($u['last_name'], 0, 1))) ?></span>
                    <span>
                        <strong><?= e(full_name($u)) ?></strong>
                        <small><?= e(role_label($u['role'])) ?></small>
                    </span>
                </div>
            </header>
            <section class="content">
                <?php if (!empty($_SESSION['flash'])): ?>
                    <?php $f = $_SESSION['flash']; unset($_SESSION['flash']); ?>
                    <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
                <?php endif; ?>
<?php else: ?>
    <header class="public-nav">
        <a class="brand dark" href="index.php">
            <span class="brand-mark">S+</span>
            <span><strong>SmartClinic+</strong><small>Intelligent Healthcare</small></span>
        </a>
        <nav>
            <a href="index.php#features">Features</a>
            <a href="index.php#workflow">How it works</a>
            <a href="login.php" class="btn btn-ghost">Sign in</a>
            <a href="register.php" class="btn btn-primary">Create account</a>
        </nav>
    </header>
<?php endif; ?>