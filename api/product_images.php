<?php
// api/product_images.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/helpers.php';

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

$isAjax = (
  (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
  || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
);

// If not logged in, do NOT redirect to HTML for AJAX
if ($isAjax) {
  $uid = (int)($_SESSION['user']['id'] ?? 0);
  if ($uid <= 0) out_err('Not authenticated', 401);
} else {
  require_login();
}

$db = $GLOBALS['db'] ?? null;
if (!($db instanceof mysqli)) out_err('DB not available', 500);

$action = (string)($_GET['action'] ?? 'upload');

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
  need_perm('products.update', $isAjax);

  if ($action === 'upload') {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
      out_err('No file uploaded or upload error');
    }
    
    $file = $_FILES['file'];
    $productId = (int)($_POST['product_id'] ?? 0);
    $csrf = (string)($_POST['csrf'] ?? '');
    
    if ($productId <= 0) out_err('Invalid product ID');
    
    // Validate CSRF token (check mobile CSRF first, then regular CSRF)
    if (!hash_equals($_SESSION['mobile_csrf'] ?? '', $csrf) && 
        !hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
      out_err('Invalid CSRF token');
    }
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
      out_err('Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed');
    }
    
    // Validate file size (6MB max for QR capture)
    if ($file['size'] > 6 * 1024 * 1024) {
      out_err('File size too large. Maximum 6MB allowed');
    }
    
    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/../uploads/products/';
    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'product_' . $productId . '_capture_' . time() . '_' . mt_rand(1000, 9999) . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
      out_err('Failed to save uploaded file');
    }
    
    // Return relative URL for storage
    $url = 'uploads/products/' . $filename;
    
    // Add the image to the product's images JSON array in database
    $stmt = $db->prepare("SELECT images FROM products WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $images = json_decode($row['images'] ?? '[]', true) ?: [];
    if (count($images) >= 5) out_err('Maximum 5 images allowed');
    
    // Add the new image to the array
    $images[] = $url;
    $jsonImages = json_encode($images);
    
    // Update the product record
    $stmt = $db->prepare("UPDATE products SET images=? WHERE id=?");
    $stmt->bind_param("si", $jsonImages, $productId);
    $stmt->execute();
    $stmt->close();
    
    // Log the action
    if (function_exists('audit_log')) {
      audit_log('products.image_upload', 'product', (string)$productId, "Uploaded image via mobile capture: $filename");
    }
    
    out_ok(['url' => $url, 'filename' => $filename, 'images' => $images]);
  }

  out_err('Unknown action', 400);

} catch (Throwable $e) {
  out_err($e->getMessage(), 500);
}
