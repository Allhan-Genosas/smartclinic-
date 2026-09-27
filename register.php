<?php
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $fn = trim($_POST['first_name'] ?? '');
    $ln = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pw = $_POST['password'] ?? '';
    $dob = $_POST['dob'] ?? '';

    if (!$fn || !$ln || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < 8 || !$dob) {
        flash('Please complete all fields. Password must be at least 8 characters.', 'error');
        redirect('register.php');
    }

    try {
        $pdo = db();
        $pdo->beginTransaction();

        $s = $pdo->prepare('INSERT INTO users(first_name, last_name, email, password_hash, role) VALUES(?, ?, ?, ?, "patient")');
        $s->execute([$fn, $ln, $email, password_hash($pw, PASSWORD_DEFAULT)]);
        $uid = (int)$pdo->lastInsertId();

        $s = $pdo->prepare('INSERT INTO patients(user_id, dob, loyalty_points) VALUES(?, ?, 0)');
        $s->execute([$uid, $dob]);

        $pdo->commit();
        $_SESSION['user_id'] = $uid;

        flash('Your patient account has been created.');
        redirect('dashboard.php');
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        flash('Unable to create account. The email may already be registered.', 'error');
        redirect('register.php');
    }
}

$pageTitle = 'Create patient account';
include __DIR__ . '/includes/header.php';
?>

<main class="auth-page">
    <div class="auth-card">
        <span class="kicker">Patient portal</span>
        <h1>Create an account</h1>
        <p>Use SmartClinic+ to book visits and manage your healthcare journey.</p>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <div class="field">
                <label>First name</label>
                <input name="first_name" required>
            </div>
            <div class="field">
                <label>Last name</label>
                <input name="last_name" required>
            </div>
            <div class="field full">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>
            <div class="field">
                <label>Date of birth</label>
                <input type="date" name="dob" required>
            </div>
            <div class="field">
                <label>Password</label>
                <input type="password" name="password" minlength="8" required>
            </div>
            <div class="field full">
                <button class="btn btn-primary">Create secure account</button>
            </div>
        </form>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>