<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$u = current_user();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($u['role'] === 'patient' && $action === 'book') {
        $pid = patient_id_for_user($u['id']);
        $doctor = (int)($_POST['doctor_id'] ?? 0);
        $date = $_POST['appointment_date'] ?? '';
        $time = $_POST['appointment_time'] ?? '';
        $reason = trim($_POST['reason'] ?? '');

        if (!$doctor || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}/', $time) || !$reason) {
            flash('Please complete all appointment fields.', 'error');
            redirect('appointments.php');
        }

        $lock = 'slot_' . $doctor . '_' . $date . '_' . str_replace(':', '', $time);
        try {
            $pdo->beginTransaction();
            $g = $pdo->prepare('SELECT GET_LOCK(?, 5)');
            $g->execute([$lock]);

            if ((int)$g->fetchColumn() !== 1) {
                throw new RuntimeException('The booking service is busy. Please try again.');
            }

            $check = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status IN("booked", "confirmed", "checked_in", "in_consultation")');
            $check->execute([$doctor, $date, $time]);

            if ((int)$check->fetchColumn() > 0) {
                throw new RuntimeException('That appointment slot has just been taken. Please choose another time.');
            }

            $ins = $pdo->prepare('INSERT INTO appointments(patient_id, doctor_id, appointment_date, appointment_time, reason, status) VALUES(?, ?, ?, ?, ?, "booked")');
            $ins->execute([$pid, $doctor, $date, $time, $reason]);

            $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);
            $pdo->commit();

            notify($u['id'], 'Appointment booked', 'Your appointment for ' . date('d M Y', strtotime($date)) . ' at ' . date('g:i A', strtotime($time)) . ' is confirmed.', 'appointment');
            audit('BOOK_APPOINTMENT', 'Appointment ID ' . $pdo->lastInsertId());
            flash('Appointment booked successfully.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);
                $pdo->rollBack();
            }
            flash($e->getMessage(), 'error');
        }
        redirect('appointments.php');
    }

    if ($u['role'] === 'patient' && $action === 'cancel') {
        $id = (int)($_POST['id'] ?? 0);
        $pid = patient_id_for_user($u['id']);

        $s = $pdo->prepare('UPDATE appointments SET status = "cancelled" WHERE id = ? AND patient_id = ? AND appointment_date >= CURDATE()');
        $s->execute([$id, $pid]);

        audit('CANCEL_APPOINTMENT', 'Appointment ID ' . $id);
        flash('Appointment cancelled.');
        redirect('appointments.php');
    }

    if ($u['role'] === 'patient' && $action === 'reschedule') {
        $id = (int)($_POST['id'] ?? 0);
        $pid = patient_id_for_user($u['id']);
        $date = $_POST['appointment_date'] ?? '';
        $time = $_POST['appointment_time'] ?? '';

        $s = $pdo->prepare('SELECT doctor_id FROM appointments WHERE id = ? AND patient_id = ? AND status NOT IN("cancelled", "completed")');
        $s->execute([$id, $pid]);
        $doctor = (int)$s->fetchColumn();

        if (!$doctor || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}/', $time) || $date < date('Y-m-d')) {
            flash('Invalid reschedule request.', 'error');
            redirect('appointments.php');
        }

        $lock = 'slot_' . $doctor . '_' . $date . '_' . str_replace(':', '', $time);
        try {
            $pdo->beginTransaction();
            $g = $pdo->prepare('SELECT GET_LOCK(?, 5)');
            $g->execute([$lock]);

            if ((int)$g->fetchColumn() !== 1) {
                throw new RuntimeException('The booking service is busy. Please try again.');
            }

            $check = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND id <> ? AND status IN("booked", "confirmed", "checked_in", "in_consultation")');
            $check->execute([$doctor, $date, $time, $id]);

            if ((int)$check->fetchColumn() > 0) {
                throw new RuntimeException('That new slot is already taken. Please choose another time.');
            }

            $pdo->prepare('UPDATE appointments SET appointment_date = ?, appointment_time = ?, status = "booked" WHERE id = ? AND patient_id = ?')->execute([$date, $time, $id, $pid]);
            $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);
            $pdo->commit();

            notify($u['id'], 'Appointment rescheduled', 'Your appointment was moved to ' . date('d M Y', strtotime($date)) . ' at ' . date('g:i A', strtotime($time)) . '.', 'appointment');
            audit('RESCHEDULE_APPOINTMENT', 'Appointment ' . $id . ' -> ' . $date . ' ' . $time);
            flash('Appointment rescheduled successfully.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);
                $pdo->rollBack();
            }
            flash($e->getMessage(), 'error');
        }
        redirect('appointments.php');
    }

    if (in_array($u['role'], ['doctor', 'admin'], true) && $action === 'status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';

        if (in_array($status, ['confirmed', 'completed', 'cancelled'], true)) {
            $prev = $pdo->prepare('SELECT a.status, a.patient_id, p.user_id FROM appointments a JOIN patients p ON p.id = a.patient_id WHERE a.id = ?');
            $prev->execute([$id]);
            $before = $prev->fetch();

            $s = $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ?');
            $s->execute([$status, $id]);

            if ($status === 'completed' && $before && $before['status'] !== 'completed') {
                $pdo->prepare('UPDATE patients SET loyalty_points = loyalty_points + 20 WHERE id = ?')->execute([$before['patient_id']]);
                $pdo->prepare('INSERT INTO loyalty_transactions(patient_id, points, reason) VALUES(?, 20, "Completed clinic visit")')->execute([$before['patient_id']]);
                notify((int)$before['user_id'], 'Visit completed', 'You earned 20 SmartClinic+ engagement points for your completed visit.', 'engagement');
            }

            audit('UPDATE_APPOINTMENT_STATUS', 'Appointment ' . $id . ' -> ' . $status);
            flash('Appointment status updated.');
        }
        redirect('appointments.php');
    }
}

$pageTitle = 'Appointments';
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <span class="kicker">Scheduling</span>
        <h1><?= $u['role'] === 'patient' ? 'My appointments' : 'Appointment schedule' ?></h1>
        <p>Real-time scheduling with conflict prevention and clear appointment status.</p>
    </div>
    <?php if ($u['role'] === 'patient'): ?>
        <button class="btn btn-primary" data-modal="bookModal">＋ Book appointment</button>
    <?php endif; ?>
</div>

<?php if ($u['role'] === 'patient' && !empty($_GET['reschedule'])): ?>
    <?php
    $rid = (int)$_GET['reschedule'];
    $pid = patient_id_for_user($u['id']);
    $rs = $pdo->prepare('SELECT a.*, CONCAT(d.title, " ", du.first_name, " ", du.last_name) doctor_name FROM appointments a JOIN doctors d ON d.id = a.doctor_id JOIN users du ON du.id = d.user_id WHERE a.id = ? AND a.patient_id = ? AND a.status NOT IN("completed", "cancelled")');
    $rs->execute([$rid, $pid]);
    $ra = $rs->fetch();
    ?>
    <?php if ($ra): ?>
        <div class="card" style="margin-bottom:18px">
            <div class="split-head">
                <div>
                    <span class="kicker">Reschedule</span>
                    <h3 style="margin-top:5px">Move appointment with <?= e($ra['doctor_name']) ?></h3>
                </div>
                <a class="btn btn-ghost btn-sm" href="appointments.php">Close</a>
            </div>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="reschedule">
                <input type="hidden" name="id" value="<?= $ra['id'] ?>">
                <div class="field">
                    <label>New date</label>
                    <input type="date" name="appointment_date" min="<?= date('Y-m-d') ?>" value="<?= e($ra['appointment_date']) ?>" required>
                </div>
                <div class="field">
                    <label>New time</label>
                    <select name="appointment_time" required>
                        <?php foreach (['09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30', '16:00'] as $t): ?>
                            <option value="<?= $t ?>" <?= substr($ra['appointment_time'], 0, 5) === $t ? 'selected' : '' ?>><?= date('g:i A', strtotime($t)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field full">
                    <p class="muted small">The server will re-check the slot before saving to prevent conflicting bookings.</p>
                    <button class="btn btn-primary">Save new schedule</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <?php if ($u['role'] !== 'patient'): ?>
                        <th>Patient</th>
                    <?php endif; ?>
                    <th>Date & time</th>
                    <th><?= $u['role'] === 'patient' ? 'Doctor' : 'Clinician' ?></th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($u['role'] === 'patient') {
                    $pid = patient_id_for_user($u['id']);
                    $s = $pdo->prepare('SELECT a.*, CONCAT(d.title, " ", du.first_name, " ", du.last_name) doctor_name, d.specialty FROM appointments a JOIN doctors d ON d.id = a.doctor_id JOIN users du ON du.id = d.user_id WHERE a.patient_id = ? ORDER BY a.appointment_date DESC, a.appointment_time DESC');
                    $s->execute([$pid]);
                } elseif ($u['role'] === 'doctor') {
                    $did = doctor_id_for_user($u['id']);
                    $s = $pdo->prepare('SELECT a.*, CONCAT(pu.first_name, " ", pu.last_name) patient_name, CONCAT(d.title, " ", du.first_name, " ", du.last_name) doctor_name FROM appointments a JOIN patients p ON p.id = a.patient_id JOIN users pu ON pu.id = p.user_id JOIN doctors d ON d.id = a.doctor_id JOIN users du ON du.id = d.user_id WHERE a.doctor_id = ? ORDER BY a.appointment_date DESC, a.appointment_time DESC');
                    $s->execute([$did]);
                } else {
                    $s = $pdo->query('SELECT a.*, CONCAT(pu.first_name, " ", pu.last_name) patient_name, CONCAT(d.title, " ", du.first_name, " ", du.last_name) doctor_name FROM appointments a JOIN patients p ON p.id = a.patient_id JOIN users pu ON pu.id = p.user_id JOIN doctors d ON d.id = a.doctor_id JOIN users du ON du.id = d.user_id ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 100');
                }
                $rows = $s->fetchAll();

                if (!$rows):
                ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty">No appointments found.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <?php if ($u['role'] !== 'patient'): ?>
                                <td><b><?= e($r['patient_name']) ?></b></td>
                            <?php endif; ?>
                            <td>
                                <b><?= date('d M Y', strtotime($r['appointment_date'])) ?></b><br>
                                <span class="muted"><?= date('g:i A', strtotime($r['appointment_time'])) ?></span>
                            </td>
                            <td>
                                <?= e($r['doctor_name']) ?>
                                <?= isset($r['specialty']) ? '<br><span class="muted">' . e($r['specialty']) . '</span>' : '' ?>
                            </td>
                            <td><?= e($r['reason']) ?></td>
                            <td><?= status_badge($r['status']) ?></td>
                            <td>
                                <?php if ($u['role'] === 'patient' && $r['status'] !== 'cancelled' && $r['status'] !== 'completed' && $r['appointment_date'] >= date('Y-m-d')): ?>
                                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                                        <a class="btn btn-ghost btn-sm" href="appointments.php?reschedule=<?= $r['id'] ?>">Reschedule</a>
                                        <form method="post">
                                            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                            <button class="btn btn-danger btn-sm" data-confirm="Cancel this appointment?">Cancel</button>
                                        </form>
                                    </div>
                                <?php elseif (in_array($u['role'], ['doctor', 'admin'], true) && !in_array($r['status'], ['completed', 'cancelled'], true)): ?>
                                    <form method="post" style="display:flex;gap:6px">
                                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="status">
                                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                        <select name="status" class="btn btn-ghost btn-sm">
                                            <option>confirmed</option>
                                            <option>completed</option>
                                            <option>cancelled</option>
                                        </select>
                                        <button class="btn btn-primary btn-sm">Save</button>
                                    </form>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($u['role'] === 'patient'): ?>
    <?php $docs = $pdo->query('SELECT d.id, d.title, d.specialty, u.first_name, u.last_name FROM doctors d JOIN users u ON u.id = d.user_id WHERE d.is_available = 1 ORDER BY u.last_name')->fetchAll(); ?>
    <div class="modal" id="bookModal">
        <div class="modal-card">
            <div class="modal-head">
                <div>
                    <span class="kicker">New appointment</span>
                    <h2>Book a consultation</h2>
                </div>
                <button class="modal-close" data-close-modal>×</button>
            </div>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="book">
                <div class="field full">
                    <label>Doctor</label>
                    <select name="doctor_id" id="doctor_id" required>
                        <option value="">Choose doctor</option>
                        <?php foreach ($docs as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= e($d['title'] . ' ' . $d['first_name'] . ' ' . $d['last_name'] . ' — ' . $d['specialty']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Date</label>
                    <input type="date" id="appointment_date" name="appointment_date" min="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="field">
                    <label>Available time</label>
                    <select id="appointment_time" name="appointment_time" required>
                        <option value="">Select date first</option>
                    </select>
                </div>
                <div class="field full">
                    <label>Reason for visit</label>
                    <textarea name="reason" rows="3" required></textarea>
                </div>
                <div class="field full">
                    <button class="btn btn-primary">Confirm appointment</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>