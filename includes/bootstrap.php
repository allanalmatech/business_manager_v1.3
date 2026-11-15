<?php
// includes/bootstrap.php
declare(strict_types=1);


/**
 * PHP 7 compatible bootstrap.
 * - No str_contains()
 * - Guards missing server vars
 * - Avoids parse errors by complete structure
 */

// Suppress displayed errors for API endpoints so they cannot corrupt JSON.
$reqUri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
$isApiRequest = defined('API_REQUEST') || preg_match('~/api(?:/|$)~', $reqUri) === 1;
if ($isApiRequest) {
    ini_set('display_errors', '0');
}

// Load configs (make sure these files RETURN arrays)
$dbCfg  = require __DIR__ . '/../config/db.php';
$appCfg = require __DIR__ . '/../config/app.php';

// Define app version constant
if (!defined('APP_VERSION')) {
    define('APP_VERSION', isset($appCfg['app_version']) ? (string)$appCfg['app_version'] : '1.0.0');
}

// Connect DB (errors are handled explicitly, never thrown to the browser)
mysqli_report(MYSQLI_REPORT_OFF);
$mysqli = @new mysqli(
    (string)$dbCfg['host'],
    (string)$dbCfg['username'],
    (string)$dbCfg['password'],
    (string)$dbCfg['database']
);

if ($mysqli->connect_error) {
    http_response_code(500);
    if ($isApiRequest) {
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode(['ok' => false, 'error' => 'Database connection failed.']));
    }
    $setupHint = is_file(__DIR__ . '/../config/installed.lock') ? '' : ' If this is a new install, open setup.php.';
    die('Database connection failed.' . $setupHint);
}

$charset = isset($dbCfg['charset']) ? (string)$dbCfg['charset'] : 'utf8mb4';
$mysqli->set_charset($charset);

// Secure session settings
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (!$isHttps && !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $isHttps = (strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
}
ini_set('session.cookie_secure', $isHttps ? '1' : '0');

function bootstrap_can_use_db_sessions(mysqli $mysqli): bool
{
    $tableCheck = $mysqli->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sessions' LIMIT 1");
    if (!$tableCheck || $tableCheck->num_rows < 1) {
        return false;
    }

    $probeId = '__session_probe_' . bin2hex(random_bytes(8));
    $now = time();
    $stmt = $mysqli->prepare("INSERT INTO sessions (id, user_id, data, ip_address, user_agent, last_activity) VALUES (?, NULL, '', '', '', ?)");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('si', $probeId, $now);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        return false;
    }

    $stmt = $mysqli->prepare("DELETE FROM sessions WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('s', $probeId);
        $stmt->execute();
        $stmt->close();
    }

    return true;
}

// DB session handler (optional)
$sessionBackend = 'php';
$sessionHandlerFile = __DIR__ . '/session_db.php';
if (is_file($sessionHandlerFile)) {
    require_once $sessionHandlerFile;

    if (class_exists('DbSessionHandler') && bootstrap_can_use_db_sessions($mysqli)) {
        $handler = new DbSessionHandler($mysqli, 60 * 60 * 2); // 2h
        if (session_set_save_handler($handler, true)) {
            $sessionBackend = 'database';
        }
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('BMSESSID');
    session_start();
}

// Make DB accessible
$GLOBALS['db'] = $mysqli;

// CSRF: one token per session, enforced for every signed-in write request
require_once __DIR__ . '/csrf.php';
csrf_token();
csrf_enforce($isApiRequest || preg_match('~/pos_api\.php~', $reqUri) === 1);
$GLOBALS['SESSION_BACKEND'] = $sessionBackend;

// Base URL for assets/links (subfolder safe)
$BASE_URL = '';
$baseResolved = false;

$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string)$_SERVER['DOCUMENT_ROOT']) : '';
$projRoot = realpath(__DIR__ . '/..');

if ($docRoot && $projRoot) {
    $docRootN = str_replace('\\', '/', rtrim($docRoot, "\\/"));
    $projRootN = str_replace('\\', '/', rtrim($projRoot, "\\/"));

    if (stripos($projRootN, $docRootN) === 0) {
        $BASE_URL = rtrim('/' . ltrim((string)substr($projRootN, strlen($docRootN)), '/'), '/');
        $baseResolved = true; // an empty value is correct here: the app sits at the web root
    }
}

if (!$baseResolved) {
    // Aliased or symlinked installs: strip this script's path inside the project from its URL
    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptFile = str_replace('\\', '/', (string)realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')));
    $projRootN  = str_replace('\\', '/', (string)$projRoot);
    $inside = ($projRootN !== '' && strpos($scriptFile, $projRootN) === 0) ? substr($scriptFile, strlen($projRootN)) : '';
    if ($inside !== '' && substr($scriptName, -strlen($inside)) === $inside) {
        $BASE_URL = rtrim(substr($scriptName, 0, -strlen($inside)), '/');
    }
}

$GLOBALS['BASE_URL'] = $BASE_URL;
