<?php
require_once __DIR__ . '/../includes/functions.php';

require_login();
header('Content-Type: application/json');

$doctor = (int)($_GET['doctor_id'] ?? 0);
$date = $_GET['date'] ?? '';

if (!$doctor || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['slots' => []]);
    exit;
}

$times = [
    '09:00:00', '09:30:00', '10:00:00', '10:30:00', 
    '11:00:00', '11:30:00', '13:00:00', '13:30:00', 
    '14:00:00', '14:30:00', '15:00:00', '15:30:00', 
    '16:00:00'
];

$s = db()->prepare('SELECT appointment_time FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status IN ("booked", "confirmed", "checked_in", "in_consultation")');
$s->execute([$doctor, $date]);

$busy = array_map(fn($x) => $x['appointment_time'], $s->fetchAll());

$slots = array_map(fn($t) => [
    'time'      => substr($t, 0, 5),
    'label'     => date('g:i A', strtotime($t)),
    'available' => !in_array($t, $busy, true)
], $times);

echo json_encode(['slots' => $slots]);