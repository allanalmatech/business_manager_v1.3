<?php
// These tables used to be created inside request handlers. Older installs may lack them.
return function (mysqli $db): void {
    mig_exec($db, "CREATE TABLE IF NOT EXISTS b2b_sales_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        sku VARCHAR(100) DEFAULT '',
        qty DECIMAL(12,4) NOT NULL,
        unit_type VARCHAR(50) NOT NULL DEFAULT 'pieces',
        unit_name VARCHAR(50) DEFAULT '',
        cost_price DECIMAL(12,2) NOT NULL,
        sell_price DECIMAL(12,2) NOT NULL,
        currency VARCHAR(10) NOT NULL DEFAULT 'UGX',
        exchange_rate DECIMAL(12,6) NOT NULL DEFAULT 1,
        supplier_text VARCHAR(255) DEFAULT '',
        note TEXT,
        line_total DECIMAL(12,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_sale_id (sale_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    mig_exec($db, "CREATE TABLE IF NOT EXISTS phone_scan_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(100) UNIQUE NOT NULL,
        product_id INT,
        status ENUM('created','connected','scanning','found','uploaded','error') DEFAULT 'created',
        image_url TEXT,
        message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
