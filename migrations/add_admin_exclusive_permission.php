<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rbac.php';

$db = $GLOBALS['db'] ?? null;
if (!$db instanceof mysqli) {
    die("Database not available\n");
}

echo "Adding admin-exclusive permission...\n";

// Insert the new permission for admin-only features
$stmt = $db->prepare("INSERT INTO permissions (perm_key, description, created_at) VALUES (?, ?, NOW())");
$permKey = 'admin.exclusive';
$description = 'Access admin-exclusive features';
$stmt->bind_param("ss", $permKey, $description);
$stmt->execute();
$stmt->close();

// Get the permission ID
$permId = $db->insert_id;
echo "Created permission ID: $permId\n";

// Assign to super_admin role (role_id = 1)
$stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
$stmt->bind_param("ii", 1, $permId);
$stmt->execute();
$stmt->close();

echo "Assigned admin.exclusive permission to super_admin role\n";
echo "Done!\n";
?>
