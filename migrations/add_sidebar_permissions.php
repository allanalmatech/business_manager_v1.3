<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rbac.php';

$db = $GLOBALS['db'] ?? null;
if (!$db instanceof mysqli) {
    die("Database not available\n");
}

echo "Adding comprehensive sidebar permission keys...\n";

// Core group permissions
$corePermissions = [
    ['sales.view', 'Access Sales module (POS group)', 0],
    ['pos.create', 'Create POS sales', 0],
    ['pos.use', 'Use POS system', 0],
    ['pos.view', 'View POS sales history', 0],
    ['pos.void', 'Process POS returns/voids', 0],
    ['documents.view', 'Access Documents module', 0],
    ['installments.view', 'Access Installments module', 0],
    ['products.view', 'Access Inventory module', 0],
    ['stores.view', 'Access Stores module', 0],
    ['procurement.view', 'Access Procurement module', 0],
    ['contacts.view', 'Access Contacts module', 0],
    ['messaging.view', 'Access Messaging module', 0],
    ['finance.view', 'Access Finance module', 0]
];

// Report permissions (submenu items)
$reportPermissions = [
    ['reports.sales.view', 'View Sales report', 0],
    ['reports.profit.view', 'View Profit report', 0],
    ['reports.inventory.view', 'View Inventory report', 0],
    ['reports.installments.view', 'View Installments report', 0],
    ['reports.expenses.view', 'View Expenses report', 0],
    ['reports.capital.view', 'View Capital report', 0],
    ['reports.b2b.view', 'View B2B report', 0],
    ['reports.audit.view', 'View Audit report', 0]
];

// Admin group gate
$adminGatePermissions = [
    ['admin.exclusive', 'Access Admin menu (super-admin only)', 1]
];

// Individual admin page permissions (future-proof)
$adminPagePermissions = [
    ['admin.settings.view', 'View Settings', 1],
    ['admin.themes.view', 'View Themes & UI', 1],
    ['admin.payments.view', 'View Payment Settings', 1],
    ['admin.reminders.view', 'View Reminders', 1],
    ['admin.users.view', 'Manage Users', 1],
    ['admin.roles.view', 'Manage Roles', 1],
    ['admin.permissions.manage', 'Manage Permissions', 1],
    ['admin.approvals.view', 'View Approvals', 1],
    ['admin.audit_trail.view', 'View Admin Audit Trail', 1],
    ['admin.updates.view', 'View Updates', 1],
    ['admin.update_history.view', 'View Update History', 1]
];

// Function to insert permissions safely
function insertPermissions(mysqli $db, array $permissions): void {
    foreach ($permissions as $permission) {
        [$permKey, $description, $isSuperAdminOnly] = $permission;
        
        // Check if permission already exists
        $stmt = $db->prepare("SELECT id FROM permissions WHERE perm_key = ?");
        $stmt->bind_param("s", $permKey);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // Insert new permission
            $stmt = $db->prepare("INSERT INTO permissions (perm_key, description, is_super_admin_only, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("ssi", $permKey, $description, $isSuperAdminOnly);
            $stmt->execute();
            $permId = $db->insert_id;
            echo "✅ Added: $permKey (ID: $permId)\n";
            
            // Assign to super_admin role (role_id = 1) if super_admin_only
            if ($isSuperAdminOnly) {
                $roleId = 1;
                $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $roleId, $permId);
                $stmt->execute();
                echo "   → Assigned to super_admin role\n";
            }
        } else {
            echo "⏭️  Skipped: $permKey (already exists)\n";
        }
        $stmt->close();
    }
}

// Insert all permission groups
echo "\n=== Core Module Permissions ===\n";
insertPermissions($db, $corePermissions);

echo "\n=== Report Permissions ===\n";
insertPermissions($db, $reportPermissions);

echo "\n=== Admin Gate Permission ===\n";
insertPermissions($db, $adminGatePermissions);

echo "\n=== Admin Page Permissions ===\n";
insertPermissions($db, $adminPagePermissions);

echo "\n=== Migration Complete ===\n";
echo "All sidebar permission keys have been added successfully!\n";
?>
