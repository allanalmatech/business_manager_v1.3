<?php
// api/phone_scan.php
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

  $action = (string)($_GET['action'] ?? 'status');
  $session = (string)($_POST['session'] ?? $_GET['session'] ?? '');

  if (empty($session)) out_err('Session ID required');

  // Create phone_scan_sessions table if it doesn't exist
  $db->query("CREATE TABLE IF NOT EXISTS phone_scan_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100) UNIQUE NOT NULL,
    product_id INT,
    status ENUM('created', 'connected', 'scanning', 'found', 'uploaded', 'error') DEFAULT 'created',
    image_url TEXT,
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_session (session_id),
    INDEX idx_status (status)
  )");

  if ($action === 'status') {
    // Get session status
    $stmt = $db->prepare("SELECT status, image_url, message FROM phone_scan_sessions WHERE session_id = ? LIMIT 1");
    $stmt->bind_param("s", $session);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$result) {
      out_err('Session not found');
    }

    out_ok([
      'status' => $result['status'],
      'image_url' => $result['image_url'],
      'message' => $result['message']
    ]);
  }

  if ($action === 'update_status') {
    // Update session status
    $input = json_decode(file_get_contents('php://input'), true);
    $status = (string)($input['status'] ?? '');
    
    if (!in_array($status, ['created', 'connected', 'scanning', 'found', 'uploaded', 'error'])) {
      out_err('Invalid status');
    }

    $stmt = $db->prepare("UPDATE phone_scan_sessions SET status = ?, updated_at = NOW() WHERE session_id = ?");
    $stmt->bind_param("ss", $status, $session);
    $stmt->execute();
    $stmt->close();

    out_ok(['status' => 'updated']);
  }

  if ($action === 'upload') {
    // Handle image upload from phone
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
      out_err('No file uploaded or upload error');
    }
    
    $file = $_FILES['file'];
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
      out_err('Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed');
    }
    
    // Validate file size (6MB max)
    if ($file['size'] > 6 * 1024 * 1024) {
      out_err('File size too large. Maximum 6MB allowed');
    }
    
    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/../uploads/phone_scans/';
    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'phone_scan_' . $session . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
      out_err('Failed to save uploaded file');
    }
    
    // Update session with image URL
    $imageUrl = 'uploads/phone_scans/' . $filename;
    $stmt = $db->prepare("UPDATE phone_scan_sessions SET status = 'uploaded', image_url = ?, updated_at = NOW() WHERE session_id = ?");
    $stmt->bind_param("ss", $imageUrl, $session);
    $stmt->execute();
    $stmt->close();
    
    out_ok(['image_url' => $imageUrl, 'filename' => $filename]);
  }

  out_err('Unknown action', 400);

} catch (Throwable $e) {
  out_err($e->getMessage(), 500);
}
