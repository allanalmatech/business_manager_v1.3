<?php
// api/permissions/save_role_permissions.php
declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/rbac.php';
require_once dirname(dirname(__DIR__)) . '/includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

function out_ok($data = [], int $code = 200): void {
  http_response_code($code);
  if (ob_get_length()) ob_clean();
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['success' => true, 'message' => 'Permissions saved successfully', 'data' => $data]);
  exit;
}

function out_err(string $msg, int $code = 400): void {
  http_response_code($code);
  if (ob_get_length()) ob_clean();
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['success' => false, 'message' => $msg]);
  exit;
}

// Check authentication
$uid = (int)($_SESSION['user']['id'] ?? 0);
if ($uid <= 0) out_err('Not authenticated', 401);

// Check permission
if (!function_exists('user_has_permission') || !user_has_permission('permissions.manage')) {
  out_err('Permission denied', 403);
}

$db = $GLOBALS['db'] ?? null;
if (!($db instanceof mysqli)) out_err('Database not available', 500);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out_err('Method not allowed', 405);

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    out_err('Invalid JSON data');
}

$roleId = (int)($input['role_id'] ?? 0);
$permissions = $input['permissions'] ?? [];

// Validation
if ($roleId <= 0) out_err('Role ID is required');

// Check if role exists
$stmt = $db->prepare("SELECT id FROM roles WHERE id = ?");
$stmt->bind_param("i", $roleId);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
  $stmt->close();
  out_err('Role not found');
}
$stmt->close();

// Determine mapping table name
$mapTable = 'role_permissions'; // Use standard name
$colPermId = 'permission_id';

// Clear existing permissions for this role
$stmt = $db->prepare("DELETE FROM $mapTable WHERE role_id = ?");
$stmt->bind_param("i", $roleId);
$stmt->execute();
$stmt->close();

// Insert new permissions
if (!empty($permissions)) {
  $stmt = $db->prepare("
    INSERT IGNORE INTO $mapTable (role_id, $colPermId)
    SELECT ?, id FROM permissions WHERE perm_key = ?
  ");

  foreach ($permissions as $permKey) {
    $stmt->bind_param("is", $roleId, $permKey);
    $stmt->execute();
  }
  $stmt->close();
}

out_ok(['role_id' => $roleId, 'permissions_count' => count($permissions)]);
?>
