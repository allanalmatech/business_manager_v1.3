<?php
// api/brands/check_slug.php
declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/rbac.php';

// Set content type
header('Content-Type: application/json; charset=utf-8');

// Check authentication
$uid = (int)($_SESSION['user']['id'] ?? 0);
if ($uid <= 0) {
    echo json_encode(['exists' => false, 'error' => 'Not authenticated']);
    exit;
}

// Check permission (brands management)
if (!function_exists('user_has_permission') || !user_has_permission('products.view')) {
    // For now, allow authenticated users to check slugs
    // You can change this to a specific brands permission later
}

$db = $GLOBALS['db'] ?? null;
if (!($db instanceof mysqli)) {
    echo json_encode(['exists' => false, 'error' => 'Database not available']);
    exit;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    echo json_encode(['exists' => false, 'error' => 'Invalid input']);
    exit;
}

$slug = trim($input['slug'] ?? '');
$excludeId = (int)($input['exclude_id'] ?? 0);

if (empty($slug)) {
    echo json_encode(['exists' => false, 'error' => 'Slug is required']);
    exit;
}

// Check if slug exists (excluding current brand)
$stmt = $db->prepare("SELECT id, slug FROM brands WHERE slug = ? AND id != ? LIMIT 1");
$stmt->bind_param("si", $slug, $excludeId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$exists = $result && $result->num_rows > 0;

if ($exists) {
    // Generate suggestions for incrementing numbers
    $suggestions = [];
    $stmt = $db->prepare("SELECT slug FROM brands WHERE slug LIKE ? AND id != ?");
    $slugPattern = $slug . '-%';
    $stmt->bind_param("si", $slugPattern, $excludeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = $row['slug'];
    }
    $stmt->close();
    
    echo json_encode([
        'exists' => true,
        'suggestions' => $suggestions,
        'message' => 'Slug already exists'
    ]);
} else {
    echo json_encode([
        'exists' => false,
        'message' => 'Slug is available'
    ]);
}
?>
