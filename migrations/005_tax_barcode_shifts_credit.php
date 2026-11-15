<?php
return function (mysqli $db): void {
    if (!mig_column_exists($db, 'products', 'barcode')) {
        mig_exec($db, "ALTER TABLE products ADD COLUMN barcode VARCHAR(64) NULL AFTER sku, ADD UNIQUE KEY uq_products_barcode (barcode)");
    }
    if (!mig_column_exists($db, 'customers', 'credit_limit')) {
        mig_exec($db, "ALTER TABLE customers ADD COLUMN credit_limit DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT '0 = no limit'");
    }
    mig_exec($db, "CREATE TABLE IF NOT EXISTS shifts (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        location_id INT NOT NULL,
        status ENUM('open','closed') NOT NULL DEFAULT 'open',
        opening_float DECIMAL(14,2) NOT NULL DEFAULT 0,
        expected_cash DECIMAL(14,2) NULL,
        counted_cash DECIMAL(14,2) NULL,
        difference DECIMAL(14,2) NULL,
        notes VARCHAR(255) NULL,
        opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        closed_at DATETIME NULL,
        KEY idx_shifts_user (user_id, status),
        KEY idx_shifts_opened (opened_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    mig_exec($db, "CREATE TABLE IF NOT EXISTS shift_cash_movements (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        shift_id INT NOT NULL,
        direction ENUM('in','out') NOT NULL,
        amount DECIMAL(14,2) NOT NULL,
        reason VARCHAR(190) NOT NULL,
        created_by INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_scm_shift (shift_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (!mig_column_exists($db, 'sales', 'shift_id')) {
        mig_exec($db, "ALTER TABLE sales ADD COLUMN shift_id INT NULL, ADD KEY idx_sales_shift (shift_id)");
    }
    mig_exec($db, "CREATE TABLE IF NOT EXISTS reminder_log (
        id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        kind VARCHAR(40) NOT NULL,
        ref_id INT NOT NULL,
        channel VARCHAR(10) NOT NULL,
        sent_on DATE NOT NULL,
        status VARCHAR(20) NOT NULL,
        detail VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_reminder (kind, ref_id, channel, sent_on)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
