<?php
require_once __DIR__ . '/includes/functions.php';
require_role(['patient', 'doctor', 'admin']);

$u = current_user();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (in_array($u['role'], ['doctor', 'admin'], true) && ($_POST['action'] ?? '') === 'add_record') {
        $patient = (int)($_POST['patient_id'] ?? 0);
        $diag = trim($_POST['diagnosis'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $all = trim($_POST['allergies'] ?? '');

        if ($patient && $diag && $notes) {
            $did = $u['role'] === 'doctor' ? doctor_id_for_user($u['id']) : null;
            $s = $pdo->prepare('INSERT INTO medical_records(patient_id, doctor_id, diagnosis, notes, allergies, record_date) VALUES(?, ?, ?, ?, ?, NOW())');
            $s->execute([$patient, $did, $diag, $notes, $all]);

            audit('ADD_EHR_RECORD', 'Patient ' . $patient);
            flash('Consultation note added to the EHR.');
        }
        redirect('ehr.php?patient_id=' . $patient);
    }
}

$patientId = $u['role'] === 'patient' ? patient_id_for_user($u['id']) : (int)($_GET['patient_id'] ?? 0);
$patients = [];

if ($u['role'] !== 'patient') {
    $patients = $pdo->query('SELECT p.id, CONCAT(u.first_name, " ", u.last_name) name, p.dob, p.blood_type FROM patients p JOIN users u ON u.id = p.user_id ORDER BY u.last_name')->fetchAll();
}

$patient = null;
$records = [];

if ($patientId) {
    $s = $pdo->prepare('SELECT p.*, CONCAT(u.first_name, " ", u.last_name) name, u.email FROM patients p JOIN users u ON u.id = p.user_id WHERE p.id = ?');
    $s->execute([$patientId]);
    $patient = $s->fetch();

    $s = $pdo->prepare('SELECT m.*, CONCAT(COALESCE(d.title, ""), " ", COALESCE(du.first_name, "System"), " ", COALESCE(du.last_name, "")) doctor_name FROM medical_records m LEFT JOIN doctors d ON d.id = m.doctor_id LEFT JOIN users du ON du.id = d.user_id WHERE m.patient_id = ? ORDER BY m.record_date DESC');
    $s->execute([$patientId]);
    $records = $s->fetchAll();
}

$pageTitle = 'Electronic Health Record';
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <span class="kicker">Secure clinical data</span>
        <h1>Electronic Health Record</h1>
        <p>Centralized medical history with privacy-aware role-based access.</p>
    </div>
    <?php if ($u['role'] !== 'patient'): ?>
        <form method="get">
            <select name="patient_id" class="btn btn-ghost" onchange="this.form.submit()">
                <option value="">Select patient</option>
                <?php foreach ($patients as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $patientId === $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    <?php endif; ?>
</div>

<?php if (!$patient): ?>
    <div class="card">
        <div class="empty">Select a patient to open their health record.</div>
    </div>
<?php else: ?>
    <div class="grid grid-3">
        <div class="card">
            <small>Patient</small>
            <h2><?= e($patient['name']) ?></h2>
            <p class="muted small"><?= e($patient['email']) ?></p>
        </div>
        <div class="card">
            <small>Date of birth</small>
            <h2><?= date('d M Y', strtotime($patient['dob'])) ?></h2>
            <p class="muted small">Blood type: <?= e($patient['blood_type'] ?: 'Not recorded') ?></p>
        </div>
        <div class="card">
            <small>Emergency contact</small>
            <h2 style="font-size:18px"><?= e($patient['emergency_contact'] ?: 'Not recorded') ?></h2>
            <p class="muted small">Sensitive data visible only to authorized roles.</p>
        </div>
    </div>

    <div class="grid grid-2" style="margin-top:18px">
        <div class="card">
            <h3>Clinical history</h3>
            <?php if (!$records): ?>
                <div class="empty">No clinical records yet.</div>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($records as $r): ?>
                        <div class="timeline-item">
                            <b><?= e($r['diagnosis']) ?></b>
                            <p><?= nl2br(e($r['notes'])) ?></p>
                            <?php if ($r['allergies']): ?>
                                <span class="pill">Allergy: <?= e($r['allergies']) ?></span>
                            <?php endif; ?>
                            <small class="muted" style="display:block;margin-top:6px"><?= date('d M Y, g:i A', strtotime($r['record_date'])) ?> · <?= e(trim($r['doctor_name'])) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($u['role'] !== 'patient'): ?>
            <div class="card">
                <h3>Add consultation note</h3>
                <form method="post" class="grid">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="add_record">
                    <input type="hidden" name="patient_id" value="<?= $patientId ?>">
                    <div class="field">
                        <label>Diagnosis / assessment</label>
                        <input name="diagnosis" required>
                    </div>
                    <div class="field">
                        <label>Allergies / alerts</label>
                        <input name="allergies">
                    </div>
                    <div class="field">
                        <label>Consultation notes</label>
                        <textarea name="notes" rows="7" required></textarea>
                    </div>
                    <button class="btn btn-primary">Save secure note</button>
                </form>
            </div>
        <?php else: ?>
            <div class="card">
                <h3>Privacy controls</h3>
                <p class="muted small">Your health information is protected using authenticated access, role restrictions and audited clinical actions. Only authorized clinical roles can add or update records in this prototype.</p>
                <div class="list">
                    <div class="list-item">
                        <span>
                            <b>Patient access</b>
                            <p>Read your own record.</p>
                        </span>
                        <span class="badge badge-success">Enabled</span>
                    </div>
                    <div class="list-item">
                        <span>
                            <b>Doctor access</b>
                            <p>Clinical care access.</p>
                        </span>
                        <span class="badge badge-success">Controlled</span>
                    </div>
                    <div class="list-item">
                        <span>
                            <b>Audit logging</b>
                            <p>Important actions are recorded.</p>
                        </span>
                        <span class="badge badge-info">Active</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>