<?php
// seed_database.php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_OFF);

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
}

$messages = [];
$errors = [];
$seeded = false;
$configFile = __DIR__ . '/config/db.php';

function seed_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function seed_log(string $message): void {
    global $messages;
    $messages[] = $message;
}

function seed_query(mysqli $db, string $sql): void {
    if (!$db->query($sql)) {
        throw new RuntimeException($db->error);
    }
}

function seed_prepare(mysqli $db, string $sql): mysqli_stmt {
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException($db->error);
    }
    return $stmt;
}

function seed_table_exists(mysqli $db, string $table): bool {
    $stmt = seed_prepare($db, "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $exists;
}

function seed_column_exists(mysqli $db, string $table, string $column): bool {
    $stmt = seed_prepare($db, "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $exists;
}

function seed_create_core_tables(mysqli $db): void {
    seed_query($db, "
        CREATE TABLE IF NOT EXISTS roles (
          id INT AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(50) NOT NULL UNIQUE,
          description VARCHAR(255) NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    seed_query($db, "
        CREATE TABLE IF NOT EXISTS users (
          id INT AUTO_INCREMENT PRIMARY KEY,
          role_id INT NOT NULL,
          username VARCHAR(60) NOT NULL UNIQUE,
          email VARCHAR(120) NULL UNIQUE,
          phone VARCHAR(40) NULL,
          full_name VARCHAR(120) NOT NULL,
          password_hash VARCHAR(255) NOT NULL,
          profile_photo VARCHAR(255) NULL,
          is_active TINYINT(1) NOT NULL DEFAULT 1,
          must_change_password TINYINT(1) NOT NULL DEFAULT 0,
          last_login_at DATETIME NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_users_role_id (role_id),
          CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    seed_query($db, "
        CREATE TABLE IF NOT EXISTS permissions (
          id INT AUTO_INCREMENT PRIMARY KEY,
          perm_key VARCHAR(100) NOT NULL UNIQUE,
          description VARCHAR(255) NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    seed_query($db, "
        CREATE TABLE IF NOT EXISTS role_permissions (
          role_id INT NOT NULL,
          permission_id INT NOT NULL,
          PRIMARY KEY (role_id, permission_id),
          CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
          CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    seed_query($db, "
        CREATE TABLE IF NOT EXISTS user_permissions (
          user_id INT NOT NULL,
          permission_id INT NOT NULL,
          is_allowed TINYINT(1) NOT NULL DEFAULT 1,
          PRIMARY KEY (user_id, permission_id),
          CONSTRAINT fk_up_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
          CONSTRAINT fk_up_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    seed_query($db, "
        CREATE TABLE IF NOT EXISTS sessions (
          id VARCHAR(128) PRIMARY KEY,
          user_id INT NULL,
          data MEDIUMBLOB NOT NULL,
          ip_address VARCHAR(45) NULL,
          user_agent VARCHAR(255) NULL,
          last_activity INT NOT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_sessions_last_activity (last_activity),
          INDEX idx_sessions_user_id (user_id),
          CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    seed_query($db, "
        CREATE TABLE IF NOT EXISTS audit_logs (
          id BIGINT AUTO_INCREMENT PRIMARY KEY,
          user_id INT NULL,
          action VARCHAR(100) NOT NULL,
          entity VARCHAR(80) NULL,
          entity_id VARCHAR(80) NULL,
          details TEXT NULL,
          ip_address VARCHAR(45) NULL,
          user_agent VARCHAR(255) NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_audit_user_id (user_id),
          INDEX idx_audit_action (action),
          CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    seed_query($db, "
        CREATE TABLE IF NOT EXISTS settings (
          id INT AUTO_INCREMENT PRIMARY KEY,
          `key` VARCHAR(100) NOT NULL UNIQUE,
          `value` TEXT NULL,
          `group` VARCHAR(50) NULL,
          `type` VARCHAR(30) NOT NULL DEFAULT 'text',
          description VARCHAR(255) NULL,
          sort_order INT NOT NULL DEFAULT 0,
          updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    seed_log('Core auth, RBAC, session, audit, and settings tables are ready.');
}

function seed_role(mysqli $db, string $name, string $description): int {
    $stmt = seed_prepare($db, "INSERT INTO roles (name, description) VALUES (?, ?) ON DUPLICATE KEY UPDATE description = VALUES(description)");
    $stmt->bind_param('ss', $name, $description);
    $stmt->execute();
    $stmt->close();

    $stmt = seed_prepare($db, "SELECT id FROM roles WHERE name = ? LIMIT 1");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        throw new RuntimeException('Unable to load role: ' . $name);
    }

    return (int)$row['id'];
}

function seed_permission(mysqli $db, string $key, string $description): void {
    $stmt = seed_prepare($db, "INSERT INTO permissions (perm_key, description) VALUES (?, ?) ON DUPLICATE KEY UPDATE description = VALUES(description)");
    $stmt->bind_param('ss', $key, $description);
    $stmt->execute();
    $stmt->close();
}

function seed_grant_permissions(mysqli $db, int $roleId, array $permissionKeys): void {
    $stmt = seed_prepare($db, "
        INSERT IGNORE INTO role_permissions (role_id, permission_id)
        SELECT ?, id FROM permissions WHERE perm_key = ?
    ");

    foreach ($permissionKeys as $permissionKey) {
        $stmt->bind_param('is', $roleId, $permissionKey);
        $stmt->execute();
    }

    $stmt->close();
}

function seed_user(mysqli $db, int $roleId, string $username, string $fullName, string $password): void {
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = seed_prepare($db, "
        INSERT INTO users (role_id, username, email, phone, full_name, password_hash, is_active)
        VALUES (?, ?, NULL, NULL, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
          role_id = VALUES(role_id),
          full_name = VALUES(full_name),
          password_hash = VALUES(password_hash),
          is_active = 1
    ");
    $stmt->bind_param('isss', $roleId, $username, $fullName, $passwordHash);
    $stmt->execute();
    $stmt->close();
}

function seed_setting(mysqli $db, string $key, string $value, string $group, string $type, string $description, int $sortOrder): void {
    $hasGrouping = seed_column_exists($db, 'settings', 'group')
        && seed_column_exists($db, 'settings', 'type')
        && seed_column_exists($db, 'settings', 'sort_order');

    if (!$hasGrouping) {
        $stmt = seed_prepare($db, "
            INSERT INTO settings (`key`, `value`, description)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
              `value` = VALUES(`value`),
              description = VALUES(description)
        ");
        $stmt->bind_param('sss', $key, $value, $description);
        $stmt->execute();
        $stmt->close();
        return;
    }

    $stmt = seed_prepare($db, "
        INSERT INTO settings (`key`, `value`, `group`, `type`, description, sort_order)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
          `value` = VALUES(`value`),
          `group` = VALUES(`group`),
          `type` = VALUES(`type`),
          description = VALUES(description),
          sort_order = VALUES(sort_order)
    ");
    $stmt->bind_param('sssssi', $key, $value, $group, $type, $description, $sortOrder);
    $stmt->execute();
    $stmt->close();
}

function seed_database(mysqli $db): void {
    seed_create_core_tables($db);

    $roles = [
        'super_admin' => seed_role($db, 'super_admin', 'Full access'),
        'cashier' => seed_role($db, 'cashier', 'POS and limited operations'),
        'accountant' => seed_role($db, 'accountant', 'Finance and reports'),
    ];
    seed_log('Roles seeded.');

    $permissions = [
        'admin.access' => 'Access admin section',
        'admin.create' => 'Create administration records',
        'admin.delete' => 'Delete administration records',
        'admin.exclusive' => 'Access super-admin-only administration',
        'admin.rbac' => 'Manage roles and permissions',
        'admin.settings' => 'Manage system settings',
        'admin.update' => 'Update administration records',
        'admin.users' => 'Manage users',
        'audit.manage' => 'Manage audit logs',
        'audit.view' => 'View audit trail',
        'brands.create' => 'Create brands',
        'brands.delete' => 'Delete brands',
        'brands.edit' => 'Edit brands',
        'brands.view' => 'View brands',
        'contacts.create' => 'Create contacts',
        'contacts.delete' => 'Delete contacts',
        'contacts.update' => 'Update contacts',
        'contacts.view' => 'View contacts',
        'dashboard.view' => 'View dashboard',
        'documents.view' => 'View documents',
        'finance.create' => 'Create finance records',
        'finance.delete' => 'Delete finance records',
        'finance.update' => 'Update finance records',
        'finance.view' => 'View finance',
        'installments.create' => 'Create installments',
        'installments.delete' => 'Delete installments',
        'installments.edit' => 'Edit installments',
        'installments.update' => 'Update installments',
        'installments.view' => 'View installments',
        'messaging.view' => 'Use messaging',
        'payments.manage' => 'Manage payment settings',
        'permissions.manage' => 'Manage permissions',
        'permissions.update' => 'Update permissions',
        'pos.allow_debt' => 'Allow debt sales',
        'pos.apply_discount' => 'Apply POS discounts',
        'pos.create' => 'Create POS sales',
        'pos.delivery_note' => 'Create delivery notes from POS',
        'pos.edit_price' => 'Edit POS item prices',
        'pos.invoice' => 'Create invoices from POS',
        'pos.manage' => 'Manage POS operations',
        'pos.use' => 'Use POS',
        'pos.view' => 'View POS records',
        'pos.void' => 'Void or return sales',
        'procurement.create' => 'Create procurement records',
        'procurement.delete' => 'Delete procurement records',
        'procurement.update' => 'Update procurement records',
        'procurement.view' => 'View procurement',
        'products.create' => 'Create products',
        'products.delete' => 'Delete products',
        'products.update' => 'Update products',
        'products.view' => 'View products',
        'reports.audit.view' => 'View audit report',
        'reports.b2b.view' => 'View B2B report',
        'reports.capital.view' => 'View capital report',
        'reports.expenses.view' => 'View expenses report',
        'reports.installments.view' => 'View installments report',
        'reports.inventory.view' => 'View inventory report',
        'reports.profit.view' => 'View profit report',
        'reports.sales.view' => 'View sales report',
        'reports.view' => 'View reports',
        'roles.view' => 'View roles',
        'sales.create' => 'Create sales',
        'sales.returns' => 'Process returns',
        'sales.view' => 'View sales history',
        'settings.manage' => 'Manage themes and UI settings',
        'shopping_list.create' => 'Add items to shopping list',
        'stores.create' => 'Create stores',
        'stores.delete' => 'Delete stores',
        'stores.manage' => 'Manage stores',
        'stores.update' => 'Update stores',
        'stores.view' => 'View stores',
        'updates.manage' => 'Manage updates',
        'updates.view' => 'View update history',
        'users.view' => 'View users',
    ];

    foreach ($permissions as $key => $description) {
        seed_permission($db, $key, $description);
    }
    seed_log('Permissions seeded.');

    $allPermissions = array_keys($permissions);

    $cashierPermissions = [
        'dashboard.view',
        'pos.use',
        'pos.view',
        'pos.create',
        'sales.view',
        'sales.create',
        'products.view',
        'contacts.view',
        'contacts.create',
        'documents.view',
        'reports.sales.view',
        'reports.b2b.view',
        'shopping_list.create',
    ];

    $accountantPermissions = [
        'dashboard.view',
        'finance.view',
        'finance.create',
        'finance.update',
        'sales.view',
        'reports.sales.view',
        'reports.profit.view',
        'reports.inventory.view',
        'reports.installments.view',
        'reports.expenses.view',
        'reports.capital.view',
        'reports.b2b.view',
        'reports.audit.view',
        'audit.view',
        'messaging.view',
    ];

    seed_grant_permissions($db, $roles['super_admin'], $allPermissions);
    seed_grant_permissions($db, $roles['cashier'], $cashierPermissions);
    seed_grant_permissions($db, $roles['accountant'], $accountantPermissions);
    seed_log('Role permissions assigned.');

    seed_user($db, $roles['super_admin'], 'admin', 'Admin', 'Admin@123');
    seed_user($db, $roles['cashier'], 'cashier1', 'Cashier', 'Cashier@123');
    seed_user($db, $roles['accountant'], 'accountant1', 'Accountant', 'Accountant1@123');
    seed_log('Demo users seeded.');

    seed_setting($db, 'app_theme', 'default', 'General', 'text', 'Active UI theme', 10);
    seed_setting($db, 'currency_symbol', 'UGX ', 'Business', 'text', 'Currency symbol', 20);
    seed_setting($db, 'currency_code', 'UGX', 'Business', 'text', 'Currency code', 30);
    seed_setting($db, 'decimal_places', '0', 'Business', 'text', 'Currency decimal places', 40);
    seed_log('Default settings seeded.');
}

$shouldRun = $isCli || ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';

if ($shouldRun) {
    $confirm = $isCli ? 'SEED DATABASE' : trim((string)($_POST['confirm'] ?? ''));

    if ($confirm !== 'SEED DATABASE') {
        $errors[] = 'Type SEED DATABASE to run the seed.';
    } elseif (!is_file($configFile)) {
        $errors[] = 'Missing config/db.php.';
    } else {
        try {
            $cfg = require $configFile;
            if (!is_array($cfg)) {
                throw new RuntimeException('config/db.php must return an array.');
            }

            $db = @new mysqli(
                (string)($cfg['host'] ?? ''),
                (string)($cfg['username'] ?? ''),
                (string)($cfg['password'] ?? ''),
                (string)($cfg['database'] ?? '')
            );

            if ($db->connect_error) {
                throw new RuntimeException('Database connection failed: ' . $db->connect_error);
            }

            $db->set_charset((string)($cfg['charset'] ?? 'utf8mb4'));
            seed_log('Connected to database: ' . (string)($cfg['database'] ?? ''));
            seed_database($db);
            $seeded = true;
            $db->close();
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

if ($isCli) {
    foreach ($messages as $message) echo '[OK] ' . $message . PHP_EOL;
    foreach ($errors as $error) echo '[ERROR] ' . $error . PHP_EOL;
    exit($errors ? 1 : 0);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Seed Database</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 32px; background: #f7f7f7; color: #222; }
    .card { max-width: 900px; margin: 0 auto; background: #fff; border: 1px solid #ddd; border-radius: 10px; padding: 24px; }
    .alert { padding: 12px 14px; border-radius: 8px; margin: 12px 0; }
    .success { background: #e8f5e9; color: #1b5e20; border: 1px solid #b7dfb9; }
    .error { background: #ffebee; color: #b00020; border: 1px solid #ffcdd2; }
    .warning { background: #fff8e1; color: #7a4f00; border: 1px solid #ffe082; }
    code { background: #f1f1f1; padding: 2px 5px; border-radius: 4px; }
    input { width: 100%; max-width: 360px; padding: 10px; border: 1px solid #bbb; border-radius: 6px; }
    button { padding: 10px 14px; border: 0; border-radius: 6px; background: #0d6efd; color: #fff; cursor: pointer; }
    table { border-collapse: collapse; width: 100%; margin-top: 16px; }
    th, td { border-bottom: 1px solid #eee; padding: 10px; text-align: left; }
  </style>
</head>
<body>
  <div class="card">
    <h1>Seed Database</h1>
    <p>This creates/updates the demo roles, permissions, settings, and users from <code>config/db.php</code>.</p>

    <?php foreach ($errors as $error): ?>
      <div class="alert error"><?= seed_h($error) ?></div>
    <?php endforeach; ?>

    <?php foreach ($messages as $message): ?>
      <div class="alert success"><?= seed_h($message) ?></div>
    <?php endforeach; ?>

    <?php if ($seeded): ?>
      <h2>Demo Users</h2>
      <table>
        <tr><th>Admin</th><td><code>admin</code> / <code>Admin@123</code></td></tr>
        <tr><th>Cashier</th><td><code>cashier1</code> / <code>Cashier@123</code></td></tr>
        <tr><th>Accountant</th><td><code>accountant1</code> / <code>Accountant1@123</code></td></tr>
      </table>
      <div class="alert warning">Delete <code>seed_database.php</code> from the public server after seeding.</div>
    <?php else: ?>
      <div class="alert warning">Only run this on a database you own. Existing demo user passwords will be reset to the values shown on the login page.</div>
      <form method="post">
        <p><label>Type <code>SEED DATABASE</code></label></p>
        <p><input name="confirm" autocomplete="off" required></p>
        <p><button type="submit">Seed Database</button></p>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
