-- Add baseline permissions for cashier role (role name: 'cashier')
-- Safe: does NOT depend on numeric permission IDs.

-- Run migrations/2026_02_09_permission_sync.sql first.

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
  ON p.perm_key IN (
    'dashboard.view',
    'pos.create',
    'pos.view',
    'sales.view',
    'sales.create',
    'products.view',
    'contacts.view',
    'documents.view'
  )
WHERE r.name = 'cashier';
