<?php
// modules/pos/pos_api.php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

$db = $GLOBALS['db'] ?? null;
if (!$db instanceof mysqli) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'DB unavailable']);
  exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();

/* ----------------- helpers ----------------- */
function out(array $arr, int $code=200): void {
  http_response_code($code);
  echo json_encode($arr);
  exit;
}

function post_json(): array {
  $raw = file_get_contents('php://input');
  $j = json_decode($raw, true);
  return is_array($j) ? $j : [];
}

function csrf_check($token): void {
  if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$token)) {
    out(['ok'=>false,'error'=>'CSRF failed (refresh page)'], 403);
  }
}

function has_perm_local(string $p): bool {
  return function_exists('user_has_permission') ? (bool) user_has_permission($p) : true;
}

function table_has_column(mysqli $db, string $table, string $col): bool {
  $sql = "SELECT 1
          FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?
          LIMIT 1";
  $st = $db->prepare($sql);
  if (!$st) return false;
  $st->bind_param('ss', $table, $col);
  $st->execute();
  $ok = (bool) $st->get_result()->fetch_row();
  $st->close();
  return $ok;
}

function first_existing_col(mysqli $db, string $table, array $cands): ?string {
  foreach ($cands as $c) if (table_has_column($db, $table, $c)) return $c;
  return null;
}

function save_b2b_lines(mysqli $db, int $sale_id, array $lines, int $uid): int {
  if (!$lines) return 0;

  // Your table columns:
  // id, sale_id, name, sku, qty, unit_type, unit_name,
  // cost_price, sell_price, currency, exchange_rate,
  // supplier_id, supplier_name, note, created_at

  $ins = $db->prepare("
    INSERT INTO b2b_sales_items
      (sale_id, name, sku, qty, unit_type, unit_name,
       cost_price, sell_price, currency, exchange_rate,
       supplier_id, supplier_name, note, created_at)
    VALUES
      (?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())
  ");
  if (!$ins) throw new Exception('Prepare failed: ' . $db->error);

  $count = 0;

  foreach ($lines as $l) {
    if (!is_array($l)) continue;

    $name = trim((string)($l['name'] ?? ''));
    $sku  = trim((string)($l['sku'] ?? ''));
    $qty  = (float)($l['qty'] ?? 0);

    $unit_type = (string)($l['unit_type'] ?? 'pieces');
    $unit_name = trim((string)($l['unit_name'] ?? ''));

    $cost = (float)($l['cost_price'] ?? 0);
    $sell = (float)($l['sell_price'] ?? 0);

    $currency = strtoupper(trim((string)($l['currency'] ?? 'UGX')));
    $rate     = (float)($l['exchange_rate'] ?? 1);

    // ✅ New fields (from your schema)
    $supplier_id = $l['supplier_id'] ?? null;
    $supplier_id = (is_numeric($supplier_id) && (int)$supplier_id > 0) ? (int)$supplier_id : null;

    $supplier_name = trim((string)($l['supplier_name'] ?? ''));
    $note = trim((string)($l['note'] ?? ''));

    // Validation
    if ($name === '') continue;
    if ($qty <= 0) continue;
    if ($cost <= 0) continue;
    if ($sell <= 0) continue;
    if ($sell < $cost) throw new Exception("B2B item '{$name}' sell price cannot be below cost.");
    if ($rate <= 0) $rate = 1;
    if ($currency === '') $currency = 'UGX';

    // Normalize to NULLs
    $sku = ($sku === '') ? null : $sku;
    $unit_name = ($unit_type === 'units' && $unit_name !== '') ? $unit_name : null;
    $supplier_name = ($supplier_name !== '') ? $supplier_name : null;
    $note = ($note !== '') ? $note : null;

    // ✅ IMPORTANT: pass supplier_id as NULL (not 0) when empty
    $ins->bind_param(
      "issdssddsdiss",
      $sale_id,
      $name,
      $sku,
      $qty,
      $unit_type,
      $unit_name,
      $cost,
      $sell,
      $currency,
      $rate,
      $supplier_id,     // <-- THIS can be NULL now
      $supplier_name,
      $note
    );

    if (!$ins->execute()) {
      throw new Exception("Failed to insert B2B line: " . $ins->error);
    }

    $count++;
  }

  $ins->close();
  return $count;
}

/* ----------------- routing ----------------- */
$action = (string)($_GET['action'] ?? '');

if ($action === '') {
  out(['ok'=>false,'error'=>'Missing action'], 400);
}

/* =========================================================
   PRODUCT SEARCH
   ========================================================= */
if ($action === 'search_products') {
  require_permission('pos.create');

  $in = post_json();
  csrf_check($in['csrf'] ?? '');

  $q = trim((string)($in['q'] ?? ''));
  $location_id = (int)($in['selling_location_id'] ?? 0);

  if ($q === '') out(['ok'=>true,'results'=>[]]);

  $like = '%' . $q . '%';

  $sql = "
    SELECT 
      p.id, p.sku, p.name, 
      p.images AS thumbnail,
      p.retail_price, p.wholesale_price,
      COALESCE(s.qty_base,0) AS stock_qty
    FROM products p
    LEFT JOIN stock_by_location s 
      ON s.product_id = p.id AND s.location_id = ?
    WHERE p.is_active = 1
      AND (p.sku LIKE ? OR p.name LIKE ?)
    ORDER BY p.name ASC
    LIMIT 20
  ";

  $st = $db->prepare($sql);
  if (!$st) out(['ok'=>false,'error'=>$db->error], 500);

  $st->bind_param('iss', $location_id, $like, $like);
  $st->execute();
  $rs = $st->get_result();

  $rows = [];
  while ($r = $rs->fetch_assoc()) {
    $rows[] = [
      'id' => (int)$r['id'],
      'sku' => (string)$r['sku'],
      'name' => (string)$r['name'],
      'thumbnail' => $r['thumbnail'],
      'retail_price' => (float)$r['retail_price'],
      'wholesale_price' => (float)$r['wholesale_price'],
      'stock_display' => 'Stock: ' . (string)$r['stock_qty'],
      'tag' => ''
    ];
  }
  $st->close();

  out(['ok'=>true,'results'=>$rows]);
}

/* =========================================================
   QUICK ITEMS
   ========================================================= */
if ($action === 'quick_items') {
  require_permission('pos.create');

  $in = post_json();
  csrf_check($in['csrf'] ?? '');

  $location_id = (int)($in['selling_location_id'] ?? 0);

  $sql = "
    SELECT 
      p.id, p.sku, p.name,
      p.images AS thumbnail,
      p.retail_price, p.wholesale_price,
      COALESCE(s.qty_base,0) AS stock_qty
    FROM products p
    LEFT JOIN stock_by_location s 
      ON s.product_id = p.id AND s.location_id = ?
    WHERE p.is_active = 1
    ORDER BY p.name ASC
    LIMIT 24
  ";

  $st = $db->prepare($sql);
  if (!$st) out(['ok'=>false,'error'=>$db->error], 500);

  $st->bind_param('i', $location_id);
  $st->execute();
  $rs = $st->get_result();

  $items = [];
  while ($r = $rs->fetch_assoc()) {
    $items[] = [
      'id' => (int)$r['id'],
      'sku' => (string)$r['sku'],
      'name' => (string)$r['name'],
      'thumbnail' => $r['thumbnail'],
      'retail_price' => (float)$r['retail_price'],
      'wholesale_price' => (float)$r['wholesale_price'],
      'stock_display' => 'Stock: ' . (string)$r['stock_qty'],
    ];
  }
  $st->close();

  out(['ok'=>true,'items'=>$items]);
}

/* =========================================================
   CONFIRM SALE
   ========================================================= */
if ($action === 'confirm_sale') {
  require_permission('pos.create');

  $in = post_json();
  csrf_check($in['csrf'] ?? '');

  $items = $in['items'] ?? [];
  $b2b_lines = $in['b2b_lines'] ?? [];
  if (!is_array($b2b_lines)) $b2b_lines = [];

  if (empty($items) && empty($b2b_lines)) {
    out(['ok'=>false,'error'=>'Cart is empty'], 400);
  }

  $payments_in = $in['payments'] ?? [];
  if (!is_array($payments_in)) $payments_in = [];

  $doc_type = (string)($in['doc_type'] ?? 'receipt');
  $pricing_mode = (string)($in['pricing_mode'] ?? 'retail');
  $location_id = (int)($in['selling_location_id'] ?? 0);

  $customer_id = $in['customer_id'] ?? null;
  $customer_id = (is_numeric($customer_id) && (int)$customer_id > 0) ? (int)$customer_id : null;

  $notes = trim((string)($in['notes'] ?? ''));
  $uid = (int)($_SESSION['user']['id'] ?? 0);
  if ($uid <= 0) out(['ok'=>false,'error'=>'Not logged in'], 401);

  $can_discount = has_perm_local('pos.apply_discount');
  $can_edit_price = has_perm_local('pos.edit_price');

  // normalize items
  $subtotal = 0.0;
  $discount_total = 0.0;
  $normalized = [];
  $needed = [];

  foreach ($items as $idx => $it) {
    $qty = (float)($it['qty'] ?? 0);
    $price = (float)($it['unit_price'] ?? 0);
    $disc = (float)($it['discount'] ?? 0);
    $is_external = !empty($it['is_external']);

    if ($qty <= 0) out(['ok'=>false,'error'=>"Invalid qty on line ".($idx+1)], 400);
    if ($price < 0) out(['ok'=>false,'error'=>"Invalid price on line ".($idx+1)], 400);

    if (!$can_discount) $disc = 0.0;
    if ($disc < 0) $disc = 0.0;

    if ($is_external) {
      $line_total = max(0.0, ($qty * $price) - $disc);
      $normalized[] = [
        'product_id'=>null,
        'name'=>(string)($it['name'] ?? 'External Item'),
        'sku'=>'EXTERNAL',
        'qty'=>$qty,
        'price'=>$price,
        'disc'=>$disc,
        'line_total'=>$line_total,
        'is_external'=>1
      ];
      $subtotal += $qty * $price;
      $discount_total += $disc;
      continue;
    }

    $pid = (int)($it['product_id'] ?? 0);
    if ($pid <= 0) out(['ok'=>false,'error'=>"Invalid product (line ".($idx+1).")"], 400);

    $stP = $db->prepare("SELECT name, sku, wholesale_price FROM products WHERE id=? AND is_active=1 LIMIT 1");
    if (!$stP) out(['ok'=>false,'error'=>$db->error], 500);

    $stP->bind_param('i', $pid);
    $stP->execute();
    $p = $stP->get_result()->fetch_assoc();
    $stP->close();

    if (!$p) out(['ok'=>false,'error'=>"Product not found (line ".($idx+1).")"], 400);

    $wholesale_floor = (float)$p['wholesale_price'];

    if ($price < $wholesale_floor) {
      out(['ok'=>false,'error'=>"Price below wholesale not allowed (line ".($idx+1).")"], 400);
    }

    // If you want to fully block price edits without permission:
    if (!$can_edit_price) {
      // Force price to system price depending on mode if desired (optional).
      // Leaving as-is because you already enforce min wholesale.
    }

    $line_total = max(0.0, ($qty * $price) - $disc);

    $normalized[] = [
      'product_id'=>$pid,
      'name'=>(string)$p['name'],
      'sku'=>(string)$p['sku'],
      'qty'=>$qty,
      'price'=>$price,
      'disc'=>$disc,
      'line_total'=>$line_total,
      'is_external'=>0
    ];

    $needed[$pid] = ($needed[$pid] ?? 0) + $qty;
    $subtotal += $qty * $price;
    $discount_total += $disc;
  }

  // stock check
  foreach ($needed as $pid => $qtyNeed) {
    $st = $db->prepare("SELECT qty_base FROM stock_by_location WHERE product_id=? AND location_id=? LIMIT 1");
    if (!$st) out(['ok'=>false,'error'=>$db->error], 500);

    $st->bind_param('ii', $pid, $location_id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $qtyAvail = (float)($row['qty_base'] ?? 0);
    $st->close();

    if ($qtyNeed > $qtyAvail) {
      // Find product name for better error message
      $stName = $db->prepare("SELECT name FROM products WHERE id=? LIMIT 1");
      if ($stName) {
        $stName->bind_param('i', $pid);
        $stName->execute();
        $nameRow = $stName->get_result()->fetch_assoc();
        $stName->close();
        $productName = $nameRow ? $nameRow['name'] : "Product $pid";
      } else {
        $productName = "Product $pid";
      }
      
      out(['ok'=>false,'error'=>"Insufficient stock for \"$productName\". Available: $qtyAvail, Required: $qtyNeed"], 400);
    }
  }

  $grand_total = max(0.0, $subtotal - $discount_total);

  // B2B lines processing
  $b2b_subtotal = 0.0;
  $b2b_normalized = [];
  $has_b2b = !empty($b2b_lines);

  if ($has_b2b) {
    foreach ($b2b_lines as $idx => $b2b) {
      $name = trim((string)($b2b['name'] ?? ''));
      $sku = trim((string)($b2b['sku'] ?? ''));
      $qty = (float)($b2b['qty'] ?? 0);
      $unit_type = (string)($b2b['unit_type'] ?? 'pieces');
      $unit_name = trim((string)($b2b['unit_name'] ?? ''));
      $cost_price = (float)($b2b['cost_price'] ?? 0);
      $sell_price = (float)($b2b['sell_price'] ?? 0);
      $currency = (string)($b2b['currency'] ?? 'UGX');
      $exchange_rate = (float)($b2b['exchange_rate'] ?? 1);
      $supplier_text = trim((string)($b2b['supplier_text'] ?? ''));
      $note = trim((string)($b2b['note'] ?? ''));

      // Validation
      if ($name === '') out(['ok'=>false,'error'=>"B2B item name is required (line ".($idx+1).")"], 400);
      if ($qty <= 0) out(['ok'=>false,'error'=>"B2B item quantity must be greater than 0 (line ".($idx+1).")"], 400);
      if ($cost_price <= 0) out(['ok'=>false,'error'=>"B2B cost price must be greater than 0 (line ".($idx+1).")"], 400);
      if ($sell_price <= 0) out(['ok'=>false,'error'=>"B2B sell price must be greater than 0 (line ".($idx+1).")"], 400);
      if ($exchange_rate <= 0) out(['ok'=>false,'error'=>"B2B exchange rate must be greater than 0 (line ".($idx+1).")"], 400);

      // Calculate line total in original currency
      $line_total = $qty * $sell_price;
      
      // Convert to UGX for subtotal calculation
      $line_ugx = ($currency === 'UGX') ? $line_total : $line_total * $exchange_rate;
      $b2b_subtotal += $line_ugx;

      $b2b_normalized[] = [
        'name' => $name,
        'sku' => $sku,
        'qty' => $qty,
        'unit_type' => $unit_type,
        'unit_name' => $unit_name,
        'cost_price' => $cost_price,
        'sell_price' => $sell_price,
        'currency' => $currency,
        'exchange_rate' => $exchange_rate,
        'supplier_text' => $supplier_text,
        'note' => $note,
        'line_total' => $line_total,
        'line_ugx' => $line_ugx
      ];
    }
  }

  // payments normalization
  $amount_paid = 0.0;
  $payments_norm = [];
  foreach ($payments_in as $p) {
    $method = trim((string)($p['method'] ?? 'cash'));
    $provider = trim((string)($p['provider'] ?? ''));
    $reference = trim((string)($p['reference'] ?? ''));
    $amt = (float)($p['amount'] ?? 0);

    if ($amt <= 0) continue;
    if ($method === 'bank' && $reference === '') out(['ok'=>false,'error'=>'Bank payments require a reference'], 400);

    $payments_norm[] = [
      'method'=>$method,
      'provider'=>$provider,
      'reference'=>$reference,
      'amount'=>$amt
    ];
    $amount_paid += $amt;
  }

  $balance = max(0.0, $grand_total - $amount_paid);

  // Include B2B subtotal in grand total
  $grand_total += $b2b_subtotal;
  $balance = max(0.0, $grand_total - $amount_paid);

  $payment_status = 'unpaid';
  if ($grand_total > 0 && $amount_paid >= $grand_total) $payment_status = 'paid';
  else if ($amount_paid > 0 && $amount_paid < $grand_total) $payment_status = 'partial';

  $mv_user_col = first_existing_col($db, 'stock_movements', ['created_by', 'user_id']);
  if (!$mv_user_col) $mv_user_col = 'created_by';

  // --- document numbers: NEVER NULL ---
$doc_type = in_array($doc_type, ['receipt','invoice','delivery_note'], true) ? $doc_type : 'receipt';

switch ($doc_type) {
  case 'receipt': $prefix = 'RC'; break;
  case 'invoice': $prefix = 'IN'; break;
  case 'delivery_note': $prefix = 'DN'; break;
  default: $prefix = 'RC'; break;
}

// always unique enough even for same-second checkouts
$doc_no = $prefix . '-' . date('Ymd-His') . '-' . random_int(10, 99);

if (trim($doc_no) === '') {
  out(['ok'=>false,'error'=>'doc_no generation failed'], 500);
}

// Add has_b2b column to sales table if it doesn't exist
if (!table_has_column($db, 'sales', 'has_b2b')) {
  $db->query("ALTER TABLE sales ADD COLUMN has_b2b TINYINT(1) NOT NULL DEFAULT 0 AFTER created_by");
}

  $db->begin_transaction();

  try {
    // --- SALES insert (safe: matches test_sale.php) ---
$status = 'confirmed';
$currency = 'UGX';
$tax_total = 0.0;

// Build INSERT statement dynamically based on whether has_b2b column exists
$has_b2b_column_exists = table_has_column($db, 'sales', 'has_b2b');

if ($has_b2b_column_exists) {
  $sqlSale = "INSERT INTO sales
    (doc_type, doc_no, selling_location_id, customer_id, pricing_mode,
     status, payment_status, currency,
     subtotal, discount_total, tax_total, grand_total,
     amount_paid, balance, notes, created_by, has_b2b)
    VALUES
    (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
  $types = "ssiissssddddddsii"; // 17 params with has_b2b
} else {
  $sqlSale = "INSERT INTO sales
    (doc_type, doc_no, selling_location_id, customer_id, pricing_mode,
     status, payment_status, currency,
     subtotal, discount_total, tax_total, grand_total,
     amount_paid, balance, notes, created_by)
    VALUES
    (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
  $types = "ssiissssddddddsi"; // 16 params without has_b2b
}

$stSale = $db->prepare($sqlSale);
if (!$stSale) throw new Exception("Prepare sale failed: " . $db->error);

$status = 'confirmed';

// Prepare has_b2b flag as variable
$has_b2b_flag = $has_b2b ? 1 : 0;

if ($has_b2b_column_exists) {
  $stSale->bind_param(
    $types,
    $doc_type,
    $doc_no,
    $location_id,
    $customer_id,     // can be NULL
    $pricing_mode,
    $status,
    $payment_status,
    $currency,
    $subtotal,
    $discount_total,
    $tax_total,
    $grand_total,
    $amount_paid,
    $balance,
    $notes,
    $uid,
    $has_b2b_flag
  );
} else {
  $stSale->bind_param(
    $types,
    $doc_type,
    $doc_no,
    $location_id,
    $customer_id,     // can be NULL
    $pricing_mode,
    $status,
    $payment_status,
    $currency,
    $subtotal,
    $discount_total,
    $tax_total,
    $grand_total,
    $amount_paid,
    $balance,
    $notes,
    $uid
  );
}

if (!$stSale->execute()) throw new Exception("Insert sale failed: " . $stSale->error);

$sale_id = (int)$stSale->insert_id;
$stSale->close();

    $stItem = $db->prepare("
      INSERT INTO sale_items
      (sale_id, product_id, sku_snapshot, name_snapshot, qty_base, unit_price, discount_amount, line_total)
      VALUES (?,?,?,?,?,?,?,?)
    ");
    if (!$stItem) throw new Exception("Prepare sale_items failed: " . $db->error);

    $stStock = $db->prepare("
      UPDATE stock_by_location
      SET qty_base = qty_base - ?
      WHERE product_id = ? AND location_id = ?
      LIMIT 1
    ");
    if (!$stStock) throw new Exception("Prepare stock update failed: " . $db->error);

    $mvCols = "product_id, from_location_id, to_location_id, movement_type, qty_change, reference_type, reference_id, note, {$mv_user_col}";
    $stMv = $db->prepare("
      INSERT INTO stock_movements ($mvCols)
      VALUES (?,?,?,?,?,?,?,?,?)
    ");
    if (!$stMv) throw new Exception("Prepare movement insert failed: " . $db->error);

    foreach ($normalized as $it) {
      $pid = $it['product_id'] ? (int)$it['product_id'] : null;
      $pidBind = $pid ?? 0;

      $stItem->bind_param(
        "iissdddd",
        $sale_id,
        $pidBind,
        $it['sku'],
        $it['name'],
        $it['qty'],
        $it['price'],
        $it['disc'],
        $it['line_total']
      );
      if (!$stItem->execute()) throw new Exception("Insert item failed: " . $stItem->error);

      if (!$it['is_external']) {
        $qtyDeduct = (float)$it['qty'];

        $stStock->bind_param("dii", $qtyDeduct, $pidBind, $location_id);
        if (!$stStock->execute()) throw new Exception("Stock update failed: " . $stStock->error);

        // STOCK MOVEMENT (FIXED bind types!)
        $qtyChange = 0.0 - $qtyDeduct;
        $mvType = "sale";
        $refType = $doc_type;
        $refId = $sale_id;
        $note = $doc_no;

        // i i i s d s i s i  => "iiisdsisi"
        $stMv->bind_param(
          "iiisdsisi",
          $pidBind,
          $location_id,
          $location_id,
          $mvType,
          $qtyChange,
          $refType,
          $refId,
          $note,
          $uid
        );
        if (!$stMv->execute()) throw new Exception("Movement insert failed: " . $stMv->error);
      }
    }

    // Insert B2B items if any using helper function
    $b2b_count = save_b2b_lines($db, (int)$sale_id, $b2b_normalized, (int)$uid);
    
    if ($b2b_count > 0) {
      $up = $db->prepare("UPDATE sales SET has_b2b = 1 WHERE id = ? LIMIT 1");
      $up->bind_param("i", $sale_id);
      $up->execute();
      $up->close();
    }

    $stItem->close();
    $stStock->close();
    $stMv->close();

    if (!empty($payments_norm)) {
      $pay_user_col = first_existing_col($db, 'sale_payments', ['received_by', 'created_by', 'user_id']);

      $payCols = "sale_id, method, provider, reference, amount";
      if ($pay_user_col) $payCols = "sale_id, method, provider, reference, amount, {$pay_user_col}";

      $stPay = $pay_user_col
        ? $db->prepare("INSERT INTO sale_payments ($payCols) VALUES (?,?,?,?,?,?)")
        : $db->prepare("INSERT INTO sale_payments ($payCols) VALUES (?,?,?,?,?)");

      if (!$stPay) throw new Exception("Prepare payments failed: " . $db->error);

      foreach ($payments_norm as $p) {
        if ($pay_user_col) {
          $stPay->bind_param("isssdi", $sale_id, $p['method'], $p['provider'], $p['reference'], $p['amount'], $uid);
        } else {
          $stPay->bind_param("isssd", $sale_id, $p['method'], $p['provider'], $p['reference'], $p['amount']);
        }
        if (!$stPay->execute()) throw new Exception("Insert payment failed: " . $stPay->error);
      }
      $stPay->close();
    }

    $stUpd = $db->prepare("UPDATE sales SET payment_status=?, amount_paid=?, balance=? WHERE id=? LIMIT 1");
    if ($stUpd) {
      $stUpd->bind_param("sddi", $payment_status, $amount_paid, $balance, $sale_id);
      $stUpd->execute();
      $stUpd->close();
    }

    if (function_exists('audit_log')) {
      audit_log('pos.confirm', "Sale $doc_no confirmed", (string)$sale_id);
      
      if ($b2b_count > 0) {
        audit_log('pos.b2b.add_lines', 'sales', (string)$sale_id, "Added {$b2b_count} B2B line(s) to sale #{$sale_id}");
      }
    }

    $db->commit();

    $base = rtrim((string)($GLOBALS['BASE_URL'] ?? ''), '/');
    out([
      'ok'=>true,
      'sale_id'=>$sale_id,
      'doc_no'=>$doc_no,
      'print_url'=>$base . '/modules/pos/pos_preview.php?id=' . $sale_id
    ]);

  } catch (Throwable $e) {
    $db->rollback();
    out(['ok'=>false,'error'=>$e->getMessage()], 500);
  }
}

out(['ok'=>false,'error'=>'Invalid action'], 404);