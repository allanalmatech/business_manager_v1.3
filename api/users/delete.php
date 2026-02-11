<?php
// api/users/delete.php
declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/rbac.php';
require_once dirname(dirname(__DIR__)) . '/includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

function out_ok($data = [], int $code = 200): void {
  http_response_code($code);
  if (ob_get_length()) ob_clean();
  echo json_encode(['success' => true, 'message' => 'User deleted successfully', 'data' => $data]);
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

// Validation
if ($id <= 0) out_err('User ID is required');

// Prevent self-deletion
if ($id === $uid) out_err('Cannot delete your own account');

// Check if user exists
$stmt = $db->prepare("SELECT id, profile_photo FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$result) {
  out_err('User not found');
}

// Delete profile photo if exists
if (!empty($result['profile_photo'])) {
  $photoPath = dirname(dirname(__DIR__)) . '/' . $result['profile_photo'];
  if (file_exists($photoPath)) {
    unlink($photoPath);
  }
}

// Delete user
$stmt = $db->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param("i", $id);

if (!$stmt->execute()) {
  $stmt->close();
  out_err('Failed to delete user: ' . $db->error);
}

$stmt->close();

out_ok(['id' => $id]);
?>
