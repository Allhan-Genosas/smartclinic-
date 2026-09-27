<?php
require_once __DIR__ . '/includes/functions.php';
require_role(['admin']);

$pdo = db();
$pageTitle = 'Operational Analytics';

$patients = (int)$pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn();
$thisMonth = (int)$pdo->query('SELECT COUNT(*) FROM appointments WHERE appointment_date >= DATE_FORMAT(CURDATE(), "%Y-%m-01") AND status <> "cancelled"')->fetchColumn();
$completed = (int)$pdo->query('SELECT COUNT(*) FROM appointments WHERE appointment_date >= DATE_FORMAT(CURDATE(), "%Y-%m-01") AND status = "completed"')->fetchColumn();
$avg = (float)$pdo->query('SELECT COALESCE(AVG(wait_minutes), 0) FROM queue_entries WHERE check_in_time >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND wait_minutes IS NOT NULL')->fetchColumn();

$hourly = $pdo->query('SELECT HOUR(appointment_time) hr, COUNT(*) total FROM appointments WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND status <> "cancelled" GROUP BY HOUR(appointment_time) ORDER BY hr')->fetchAll();
$max = max(array_column($hourly, 'total') ?: [1]);

$util = $pdo->query('SELECT CONCAT(d.title, " ", u.first_name, " ", u.last_name) doctor, d.specialty, COUNT(a.id) total FROM doctors d JOIN users u ON u.id = d.user_id LEFT JOIN appointments a ON a.doctor_id = d.id AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND a.status <> "cancelled" GROUP BY d.id ORDER BY total DESC')->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <span class="kicker">Predictive & operational insight</span>
        <h1>Clinic analytics</h1>
        <p>Patient flow, wait time and doctor utilization indicators support staffing and service improvement decisions.</p>
    </div>
    <span class="pill">Last 30 days</span>
</div>

<div class="grid grid-4">
    <div class="card stat-card">
        <div>
            <small>Registered patients</small>
            <div class="value"><?= $patients ?></div>
        </div>
        <span class="stat-icon">♙</span>
    </div>
    <div class="card stat-card">
        <div>
            <small>Monthly bookings</small>
            <div class="value"><?= $thisMonth ?></div>
        </div>
        <span class="stat-icon">◫</span>
    </div>
    <div class="card stat-card">
        <div>
            <small>Completed visits</small>
            <div class="value"><?= $completed ?></div>
        </div>
        <span class="stat-icon">✓</span>
    </div>
    <div class="card stat-card">
        <div>
            <small>Average wait</small>
            <div class="value"><?= round($avg) ?>m</div>
        </div>
        <span class="stat-icon">◷</span>
    </div>
</div>

<div class="grid grid-2" style="margin-top:18px">
    <div class="card">
        <h3>Appointment demand by hour</h3>
        <p class="muted small">Historical demand indicator for identifying peak periods.</p>
        <?php if (!$hourly): ?>
            <div class="empty">Not enough appointment data.</div>
        <?php else: ?>
            <?php foreach ($hourly as $h): ?>
                <?php $pct = round($h['total'] / $max * 100); ?>
                <div class="metric-row">
                    <span><?= date('g A', strtotime($h['hr'] . ':00')) ?></span>
                    <div class="bar">
                        <span style="width:<?= $pct ?>%"></span>
                    </div>
                    <b><?= $h['total'] ?></b>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="card">
        <h3>Doctor utilization</h3>
        <p class="muted small">Number of appointments assigned during the last 30 days.</p>
        <?php foreach ($util as $d): ?>
            <div class="list-item">
                <span>
                    <b><?= e($d['doctor']) ?></b>
                    <p><?= e($d['specialty']) ?></p>
                </span>
                <span class="pill"><?= $d['total'] ?> visits</span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="card" style="margin-top:18px">
    <div class="split-head">
        <h3>Predictive staffing insight</h3>
        <span class="badge badge-info">Prototype analytics</span>
    </div>
    <p class="muted">SmartClinic+ can use historical appointment volume and average wait time to identify peak clinic hours. In a production system, this layer can be extended with forecasting models to predict demand and recommend staffing levels. For the prototype, the hourly demand chart provides a transparent baseline indicator without making unsupported clinical predictions.</p>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>