<?php
declare(strict_types=1);

ob_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/audit.php';

function mobile_out(array $payload, int $status = 200): void
{
    http_response_code($status);
    if (ob_get_length()) ob_clean();
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mobile_out(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$username = trim((string)($input['username'] ?? ''));
$password = (string)($input['password'] ?? '');

if ($username === '' || $password === '') {
    mobile_out(['success' => false, 'message' => 'Username and password are required'], 422);
}

$db = $GLOBALS['db'] ?? null;
if (!$db instanceof mysqli) {
    mobile_out(['success' => false, 'message' => 'DB not available'], 500);
}

$stmt = $db->prepare("SELECT u.id, u.role_id, r.name AS role, u.username, u.full_name,
                             u.password_hash, u.is_active
                      FROM users u
                      JOIN roles r ON r.id = u.role_id
                      WHERE u.username = ? OR u.email = ?
                      LIMIT 1");
if (!$stmt) mobile_out(['success' => false, 'message' => 'Login service unavailable'], 500);

$stmt->bind_param('ss', $username, $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || (int)$user['is_active'] !== 1 || !password_verify($password, (string)$user['password_hash'])) {
    audit_log('auth.mobile_login_failed', 'user', $username, 'Invalid credentials');
    mobile_out(['success' => false, 'message' => 'Invalid credentials'], 401);
}

session_regenerate_id(true);
$_SESSION['csrf'] = bin2hex(random_bytes(32));
$_SESSION['user'] = [
    'id' => (int)$user['id'],
    'role_id' => (int)$user['role_id'],
    'role' => (string)$user['role'],
    'username' => (string)$user['username'],
    'name' => (string)$user['full_name'],
];

$stmt = $db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
if ($stmt) {
    $userId = (int)$user['id'];
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
}

audit_log('auth.mobile_login_success', 'user', (string)$user['id'], 'Mobile login successful');

mobile_out([
    'success' => true,
    'user' => $_SESSION['user'],
    'csrf' => $_SESSION['csrf'],
]);
