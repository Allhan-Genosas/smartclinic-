<?php
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $s = db()->prepare('SELECT * FROM users WHERE email = ? AND status = "active" LIMIT 1');
    $s->execute([$email]);
    $u = $s->fetch();

    if ($u && password_verify($password, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $u['id'];
        audit('LOGIN', 'Successful login');
        redirect('dashboard.php');
    }

    flash('Incorrect email or password.', 'error');
    redirect('login.php');
}

$pageTitle = 'Sign in';
include __DIR__ . '/includes/header.php';
?>

<main class="auth-page">
    <div class="auth-card">
        <span class="kicker">Secure access</span>
        <h1>Welcome back</h1>
        <p>Sign in to your SmartClinic+ role workspace.</p>
        <form method="post" class="grid">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <div class="field">
                <label>Email</label>
                <input type="email" name="email" required placeholder="name@example.com">
            </div>
            <div class="field">
                <label>Password</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            <button class="btn btn-primary" type="submit">Sign in</button>
        </form>
        <div class="demo-box">
            <b>Prototype demo accounts</b>
            <code>admin@smartclinic.test · doctor@smartclinic.test</code>
            <code>nurse@smartclinic.test · patient@smartclinic.test</code>
            <code>Password: Password123!</code>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>