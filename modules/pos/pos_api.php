<?php
// modules/pos/pos_api.php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/sales.php';

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

/** First usable image path for a product, or null. The column holds a JSON array. */
function pos_thumb($imagesJson): ?string {
  if ($imagesJson === null || $imagesJson === '') return null;
  $list = json_decode((string)$imagesJson, true);
  if (!is_array($list)) {
    // Older rows sometimes hold a bare path rather than a JSON array
    $bare = trim((string)$imagesJson);
    return ($bare !== '' && $bare !== '[]') ? $bare : null;
  }
  foreach ($list as $img) {
    if (is_string($img) && trim($img) !== '') return trim($img);
    if (is_array($img)) {
      foreach (['path', 'url', 'src', 'file'] as $k) {
        if (!empty($img[$k]) && is_string($img[$k])) return trim($img[$k]);
      }
    }
  }
  return null;
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

  // Built to match the database in front of us: if an update has landed but the
  // database step has not run yet, the till still searches by name and SKU.
  $hasBarcode = table_has_column($db, 'products', 'barcode');

  $sql = "
    SELECT 
      p.id, p.sku, " . ($hasBarcode ? 'p.barcode,' : 'NULL AS barcode,') . " p.name, 
      p.images AS thumbnail,
      p.retail_price, p.wholesale_price,
      COALESCE(s.qty_base,0) AS stock_qty
    FROM products p
    LEFT JOIN stock_by_location s 
      ON s.product_id = p.id AND s.location_id = ?
    WHERE p.is_active = 1
      AND (p.sku LIKE ? OR p.name LIKE ? " . ($hasBarcode ? 'OR p.barcode = ?' : '') . ")
    ORDER BY " . ($hasBarcode ? '(p.barcode = ?) DESC,' : '') . " (p.sku = ?) DESC, p.name ASC
    LIMIT 20
  ";

  $st = $db->prepare($sql);
  if (!$st) pos_fail($db, 'search');

  if ($hasBarcode) {
    $st->bind_param('isssss', $location_id, $like, $like, $q, $q, $q);
  } else {
    $st->bind_param('isss', $location_id, $like, $like, $q);
  }
  $st->execute();
  $rs = $st->get_result();

  $rows = [];
  while ($r = $rs->fetch_assoc()) {
    $rows[] = [
      'id' => (int)$r['id'],
      'sku' => (string)$r['sku'],
      'name' => (string)$r['name'],
      'exact' => ($r['barcode'] !== null && $r['barcode'] === $q) || (mb_strlen($q) >= 4 && strcasecmp((string)$r['sku'], $q) === 0),
      'thumbnail' => pos_thumb($r['thumbnail']),
      'retail_price' => (float)$r['retail_price'],
      'wholesale_price' => (float)$r['wholesale_price'],
      'stock_display' => 'Stock: ' . rtrim(rtrim(number_format((float)$r['stock_qty'], 3, '.', ''), '0'), '.'),
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
      'thumbnail' => pos_thumb($r['thumbnail']),
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

  try {
    $res = sale_create($db, $in, (int)($_SESSION['user']['id'] ?? 0));
  } catch (SaleError $e) {
    out(['ok'=>false,'error'=>$e->getMessage()], $e->getCode() ?: 400);
  } catch (Throwable $e) {
    error_log('[pos.confirm_sale] ' . $e->getMessage());
    out(['ok'=>false,'error'=>'The sale could not be saved. Nothing was charged or deducted. Try again.'], 500);
  }
  $base = rtrim((string)($GLOBALS['BASE_URL'] ?? ''), '/');
  out(['ok'=>true] + $res + ['print_url'=>$base . '/modules/pos/pos_preview.php?id=' . $res['sale_id']]);
}

out(['ok'=>false,'error'=>'Unknown action'], 400);
