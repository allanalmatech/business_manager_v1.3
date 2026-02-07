<?php
// api/sales.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/audit.php';

header('Content-Type: application/json; charset=utf-8');

require_login();
require_permission('sales.create');

$db = $GLOBALS['db'];

$action = $_GET['action'] ?? 'list';

function json_ok($data=[]){ echo json_encode(['ok'=>true,'data'=>$data]); exit; }
function json_err($msg, $code=400){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }

function save_b2b_lines(mysqli $db, int $sale_id, array $lines, int $uid): int {
  if (!$lines) return 0;

  $ins = $db->prepare("
    INSERT INTO b2b_sales_items
      (sale_id, name, sku, qty, unit_type, unit_name,
       cost_price, sell_price, currency, exchange_rate,
       supplier_text, note, created_at)
    VALUES
      (?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())
  ");
  if (!$ins) throw new Exception("Prepare failed: " . $db->error);

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

    $supplier_text = trim((string)($l['supplier_text'] ?? ''));
    $note = trim((string)($l['note'] ?? ''));

    // -------- validation (V1 rules) --------
    if ($name === '') continue;
    if ($qty <= 0) continue;
    if ($cost <= 0) continue;
    if ($sell <= 0) continue;

    // Default rule: do not allow loss here (admin override later)
    if ($sell < $cost) throw new Exception("B2B item '{$name}' sell price cannot be below cost.");

    if ($currency === '') $currency = 'UGX';
    if ($rate <= 0) $rate = 1;

    // normalize empties to NULL-like values
    $sku = ($sku === '') ? null : $sku;
    $unit_name = ($unit_type === 'units' && $unit_name !== '') ? $unit_name : null;
    $supplier_text = ($supplier_text !== '') ? $supplier_text : null;
    $note = ($note !== '') ? $note : null;

    // Bind parameters
    $ins->bind_param(
      "isssssddssss",
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
      $supplier_text,
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

if (!$db instanceof mysqli) json_err('DB not available', 500);

if ($action === 'create') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);
    $raw = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($raw)) json_err('Invalid JSON');

    $b2b_lines = $raw['b2b_lines'] ?? [];
    if (!is_array($b2b_lines)) $b2b_lines = [];

    $items = $raw['items'] ?? [];
    $paymentMethod = trim((string)($raw['payment_method'] ?? ''));
    $customerName = trim((string)($raw['customer_name'] ?? ''));
    $subtotal = (float)($raw['subtotal'] ?? 0);
    $tax = (float)($raw['tax'] ?? 0);
    $total = (float)($raw['total'] ?? 0);
    $csrf = $raw['csrf'] ?? '';

    if (!hash_equals($_SESSION['csrf'] ?? '', (string)$csrf)) json_err('Invalid CSRF token', 403);
    if (empty($items) && empty($b2b_lines)) json_err('Items or B2B lines required');
    if ($paymentMethod === '') json_err('Payment method required');

    $uid = (int)($_SESSION['user']['id'] ?? 0);

    $db->begin_transaction();
    try {
        // Insert sale
        $stmt = $db->prepare("
          INSERT INTO sales (customer_name, payment_method, subtotal, tax, total, status, created_by)
          VALUES (?, ?, ?, ?, ?, 'completed', ?)
        ");
        $stmt->bind_param("ssdddi", $customerName, $paymentMethod, $subtotal, $tax, $total, $uid);
        $stmt->execute();
        $saleId = (int)$stmt->insert_id;
        $stmt->close();

        // Process B2B lines if any using helper function
        if (!empty($b2b_lines)) {
            // Create b2b_sales_items table if it doesn't exist
            $createB2BTable = "
                CREATE TABLE IF NOT EXISTS b2b_sales_items (
                  id INT AUTO_INCREMENT PRIMARY KEY,
                  sale_id INT NOT NULL,
                  name VARCHAR(255) NOT NULL,
                  sku VARCHAR(100) DEFAULT '',
                  qty DECIMAL(12,4) NOT NULL,
                  unit_type VARCHAR(50) NOT NULL DEFAULT 'pieces',
                  unit_name VARCHAR(50) DEFAULT '',
                  cost_price DECIMAL(12,2) NOT NULL,
                  sell_price DECIMAL(12,2) NOT NULL,
                  currency VARCHAR(10) NOT NULL DEFAULT 'UGX',
                  exchange_rate DECIMAL(12,6) NOT NULL DEFAULT 1,
                  supplier_text VARCHAR(255) DEFAULT '',
                  note TEXT DEFAULT '',
                  line_total DECIMAL(12,2) NOT NULL,
                  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                  INDEX idx_sale_id (sale_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            $db->query($createB2BTable);

            // Insert B2B items using helper function
            $b2b_count = save_b2b_lines($db, $saleId, $b2b_lines, $uid);

            // Update sales record to indicate B2B items
            if ($b2b_count > 0) {
                $updateB2B = $db->prepare("UPDATE sales SET has_b2b = 1 WHERE id = ? LIMIT 1");
                $updateB2B->bind_param("i", $saleId);
                $updateB2B->execute();
                $updateB2B->close();

                // audit trail hook
                if (function_exists('audit_log')) {
                    audit_log('sales.b2b.add_lines', 'sales', (string)$saleId, "Added {$b2b_count} B2B line(s) to sale #{$saleId}");
                }
            }
        }

        // Insert sale items and update stock
        foreach ($items as $it) {
            $pid = (int)($it['id'] ?? 0);
            $qty = (float)($it['qty'] ?? 0);
            $unitPrice = (float)($it['unit_price'] ?? 0);
            if ($pid <= 0 || $qty <= 0) continue;

            // Insert sale item
            $stmt = $db->prepare("
              INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, total_price)
              VALUES (?, ?, ?, ?, ?)
            ");
            $totalPrice = $qty * $unitPrice;
            $stmt->bind_param("idddd", $saleId, $pid, $qty, $unitPrice, $totalPrice);
            $stmt->execute();
            $stmt->close();

            // Decrease stock from default location (or first location with stock)
            // Simplified: use default_location_id from product or fallback to 1 (Store)
            $locStmt = $db->prepare("SELECT default_location_id FROM products WHERE id=? LIMIT 1");
            $locStmt->bind_param("i", $pid);
            $locStmt->execute();
            $locRes = $locStmt->get_result()->fetch_assoc();
            $locId = (int)($locRes['default_location_id'] ?? 1);
            $locStmt->close();

            // Lock and fetch current stock
            $stmt = $db->prepare("SELECT qty_base FROM stock_by_location WHERE product_id=? AND location_id=? FOR UPDATE");
            $stmt->bind_param("ii", $pid, $locId);
            $stmt->execute();
            $before = (float)($stmt->get_result()->fetch_assoc()['qty_base'] ?? 0);
            $stmt->close();

            $after = $before - $qty;
            if ($after < 0) {
                throw new Exception("Insufficient stock for product ID $pid at location $locId");
            }

            // Update stock
            $stmt = $db->prepare("UPDATE stock_by_location SET qty_base=? WHERE product_id=? AND location_id=?");
            $stmt->bind_param("dii", $after, $pid, $locId);
            $stmt->execute();
            $stmt->close();

            // Insert stock movement
            $stmt = $db->prepare("
              INSERT INTO stock_movements
              (product_id, from_location_id, to_location_id, movement_type, qty_change, qty_before, qty_after,
               reference_type, reference_id, note, created_by)
              VALUES (?, ?, ?, 'sale', ?, ?, ?, 'sale', ?, ?, ?)
            ");
            $note = "Sale #$saleId";
            $qtyChange = -$qty;
            $stmt->bind_param("iiddddsi", $pid, $locId, $locId, $qtyChange, $before, $after, $saleId, $note, $uid);
            $stmt->execute();
            $stmt->close();
        }

        audit_log('sales.create', 'sale', (string)$saleId, "Sale completed: $paymentMethod $total");
        $db->commit();
        json_ok(['id' => $saleId, 'items' => $items, 'payment_method' => $paymentMethod, 'customer_name' => $customerName, 'subtotal' => $subtotal, 'tax' => $tax, 'total' => $total]);
    } catch (Throwable $e) {
        $db->rollback();
        json_err($e->getMessage(), 400);
    }
}

json_err('Unknown action', 400);
