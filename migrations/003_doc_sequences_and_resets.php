<?php
return function (mysqli $db): void {
    if (!mig_table_exists($db, 'doc_sequences')) {
        mig_exec($db, "CREATE TABLE doc_sequences (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            doc_type ENUM('receipt','invoice','delivery_note') NOT NULL,
            year SMALLINT NOT NULL,
            prefix VARCHAR(10) NOT NULL,
            current_no INT NOT NULL DEFAULT 0,
            UNIQUE KEY uq_doc_sequences (doc_type, year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    if (!mig_table_exists($db, 'password_resets')) {
        mig_exec($db, "CREATE TABLE password_resets (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL,
            token VARCHAR(128) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_pr_token (token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    if (!mig_column_exists($db, 'settings', 'options')) {
        mig_exec($db, "ALTER TABLE settings ADD COLUMN options TEXT NULL AFTER `type`");
    }
};
