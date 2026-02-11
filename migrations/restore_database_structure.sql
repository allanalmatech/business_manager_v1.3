-- Database Structure Restoration Script
-- Run this in phpMyAdmin or MySQL client to restore messaging tables

-- Drop existing messaging tables
DROP TABLE IF EXISTS message_logs;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS message_templates;

-- Create message_logs table with all required columns
CREATE TABLE message_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT,
    recipient_type ENUM('user', 'role', 'all') NOT NULL DEFAULT 'user',
    recipient_id INT,
    subject VARCHAR(255),
    message TEXT NOT NULL,
    status ENUM('queued', 'sent', 'failed', 'delivered') NOT NULL DEFAULT 'queued',
    is_read TINYINT(1) DEFAULT 0,
    read_at DATETIME NULL,
    scheduled_at TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_recipient (recipient_type, recipient_id),
    INDEX idx_status (status),
    INDEX idx_read (is_read),
    INDEX idx_scheduled (scheduled_at),
    INDEX idx_created (created_at),
    INDEX idx_sender (sender_id),
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create message_templates table
CREATE TABLE message_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    message TEXT NOT NULL,
    category VARCHAR(100) DEFAULT 'general',
    variables JSON NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_active (is_active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default template
INSERT INTO message_templates (name, subject, message, category, created_by) 
VALUES (
    'Welcome Message',
    'Welcome to our system',
    'Hello {username},\n\nWelcome to our business management system. Your account has been successfully created.\n\nBest regards,\nThe Team',
    'welcome',
    1
);

-- Verify structure
SHOW TABLES LIKE 'message_logs';
SHOW TABLES LIKE 'message_templates';
DESCRIBE message_logs;
DESCRIBE message_templates;
