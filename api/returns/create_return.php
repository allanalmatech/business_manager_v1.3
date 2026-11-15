<?php
// api/returns/create_return.php
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

function json_out(array $data, int $code = 200): void {
  http_response_code($code);
  echo json_encode($data);
  exit;
}

function table_exists(mysqli $db, string $table): bool {
  $r = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");
  return $r && $r->num_rows > 0;
}

function column_exists(mysqli $db, string $table, string $col): bool {
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
  $ok = (bool)$st->get_result()->fetch_row();
  $st->close();
  return $ok;
}

require_once dirname(dirname(__DIR__)) . '/includes/stock.php';

// Auth
if (empty($_SESSION['user']['id'])) {
  json_out(['success' => false, 'message' => 'Authentication required'], 401);
}

// Permission check
if (function_exists('user_has_permission')) {
  $canManage = user_has_permission('pos.void') || user_has_permission('pos.manage');
  if (!$canManage) json_out(['success' => false, 'message' => 'Insufficient permissions'], 403);
}

$db = $GLOBALS['db'] ?? null;
if (!$db instanceof mysqli) {
  json_out(['success' => false, 'message' => 'Database not available'], 500);
}

// Input
$receiptNo    = trim((string)($_POST['receipt_no'] ?? ''));
$saleId       = (int)($_POST['sale_id'] ?? 0);
$reason       = trim((string)($_POST['reason'] ?? ''));
$refundAmount = (float)($_POST['refund_amount'] ?? 0);
$status       = trim((string)($_POST['status'] ?? 'pending'));
$locationId   = (int)($_POST['selling_location_id'] ?? 0);
$returnDate   = trim((string)($_POST['return_date'] ?? date('Y-m-d')));
$refunded     = (int)($_POST['refunded'] ?? 0); // 1/0
$returnItems  = json_decode((string)($_POST['return_items'] ?? '[]'), true);

// Basic validation
if ($receiptNo === '' && $saleId <= 0) json_out(['success' => false, 'message' => 'Receipt number is required']);
if ($reason === '') json_out(['success' => false, 'message' => 'Return reason is required']);
if (!in_array($status, ['pending','approved','completed'], true)) json_out(['success' => false, 'message' => 'Invalid return status']);
if ($locationId <= 0) json_out(['success' => false, 'message' => 'Location is required']);
if (empty($returnItems) || !is_array($returnItems)) json_out(['success' => false, 'message' => 'No items selected for return']);

// Resolve saleId via receipt
if ($saleId <= 0 && $receiptNo !== '') {
  $st = $db->prepare("SELECT id FROM sales WHERE doc_no = ? LIMIT 1");
  if (!$st) json_out(['success' => false, 'message' => 'Query preparation failed'], 500);
  $st->bind_param('s', $receiptNo);
  $st->execute();
  $saleRow = $st->get_result()->fetch_assoc();
  $st->close();
  if (!$saleRow) json_out(['success' => false, 'message' => 'Receipt not found']);
  $saleId = (int)$saleRow['id'];
}

// Verify sale exists + date validation
$saleCheck = $db->prepare("SELECT id, created_at FROM sales WHERE id = ? LIMIT 1");
$saleCheck->bind_param('i', $saleId);
$saleCheck->execute();
$saleData = $saleCheck->get_result()->fetch_assoc();
$saleCheck->close();

if (!$saleData) json_out(['success' => false, 'message' => 'Sale not found']);

$saleDate = substr((string)$saleData['created_at'], 0, 10);
$today = date('Y-m-d');

if ($returnDate > $today) json_out(['success' => false, 'message' => 'Return date cannot be in the future']);
if ($returnDate < $saleDate) json_out(['success' => false, 'message' => 'Return date cannot be before the sale date']);

// Return No
$returnNo = 'RET-' . date('Y') . '-' . str_pad((string)$saleId, 6, '0', STR_PAD_LEFT) . '-' . substr(bin2hex(random_bytes(2)), 0, 4);

// Ensure DATETIME
$createdAt = $returnDate . ' 00:00:00';

// Check tables
$hasReturnsTable = table_exists($db, 'sale_returns');
$hasItemsTable   = table_exists($db, 'return_items');

if (!$hasReturnsTable) {
  json_out(['success' => false, 'message' => 'sale_returns table missing. Create it first.'], 500);
}

try {
  $db->begin_transaction();

  // Detect refunded column exists (your table might not have it yet)
  $hasRefundedCol = column_exists($db, 'sale_returns', 'refunded');

  // Insert return header
  if ($hasRefundedCol) {
    $sql = "INSERT INTO sale_returns
      (sale_id, return_no, reason, refund_amount, status, selling_location_id, created_at, created_by, refunded)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $st = $db->prepare($sql);
    $createdBy = (int)$_SESSION['user']['id'];
    $st->bind_param('issdsisii', $saleId, $returnNo, $reason, $refundAmount, $status, $locationId, $createdAt, $createdBy, $refunded);
  } else {
    $sql = "INSERT INTO sale_returns
      (sale_id, return_no, reason, refund_amount, status, selling_location_id, created_at, created_by)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $st = $db->prepare($sql);
    $createdBy = (int)$_SESSION['user']['id'];
    $st->bind_param('issdsisi', $saleId, $returnNo, $reason, $refundAmount, $status, $locationId, $createdAt, $createdBy);
  }

  if (!$st || !$st->execute()) {
    throw new Exception('Failed to insert return: ' . $db->error);
  }
  $returnId = (int)$db->insert_id;
  if ($st) $st->close();

  // Insert items
  if ($hasItemsTable) {
    $hasItemLoc = column_exists($db, 'return_items', 'location_id');

    // prepare insert with/without location_id
    if ($hasItemLoc) {
      $ins = $db->prepare("INSERT INTO return_items (return_id, product_id, location_id, quantity, unit_price, total)
                           VALUES (?, ?, ?, ?, ?, ?)");
    } else {
      $ins = $db->prepare("INSERT INTO return_items (return_id, product_id, quantity, unit_price, total)
                           VALUES (?, ?, ?, ?, ?)");
    }
    if (!$ins) throw new Exception('Prepare return_items failed: ' . $db->error);

    $createdBy = (int)$_SESSION['user']['id'];
    $returnValue = 0.0;
    $lines = 0;
    foreach ($returnItems as $productId => $qty) {
      $productId = (int)$productId;
      $qty = round((float)$qty, 3);
      if ($qty <= 0) continue;

      // What was sold on this receipt, locked so two returns cannot both claim it
      $pc = $db->prepare("SELECT COALESCE(SUM(qty_base),0) AS sold, MAX(unit_price) AS unit_price,
                                 COALESCE(SUM(line_total),0) AS line_total, MAX(name_snapshot) AS name
                          FROM sale_items WHERE sale_id = ? AND product_id = ? FOR UPDATE");
      $pc->bind_param('ii', $saleId, $productId);
      $pc->execute();
      $pd = $pc->get_result()->fetch_assoc();
      $pc->close();

      $sold = (float)($pd['sold'] ?? 0);
      if ($sold <= 0) throw new StockError('That product is not on this receipt.');
      $already = stock_returned_qty($db, $saleId, $productId, $returnId);
      if ($qty > $sold - $already + 0.0005) {
        $left = max(0, $sold - $already);
        throw new StockError('Only ' . rtrim(rtrim(number_format($left, 3, '.', ''), '0'), '.') . ' of "' . $pd['name'] . '" can still be returned.');
      }

      // Refund value per unit is what the customer actually paid after discounts
      $unitPrice = round((float)$pd['line_total'] / $sold, 2);
      $total = round($unitPrice * $qty, 2);
      $returnValue += $total;
      $lines++;

      if ($hasItemLoc) {
        $ins->bind_param('iiiddd', $returnId, $productId, $locationId, $qty, $unitPrice, $total);
      } else {
        $ins->bind_param('iiddd', $returnId, $productId, $qty, $unitPrice, $total);
      }

      if (!$ins->execute()) {
        throw new Exception('Failed to insert return item: ' . $db->error);
      }

      // Goods go back on the shelf only when the return is completed
      if ($status === 'completed') {
        stock_move($db, $productId, $locationId, $qty, 'return', 'sale_return', (string)$returnId, "Return $returnNo", $createdBy);
      }
    }

    if ($lines === 0) throw new StockError('No items selected for return');
    if ($refundAmount < 0 || $refundAmount > $returnValue + 0.005) {
      throw new StockError('Refund cannot be more than the value of the returned items (' . number_format($returnValue, 2) . ').');
    }

    $ins->close();
  }

  $db->commit();

  json_out([
    'success' => true,
    'message' => 'Return created successfully',
    'return_id' => $returnId,
    'return_no' => $returnNo
  ]);

} catch (StockError $e) {
  $db->rollback();
  json_out(['success' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
  $db->rollback();
  error_log('[returns.create] ' . $e->getMessage());
  json_out(['success' => false, 'message' => 'The return could not be saved. Nothing was changed.'], 500);
}