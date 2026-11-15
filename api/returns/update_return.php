<?php
// api/returns/update_return.php
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

// Stock update (same behavior as create_return.php)
require_once dirname(dirname(__DIR__)) . '/includes/stock.php';

// -------------------- AUTH --------------------
if (empty($_SESSION['user']['id'])) {
  json_out(['success' => false, 'message' => 'Authentication required'], 401);
}

if (function_exists('user_has_permission')) {
  $canManage = user_has_permission('pos.void') || user_has_permission('pos.manage');
  if (!$canManage) json_out(['success' => false, 'message' => 'Insufficient permissions'], 403);
}

$db = $GLOBALS['db'] ?? null;
if (!$db instanceof mysqli) {
  json_out(['success' => false, 'message' => 'Database not available'], 500);
}

if (!table_exists($db, 'sale_returns')) {
  json_out(['success' => false, 'message' => 'sale_returns table not found'], 500);
}

// -------------------- INPUT --------------------
$returnId = (int)($_POST['return_id'] ?? 0);
if ($returnId <= 0) json_out(['success' => false, 'message' => 'Return ID is required'], 400);

$reason       = trim((string)($_POST['reason'] ?? ''));
$status       = trim((string)($_POST['status'] ?? 'pending'));
$refundAmount = (float)($_POST['refund_amount'] ?? 0);
$refunded     = (int)($_POST['refunded'] ?? 0);
$locationId   = (int)($_POST['selling_location_id'] ?? 0);
$returnDate   = trim((string)($_POST['return_date'] ?? ''));

// Validate required
if ($reason === '') json_out(['success' => false, 'message' => 'Return reason is required'], 400);
if ($status === '') json_out(['success' => false, 'message' => 'Return status is required'], 400);
if ($locationId <= 0) json_out(['success' => false, 'message' => 'Location is required'], 400);

$allowed = ['pending','approved','completed','rejected','cancelled'];
if (!in_array($status, $allowed, true)) {
  json_out(['success' => false, 'message' => 'Invalid status'], 400);
}

// Validate date if provided
if ($returnDate !== '') {
  $dt = DateTime::createFromFormat('Y-m-d', $returnDate);
  if (!$dt || $dt->format('Y-m-d') !== $returnDate) {
    json_out(['success' => false, 'message' => 'Invalid return date format'], 400);
  }
  $today = new DateTime();
  $today->setTime(0, 0, 0); // Set to midnight for fair comparison
  if ($dt > $today) {
    json_out(['success' => false, 'message' => 'Return date cannot be in the future'], 400);
  }
}

// -------------------- CURRENT RETURN --------------------
$get = $db->prepare("SELECT id, sale_id, status FROM sale_returns WHERE id = ? LIMIT 1");
$get->bind_param('i', $returnId);
$get->execute();
$current = $get->get_result()->fetch_assoc();
$get->close();

if (!$current) json_out(['success' => false, 'message' => 'Return not found'], 404);

$currentStatus = (string)($current['status'] ?? '');
if ($currentStatus === '' || $currentStatus === 'n/a') $currentStatus = 'pending';

// only pending/approved editable
if (!in_array($currentStatus, ['pending','approved'], true)) {
  json_out(['success' => false, 'message' => "Cannot edit return in {$currentStatus} status"], 400);
}

$oldStatus = $currentStatus;
$newStatus = $status;

// -------------------- UPDATE --------------------
$hasRefundedCol = column_exists($db, 'sale_returns', 'refunded');

try {
  $db->begin_transaction();

  // Re-read under lock: only one request may complete (and restock) a return
  $lk = $db->prepare("SELECT status FROM sale_returns WHERE id = ? FOR UPDATE");
  $lk->bind_param('i', $returnId);
  $lk->execute();
  $locked = (string)($lk->get_result()->fetch_row()[0] ?? '');
  $lk->close();
  if (!in_array($locked === '' ? 'pending' : $locked, ['pending','approved'], true)) {
    throw new StockError("This return was already {$locked}.");
  }

  $sql = "UPDATE sale_returns SET reason = ?, status = ?, refund_amount = ?, selling_location_id = ?";
  $types = "ssdi";
  $params = [$reason, $status, $refundAmount, $locationId];

  if ($hasRefundedCol) {
    $sql .= ", refunded = ?";
    $types .= "i";
    $params[] = $refunded;
  }

  if ($returnDate !== '') {
    $sql .= ", created_at = ?";
    $types .= "s";
    $params[] = $returnDate . " 00:00:00";
  }

  $sql .= " WHERE id = ?";
  $types .= "i";
  $params[] = $returnId;

  $st = $db->prepare($sql);
  if (!$st) throw new Exception("Prepare failed: " . $db->error);

  $st->bind_param($types, ...$params);

  if (!$st->execute()) {
    throw new Exception("Failed to update return: " . $db->error);
  }
  $st->close();

  // If status becomes completed, update stock once
  if ($oldStatus !== 'completed' && $newStatus === 'completed' && $locationId > 0) {
    if (table_exists($db, 'return_items')) {
      $it = $db->prepare("SELECT product_id, quantity FROM return_items WHERE return_id = ?");
      $it->bind_param('i', $returnId);
      $it->execute();
      $rs = $it->get_result();

      $saleId = (int)($current['sale_id'] ?? 0);

      $rows = $rs->fetch_all(MYSQLI_ASSOC);
      foreach ($rows as $row) {
        stock_move($db, (int)$row['product_id'], $locationId, (float)$row['quantity'], 'return', 'sale_return', (string)$returnId, "Return #$returnId completed", (int)$_SESSION['user']['id']);
      }
      $it->close();
    }
  }

  $db->commit();

  json_out(['success' => true, 'message' => 'Return updated successfully', 'return_id' => $returnId]);

} catch (StockError $e) {
  $db->rollback();
  json_out(['success' => false, 'message' => $e->getMessage()], 422);
} catch (Throwable $e) {
  $db->rollback();
  error_log('[returns.update] ' . $e->getMessage());
  json_out(['success' => false, 'message' => 'The return could not be updated. Nothing was changed.'], 500);
}