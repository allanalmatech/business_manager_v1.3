<?php
// modules/products/stock_in.php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/rbac.php';

require_permission('products.update');

$db = $GLOBALS['db'];
$BASE_URL = $GLOBALS['BASE_URL'] ?? '';

$page_title = 'Stock In';
$page_subtitle = 'Add stock to products';

$message = '';
$message_type = '';

// Handle POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $location_id = (int)($_POST['location_id'] ?? 0);
    $product_id = (int)($_POST['product_id'] ?? 0);
    $unit_type = trim((string)($_POST['unit_type'] ?? ''));
    $qty_change = (float)($_POST['qty_change'] ?? 0);
    $unit_price = (float)($_POST['unit_price'] ?? 0);
    $note = trim((string)($_POST['note'] ?? ''));
    $csrf = $_POST['csrf'] ?? '';

    // Validate CSRF
    if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
        $message = 'Invalid request - CSRF token mismatch';
        $message_type = 'danger';
    } elseif ($location_id <= 0) {
        $message = 'Please select a location';
        $message_type = 'danger';
    } elseif ($product_id <= 0) {
        $message = 'Please select a product';
        $message_type = 'danger';
    } elseif (empty($unit_type)) {
        $message = 'Please select a unit type';
        $message_type = 'danger';
    } elseif ($qty_change <= 0) {
        $message = 'Quantity must be greater than 0';
        $message_type = 'danger';
    } else {
        // Process stock in
        try {
            $db->begin_transaction();
            
            // Verify location exists
            $locCheck = $db->prepare("SELECT 1 FROM locations WHERE id=? AND is_active=1 LIMIT 1");
            $locCheck->bind_param("i", $location_id);
            $locCheck->execute();
            $locExists = $locCheck->get_result()->fetch_row();
            $locCheck->close();
            
            if (!$locExists) {
                throw new Exception('Invalid location');
            }
            
            // Verify product exists
            $prodCheck = $db->prepare("SELECT name, unit_type, pieces_per_box FROM products WHERE id=? LIMIT 1");
            $prodCheck->bind_param("i", $product_id);
            $prodCheck->execute();
            $product = $prodCheck->get_result()->fetch_assoc();
            $prodCheck->close();
            
            if (!$product) {
                throw new Exception('Invalid product');
            }
            
            // Update or insert stock by location
            $stmt = $db->prepare("
                INSERT INTO stock_by_location (product_id, location_id, qty_base, low_level_base)
                VALUES (?, ?, ?, 0)
                ON DUPLICATE KEY UPDATE qty_base = qty_base + VALUES(qty_base)
            ");
            $stmt->bind_param("iid", $product_id, $location_id, $qty_change);
            if (!$stmt->execute()) {
                throw new Exception('Failed to update stock');
            }
            $stmt->close();
            
            // Record stock movement
            $stmt = $db->prepare("
                INSERT INTO stock_movements (product_id, from_location_id, to_location_id, movement_type, qty_change, qty_before, qty_after, reference_type, reference_id, note, created_by)
                VALUES (?, NULL, ?, 'stock_in', ?, 
                    (SELECT COALESCE(qty_base, 0) FROM stock_by_location WHERE product_id=? AND location_id=?), 
                    (SELECT COALESCE(qty_base, 0) FROM stock_by_location WHERE product_id=? AND location_id=?) + ?, 
                    'stock_in', '', ?, ?)
            ");
            $userId = (int)($_SESSION['user']['id'] ?? 0);
            $stmt->bind_param("iidddiisssi", $product_id, $location_id, $qty_change, $product_id, $location_id, $product_id, $location_id, $qty_change, $note, $userId);
            if (!$stmt->execute()) {
                throw new Exception('Failed to record stock movement');
            }
            $stmt->close();
            
            // Log action
            if (function_exists('audit_log')) {
                audit_log('stock.in', 'stock', (string)$product_id, "Stock in: {$qty_change} {$unit_type} of {$product['name']} at location {$location_id}");
            }
            
            $db->commit();
            
            $message = "Successfully added {$qty_change} {$unit_type} of {$product['name']} to stock";
            $message_type = 'success';
            
            // Reset form
            $_POST = [];
            
        } catch (Exception $e) {
            $db->rollback();
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

$user = current_user();
$current_user_name = $user['name'] ?? $user['username'] ?? 'User';
$current_user_role = $user['role'] ?? '';

$extra_js = [
  'assets/js/stock_in.js',
];

require_once __DIR__ . '/../../templates/layout/header.php';
?>
<div class="app-shell">
  <?php require_once __DIR__ . '/../../templates/layout/sidebar.php'; ?>

  <div class="app-content">
    <?php require_once __DIR__ . '/../../templates/layout/topbar.php'; ?>

    <main class="page-wrap">

      <div class="card shadow-sm rounded-4">
        <div class="card-body">
          <h5 class="mb-3">Record Stock In</h5>

          <?php if ($message): ?>
            <div class="alert alert-<?= htmlspecialchars($message_type) ?> mb-3">
              <?= htmlspecialchars($message) ?>
            </div>
          <?php endif; ?>

          <form id="stockInForm" method="POST">
            <div class="row g-3">
              <div class="col-12 col-md-4">
                <label class="form-label">Store/Location *</label>
                <select class="form-select" id="locationId" name="location_id" required>
                  <option value="">-- Select Location --</option>
                </select>
                <div class="form-text">Select the store location</div>
              </div>

              <div class="col-12 col-md-4">
                <label class="form-label">Product *</label>
                <select class="form-select" id="productId" name="product_id" required>
                  <option value="">-- Select Product --</option>
                </select>
                <div class="form-text">Start typing to filter</div>
              </div>

              <div class="col-12 col-md-2">
                <label class="form-label">Unit Type *</label>
                <select class="form-select" id="unitType" name="unit_type" required>
                  <option value="units">Units</option>
                  <option value="boxes">Boxes</option>
                  <option value="pieces">Pieces</option>
                </select>
                <div class="form-text">Choose measurement unit</div>
              </div>

              <div class="col-12 col-md-2">
                <label class="form-label">Quantity *</label>
                <input class="form-control" type="number" step="0.01" min="0.01" id="qtyChange" name="qty_change" required>
                <div class="form-text" id="unitHint">Specify quantity</div>
              </div>

              <div class="col-12 col-md-3">
                <label class="form-label">Unit Price (optional)</label>
                <input class="form-control" type="number" step="0.01" min="0" id="unitPrice" name="unit_price" placeholder="0.00">
              </div>

              <div class="col-12">
                <label class="form-label">Note</label>
                <textarea class="form-control" id="note" name="note" rows="2" placeholder="e.g. New shipment, purchase order #123"></textarea>
              </div>

              <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">

              <div class="col-12">
                <button type="submit" class="btn btn-primary" id="btnSave">Add Stock</button>
                <button type="reset" class="btn btn-outline-secondary ms-2">Reset</button>
              </div>
            </div>
          </form>

          <div class="alert alert-danger d-none mt-3" id="formError"></div>
          <div class="alert alert-success d-none mt-3" id="formSuccess"></div>
        </div>
      </div>

      <div class="card shadow-sm rounded-4 mt-4">
        <div class="card-body">
          <h6 class="mb-2">Current Stock Levels</h6>
          <div id="currentStock" class="small text-muted">Select a product to see current quantity.</div>
        </div>
      </div>

      <script>
        window.APP = {
          BASE_URL: <?= json_encode($BASE_URL) ?>,
          CSRF: <?= json_encode($_SESSION['csrf'] ?? '') ?>,
        };
      </script>

    </main>
  </div>
</div>
<?php require_once __DIR__ . '/../../templates/layout/footer.php'; ?>