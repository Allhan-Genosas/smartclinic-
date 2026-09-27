<?php
require_once __DIR__ . '/includes/functions.php';
require_role(['patient', 'doctor', 'nurse', 'admin']);

$u = current_user();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($u['role'], ['nurse', 'admin'], true)) {
    verify_csrf();
    
    $patient = (int)($_POST['patient_id'] ?? 0);
    $test = trim($_POST['test_name'] ?? '');
    $result = trim($_POST['result'] ?? '');
    $range = trim($_POST['reference_range'] ?? '');
    $status = $_POST['status'] ?? 'pending';

    if ($patient && $test && in_array($status, ['pending', 'processing', 'ready'], true)) {
        $s = $pdo->prepare(
            'INSERT INTO lab_results(patient_id, test_name, result, reference_range, status, ordered_at, resulted_at) 
             VALUES(?, ?, ?, ?, ?, NOW(), IF(? = "ready", NOW(), NULL))'
        );
        $s->execute([$patient, $test, $result, $range, $status, $status]);

        $uid = $pdo->prepare('SELECT user_id FROM patients WHERE id = ?');
        $uid->execute([$patient]);

        notify((int)$uid->fetchColumn(), 'Lab update', $test . ' is now ' . $status . '.', 'lab');
        audit('ADD_LAB_RESULT', 'Patient ' . $patient . ' / ' . $test);
        flash('Lab record saved.');
    }
    redirect('labs.php');
}

$pageTitle = 'Lab Results';
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <span class="kicker">Diagnostics integration</span>
        <h1>Lab & test tracking</h1>
        <p>Diagnostic results are linked directly to the patient profile and visible to authorized users.</p>
    </div>
    <?php if (in_array($u['role'], ['nurse', 'admin'], true)): ?>
        <button class="btn btn-primary" data-modal="labModal">＋ Record test</button>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <?php if ($u['role'] !== 'patient'): ?>
                        <th>Patient</th>
                    <?php endif; ?>
                    <th>Test</th>
                    <th>Result</th>
                    <th>Reference</th>
                    <th>Ordered</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($u['role'] === 'patient') {
                    $pid = patient_id_for_user($u['id']);
                    $s = $pdo->prepare('SELECT l.* FROM lab_results l WHERE patient_id = ? ORDER BY ordered_at DESC');
                    $s->execute([$pid]);
                } else {
                    $s = $pdo->query('SELECT l.*, CONCAT(u.first_name, " ", u.last_name) patient_name FROM lab_results l JOIN patients p ON p.id = l.patient_id JOIN users u ON u.id = p.user_id ORDER BY l.ordered_at DESC LIMIT 100');
                }
                $rows = $s->fetchAll();

                if (!$rows):
                ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty">No lab records found.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <?php if ($u['role'] !== 'patient'): ?>
                                <td><?= e($r['patient_name']) ?></td>
                            <?php endif; ?>
                            <td><b><?= e($r['test_name']) ?></b></td>
                            <td><?= e($r['result'] ?: 'Awaiting result') ?></td>
                            <td><?= e($r['reference_range'] ?: '—') ?></td>
                            <td><?= date('d M Y', strtotime($r['ordered_at'])) ?></td>
                            <td><?= status_badge($r['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (in_array($u['role'], ['nurse', 'admin'], true)): ?>
    <?php $patients = $pdo->query('SELECT p.id, CONCAT(u.first_name, " ", u.last_name) name FROM patients p JOIN users u ON u.id = p.user_id ORDER BY u.last_name')->fetchAll(); ?>
    <div class="modal" id="labModal">
        <div class="modal-card">
            <div class="modal-head">
                <div>
                    <span class="kicker">Diagnostics</span>
                    <h2>Record lab/test update</h2>
                </div>
                <button class="modal-close" data-close-modal>×</button>
            </div>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <div class="field full">
                    <label>Patient</label>
                    <select name="patient_id" required>
                        <option value="">Select patient</option>
                        <?php foreach ($patients as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Test name</label>
                    <input name="test_name" required>
                </div>
                <div class="field">
                    <label>Status</label>
                    <select name="status">
                        <option>pending</option>
                        <option>processing</option>
                        <option>ready</option>
                    </select>
                </div>
                <div class="field">
                    <label>Result</label>
                    <input name="result">
                </div>
                <div class="field">
                    <label>Reference range</label>
                    <input name="reference_range">
                </div>
                <div class="field full">
                    <button class="btn btn-primary">Save lab record</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>