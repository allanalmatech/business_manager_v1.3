-- Permission system hardening + full permission registry
-- Goal:
-- 1) All modules used in code exist as permissions (perm_key)
-- 2) Some permissions are super-admin-only (cannot be assigned to other roles)
-- 3) Sidebar hides modules the user can't access (handled in PHP)

-- 1) Add flag for super-admin-only permissions
-- MySQL 8+: safe add
ALTER TABLE permissions
  ADD COLUMN IF NOT EXISTS is_super_admin_only TINYINT(1) NOT NULL DEFAULT 0;

-- 2) Upsert required permissions (extend this list as you add modules)
INSERT INTO permissions (perm_key, description)
VALUES
  ('dashboard.view','View dashboard'),

  ('pos.create','Create a POS sale'),
  ('pos.view','View POS sales / history'),
  ('pos.void','Void a POS sale'),

  ('sales.view','View sales reports'),
  ('sales.create','Create a sale via API'),

  ('documents.view','View documents (receipts, invoices, delivery notes, history)'),

  ('installments.view','View installments'),
  ('installments.create','Create installment / receive payment'),
  ('installments.edit','Edit installment'),
  ('installments.delete','Delete installment'),
  ('installments.update','Update installments / run actions'),

  ('products.view','View products and inventory'),
  ('products.update','Update products / stock / pricing'),

  ('brands.view','View brands'),
  ('brands.create','Create brands'),
  ('brands.edit','Edit brands'),
  ('brands.delete','Delete brands'),

  ('stores.manage','Manage stores'),
  ('stores.update','Update stores'),
  ('stores.delete','Delete stores'),

  ('procurement.view','View procurement module'),
  ('shopping_list.create','Create shopping list entries'),

  ('contacts.view','View contacts'),

  ('messaging.view','Messaging module access'),

  ('finance.view','View finance module'),
  ('finance.create','Create finance entries'),

  ('reports.view','Access reports module'),

  ('audit.view','View audit logs'),
  ('audit.manage','Manage audit logs (delete ranges, etc.)'),

  ('admin.access','Access admin area'),
  ('reminders.view','View reminders'),
  ('approvals.view','View approvals'),

  ('users.view','Manage users'),
  ('roles.view','Manage roles'),
  ('permissions.manage','Manage permissions'),
  ('settings.manage','Manage system settings'),
  ('payments.manage','Manage payment settings'),
  ('updates.manage','Run updates'),
  ('updates.view','View update history')
ON DUPLICATE KEY UPDATE
  description = VALUES(description);

-- 3) Mark super-admin-only permission keys
UPDATE permissions
SET is_super_admin_only = 1
WHERE perm_key IN (
  'users.view',
  'roles.view',
  'permissions.manage',
  'settings.manage',
  'payments.manage',
  'updates.manage',
  'updates.view'
);

-- NOTE: Enforcing super-admin-only is done in PHP too (require_super_admin + sidebar super_only).
