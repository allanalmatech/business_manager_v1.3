<?php
// api/users/create.php
declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/rbac.php';
require_once dirname(dirname(__DIR__)) . '/includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

function out_ok($data = [], int $code = 200): void {
  http_response_code($code);
  if (ob_get_length()) ob_clean();
  echo json_encode(['success' => true, 'message' => 'User created successfully', 'data' => $data]);
  exit;
}

function out_err(string $msg, int $code = 400): void {
  http_response_code($code);
  if (ob_get_length()) ob_clean();
  echo json_encode(['success' => false, 'message' => $msg]);
  exit;
}

// Check authentication
$uid = (int)($_SESSION['user']['id'] ?? 0);
if ($uid <= 0) out_err('Not authenticated', 401);

// Check permission
if (!function_exists('user_has_permission') || !user_has_permission('admin.users')) {
  out_err('Permission denied', 403);
}

$db = $GLOBALS['db'] ?? null;
if (!($db instanceof mysqli)) out_err('Database not available', 500);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out_err('Method not allowed', 405);

$username = trim((string)($_POST['username'] ?? ''));
$fullName = trim((string)($_POST['name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$roleId = (int)($_POST['role_id'] ?? 0);
$isActive = (int)($_POST['is_active'] ?? 1);
$password = (string)($_POST['password'] ?? '');

// Validation
if (empty($username)) out_err('Username is required');
if (empty($fullName)) out_err('Full name is required');
if (empty($password) || strlen($password) < 6) out_err('Password must be at least 6 characters');
if ($roleId <= 0) out_err('Role is required');

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  out_err('Valid email is required');
}

// Check if username already exists
$stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
  $stmt->close();
  out_err('Username already exists');
}
$stmt->close();

// Check if email already exists (if provided)
if (!empty($email)) {
  $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  if ($stmt->get_result()->num_rows > 0) {
    $stmt->close();
    out_err('Email already exists');
  }
  $stmt->close();
}

// Verify role exists
$stmt = $db->prepare("SELECT id FROM roles WHERE id = ?");
$stmt->bind_param("i", $roleId);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
  $stmt->close();
  out_err('Invalid role');
}
$stmt->close();

// Hash password
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// Insert user
$stmt = $db->prepare("INSERT INTO users (username, full_name, email, phone, role_id, password_hash, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
$stmt->bind_param("sssssis", $username, $fullName, $email, $phone, $roleId, $passwordHash, $isActive);

if (!$stmt->execute()) {
  $stmt->close();
  out_err('Failed to create user: ' . $db->error);
}

$newUserId = $stmt->insert_id;
$stmt->close();

out_ok(['id' => $newUserId, 'username' => $username, 'full_name' => $fullName]);
?>
