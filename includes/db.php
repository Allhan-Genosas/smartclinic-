<?php
require_once __DIR__ . '/../config/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        $safe = APP_ENV === 'development' ? htmlspecialchars($e->getMessage()) : 'Database connection failed.';
        die('<div style="font-family:Arial;padding:2rem;max-width:760px;margin:auto"><h2>SmartClinic+ database connection error</h2><p>' . $safe . '</p><p>Import <code>database/smartclinic_plus.sql</code> in phpMyAdmin, then check <code>config/config.php</code>.</p></div>');
    }
}
