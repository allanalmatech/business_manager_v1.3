<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_login(); // or your require_admin_login if you prefer
require_permission('shopping_list.create');

$db = $GLOBALS['db'];
if (!$db instanceof mysqli) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'DB not available']);
  exit;
}

function out(array $arr, int $code=200): void {
  http_response_code($code);
  echo json_encode($arr);
  exit;
}

$action = (string)($_GET['action'] ?? '');

if ($action !== 'add_to_shopping_list') {
  out(['ok'=>false,'error'=>'Unknown action'], 400);
}

$raw = file_get_contents('php://input');
$data = json_decode((string)$raw, true);
if (!is_array($data)) out(['ok'=>false,'error'=>'Invalid JSON'], 400);

$b2b_id = (int)($data['b2b_id'] ?? 0);
if ($b2b_id <= 0) out(['ok'=>false,'error'=>'Invalid b2b id'], 400);

$uid = (int)($_SESSION['user']['id'] ?? 0);

$db->begin_transaction();
try {
  // get b2b item first to check if it already exists in procurement_shopping_list
  $stmt = $db->prepare("
    SELECT id, name, sku, qty, cost_price, currency, exchange_rate, supplier_name
    FROM b2b_sales_items
    WHERE id = ?
    LIMIT 1
  ");
  $stmt->bind_param("i", $b2b_id);
  $stmt->execute();
  $b2b = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$b2b) throw new Exception("B2B item not found");

  // Check if already exists in procurement_shopping_list (using description to identify B2B items)
  $chk = $db->prepare("SELECT id FROM procurement_shopping_list WHERE description LIKE ? AND product_name = ? LIMIT 1");
  $b2b_desc = "%From B2B: " . trim((string)($b2b['supplier_name'] ?? '')) . "%";
  $b2b_name = (string)$b2b['name'];
  $chk->bind_param("ss", $b2b_desc, $b2b_name);
  $chk->execute();
  $exists = $chk->get_result()->fetch_assoc();
  $chk->close();

  if ($exists) {
    $db->commit();
    out(['ok'=>true,'data'=>['procurement_shopping_list_id'=>(int)$exists['id'], 'already'=>1]]);
  }

  // Insert into procurement_shopping_list
  $notes = "From B2B: " . trim((string)($b2b['supplier_name'] ?? ''));
  $ins = $db->prepare("
    INSERT INTO procurement_shopping_list
      (product_id, product_name, description, quantity, unit, estimated_cost, status, priority, supplier_id, user_id, created_at)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
  ");

  $product_id = null; // B2B items don't have product_id
  $product_name = (string)$b2b['name'];
  $description = "SKU: " . (string)($b2b['sku'] ?? '') . " | From B2B sale #" . $b2b_id;
  $quantity = (float)$b2b['qty'];
  $unit = 'pieces'; // Default unit for B2B items
  $estimated_cost = (float)$b2b['cost_price'];
  $status = 'wanted';
  $priority = 'medium';
  $supplier_id = null; // B2B items don't have supplier_id in this context
  $user_id = $uid;

  $ins->bind_param("issdssdsssi",
    $product_id,
    $product_name,
    $description,
    $quantity,
    $unit,
    $estimated_cost,
    $status,
    $priority,
    $supplier_id,
    $user_id
  );

  if (!$ins->execute()) throw new Exception("Insert failed: " . $ins->error);
  $sl_id = (int)$ins->insert_id;
  $ins->close();

  // audit log hook
  if (function_exists('audit_log')) {
    audit_log('b2b.add_to_shopping_list', 'b2b_sales_items', (string)$b2b_id, "ShoppingList #{$sl_id}");
  }

  $db->commit();
  out(['ok'=>true,'data'=>['shopping_list_id'=>$sl_id, 'already'=>0]]);
} catch (Throwable $e) {
  $db->rollback();
  out(['ok'=>false,'error'=>$e->getMessage()], 400);
}
