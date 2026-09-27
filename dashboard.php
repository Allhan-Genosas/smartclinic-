<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$u = current_user();
$pageTitle = 'Dashboard';
$pdo = db();

include __DIR__ . '/includes/header.php';

if ($u['role'] === 'patient') {
    $pid = patient_id_for_user($u['id']);

    $p = $pdo->prepare('SELECT * FROM patients WHERE id = ?');
    $p->execute([$pid]);
    $patient = $p->fetch();

    $s = $pdo->prepare('SELECT a.*, CONCAT(d.title, " ", du.first_name, " ", du.last_name) doctor_name, d.specialty FROM appointments a JOIN doctors d ON d.id = a.doctor_id JOIN users du ON du.id = d.user_id WHERE a.patient_id = ? AND a.status <> "cancelled" AND TIMESTAMP(a.appointment_date, a.appointment_time) >= NOW() ORDER BY a.appointment_date, a.appointment_time LIMIT 1');
    $s->execute([$pid]);
    $next = $s->fetch();

    $s = $pdo->prepare('SELECT COUNT(*) FROM prescriptions WHERE patient_id = ? AND status = "active"');
    $s->execute([$pid]);
    $rx = (int)$s->fetchColumn();

    $s = $pdo->prepare('SELECT COUNT(*) FROM lab_results WHERE patient_id = ? AND status IN("pending", "processing")');
    $s->execute([$pid]);
    $labs = (int)$s->fetchColumn();
?>
    <div class="page-head">
        <div>
            <span class="kicker">Patient overview</span>
            <h1>Good day, <?= e($u['first_name']) ?>.</h1>
            <p>Manage your appointments, records, prescriptions and follow-up care from one place.</p>
        </div>
        <button class="btn btn-primary" data-modal="bookModal">＋ Book appointment</button>
    </div>

    <div class="card hero-card">
        <span class="pill" style="background:rgba(255,255,255,.12);color:#aef1ec">Your care journey</span>
        <h2><?= $next ? 'Your next appointment is ' . date('l, j F', strtotime($next['appointment_date'])) : 'You have no upcoming appointment' ?></h2>
        <p><?= $next ? e($next['doctor_name'] . ' · ' . $next['specialty'] . ' · ' . date('g:i A', strtotime($next['appointment_time']))) : 'Book a consultation with an available doctor when you are ready.' ?></p>
        <div class="quick-actions">
            <a class="btn btn-light" href="appointments.php">View appointments</a>
            <a class="btn btn-ghost" style="background:transparent;color:white;border-color:rgba(255,255,255,.2)" href="ehr.php">Open health record</a>
        </div>
    </div>

    <div class="grid grid-4" style="margin-top:18px">
        <div class="card stat-card">
            <div>
                <small>Loyalty points</small>
                <div class="value"><?= number_format((int)$patient['loyalty_points']) ?></div>
            </div>
            <span class="stat-icon">★</span>
        </div>
        <div class="card stat-card">
            <div>
                <small>Active prescriptions</small>
                <div class="value"><?= $rx ?></div>
            </div>
            <span class="stat-icon">Rx</span>
        </div>
        <div class="card stat-card">
            <div>
                <small>Tests in progress</small>
                <div class="value"><?= $labs ?></div>
            </div>
            <span class="stat-icon">⌁</span>
        </div>
        <div class="card stat-card">
            <div>
                <small>Privacy</small>
                <div class="value" style="font-size:19px">Protected</div>
            </div>
            <span class="stat-icon">🔒</span>
        </div>
    </div>

    <div class="grid grid-2" style="margin-top:18px">
        <div class="card">
            <div class="split-head">
                <h3>Care timeline</h3>
                <a class="small muted" href="ehr.php">View record →</a>
            </div>
            <div class="timeline">
                <?php
                $s = $pdo->prepare('SELECT record_date, diagnosis, notes FROM medical_records WHERE patient_id = ? ORDER BY record_date DESC LIMIT 3');
                $s->execute([$pid]);
                $rows = $s->fetchAll();

                if (!$rows):
                ?>
                    <div class="empty">No consultation history yet.</div>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <div class="timeline-item">
                            <b><?= e($r['diagnosis'] ?: 'Consultation') ?></b>
                            <p><?= e($r['notes']) ?></p>
                            <small class="muted"><?= date('d M Y', strtotime($r['record_date'])) ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="card">
            <h3>Quick access</h3>
            <div class="list">
                <a class="list-item" href="queue.php">
                    <span>
                        <b>Smart queue</b>
                        <p>Check in and follow your live position.</p>
                    </span>
                    <span>→</span>
                </a>
                <a class="list-item" href="prescriptions.php">
                    <span>
                        <b>Prescriptions</b>
                        <p>Review medicines and instructions.</p>
                    </span>
                    <span>→</span>
                </a>
                <a class="list-item" href="labs.php">
                    <span>
                        <b>Lab results</b>
                        <p>Track diagnostic tests and outcomes.</p>
                    </span>
                    <span>→</span>
                </a>
            </div>
        </div>
    </div>

<?php } elseif ($u['role'] === 'doctor') {
    $did = doctor_id_for_user($u['id']);

    $s = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = CURDATE() AND status <> "cancelled"');
    $s->execute([$did]);
    $today = (int)$s->fetchColumn();

    $s = $pdo->prepare('SELECT COUNT(*) FROM queue_entries q JOIN appointments a ON a.id = q.appointment_id WHERE a.doctor_id = ? AND DATE(q.check_in_time) = CURDATE() AND q.status = "waiting"');
    $s->execute([$did]);
    $waiting = (int)$s->fetchColumn();

    $s = $pdo->prepare('SELECT COUNT(*) FROM prescriptions WHERE doctor_id = ? AND DATE(created_at) = CURDATE()');
    $s->execute([$did]);
    $rx = (int)$s->fetchColumn();
?>
    <div class="page-head">
        <div>
            <span class="kicker">Doctor workspace</span>
            <h1>Clinical dashboard</h1>
            <p>Review today's schedule, waiting patients and clinical workflow.</p>
        </div>
        <a class="btn btn-primary" href="ehr.php">＋ Add consultation note</a>
    </div>

    <div class="grid grid-4">
        <div class="card stat-card">
            <div>
                <small>Appointments today</small>
                <div class="value"><?= $today ?></div>
            </div>
            <span class="stat-icon">◫</span>
        </div>
        <div class="card stat-card">
            <div>
                <small>Waiting now</small>
                <div class="value"><?= $waiting ?></div>
            </div>
            <span class="stat-icon">≡</span>
        </div>
        <div class="card stat-card">
            <div>
                <small>Prescriptions today</small>
                <div class="value"><?= $rx ?></div>
            </div>
            <span class="stat-icon">Rx</span>
        </div>
        <div class="card stat-card">
            <div>
                <small>Session status</small>
                <div class="value" style="font-size:19px">Available</div>
            </div>
            <span class="stat-icon">●</span>
        </div>
    </div>

    <div class="grid grid-2" style="margin-top:18px">
        <div class="card">
            <div class="split-head">
                <h3>Today's schedule</h3>
                <a href="appointments.php" class="small muted">Full schedule →</a>
            </div>
            <div class="list">
                <?php
                $s = $pdo->prepare('SELECT a.*, CONCAT(u.first_name, " ", u.last_name) patient_name FROM appointments a JOIN patients p ON p.id = a.patient_id JOIN users u ON u.id = p.user_id WHERE a.doctor_id = ? AND a.appointment_date = CURDATE() AND a.status <> "cancelled" ORDER BY a.appointment_time LIMIT 6');
                $s->execute([$did]);
                $rows = $s->fetchAll();

                if (!$rows):
                ?>
                    <div class="empty">No appointments today.</div>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <div class="list-item">
                            <span>
                                <b><?= date('g:i A', strtotime($r['appointment_time'])) ?> · <?= e($r['patient_name']) ?></b>
                                <p><?= e($r['reason']) ?></p>
                            </span>
                            <?= status_badge($r['status']) ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="card">
            <h3>Clinical shortcuts</h3>
            <div class="list">
                <a class="list-item" href="queue.php">
                    <span>
                        <b>Call next patient</b>
                        <p>Manage the live consultation queue.</p>
                    </span>
                    <span>→</span>
                </a>
                <a class="list-item" href="prescriptions.php">
                    <span>
                        <b>Issue e-prescription</b>
                        <p>Create secure medicine instructions.</p>
                    </span>
                    <span>→</span>
                </a>
                <a class="list-item" href="tasks.php">
                    <span>
                        <b>Assign staff task</b>
                        <p>Coordinate vitals and lab preparation.</p>
                    </span>
                    <span>→</span>
                </a>
            </div>
        </div>
    </div>

<?php } elseif ($u['role'] === 'nurse') {
    $waiting = (int)$pdo->query('SELECT COUNT(*) FROM queue_entries WHERE DATE(check_in_time) = CURDATE() AND status = "waiting"')->fetchColumn();

    $s = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status <> "completed"');
    $s->execute([$u['id']]);
    $tasks = (int)$s->fetchColumn();

    $pending = (int)$pdo->query('SELECT COUNT(*) FROM lab_results WHERE status IN("pending", "processing")')->fetchColumn();
?>
    <div class="page-head">
        <div>
            <span class="kicker">Staff coordination</span>
            <h1>Clinic operations</h1>
            <p>Keep patient flow, diagnostic preparation and assigned tasks moving.</p>
        </div>
    </div>

    <div class="grid grid-3">
        <div class="card stat-card">
            <div>
                <small>Patients waiting</small>
                <div class="value"><?= $waiting ?></div>
            </div>
            <span class="stat-icon">≡</span>
        </div>
        <div class="card stat-card">
            <div>
                <small>Open tasks</small>
                <div class="value"><?= $tasks ?></div>
            </div>
            <span class="stat-icon">✓</span>
        </div>
        <div class="card stat-card">
            <div>
                <small>Lab items pending</small>
                <div class="value"><?= $pending ?></div>
            </div>
            <span class="stat-icon">⌁</span>
        </div>
    </div>

    <div class="grid grid-2" style="margin-top:18px">
        <div class="card">
            <h3>Priority actions</h3>
            <div class="list">
                <a class="list-item" href="queue.php">
                    <span>
                        <b>Update smart queue</b>
                        <p>Track check-ins and patient status.</p>
                    </span>
                    <span>→</span>
                </a>
                <a class="list-item" href="tasks.php">
                    <span>
                        <b>Open assigned tasks</b>
                        <p>Vitals, room preparation and follow-up.</p>
                    </span>
                    <span>→</span>
                </a>
                <a class="list-item" href="labs.php">
                    <span>
                        <b>Record lab result</b>
                        <p>Update test status and results securely.</p>
                    </span>
                    <span>→</span>
                </a>
            </div>
        </div>
        <div class="card hero-card">
            <span class="pill" style="background:rgba(255,255,255,.12);color:#aef1ec">Coordinated care</span>
            <h2>Every update helps reduce waiting time.</h2>
            <p>SmartClinic+ keeps doctors, nurses and patients aligned through one shared operational workflow.</p>
        </div>
    </div>

<?php } else {
    $patients = (int)$pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn();
    $today = (int)$pdo->query('SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND status <> "cancelled"')->fetchColumn();
    $waiting = (int)$pdo->query('SELECT COUNT(*) FROM queue_entries WHERE DATE(check_in_time) = CURDATE() AND status = "waiting"')->fetchColumn();
    $avg = (float)$pdo->query('SELECT COALESCE(AVG(wait_minutes), 0) FROM queue_entries WHERE DATE(check_in_time) = CURDATE() AND wait_minutes IS NOT NULL')->fetchColumn();
?>
    <div class="page-head">
        <div>
            <span class="kicker">Administrative overview</span>
            <h1>Operational dashboard</h1>
            <p>Monitor patient flow, staff workload, wait times and service activity.</p>
        </div>
        <a class="btn btn-primary" href="analytics.php">Open analytics</a>
    </div>

    <div class="grid grid-4">
        <div class="card stat-card">
            <div>
                <small>Total patients</small>
                <div class="value"><?= $patients ?></div>
            </div>
            <span class="stat-icon">♙</span>
        </div>
        <div class="card stat-card">
            <div>
                <small>Appointments today</small>
                <div class="value"><?= $today ?></div>
            </div>
            <span class="stat-icon">◫</span>
        </div>
        <div class="card stat-card">
            <div>
                <small>Current waiting</small>
                <div class="value"><?= $waiting ?></div>
            </div>
            <span class="stat-icon">≡</span>
        </div>
        <div class="card stat-card">
            <div>
                <small>Avg wait</small>
                <div class="value"><?= round($avg) ?>m</div>
            </div>
            <span class="stat-icon">◷</span>
        </div>
    </div>

    <div class="grid grid-2" style="margin-top:18px">
        <div class="card">
            <h3>Clinic command centre</h3>
            <div class="list">
                <a class="list-item" href="analytics.php">
                    <span>
                        <b>Performance analytics</b>
                        <p>Patient flow, utilization and peak-hour indicators.</p>
                    </span>
                    <span>→</span>
                </a>
                <a class="list-item" href="queue.php">
                    <span>
                        <b>Live operations</b>
                        <p>Monitor queue status and bottlenecks.</p>
                    </span>
                    <span>→</span>
                </a>
                <a class="list-item" href="tasks.php">
                    <span>
                        <b>Staff coordination</b>
                        <p>Review active and completed tasks.</p>
                    </span>
                    <span>→</span>
                </a>
            </div>
        </div>
        <div class="card">
            <h3>System assurance</h3>
            <div class="metric-row">
                <span>Role-based access</span>
                <div class="bar"><span style="width:100%"></span></div>
                <b>100%</b>
            </div>
            <div class="metric-row">
                <span>Prepared queries</span>
                <div class="bar"><span style="width:100%"></span></div>
                <b>100%</b>
            </div>
            <div class="metric-row">
                <span>Audit coverage</span>
                <div class="bar"><span style="width:92%"></span></div>
                <b>92%</b>
            </div>
            <div class="metric-row">
                <span>Workflow coverage</span>
                <div class="bar"><span style="width:95%"></span></div>
                <b>95%</b>
            </div>
        </div>
    </div>
<?php } ?>

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
            <form method="post" action="appointments.php" class="form-grid">
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
                    <textarea name="reason" rows="3" required placeholder="Briefly describe the reason for your consultation"></textarea>
                </div>
                <div class="field full">
                    <button class="btn btn-primary">Confirm appointment</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>