<?php
require_once __DIR__ . '/auth.php';

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function full_name(array $u): string
{
    return trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function audit(string $action, string $details = ''): void
{
    $u = current_user();
    if (!$u) return;

    $stmt = db()->prepare('INSERT INTO audit_logs(user_id, action, details, ip_address) VALUES(?, ?, ?, ?)');
    $stmt->execute([$u['id'], $action, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
}

function notify(int $userId, string $title, string $message, string $type = 'info'): void
{
    $stmt = db()->prepare('INSERT INTO notifications(user_id, title, message, type) VALUES(?, ?, ?, ?)');
    $stmt->execute([$userId, $title, $message, $type]);
}

function patient_id_for_user(int $userId): ?int
{
    $s = db()->prepare('SELECT id FROM patients WHERE user_id = ?');
    $s->execute([$userId]);
    $v = $s->fetchColumn();
    return $v ? (int)$v : null;
}

function doctor_id_for_user(int $userId): ?int
{
    $s = db()->prepare('SELECT id FROM doctors WHERE user_id = ?');
    $s->execute([$userId]);
    $v = $s->fetchColumn();
    return $v ? (int)$v : null;
}

function role_label(string $role): string
{
    return [
        'patient' => 'Patient',
        'doctor'  => 'Doctor',
        'nurse'   => 'Nurse / Staff',
        'admin'   => 'Administrator'
    ][$role] ?? ucfirst($role);
}

function status_badge(string $status): string
{
    $class = match ($status) {
        'confirmed', 'completed', 'active', 'ready' => 'success',
        'booked', 'pending', 'waiting'             => 'warning',
        'cancelled', 'inactive'                    => 'danger',
        'checked_in', 'in_consultation'           => 'info',
        default                                    => 'neutral'
    };
    return '<span class="badge badge-' . $class . '">' . e(str_replace('_', ' ', ucwords($status, '_'))) . '</span>';
}