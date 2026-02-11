<?php
// migrations/setup_messaging.php
// Simple script to create messaging tables

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'business_manager';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Creating messaging tables...\n";
    
    // Create message_logs table
    $createMessageLogs = "
        CREATE TABLE IF NOT EXISTS message_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT,
            recipient_type ENUM('user', 'role', 'all') NOT NULL DEFAULT 'user',
            recipient_id INT,
            subject VARCHAR(255),
            message TEXT NOT NULL,
            status ENUM('queued', 'sent', 'failed', 'delivered') NOT NULL DEFAULT 'queued',
            scheduled_at TIMESTAMP NULL,
            sent_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($createMessageLogs);
    echo "✓ message_logs table created\n";
    
    // Create message_templates table
    $createMessageTemplates = "
        CREATE TABLE IF NOT EXISTS message_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            subject VARCHAR(255),
            message TEXT NOT NULL,
            category VARCHAR(100) DEFAULT 'general',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($createMessageTemplates);
    echo "✓ message_templates table created\n";
    
    // Create messages table (for compatibility)
    $createMessages = "
        CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT,
            recipient_type ENUM('user', 'role', 'all') NOT NULL DEFAULT 'user',
            recipient_id INT,
            subject VARCHAR(255),
            message TEXT NOT NULL,
            status ENUM('queued', 'sent', 'failed', 'delivered') NOT NULL DEFAULT 'queued',
            scheduled_at TIMESTAMP NULL,
            sent_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($createMessages);
    echo "✓ messages table created\n";
    
    // Insert default templates
    $insertTemplates = "
        INSERT IGNORE INTO message_templates (name, subject, message, category) VALUES
        ('Welcome Message', 'Welcome to Business Manager', 'Hello {username},\n\nWelcome to the Business Manager system! We are excited to have you on board.\n\nBest regards,\nAdmin Team', 'welcome'),
        ('System Maintenance', 'Scheduled System Maintenance', 'Dear Users,\n\nWe will be performing system maintenance on {date} from {start_time} to {end_time}.\n\nDuring this time, the system may be temporarily unavailable.\n\nThank you for your patience.\n\nIT Team', 'notification'),
        ('Password Reset', 'Password Reset Request', 'Hello {username},\n\nA password reset was requested for your account. If this was not you, please contact support immediately.\n\nOtherwise, please reset your password using the link provided.\n\nBest regards,\nSupport Team', 'support')
    ";
    
    $pdo->exec($insertTemplates);
    echo "✓ Default templates inserted\n";
    
    echo "\n✅ Messaging system setup complete!\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
