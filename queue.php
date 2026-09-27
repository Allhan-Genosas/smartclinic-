<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$u = current_user();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($u['role'] === 'patient' && $action === 'checkin') {
        $pid = patient_id_for_user($u['id']);
        $s = $pdo->prepare('SELECT id FROM appointments WHERE id = ? AND patient_id = ? AND appointment_date = CURDATE() AND status IN("booked", "confirmed")');
        $s->execute([$id, $pid]);

        if ($s->fetchColumn()) {
            $max = (int)$pdo->query('SELECT COALESCE(MAX(queue_number), 0) FROM queue_entries WHERE DATE(check_in_time) = CURDATE()')->fetchColumn();
            try {
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO queue_entries(appointment_id, queue_number, status, priority) VALUES(?, ?, "waiting", "normal")')->execute([$id, $max + 1]);
                $pdo->prepare('UPDATE appointments SET status = "checked_in" WHERE id = ?')->execute([$id]);
                $pdo->commit();

                audit('PATIENT_CHECKIN', 'Appointment ' . $id);
                flash('Check-in complete. You are now in the smart queue.');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                flash('You may already be checked in.', 'error');
            }
        }
        redirect('queue.php');
    }

    if (in_array($u['role'], ['doctor', 'nurse', 'admin'], true) && $action === 'queue_status') {
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['waiting', 'called', 'in_consultation', 'completed', 'skipped'], true)) {
            $s = $pdo->prepare(
                'UPDATE queue_entries q 
                 JOIN appointments a ON a.id = q.appointment_id 
                 SET q.status = ?, 
                     q.called_at = IF(? = "called", NOW(), q.called_at), 
                     q.completed_at = IF(? = "completed", NOW(), q.completed_at), 
                     q.wait_minutes = IF(? IN ("called", "in_consultation", "completed"), TIMESTAMPDIFF(MINUTE, q.check_in_time, NOW()), q.wait_minutes), 
                     a.status = CASE 
                        WHEN ? = "in_consultation" THEN "in_consultation" 
                        WHEN ? = "completed" THEN "completed" 
                        ELSE a.status 
                     END 
                 WHERE q.id = ?'
            );
            $s->execute([$status, $status, $status, $status, $status, $status, $id]);
            audit('UPDATE_QUEUE', 'Queue ' . $id . ' -> ' . $status);
            flash('Queue status updated.');
        }
        redirect('queue.php');
    }
}

$pageTitle = 'Smart Queue';
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <span class="kicker">Dynamic patient flow</span>
        <h1>Smart queue</h1>
        <p>Live check-in and queue status helps reduce uncertainty and improves staff coordination.</p>
    </div>
</div>

<?php if ($u['role'] === 'patient'): ?>
    <?php
    $pid = patient_id_for_user($u['id']);
    $s = $pdo->prepare('SELECT q.*, a.appointment_time, CONCAT(d.title, " ", du.first_name, " ", du.last_name) doctor_name FROM queue_entries q JOIN appointments a ON a.id = q.appointment_id JOIN doctors d ON d.id = a.doctor_id JOIN users du ON du.id = d.user_id WHERE a.patient_id = ? AND DATE(q.check_in_time) = CURDATE() ORDER BY q.id DESC LIMIT 1');
    $s->execute([$pid]);
    $q = $s->fetch();

    if ($q):
        $ahead = $pdo->prepare('SELECT COUNT(*) FROM queue_entries WHERE DATE(check_in_time) = CURDATE() AND status = "waiting" AND queue_number < ?');
        $ahead->execute([$q['queue_number']]);
        $ahead = (int)$ahead->fetchColumn();
    ?>
        <div class="grid grid-3">
            <div class="card">
                <small>Your queue number</small>
                <div class="value" style="font-size:46px;font-weight:800;color:var(--teal2)">
                    A<?= str_pad($q['queue_number'], 2, '0', STR_PAD_LEFT) ?>
                </div>
            </div>
            <div class="card">
                <small>Patients ahead</small>
                <div class="value" style="font-size:46px;font-weight:800"><?= $ahead ?></div>
            </div>
            <div class="card">
                <small>Current status</small>
                <div style="margin-top:14px"><?= status_badge($q['status']) ?></div>
                <p class="muted small"><?= e($q['doctor_name']) ?></p>
            </div>
        </div>
    <?php else: ?>
        <?php
        $s = $pdo->prepare('SELECT a.id, a.appointment_time, CONCAT(d.title, " ", du.first_name, " ", du.last_name) doctor_name FROM appointments a JOIN doctors d ON d.id = a.doctor_id JOIN users du ON du.id = d.user_id LEFT JOIN queue_entries q ON q.appointment_id = a.id WHERE a.patient_id = ? AND a.appointment_date = CURDATE() AND a.status IN("booked", "confirmed") AND q.id IS NULL ORDER BY a.appointment_time LIMIT 1');
        $s->execute([$pid]);
        $appt = $s->fetch();
        ?>
        <div class="card">
            <?php if ($appt): ?>
                <h3>Ready to check in?</h3>
                <p class="muted">Today's appointment: <?= date('g:i A', strtotime($appt['appointment_time'])) ?> with <?= e($appt['doctor_name']) ?>.</p>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="checkin">
                    <input type="hidden" name="id" value="<?= $appt['id'] ?>">
                    <button class="btn btn-primary">Check in now</button>
                </form>
            <?php else: ?>
                <div class="empty">You do not have an eligible appointment to check in for today.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <div class="card">
        <div class="split-head">
            <h3>Today's live queue</h3>
            <span class="pill">Auto-updated on refresh</span>
        </div>
        <div class="list">
            <?php
            $sql = 'SELECT q.*, a.appointment_time, CONCAT(pu.first_name, " ", pu.last_name) patient_name, CONCAT(d.title, " ", du.first_name, " ", du.last_name) doctor_name, a.doctor_id FROM queue_entries q JOIN appointments a ON a.id = q.appointment_id JOIN patients p ON p.id = a.patient_id JOIN users pu ON pu.id = p.user_id JOIN doctors d ON d.id = a.doctor_id JOIN users du ON du.id = d.user_id WHERE DATE(q.check_in_time) = CURDATE()';
            $args = [];
            if ($u['role'] === 'doctor') {
                $sql .= ' AND a.doctor_id = ?';
                $args[] = doctor_id_for_user($u['id']);
            }
            $sql .= ' ORDER BY FIELD(q.priority, "emergency", "high", "normal"), q.queue_number';
            $s = $pdo->prepare($sql);
            $s->execute($args);
            $rows = $s->fetchAll();

            if (!$rows):
            ?>
                <div class="empty">No patients have checked in yet.</div>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <div class="queue-card">
                        <div class="queue-number">A<?= str_pad($r['queue_number'], 2, '0', STR_PAD_LEFT) ?></div>
                        <div>
                            <b><?= e($r['patient_name']) ?></b>
                            <p class="muted small"><?= e($r['doctor_name']) ?> · Check-in <?= date('g:i A', strtotime($r['check_in_time'])) ?> · <?= e(ucfirst($r['priority'])) ?> priority</p>
                        </div>
                        <div class="queue-actions">
                            <form method="post" style="display:flex;gap:7px;align-items:center">
                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                <input type="hidden" name="action" value="queue_status">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <select name="status" class="btn btn-ghost btn-sm">
                                    <?php foreach (['waiting', 'called', 'in_consultation', 'completed', 'skipped'] as $st): ?>
                                        <option value="<?= $st ?>" <?= $r['status'] === $st ? 'selected' : '' ?>><?= str_replace('_', ' ', ucfirst($st)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-primary btn-sm">Update</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>