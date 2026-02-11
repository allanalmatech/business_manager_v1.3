<?php
// migrations/add_cashier_permissions.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$db = $GLOBALS['db'] ?? null;
if (!($db instanceof mysqli)) {
  die("Database not available\n");
}

echo "Adding cashier permissions...\n";

// Permissions to add to cashier role (role_id = 2)
$permissions = [
    // Sales permissions
    ['perm_key' => 'admin.access', 'description' => 'View sales history'],
    ['perm_key' => 'sales.returns', 'description' => 'Process sales returns'],
    ['perm_key' => 'pos.use', 'description' => 'Use POS'],
    ['perm_key' => 'pos.create', 'description' => 'Create sales'],
    
    // Product permissions
    ['perm_key' => 'products.view', 'description' => 'View products'],
    ['perm_key' => 'products.create', 'description' => 'Create products'],
    ['perm_key' => 'products.update', 'description' => 'Update products'],
    ['perm_key' => 'products.delete', 'description' => 'Delete products'],
    ['perm_key' => 'categories.view', 'description' => 'View categories'],
    
    // Dashboard access
    ['perm_key' => 'dashboard.view', 'description' => 'View dashboard'],
    
    // Reports permissions
    ['perm_key' => 'reports.sales', 'description' => 'View sales reports'],
    ['perm_key' => 'reports.b2b.view', 'description' => 'View B2B reports'],
    
    // Contacts permissions
    ['perm_key' => 'contacts.view', 'description' => 'View contacts'],
    ['perm_key' => 'customers.view', 'description' => 'View customers'],
    ['perm_key' => 'contacts.create', 'description' => 'Create contacts'],
    ['perm_key' => 'contacts.update', 'description' => 'Update contacts'],
    ['perm_key' => 'contacts.delete', 'description' => 'Delete contacts'],
    
    // Documents permissions
    ['perm_key' => 'documents.view', 'description' => 'View documents'],
    ['perm_key' => 'documents.receipts', 'description' => 'View receipts'],
    
    // Installments permissions
    ['perm_key' => 'installments.view', 'description' => 'View installments'],
    ['perm_key' => 'installments.create', 'description' => 'Create installments'],
    ['perm_key' => 'installments.update', 'description' => 'Update installments'],
    ['perm_key' => 'installments.delete', 'description' => 'Delete installments'],
    
    // Additional permissions that exist in database
    ['perm_key' => 'shopping_list.create', 'description' => 'Create shopping list'],
    ['perm_key' => 'finance.view', 'description' => 'View finance'],
    ['perm_key' => 'finance.create', 'description' => 'Create finance'],
    ['perm_key' => 'finance.update', 'description' => 'Update finance'],
    ['perm_key' => 'finance.delete', 'description' => 'Delete finance'],
    ['perm_key' => 'reports.view', 'description' => 'View reports'],
    ['perm_key' => 'audit.view', 'description' => 'View audit trail'],
    ['perm_key' => 'admin.rbac', 'description' => 'Manage RBAC'],
    ['perm_key' => 'admin.settings', 'description' => 'Admin settings'],
    ['perm_key' => 'admin.updates', 'description' => 'Admin updates'],
    ['perm_key' => 'admin.users', 'description' => 'Manage users'],
];

// Add permissions if they don't exist
foreach ($permissions as $perm) {
    // Check if permission exists
    $stmt = $db->prepare("SELECT id FROM permissions WHERE perm_key = ?");
    $stmt->bind_param("s", $perm['perm_key']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $permId = $result['id'] ?? null;
    
    if ($permId) {
        // Check if role permission already exists
        $stmt = $db->prepare("SELECT 1 FROM role_permissions WHERE role_id = 2 AND permission_id = ?");
        $stmt->bind_param("i", $permId);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_row();
        $stmt->close();
        
        if (!$exists) {
            // Add role permission
            $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            $stmt->bind_param("ii", 2, $permId);
            $stmt->execute();
            $stmt->close();
            
            echo "Added: {$perm['perm_key']}\n";
        } else {
            echo "Already exists: {$perm['perm_key']}\n";
        }
    } else {
        echo "Permission not found: {$perm['perm_key']}\n";
    }
}

echo "Cashier permissions update complete!\n";
?>
