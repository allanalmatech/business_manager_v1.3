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
$group = trim((string)($_POST['group'] ?? 'General'));
$type = trim((string)($_POST['type'] ?? 'text'));
$desc = trim((string)($_POST['description'] ?? ''));
$sort = (int)($_POST['sort_order'] ?? 0);
$optionsRaw = trim((string)($_POST['options'] ?? ''));
$options = '';
if ($optionsRaw !== '') {
  $opts = array_filter(array_map('trim', explode("\n", $optionsRaw)));
  $options = json_encode(array_values($opts));
}

if ($key === '') out(['success'=>false,'message'=>'Key is required'], 422);

// Check if table supports additional columns
$cols = [];
$rsCols = $db->query("SHOW COLUMNS FROM settings");
if ($rsCols) {
  while ($c = $rsCols->fetch_assoc()) $cols[] = $c['Field'];
}

$hasGrouping = in_array('group', $cols, true);
$hasType = in_array('type', $cols, true);
$hasDescription = in_array('description', $cols, true);
$hasSortOrder = in_array('sort_order', $cols, true);
$hasOptions = in_array('options', $cols, true);

// Build query based on available columns
if ($hasGrouping && $hasType && $hasDescription && $hasSortOrder) {
  if ($hasOptions) {
    $sql = "INSERT INTO settings (`key`,`value`,`group`,`type`,`description`,`sort_order`,`options`) VALUES (?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), `group`=VALUES(`group`), 
            `type`=VALUES(`type`), `description`=VALUES(`description`), 
            `sort_order`=VALUES(`sort_order`), `options`=VALUES(`options`),
            updated_at=CURRENT_TIMESTAMP";
    $stmt = $db->prepare($sql);
    if (!$stmt) out(['success'=>false,'message'=>'Prepare failed: '.$db->error], 500);
    $stmt->bind_param("sssssis", $key, $val, $group, $type, $desc, $sort, $options);
  } else {
    $sql = "INSERT INTO settings (`key`,`value`,`group`,`type`,`description`,`sort_order`) VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), `group`=VALUES(`group`), 
            `type`=VALUES(`type`), `description`=VALUES(`description`), 
            `sort_order`=VALUES(`sort_order`), updated_at=CURRENT_TIMESTAMP";
    $stmt = $db->prepare($sql);
    if (!$stmt) out(['success'=>false,'message'=>'Prepare failed: '.$db->error], 500);
    $stmt->bind_param("sssssi", $key, $val, $group, $type, $desc, $sort);
  }
} else {
  // Basic table - just key/value
  $sql = "INSERT INTO settings (`key`,`value`) VALUES (?,?)
          ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), updated_at=CURRENT_TIMESTAMP";
  
  $stmt = $db->prepare($sql);
  if (!$stmt) out(['success'=>false,'message'=>'Prepare failed: '.$db->error], 500);
  
  $stmt->bind_param("ss", $key, $val);
}

$ok = $stmt->execute();
$err = $stmt->error;
$stmt->close();

if (!$ok) out(['success'=>false,'message'=>'Save failed: '.$err], 500);

out(['success'=>true,'message'=>'Saved']);
