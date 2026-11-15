<?php
return function (mysqli $db): void {
    mig_exec($db, "CREATE TABLE IF NOT EXISTS login_attempts (
        id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        identifier VARCHAR(190) NOT NULL,
        ip_address VARCHAR(45) NOT NULL DEFAULT '',
        success TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_la_identifier (identifier, created_at),
        KEY idx_la_ip (ip_address, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
