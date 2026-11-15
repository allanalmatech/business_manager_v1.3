<?php
// Everything the mobile app's offline sync needs.
return function (mysqli $db): void {
    // Measured goods need three decimals on the shelf as well as on the sale line
    mig_exec($db, "ALTER TABLE stock_by_location MODIFY qty_base DECIMAL(14,3) NOT NULL DEFAULT 0.000, MODIFY low_level_base DECIMAL(14,3) NOT NULL DEFAULT 0.000");

    foreach ([
        'client_uuid' => "ADD COLUMN client_uuid CHAR(36) NULL, ADD UNIQUE KEY uq_sales_client_uuid (client_uuid)",
        'client_ref'  => "ADD COLUMN client_ref VARCHAR(40) NULL",
        'review_note' => "ADD COLUMN review_note VARCHAR(500) NULL",
        'reviewed_at' => "ADD COLUMN reviewed_at DATETIME NULL",
    ] as $col => $ddl) {
        if (!mig_column_exists($db, 'sales', $col)) mig_exec($db, "ALTER TABLE sales $ddl");
    }

    mig_exec($db, "CREATE TABLE IF NOT EXISTS api_devices (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(120) NOT NULL,
        token_hash CHAR(64) NOT NULL,
        last_seen_at DATETIME NULL,
        last_ip VARCHAR(45) NULL,
        last_pull_version BIGINT NOT NULL DEFAULT 0,
        revoked_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_device_token (token_hash),
        KEY idx_device_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // One row per action a device has pushed: the same action can be retried forever and applies once
    mig_exec($db, "CREATE TABLE IF NOT EXISTS sync_mutations (
        id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        device_id INT NOT NULL,
        client_uuid CHAR(36) NOT NULL,
        kind VARCHAR(40) NOT NULL,
        status ENUM('applied','rejected') NOT NULL,
        result_json TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_mutation (client_uuid),
        KEY idx_mutation_device (device_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // The change feed. Its auto-increment id is the sync version devices pull from.
    mig_exec($db, "CREATE TABLE IF NOT EXISTS sync_changes (
        id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        entity VARCHAR(30) NOT NULL,
        entity_key VARCHAR(40) NOT NULL,
        op ENUM('upsert','delete') NOT NULL,
        changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_sync_entity (entity, entity_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Triggers feed it, so changes made anywhere (web pages, imports, the API) are never missed.
    $tables = [
        'products' => ['product', 'NEW.id', 'OLD.id'],
        'product_categories' => ['category', 'NEW.id', 'OLD.id'],
        'brands' => ['brand', 'NEW.id', 'OLD.id'],
        'customers' => ['customer', 'NEW.id', 'OLD.id'],
        'locations' => ['location', 'NEW.id', 'OLD.id'],
        'stock_by_location' => ['stock', "CONCAT(NEW.product_id, ':', NEW.location_id)", "CONCAT(OLD.product_id, ':', OLD.location_id)"],
    ];
    $ok = true;
    foreach ($tables as $table => [$entity, $newKey, $oldKey]) {
        foreach ([['ai', 'AFTER INSERT', 'upsert', $newKey], ['au', 'AFTER UPDATE', 'upsert', $newKey], ['ad', 'AFTER DELETE', 'delete', $oldKey]] as [$sfx, $when, $op, $key]) {
            $name = "trg_sync_{$table}_{$sfx}";
            $db->query("DROP TRIGGER IF EXISTS $name");
            if (!$db->query("CREATE TRIGGER $name $when ON $table FOR EACH ROW INSERT INTO sync_changes (entity, entity_key, op) VALUES ('$entity', $key, '$op')")) {
                $ok = false; // some hosts do not allow triggers; sync then sends full snapshots instead
            }
        }
    }
    $mode = $ok ? 'triggers' : 'snapshot';
    $st = $db->prepare("INSERT INTO settings (`key`, `value`, `group`, `type`, `description`) VALUES ('sync_mode', ?, 'System', 'text', 'How mobile sync tracks changes')
                        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
    $st->bind_param('s', $mode);
    $st->execute();
    $st->close();

    // Everything that already exists becomes the first page of the feed
    if ((int)$db->query("SELECT COUNT(*) FROM sync_changes")->fetch_row()[0] === 0) {
        mig_exec($db, "INSERT INTO sync_changes (entity, entity_key, op) SELECT 'location', id, 'upsert' FROM locations ORDER BY id");
        mig_exec($db, "INSERT INTO sync_changes (entity, entity_key, op) SELECT 'category', id, 'upsert' FROM product_categories ORDER BY id");
        mig_exec($db, "INSERT INTO sync_changes (entity, entity_key, op) SELECT 'brand', id, 'upsert' FROM brands ORDER BY id");
        mig_exec($db, "INSERT INTO sync_changes (entity, entity_key, op) SELECT 'customer', id, 'upsert' FROM customers ORDER BY id");
        mig_exec($db, "INSERT INTO sync_changes (entity, entity_key, op) SELECT 'product', id, 'upsert' FROM products ORDER BY id");
        mig_exec($db, "INSERT INTO sync_changes (entity, entity_key, op) SELECT 'stock', CONCAT(product_id, ':', location_id), 'upsert' FROM stock_by_location ORDER BY product_id, location_id");
    }
};
