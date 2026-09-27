<?php
require_once __DIR__ . '/includes/functions.php';
require_role(['patient', 'doctor', 'admin']);

$u = current_user();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $u['role'] === 'doctor') {
    verify_csrf();

    $patient = (int)($_POST['patient_id'] ?? 0);
    $med = trim($_POST['medication'] ?? '');
    $dos = trim($_POST['dosage'] ?? '');
    $freq = trim($_POST['frequency'] ?? '');
    $days = (int)($_POST['duration_days'] ?? 0);
    $ins = trim($_POST['instructions'] ?? '');

    if ($patient && $med && $dos && $freq && $days > 0) {
        $did = doctor_id_for_user($u['id']);
        $s = $pdo->prepare('INSERT INTO prescriptions(patient_id, doctor_id, medication, dosage, frequency, duration_days, instructions, status) VALUES(?, ?, ?, ?, ?, ?, ?, "active")');
        $s->execute([$patient, $did, $med, $dos, $freq, $days, $ins]);

        $uid = $pdo->prepare('SELECT user_id FROM patients WHERE id = ?');
        $uid->execute([$patient]);

        notify((int)$uid->fetchColumn(), 'New e-prescription', $med . ' · ' . $dos . ' · ' . $freq, 'prescription');
        audit('CREATE_PRESCRIPTION', 'Patient ' . $patient . ' / ' . $med);
        flash('E-prescription issued successfully.');
    }
    redirect('prescriptions.php');
}

$pageTitle = 'E-Prescriptions';
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <span class="kicker">Digital medication workflow</span>
        <h1>E-Prescriptions</h1>
        <p>Secure, readable medication instructions linked to the patient record.</p>
    </div>
    <?php if ($u['role'] === 'doctor'): ?>
        <button class="btn btn-primary" data-modal="rxModal">＋ New prescription</button>
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
                    <th>Medication</th>
                    <th>Dosage</th>
                    <th>Schedule</th>
                    <th>Doctor</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($u['role'] === 'patient') {
                    $pid = patient_id_for_user($u['id']);
                    $s = $pdo->prepare('SELECT pr.*, CONCAT(d.title, " ", du.first_name, " ", du.last_name) doctor_name FROM prescriptions pr JOIN doctors d ON d.id = pr.doctor_id JOIN users du ON du.id = d.user_id WHERE pr.patient_id = ? ORDER BY pr.created_at DESC');
                    $s->execute([$pid]);
                } else {
                    $sql = 'SELECT pr.*, CONCAT(pu.first_name, " ", pu.last_name) patient_name, CONCAT(d.title, " ", du.first_name, " ", du.last_name) doctor_name FROM prescriptions pr JOIN patients p ON p.id = pr.patient_id JOIN users pu ON pu.id = p.user_id JOIN doctors d ON d.id = pr.doctor_id JOIN users du ON du.id = d.user_id';
                    $args = [];
                    if ($u['role'] === 'doctor') {
                        $sql .= ' WHERE pr.doctor_id = ?';
                        $args[] = doctor_id_for_user($u['id']);
                    }
                    $sql .= ' ORDER BY pr.created_at DESC';
                    $s = $pdo->prepare($sql);
                    $s->execute($args);
                }
                $rows = $s->fetchAll();

                if (!$rows):
                ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty">No prescriptions found.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <?php if ($u['role'] !== 'patient'): ?>
                                <td><?= e($r['patient_name']) ?></td>
                            <?php endif; ?>
                            <td>
                                <b><?= e($r['medication']) ?></b><br>
                                <span class="muted"><?= e($r['instructions']) ?></span>
                            </td>
                            <td><?= e($r['dosage']) ?></td>
                            <td>
                                <?= e($r['frequency']) ?><br>
                                <span class="muted"><?= $r['duration_days'] ?> days</span>
                            </td>
                            <td><?= e($r['doctor_name']) ?></td>
                            <td><?= status_badge($r['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($u['role'] === 'doctor'): ?>
    <?php $patients = $pdo->query('SELECT p.id, CONCAT(u.first_name, " ", u.last_name) name FROM patients p JOIN users u ON u.id = p.user_id ORDER BY u.last_name')->fetchAll(); ?>
    <div class="modal" id="rxModal">
        <div class="modal-card">
            <div class="modal-head">
                <div>
                    <span class="kicker">Clinical order</span>
                    <h2>Issue e-prescription</h2>
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
                    <label>Medication</label>
                    <input name="medication" required>
                </div>
                <div class="field">
                    <label>Dosage</label>
                    <input name="dosage" placeholder="e.g. 500 mg" required>
                </div>
                <div class="field">
                    <label>Frequency</label>
                    <input name="frequency" placeholder="e.g. Twice daily" required>
                </div>
                <div class="field">
                    <label>Duration (days)</label>
                    <input type="number" min="1" max="365" name="duration_days" required>
                </div>
                <div class="field full">
                    <label>Instructions</label>
                    <textarea name="instructions" rows="3"></textarea>
                </div>
                <div class="field full">
                    <button class="btn btn-primary">Issue prescription</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>