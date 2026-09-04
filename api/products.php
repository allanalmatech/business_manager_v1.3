<?php
// api/products.php
declare(strict_types=1);

ob_start();
header('Content-Type: application/json; charset=utf-8');

register_shutdown_function(function (): void {
  $error = error_get_last();
  if (!$error) return;

  $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
  if (!in_array((int)$error['type'], $fatalTypes, true)) return;

  if (ob_get_length()) ob_clean();
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'Server error: ' . ($error['message'] ?? 'Unknown error'),
  ]);
});

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/audit.php';

if (session_status() === PHP_SESSION_NONE) session_start();

function out_ok($data = [], int $code = 200): void {
  http_response_code($code);
  if (ob_get_length()) ob_clean();
  echo json_encode(['ok' => true, 'data' => $data]);
  exit;
}
function out_err(string $msg, int $code = 400): void {
  http_response_code($code);
  if (ob_get_length()) ob_clean();
  echo json_encode(['ok' => false, 'error' => $msg]);
  exit;
}

// This file is an API endpoint, so it must return JSON even when opened directly.
$isAjax = true;

// If not logged in, do NOT redirect to HTML for AJAX
if ($isAjax) {
  $uid = (int)($_SESSION['user']['id'] ?? 0);
  if ($uid <= 0) out_err('Not authenticated', 401);
} else {
  // normal browser navigation
  require_login();
}

$db = $GLOBALS['db'] ?? null;
if (!($db instanceof mysqli)) out_err('DB not available', 500);

$action = (string)($_GET['action'] ?? $_POST['action'] ?? 'list');

// Permission gate (JSON-safe for AJAX)
function need_perm(string $perm, bool $isAjax): void {
  if ($isAjax) {
    if (!function_exists('user_has_permission') || !user_has_permission($perm)) {
      out_err('Permission denied', 403);
    }
  } else {
    require_permission($perm);
  }
}

try {

  // Everyone reaching here must at least view products
  need_perm('products.view', $isAjax);

  /* =========================
     BRANDS
  ========================== */
  if ($action === 'brands') {
    $rows = [];
    $res = $db->query("SELECT id, name FROM brands WHERE status='active' ORDER BY name ASC");
    if (!$res) out_err('Brands table not found. Create brands first.', 500);

    while ($r = $res->fetch_assoc()) $rows[] = $r;
    out_ok(['brands' => $rows]);
  }

  /* =========================
     PRICE UPDATE (FORM POST)
  ========================== */
  if ($action === 'price_update') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') out_err('Method not allowed', 405);
    need_perm('products.update', $isAjax);

    $csrf = (string)($_POST['csrf'] ?? '');
    if (!hash_equals((string)($_SESSION['csrf'] ?? ''), $csrf)) out_err('Invalid CSRF token', 403);

    $pid = (int)($_POST['product_id'] ?? 0);
    $cost = (float)($_POST['cost_price'] ?? 0);
    $wholesale = (float)($_POST['wholesale_price'] ?? 0);
    $retail = (float)($_POST['retail_price'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? ''));

    if ($pid <= 0) out_err('Invalid product');
    if ($reason === '') out_err('Reason is required');

    $db->begin_transaction();
    try {
      $stmt = $db->prepare("SELECT cost_price, wholesale_price, retail_price FROM products WHERE id=? LIMIT 1");
      if (!$stmt) throw new Exception('Prepare failed');
      $stmt->bind_param("i", $pid);
      $stmt->execute();
      $old = $stmt->get_result()->fetch_assoc();
      $stmt->close();
      if (!$old) throw new Exception('Product not found');

      $stmt = $db->prepare("UPDATE products SET cost_price=?, wholesale_price=?, retail_price=? WHERE id=?");
      if (!$stmt) throw new Exception('Prepare failed');
      $stmt->bind_param("dddi", $cost, $wholesale, $retail, $pid);
      $stmt->execute();
      $stmt->close();

      audit_log('products.price_update', 'product', (string)$pid, "Price update: $reason");
      $db->commit();
      out_ok(['message' => 'Prices updated successfully']);
    } catch (Throwable $e) {
      $db->rollback();
      out_err($e->getMessage(), 400);
    }
  }

  /* =========================
     LIST (includes brand_name)
  ========================== */
  if ($action === 'list') {
    $q = trim((string)($_GET['q'] ?? ''));
    $where = "1=1";
    $params = [];
    $types = "";

    if ($q !== '') {
      $where .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
      $like = "%$q%";
      $params[] = $like; $params[] = $like;
      $types .= "ss";
    }

    $sql = "
      SELECT
        p.*,
        c.name AS category_name,
        b.name AS brand_name
      FROM products p
      LEFT JOIN product_categories c ON c.id = p.category_id
      LEFT JOIN brands b ON b.id = p.brand_id
      WHERE $where
      ORDER BY p.id DESC
      LIMIT 300
    ";

    $stmt = $db->prepare($sql);
    if (!$stmt) out_err('Prepare failed', 500);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($r = $res->fetch_assoc()) {
      $r['stock_display'] = function_exists('format_stock') ? format_stock($r) : '';
      $rows[] = $r;
    }
    $stmt->close();

    out_ok($rows);
  }

  /* =========================
     CATEGORIES
  ========================== */
  if ($action === 'categories') {
    $rows = [];
    $res = $db->query("SELECT id, name FROM product_categories WHERE is_active=1 ORDER BY name ASC");
    if (!$res) out_err('Categories table not found. Create product_categories first.', 500);
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    out_ok(['categories' => $rows]);
  }

  if ($action === 'categories_admin_list') {
    $q = trim((string)($_GET['q'] ?? ''));
    $active = (string)($_GET['active'] ?? '');

    $where = " WHERE 1=1 ";
    $params = [];
    $types = "";

    if ($q !== '') {
      $where .= " AND name LIKE ? ";
      $like = "%{$q}%";
      $params[] = $like;
      $types .= "s";
    }
    if ($active === '0' || $active === '1') {
      $where .= " AND is_active = ? ";
      $params[] = (int)$active;
      $types .= "i";
    }

    $sql = "SELECT id, name, is_active FROM product_categories {$where} ORDER BY name ASC";
    $stmt = $db->prepare($sql);
    if (!$stmt) out_err('Categories table not found. Create product_categories first.', 500);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();

    out_ok(['items' => $rows]);
  }

  /* =========================
     CATEGORIES SAVE (CREATE/UPDATE)
  ========================== */
  if ($action === 'categories_save') {
    need_perm('products.update', $isAjax);

    $id = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $is_active = (int)($_POST['is_active'] ?? 1);

    if (empty($name)) {
      out_err('Category name is required');
    }

    if ($id > 0) {
      // Update existing category
      $stmt = $db->prepare("UPDATE product_categories SET name = ?, is_active = ? WHERE id = ?");
      if (!$stmt) out_err('Prepare failed', 500);
      
      $stmt->bind_param("sii", $name, $is_active, $id);
      if (!$stmt->execute()) {
        out_err('Update failed: ' . $stmt->error);
      }
      $stmt->close();
      
      // Log action
      if (function_exists('audit_log')) {
        audit_log('categories.update', 'categories', (string)$id, "Updated category: $name");
      }
      
      out_ok(['message' => 'Category updated successfully', 'id' => $id]);
    } else {
      // Create new category
      $stmt = $db->prepare("INSERT INTO product_categories (name, is_active) VALUES (?, ?)");
      if (!$stmt) out_err('Prepare failed', 500);
      
      $stmt->bind_param("si", $name, $is_active);
      if (!$stmt->execute()) {
        out_err('Insert failed: ' . $stmt->error);
      }
      $newId = $stmt->insert_id;
      $stmt->close();
      
      // Log action
      if (function_exists('audit_log')) {
        audit_log('categories.create', 'categories', (string)$newId, "Created category: $name");
      }
      
      out_ok(['message' => 'Category created successfully', 'id' => $newId]);
    }
  }

  /* =========================
     CATEGORIES DELETE
  ========================== */
  if ($action === 'categories_delete') {
    need_perm('products.delete', $isAjax);

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) out_err('Invalid ID');

    // Check if category is being used by products
    $check = $db->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
    if (!$check) out_err('Prepare failed', 500);
    
    $check->bind_param("i", $id);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();
    $check->close();

    if ($result['count'] > 0) {
      out_err('Cannot delete category - it is being used by ' . $result['count'] . ' products');
    }

    // Delete the category
    $stmt = $db->prepare("DELETE FROM product_categories WHERE id = ?");
    if (!$stmt) out_err('Prepare failed', 500);
    
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
      out_err('Delete failed: ' . $stmt->error);
    }
    $stmt->close();
    
    // Log action
    if (function_exists('audit_log')) {
      audit_log('categories.delete', 'categories', (string)$id, "Deleted category ID: $id");
    }
    
    out_ok(['message' => 'Category deleted successfully']);
  }

  /* =========================
     CATEGORIES GET (single)
  ========================== */
  if ($action === 'categories_get') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) out_err('Invalid ID');

    $stmt = $db->prepare("SELECT id, name, is_active FROM product_categories WHERE id = ? LIMIT 1");
    if (!$stmt) out_err('Prepare failed', 500);

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) out_err('Category not found', 404);
    
    out_ok($row);
  }

  /* =========================
     GET (returns brand_id too)
  ========================== */
  if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) out_err('Invalid ID');

    $stmt = $db->prepare("SELECT p.*, b.name AS brand_name FROM products p LEFT JOIN brands b ON b.id=p.brand_id WHERE p.id=? LIMIT 1");
    if (!$stmt) out_err('Prepare failed', 500);

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) out_err('Not found', 404);
    $row['stock_display'] = function_exists('format_stock') ? format_stock($row) : '';
    out_ok($row);
  }

  /* =========================
     CREATE / UPDATE (includes brand_id)
  ========================== */
  if ($action === 'create' || $action === 'update') {
    $needPerm = $action === 'create' ? 'products.create' : 'products.update';
    need_perm($needPerm, $isAjax);

    $raw = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($raw)) out_err('Invalid JSON');

    $id = (int)($raw['id'] ?? 0);

    $category_id = !empty($raw['category_id']) ? (int)$raw['category_id'] : null;
    $brand_id    = !empty($raw['brand_id']) ? (int)$raw['brand_id'] : null;

    $name        = trim((string)($raw['name'] ?? ''));
    $sku         = trim((string)($raw['sku'] ?? ''));
    $desc        = trim((string)($raw['description'] ?? ''));
    $source      = trim((string)($raw['source'] ?? ''));

    $unit_type   = (string)($raw['unit_type'] ?? 'pieces');
    $unit_name   = trim((string)($raw['unit_name'] ?? ''));
    $ppb         = (int)($raw['pieces_per_box'] ?? 0);

    $cost        = (float)($raw['cost_price'] ?? 0);
    $wholesale   = (float)($raw['wholesale_price'] ?? 0);
    $retail      = (float)($raw['retail_price'] ?? 0);

    $qty_base    = (float)($raw['qty_base'] ?? 0);
    $low_base    = (float)($raw['low_level_base'] ?? 0);
    $default_location_id = !empty($raw['default_location_id']) ? (int)$raw['default_location_id'] : null;

    $is_active   = isset($raw['is_active']) ? (int)(!!$raw['is_active']) : 1;
    
    // Handle images
    $images = [];
    if (!empty($raw['images'])) {
      try {
        $decoded = is_string($raw['images']) ? json_decode($raw['images'], true) : $raw['images'];
        if (is_array($decoded)) {
          $images = array_filter($decoded, function($img) {
            return is_string($img) && !empty(trim($img));
          });
        }
      } catch (Exception $e) {
        // Invalid JSON, ignore images
      }
    }

    if ($name === '') out_err('Product name is required');
    if ($sku === '') out_err('SKU is required');
    if (strlen($sku) > 50) out_err('SKU must be 50 characters or less');
    if (strlen($name) > 200) out_err('Product name must be 200 characters or less');
    if (strlen($source) > 255) out_err('Source must be 255 characters or less');

    $valid = ['boxes','dozens','pairs','pieces','units'];
    if (!in_array($unit_type, $valid, true)) out_err('Invalid unit_type');
    if ($unit_type === 'units' && $unit_name === '') out_err('Unit name required for units (e.g. kg)');
    if ($unit_type === 'boxes' && $ppb <= 0) out_err('pieces_per_box required for boxes');

    if ($cost < 0 || $wholesale < 0 || $retail < 0) out_err('Prices cannot be negative');
    if ($qty_base < 0 || $low_base < 0) out_err('Stock quantities cannot be negative');

    // Validate brand exists (if provided)
    if ($brand_id !== null) {
      $chk = $db->prepare("SELECT 1 FROM brands WHERE id=? LIMIT 1");
      if (!$chk) out_err('Brands table missing', 500);
      $chk->bind_param("i", $brand_id);
      $chk->execute();
      $exists = $chk->get_result()->fetch_row();
      $chk->close();
      if (!$exists) out_err('Invalid brand selected');
    }

    // SKU uniqueness
    if ($action === 'create') {
      $stmt = $db->prepare("SELECT 1 FROM products WHERE sku=? LIMIT 1");
      if (!$stmt) out_err('Prepare failed', 500);
      $stmt->bind_param("s", $sku);
      $stmt->execute();
      $exists = $stmt->get_result()->fetch_row();
      $stmt->close();
      if ($exists) out_err('SKU already exists');
    } else {
      if ($id <= 0) out_err('Invalid ID');
      $stmt = $db->prepare("SELECT 1 FROM products WHERE sku=? AND id<>? LIMIT 1");
      if (!$stmt) out_err('Prepare failed', 500);
      $stmt->bind_param("si", $sku, $id);
      $stmt->execute();
      $exists = $stmt->get_result()->fetch_row();
      $stmt->close();
      if ($exists) out_err('SKU already exists');
    }

    $db->begin_transaction();
    try {
      if ($action === 'create') {
        $stmt = $db->prepare("
          INSERT INTO products
          (category_id, brand_id, name, sku, description, source, unit_type, unit_name, pieces_per_box,
           cost_price, wholesale_price, retail_price, qty_base, low_level_base, default_location_id, is_active, images, created_at)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())
        ");
        if (!$stmt) throw new Exception('Prepare failed');

        $imagesJson = json_encode($images);
        // types: i i s s s s s i d d d d i i s (17 params)
        $stmt->bind_param(
          "iissssssidddddiis",
          $category_id, $brand_id, $name, $sku, $desc, $source, $unit_type, $unit_name, $ppb,
          $cost, $wholesale, $retail, $qty_base, $low_base,
          $default_location_id, $is_active, $imagesJson
        );

        $ok = $stmt->execute();
        $newId = (int)$stmt->insert_id;
        $stmt->close();
        if (!$ok) throw new Exception('Create failed');

        if ($qty_base > 0 && $default_location_id) {
          $stmt2 = $db->prepare("INSERT INTO stock_by_location (product_id, location_id, qty_base, low_level_base) VALUES (?,?,?,?)");
          if ($stmt2) {
            $stmt2->bind_param("iidd", $newId, $default_location_id, $qty_base, $low_base);
            $stmt2->execute();
            $stmt2->close();
          }
        }

        audit_log('products.create', 'product', (string)$newId, "Created: $sku");
        $db->commit();
        out_ok(['id' => $newId]);
      }

      // UPDATE
      $stmt = $db->prepare("
        UPDATE products SET
          category_id=?, brand_id=?, name=?, sku=?, description=?, source=?,
          unit_type=?, unit_name=?, pieces_per_box=?,
          cost_price=?, wholesale_price=?, retail_price=?,
          qty_base=?, low_level_base=?, default_location_id=?, is_active=?, images=?
        WHERE id=?
      ");
      if (!$stmt) throw new Exception('Prepare failed');

      $imagesJson = json_encode($images);
      // types: i i s s s s s i d d d d i i s i  (18 params)
      $stmt->bind_param(
        "iissssssidddddiisi",
        $category_id, $brand_id, $name, $sku, $desc, $source,
        $unit_type, $unit_name, $ppb,
        $cost, $wholesale, $retail,
        $qty_base, $low_base,
        $default_location_id, $is_active, $imagesJson, $id
      );

      $ok = $stmt->execute();
      $stmt->close();
      if (!$ok) throw new Exception('Update failed');

      if ($default_location_id) {
        $db->query("INSERT IGNORE INTO stock_by_location (product_id, location_id, qty_base, low_level_base)
                    VALUES ($id, $default_location_id, 0, 0)");
        $stmt2 = $db->prepare("UPDATE stock_by_location SET qty_base=?, low_level_base=? WHERE product_id=? AND location_id=?");
        if ($stmt2) {
          $stmt2->bind_param("ddii", $qty_base, $low_base, $id, $default_location_id);
          $stmt2->execute();
          $stmt2->close();
        }
      }

      audit_log('products.update', 'product', (string)$id, "Updated: $sku");
      $db->commit();
      out_ok(['id' => $id]);

    } catch (Throwable $e) {
      $db->rollback();
      out_err($e->getMessage(), 500);
    }
  }

  /* =========================
     SCRAPE IMAGE FROM URL
  ========================== */
  if ($action === 'scrape_image') {
    need_perm('products.update', $isAjax);
    
    $url = trim((string)($_POST['url'] ?? ''));
    $productId = (int)($_POST['product_id'] ?? 0);
    
    if (empty($url)) {
      out_err('URL is required');
    }
    
    // Validate URL format
    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $url)) {
      out_err('Invalid image URL format');
    }
    
    // Download image content
    $ctx = stream_context_create([
      'http' => [
        'timeout' => 10,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
      ]
    ]);
    
    $imageData = @file_get_contents($url, false, $ctx);
    if ($imageData === false) {
      out_err('Failed to download image from URL');
    }
    
    // Validate image content
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_buffer($finfo, $imageData);
    finfo_close($finfo);
    
    if (!in_array($mimeType, ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'])) {
      out_err('URL does not contain a valid image');
    }
    
    // Check file size (5MB max)
    if (strlen($imageData) > 5 * 1024 * 1024) {
      out_err('Image size too large. Maximum 5MB allowed');
    }
    
    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/../uploads/products/';
    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = 'jpg';
    if ($mimeType === 'image/png') {
      $extension = 'png';
    } elseif ($mimeType === 'image/gif') {
      $extension = 'gif';
    } elseif ($mimeType === 'image/webp') {
      $extension = 'webp';
    }
    
    $filename = 'product_' . ($productId > 0 ? $productId : 'scraped') . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Save image
    if (file_put_contents($filepath, $imageData) === false) {
      out_err('Failed to save downloaded image');
    }
    
    // Return relative URL for storage
    $url = 'uploads/products/' . $filename;
    
    out_ok(['url' => $url, 'filename' => $filename]);
  }

  /* =========================
     UPLOAD IMAGE
  ========================== */
  if ($action === 'upload_image') {
    need_perm('products.update', $isAjax);
    
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
      out_err('No file uploaded or upload error');
    }
    
    $file = $_FILES['file'];
    $productId = (int)($_POST['product_id'] ?? 0);
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
      out_err('Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed');
    }
    
    // Validate file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
      out_err('File size too large. Maximum 5MB allowed');
    }
    
    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/../uploads/products/';
    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'product_' . ($productId > 0 ? $productId : 'temp') . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
      out_err('Failed to save uploaded file');
    }
    
    // Return relative URL for storage
    $url = 'uploads/products/' . $filename;
    
    out_ok(['url' => $url]);
  }

  /* =========================
     DELETE
  ========================== */
  if ($action === 'delete') {
    need_perm('products.delete', $isAjax);

    // your UI calls delete with POST id (recommended) — accept both GET and POST
    $id = (int)($_POST['id'] ?? ($_GET['id'] ?? 0));
    if ($id <= 0) out_err('Invalid ID');

    $stmt = $db->prepare("DELETE FROM products WHERE id=?");
    if (!$stmt) out_err('Prepare failed', 500);

    $stmt->bind_param("i", $id);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) out_err('Delete failed', 500);

    audit_log('products.delete', 'product', (string)$id, "Deleted");
    out_ok(['id' => $id]);
  }

  /* =========================
     STOCK IN RECORD
  ========================== */
  if ($action === 'stock_in_record') {
    need_perm('products.update', $isAjax);

    $location_id = (int)($_POST['location_id'] ?? 0);
    $product_id = (int)($_POST['product_id'] ?? 0);
    $qty_change = (float)($_POST['qty_change'] ?? 0);
    $unit_type = trim((string)($_POST['unit_type'] ?? ''));
    $unit_price = (float)($_POST['unit_price'] ?? 0);
    $note = trim((string)($_POST['note'] ?? ''));

    if ($location_id <= 0) out_err('Location is required');
    if ($product_id <= 0) out_err('Product is required');
    if ($qty_change <= 0) out_err('Quantity must be greater than 0');
    if (empty($unit_type)) out_err('Unit type is required');
    if ($unit_price < 0) out_err('Unit price cannot be negative');

    // Verify location exists
    $locCheck = $db->prepare("SELECT 1 FROM locations WHERE id=? AND is_active=1 LIMIT 1");
    if (!$locCheck) out_err('Prepare failed', 500);
    $locCheck->bind_param("i", $location_id);
    $locCheck->execute();
    $locExists = $locCheck->get_result()->fetch_row();
    $locCheck->close();
    if (!$locExists) out_err('Invalid location');

    // Verify product exists
    $prodCheck = $db->prepare("SELECT 1 FROM products WHERE id=? LIMIT 1");
    if (!$prodCheck) out_err('Prepare failed', 500);
    $prodCheck->bind_param("i", $product_id);
    $prodCheck->execute();
    $prodExists = $prodCheck->get_result()->fetch_row();
    $prodCheck->close();
    if (!$prodExists) out_err('Invalid product');

    $db->begin_transaction();
    try {
      // Update or insert stock by location
      $stmt = $db->prepare("
        INSERT INTO stock_by_location (product_id, location_id, qty_base, low_level_base)
        VALUES (?, ?, ?, 0)
        ON DUPLICATE KEY UPDATE qty_base = qty_base + VALUES(qty_base)
      ");
      if (!$stmt) throw new Exception('Prepare failed');
      $stmt->bind_param("iid", $product_id, $location_id, $qty_change);
      if (!$stmt->execute()) throw new Exception('Stock update failed');
      $stmt->close();

      // Record stock movement
      $stmt = $db->prepare("
        INSERT INTO stock_movements (product_id, location_id, movement_type, quantity, unit_type, unit_price, notes, created_by)
        VALUES (?, ?, 'stock_in', ?, ?, ?, ?, ?)
      ");
      if (!$stmt) throw new Exception('Prepare failed');
      $userId = (int)($_SESSION['user']['id'] ?? 0);
      $stmt->bind_param("iidsssi", $product_id, $location_id, $qty_change, $unit_type, $unit_price, $note, $userId);
      if (!$stmt->execute()) throw new Exception('Stock movement record failed');
      $stmt->close();

      // Log action
      if (function_exists('audit_log')) {
        audit_log('stock.in', 'stock', (string)$product_id, "Stock in: {$qty_change} {$unit_type} at location {$location_id}");
      }

      $db->commit();
      out_ok(['message' => 'Stock recorded successfully']);
    } catch (Exception $e) {
      $db->rollback();
      out_err('Stock in failed: ' . $e->getMessage());
    }
  }

  /* =========================
     KEEP YOUR OTHER ACTIONS
     stock_adjustment, stock_in_record...
     (they can remain below unchanged)
  ========================== */

  out_err('Unknown action', 400);

} catch (Throwable $e) {
  out_err($e->getMessage(), 500);
}
