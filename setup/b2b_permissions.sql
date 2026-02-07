-- B2B Functionality Permissions Setup
-- Run this script to add B2B-related permissions to the system

-- Add new permissions for B2B functionality
INSERT IGNORE INTO permissions (perm_key, description) VALUES
('reports.b2b.view', 'View B2B items report'),
('shopping_list.create', 'Add items to shopping list');

-- Grant permissions to existing roles
-- Super admin gets all permissions (already handled by existing logic)

-- Cashier role - basic B2B access
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name='cashier' AND p.perm_key IN (
  'reports.b2b.view', 'shopping_list.create'
);

-- Accountant role - reporting access
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name='accountant' AND p.perm_key IN (
  'reports.b2b.view'
);

-- Manager role (if exists) - full B2B access
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.name='manager' AND p.perm_key IN (
  'reports.b2b.view', 'shopping_list.create'
);
