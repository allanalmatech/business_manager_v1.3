<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/helpers.php';

header('Content-Type: application/json');

function out(array $a, int $code=200): void {
  http_response_code($code);
  echo json_encode($a);
  exit;
}

require_super_admin();
require_permission('admin.settings');

$db = $GLOBALS['db'] ?? null;
if (!$db instanceof mysqli) out(['success'=>false,'message'=>'DB not available'], 500);

// CSRF
$csrf = (string)($_POST['csrf'] ?? '');
if (empty($_SESSION['csrf']) || !hash_equals((string)$_SESSION['csrf'], $csrf)) {
  out(['success'=>false,'message'=>'CSRF failed'], 403);
}

$key = trim((string)($_POST['key'] ?? ''));
$val = (string)($_POST['value'] ?? '');

if ($key === '') out(['success'=>false,'message'=>'Key is required'], 422);

$sql = "INSERT INTO settings (`key`,`value`) VALUES (?,?)
        ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), updated_at=CURRENT_TIMESTAMP";

$stmt = $db->prepare($sql);
if (!$stmt) out(['success'=>false,'message'=>'Prepare failed: '.$db->error], 500);

$stmt->bind_param("ss", $key, $val);
$ok = $stmt->execute();
$err = $stmt->error;
$stmt->close();

if (!$ok) out(['success'=>false,'message'=>'Save failed: '.$err], 500);

out(['success'=>true,'message'=>'Saved']);
