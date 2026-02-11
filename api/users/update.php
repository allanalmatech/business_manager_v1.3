<?php
// api/users/update.php
declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/rbac.php';
require_once dirname(dirname(__DIR__)) . '/includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

function out_ok($data = [], int $code = 200): void {
  http_response_code($code);
  if (ob_get_length()) ob_clean();
  echo json_encode(['success' => true, 'message' => 'User updated successfully', 'data' => $data]);
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

$id = (int)($_POST['id'] ?? 0);
$username = trim((string)($_POST['username'] ?? ''));
$fullName = trim((string)($_POST['name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$roleId = (int)($_POST['role_id'] ?? 0);
$isActive = (int)($_POST['is_active'] ?? 1);
$password = (string)($_POST['password'] ?? '');

// Validation
if ($id <= 0) out_err('User ID is required');
if (empty($username)) out_err('Username is required');
if (empty($fullName)) out_err('Full name is required');
if ($roleId <= 0) out_err('Role is required');

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  out_err('Valid email is required');
}

// Check if user exists
$stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
  $stmt->close();
  out_err('User not found');
}
$stmt->close();

// Check if username already exists (excluding current user)
$stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
$stmt->bind_param("si", $username, $id);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
  $stmt->close();
  out_err('Username already exists');
}
$stmt->close();

// Check if email already exists (if provided, excluding current user)
if (!empty($email)) {
  $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
  $stmt->bind_param("si", $email, $id);
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

// Build update query
$updateFields = ["username = ?", "full_name = ?", "email = ?", "phone = ?", "role_id = ?", "is_active = ?", "updated_at = NOW()"];
$types = "sssisi";
$values = [$username, $fullName, $email, $phone, $roleId, $isActive];

// Add password to update if provided
if (!empty($password) && strlen($password) >= 6) {
  $updateFields[] = "password_hash = ?";
  $types .= "s";
  $values[] = password_hash($password, PASSWORD_DEFAULT);
}

$sql = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE id = ?";
$types .= "i";
$values[] = $id;

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$values);

if (!$stmt->execute()) {
  $stmt->close();
  out_err('Failed to update user: ' . $db->error);
}

$stmt->close();

out_ok(['id' => $id, 'username' => $username, 'full_name' => $fullName]);
?>
