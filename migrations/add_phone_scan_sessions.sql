-- Add phone_scan_sessions table for cross-device QR scanning
-- This table manages sessions between PC and phone for QR scanning

CREATE TABLE IF NOT EXISTS phone_scan_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id VARCHAR(100) UNIQUE NOT NULL,
  product_id INT,
  status ENUM('created', 'connected', 'scanning', 'found', 'uploaded', 'error') DEFAULT 'created',
  image_url TEXT,
  message TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_session (session_id),
  INDEX idx_status (status),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
