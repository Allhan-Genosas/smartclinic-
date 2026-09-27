<?php
// SmartClinic+ local configuration. XAMPP defaults work out of the box after importing the SQL file.
define('APP_NAME', 'SmartClinic+');
define('APP_BASE', rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\'));
define('DB_HOST', getenv('SMARTCLINIC_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('SMARTCLINIC_DB_PORT') ?: '3306');
define('DB_NAME', getenv('SMARTCLINIC_DB_NAME') ?: 'smartclinic_plus');
define('DB_USER', getenv('SMARTCLINIC_DB_USER') ?: 'root');
define('DB_PASS', getenv('SMARTCLINIC_DB_PASS') ?: '');
define('APP_ENV', getenv('SMARTCLINIC_ENV') ?: 'development');

date_default_timezone_set('Australia/Sydney');

if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}
