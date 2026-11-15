<?php
// modules/pos/pos_api.php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/audit.php';

header('Content-Type: application/json; charset=utf-8');

$db = $GLOBALS['db'] ?? null;
if (!$db instanceof mysqli) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'DB unavailable']);
  exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();

/* ----------------- helpers ----------------- */
/** A problem the cashier can act on; its message is safe to show. */
class PosUserError extends Exception {}

/** Logs the real database error and returns a neutral message. */
function pos_fail(mysqli $db, string $what): void {
  error_log("[pos_api] $what: " . $db->error);
  out(['ok'=>false,'error'=>'The system could not complete that. Try again.'], 500);
}

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
  // The token (header, form field or JSON body) is verified centrally in includes/csrf.php
  if (!csrf_is_valid() && !verify_csrf_token((string)$token)) out(['ok'=>false,'error'=>'CSRF failed (refresh page)'], 403);
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
  if (!is_array($items)) $items = [];
  $b2b_lines = $in['b2b_lines'] ?? [];
  if (!is_array($b2b_lines)) $b2b_lines = [];

  if (empty($items) && empty($b2b_lines)) {
    out(['ok'=>false,'error'=>'Cart is empty'], 400);
  }

  $payments_in = $in['payments'] ?? [];
  if (!is_array($payments_in)) $payments_in = [];

  $doc_type = (string)($in['doc_type'] ?? 'receipt');
  if (!in_array($doc_type, ['receipt','invoice','delivery_note'], true)) $doc_type = 'receipt';
  $pricing_mode = ((string)($in['pricing_mode'] ?? 'retail') === 'wholesale') ? 'wholesale' : 'retail';
  $location_id = (int)($in['selling_location_id'] ?? 0);

  $customer_id = $in['customer_id'] ?? null;
  $customer_id = (is_numeric($customer_id) && (int)$customer_id > 0) ? (int)$customer_id : null;

  $notes = mb_substr(trim((string)($in['notes'] ?? '')), 0, 255);
  $uid = (int)($_SESSION['user']['id'] ?? 0);
  if ($uid <= 0) out(['ok'=>false,'error'=>'Not logged in'], 401);

  // The selling location must exist and be active
  $stL = $db->prepare("SELECT id FROM locations WHERE id=? AND is_active=1 LIMIT 1");
  $stL->bind_param('i', $location_id);
  $stL->execute();
  $locOk = (bool)$stL->get_result()->fetch_row();
  $stL->close();
  if (!$locOk) out(['ok'=>false,'error'=>'Choose a selling location'], 400);

  $can_discount = has_perm_local('pos.apply_discount');
  $can_edit_price = has_perm_local('pos.edit_price');

  // normalize items — prices come from the database, not from the browser
  $subtotal = 0.0;
  $discount_total = 0.0;
  $normalized = [];
  $needed = [];

  $stP = $db->prepare("SELECT name, sku, unit_type, pieces_per_box, cost_price, wholesale_price, retail_price FROM products WHERE id=? AND is_active=1 LIMIT 1");
  if (!$stP) pos_fail($db, 'product lookup');

  foreach ($items as $idx => $it) {
    if (!is_array($it)) continue;
    $line = $idx + 1;
    $qty = round((float)($it['qty'] ?? 0), 3);
    $price = round((float)($it['unit_price'] ?? 0), 2);
    $disc = round((float)($it['discount'] ?? 0), 2);
    $is_external = !empty($it['is_external']);

    if ($qty <= 0) out(['ok'=>false,'error'=>"Invalid qty on line $line"], 400);
    if ($price < 0) out(['ok'=>false,'error'=>"Invalid price on line $line"], 400);
    if (!$can_discount || $disc < 0) $disc = 0.0;

    if ($is_external) {
      if ($disc > $qty * $price) out(['ok'=>false,'error'=>"Discount is larger than the line total (line $line)"], 400);
      $normalized[] = [
        'product_id'=>null,
        'name'=>mb_substr(trim((string)($it['name'] ?? '')) ?: 'External Item', 0, 200),
        'sku'=>'EXTERNAL',
        'qty'=>$qty, 'price'=>$price, 'disc'=>$disc,
        'cost'=>null,
        'line_total'=>round($qty * $price - $disc, 2),
        'is_external'=>1,
      ];
      $subtotal += $qty * $price;
      $discount_total += $disc;
      continue;
    }

    $pid = (int)($it['product_id'] ?? 0);
    if ($pid <= 0) out(['ok'=>false,'error'=>"Invalid product (line $line)"], 400);

    $stP->bind_param('i', $pid);
    $stP->execute();
    $p = $stP->get_result()->fetch_assoc();
    if (!$p) out(['ok'=>false,'error'=>"Product not found (line $line)"], 400);

    $listPrice = (float)($pricing_mode === 'wholesale' ? $p['wholesale_price'] : $p['retail_price']);
    $floor = (float)$p['wholesale_price'];

    // Without the price-edit permission the cashier sells at the list price for the chosen mode
    if (!$can_edit_price) $price = $listPrice;

    if ($price < $floor) {
      out(['ok'=>false,'error'=>"Price below wholesale not allowed (line $line)"], 400);
    }
    // A discount may not take the line below the wholesale floor either
    if ($disc > 0 && ($qty * $price - $disc) < ($qty * $floor) - 0.005) {
      out(['ok'=>false,'error'=>"Discount takes \"{$p['name']}\" below the wholesale price (line $line)"], 400);
    }

    // Piece-based products are sold in whole pieces; only measured units (kg, litres) take decimals
    if ($p['unit_type'] !== 'units' && abs($qty - round($qty)) > 0.0005) {
      out(['ok'=>false,'error'=>"\"{$p['name']}\" is sold in whole pieces (line $line)"], 400);
    }

    $normalized[] = [
      'product_id'=>$pid,
      'name'=>(string)$p['name'],
      'sku'=>(string)$p['sku'],
      'qty'=>$qty, 'price'=>$price, 'disc'=>$disc,
      'cost'=>(float)$p['cost_price'],
      'line_total'=>round($qty * $price - $disc, 2),
      'is_external'=>0,
    ];

    $needed[$pid] = ($needed[$pid] ?? 0) + $qty;
    $subtotal += $qty * $price;
    $discount_total += $disc;
  }
  $stP->close();

  $subtotal = round($subtotal, 2);
  $discount_total = round($discount_total, 2);
  $grand_total = max(0.0, round($subtotal - $discount_total, 2));

  // B2B lines processing
  $b2b_subtotal = 0.0;
  $b2b_normalized = [];
  $has_b2b = !empty($b2b_lines);

  if ($has_b2b) {
    foreach ($b2b_lines as $idx => $b2b) {
      if (!is_array($b2b)) continue;
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

      if ($name === '') out(['ok'=>false,'error'=>"B2B item name is required (line ".($idx+1).")"], 400);
      if ($qty <= 0) out(['ok'=>false,'error'=>"B2B item quantity must be greater than 0 (line ".($idx+1).")"], 400);
      if ($cost_price <= 0) out(['ok'=>false,'error'=>"B2B cost price must be greater than 0 (line ".($idx+1).")"], 400);
      if ($sell_price <= 0) out(['ok'=>false,'error'=>"B2B sell price must be greater than 0 (line ".($idx+1).")"], 400);
      if ($sell_price < $cost_price) out(['ok'=>false,'error'=>"B2B sell price cannot be below cost (line ".($idx+1).")"], 400);
      if ($exchange_rate <= 0) out(['ok'=>false,'error'=>"B2B exchange rate must be greater than 0 (line ".($idx+1).")"], 400);

      $line_total = $qty * $sell_price;
      $line_ugx = ($currency === 'UGX') ? $line_total : $line_total * $exchange_rate;
      $b2b_subtotal += $line_ugx;

      $b2b_normalized[] = [
        'name' => $name, 'sku' => $sku, 'qty' => $qty,
        'unit_type' => $unit_type, 'unit_name' => $unit_name,
        'cost_price' => $cost_price, 'sell_price' => $sell_price,
        'currency' => $currency, 'exchange_rate' => $exchange_rate,
        'supplier_text' => $supplier_text, 'note' => $note,
        'line_total' => $line_total, 'line_ugx' => $line_ugx,
      ];
    }
  }
  $grand_total = round($grand_total + $b2b_subtotal, 2);

  // payments: only known methods; anything tendered above the total is change, not income
  $allowedMethods = ['cash', 'mobile_money', 'bank'];
  $tendered = 0.0;
  $amount_paid = 0.0;
  $payments_norm = [];
  foreach ($payments_in as $p) {
    if (!is_array($p)) continue;
    $method = trim((string)($p['method'] ?? 'cash'));
    if (!in_array($method, $allowedMethods, true)) out(['ok'=>false,'error'=>'Unknown payment method'], 400);
    $provider = mb_substr(trim((string)($p['provider'] ?? '')), 0, 60);
    $reference = mb_substr(trim((string)($p['reference'] ?? '')), 0, 100);
    $amt = round((float)($p['amount'] ?? 0), 2);

    if ($amt <= 0) continue;
    if ($method === 'bank' && $reference === '') out(['ok'=>false,'error'=>'Bank payments require a reference'], 400);

    $tendered += $amt;
    $applied = min($amt, max(0.0, round($grand_total - $amount_paid, 2)));
    if ($applied <= 0) continue;
    $payments_norm[] = ['method'=>$method, 'provider'=>$provider, 'reference'=>$reference, 'amount'=>$applied];
    $amount_paid = round($amount_paid + $applied, 2);
  }
  $change_due = max(0.0, round($tendered - $amount_paid, 2));
  $balance = max(0.0, round($grand_total - $amount_paid, 2));

  $payment_status = 'unpaid';
  if ($grand_total > 0 && $balance <= 0) $payment_status = 'paid';
  else if ($amount_paid > 0) $payment_status = 'partial';

  $mv_user_col = first_existing_col($db, 'stock_movements', ['created_by', 'user_id']) ?: 'created_by';
  $pay_user_col = first_existing_col($db, 'sale_payments', ['received_by', 'created_by', 'user_id']);

  $db->begin_transaction();

  try {
    // 1) Lock the stock rows, then check them. Two tills can no longer sell the same last unit.
    ksort($needed); // consistent lock order avoids deadlocks between tills
    $stLock = $db->prepare("SELECT qty_base FROM stock_by_location WHERE product_id=? AND location_id=? FOR UPDATE");
    if (!$stLock) throw new Exception('Prepare stock lock failed: ' . $db->error);
    $onHand = []; // running quantity per product, for the movement ledger
    foreach ($needed as $pid => $qtyNeed) {
      $stLock->bind_param('ii', $pid, $location_id);
      $stLock->execute();
      $row = $stLock->get_result()->fetch_assoc();
      $qtyAvail = (float)($row['qty_base'] ?? 0);
      $onHand[$pid] = $qtyAvail;
      if ($qtyNeed > $qtyAvail + 0.0005) {
        $pname = "Product $pid";
        foreach ($normalized as $n) if ($n['product_id'] === $pid) { $pname = $n['name']; break; }
        throw new PosUserError("Insufficient stock for \"$pname\". Available: " . rtrim(rtrim(number_format($qtyAvail, 3, '.', ''), '0'), '.') . ", required: " . rtrim(rtrim(number_format($qtyNeed, 3, '.', ''), '0'), '.'));
      }
    }
    $stLock->close();

    // 2) Next document number from the per-year sequence (gapless inside this transaction)
    $year = (int)date('Y');
    $defaultPrefix = ['receipt'=>'RC', 'invoice'=>'INV', 'delivery_note'=>'DN'][$doc_type];
    $stSeq = $db->prepare("INSERT INTO doc_sequences (doc_type, year, prefix, current_no) VALUES (?,?,?,LAST_INSERT_ID(1))
                           ON DUPLICATE KEY UPDATE current_no = LAST_INSERT_ID(current_no + 1)");
    if (!$stSeq) throw new Exception('Prepare sequence failed: ' . $db->error);
    $stSeq->bind_param('sis', $doc_type, $year, $defaultPrefix);
    if (!$stSeq->execute()) throw new Exception('Sequence failed: ' . $stSeq->error);
    $stSeq->close();
    $seqNo = (int)$db->query("SELECT LAST_INSERT_ID()")->fetch_row()[0];
    $stPre = $db->prepare("SELECT prefix FROM doc_sequences WHERE doc_type=? AND year=? LIMIT 1");
    $stPre->bind_param('si', $doc_type, $year);
    $stPre->execute();
    $prefix = (string)($stPre->get_result()->fetch_row()[0] ?? $defaultPrefix);
    $stPre->close();
    $doc_no = sprintf('%s-%d-%06d', $prefix, $year, $seqNo);

    // 3) Sale header
    $status = 'confirmed';
    $currency = 'UGX';
    $tax_total = 0.0;
    $has_b2b_flag = $has_b2b ? 1 : 0;

    $stSale = $db->prepare("INSERT INTO sales
      (doc_type, doc_no, selling_location_id, customer_id, pricing_mode,
       status, payment_status, currency,
       subtotal, discount_total, tax_total, grand_total,
       amount_paid, balance, notes, created_by, has_b2b)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    if (!$stSale) throw new Exception('Prepare sale failed: ' . $db->error);
    $stSale->bind_param(
      'ssiissssddddddsii',
      $doc_type, $doc_no, $location_id, $customer_id, $pricing_mode,
      $status, $payment_status, $currency,
      $subtotal, $discount_total, $tax_total, $grand_total,
      $amount_paid, $balance, $notes, $uid, $has_b2b_flag
    );
    if (!$stSale->execute()) throw new Exception('Insert sale failed: ' . $stSale->error);
    $sale_id = (int)$stSale->insert_id;
    $stSale->close();

    // 4) Lines, stock and movements
    $stItem = $db->prepare("
      INSERT INTO sale_items
      (sale_id, product_id, is_external, sku_snapshot, name_snapshot, qty_input, qty_base,
       price_mode_snapshot, unit_price, unit_cost, discount_amount, line_total)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    if (!$stItem) throw new Exception('Prepare sale_items failed: ' . $db->error);

    // The guard in WHERE makes it impossible to drive stock negative
    $stStock = $db->prepare("
      UPDATE stock_by_location
      SET qty_base = qty_base - ?
      WHERE product_id = ? AND location_id = ? AND qty_base >= ?
    ");
    if (!$stStock) throw new Exception('Prepare stock update failed: ' . $db->error);

    $stMv = $db->prepare("
      INSERT INTO stock_movements (product_id, from_location_id, to_location_id, movement_type, qty_change, qty_before, qty_after, reference_type, reference_id, note, {$mv_user_col})
      VALUES (?,?,?,?,?,?,?,?,?,?,?)
    ");
    if (!$stMv) throw new Exception('Prepare movement insert failed: ' . $db->error);

    foreach ($normalized as $it) {
      $pid = $it['product_id'];           // NULL for external items
      $isExt = (int)$it['is_external'];
      $cost = $it['cost'];                // NULL for external items
      $stItem->bind_param(
        'iiissddsdddd',
        $sale_id, $pid, $isExt, $it['sku'], $it['name'], $it['qty'], $it['qty'],
        $pricing_mode, $it['price'], $cost, $it['disc'], $it['line_total']
      );
      if (!$stItem->execute()) throw new Exception('Insert item failed: ' . $stItem->error);

      if (!$isExt) {
        $qtyDeduct = (float)$it['qty'];
        $stStock->bind_param('diid', $qtyDeduct, $pid, $location_id, $qtyDeduct);
        if (!$stStock->execute()) throw new Exception('Stock update failed: ' . $stStock->error);
        if ($stStock->affected_rows !== 1) throw new PosUserError("Stock for \"{$it['name']}\" changed while you were selling. Check the quantity and try again.");

        $qtyChange = 0.0 - $qtyDeduct;
        $mvType = 'sale';
        $before = $onHand[$pid];
        $after = round($before - $qtyDeduct, 3);
        $onHand[$pid] = $after;
        $refId = (string)$sale_id;
        $stMv->bind_param('iiisdddsssi', $pid, $location_id, $location_id, $mvType, $qtyChange, $before, $after, $doc_type, $refId, $doc_no, $uid);
        if (!$stMv->execute()) throw new Exception('Movement insert failed: ' . $stMv->error);
      }
    }
    $stItem->close();
    $stStock->close();
    $stMv->close();

    $b2b_count = save_b2b_lines($db, (int)$sale_id, $b2b_normalized, (int)$uid);

    // 5) Payments
    if ($payments_norm) {
      $stPay = $pay_user_col
        ? $db->prepare("INSERT INTO sale_payments (sale_id, method, provider, reference, amount, {$pay_user_col}) VALUES (?,?,?,?,?,?)")
        : $db->prepare("INSERT INTO sale_payments (sale_id, method, provider, reference, amount) VALUES (?,?,?,?,?)");
      if (!$stPay) throw new Exception('Prepare payments failed: ' . $db->error);

      foreach ($payments_norm as $p) {
        if ($pay_user_col) {
          $stPay->bind_param('isssdi', $sale_id, $p['method'], $p['provider'], $p['reference'], $p['amount'], $uid);
        } else {
          $stPay->bind_param('isssd', $sale_id, $p['method'], $p['provider'], $p['reference'], $p['amount']);
        }
        if (!$stPay->execute()) throw new Exception('Insert payment failed: ' . $stPay->error);
      }
      $stPay->close();
    }

    $db->commit();

    if (function_exists('audit_log')) {
      audit_log('pos.confirm', 'sale', (string)$sale_id, "Sale $doc_no confirmed");
      if ($b2b_count > 0) {
        audit_log('pos.b2b.add_lines', 'sale', (string)$sale_id, "Added {$b2b_count} B2B line(s) to sale #{$sale_id}");
      }
    }

    $base = rtrim((string)($GLOBALS['BASE_URL'] ?? ''), '/');
    out([
      'ok'=>true,
      'sale_id'=>$sale_id,
      'doc_no'=>$doc_no,
      'grand_total'=>$grand_total,
      'amount_paid'=>$amount_paid,
      'balance'=>$balance,
      'change_due'=>$change_due,
      'print_url'=>$base . '/modules/pos/pos_preview.php?id=' . $sale_id
    ]);

  } catch (PosUserError $e) {
    $db->rollback();
    out(['ok'=>false,'error'=>$e->getMessage()], 409);
  } catch (Throwable $e) {
    $db->rollback();
    error_log('[pos.confirm_sale] ' . $e->getMessage());
    out(['ok'=>false,'error'=>'The sale could not be saved. Nothing was charged or deducted. Try again.'], 500);
  }
}

out(['ok'=>false,'error'=>'Unknown action'], 400);
