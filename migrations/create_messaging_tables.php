<?php
// migrations/create_messaging_tables.php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$db = $GLOBALS['db'] ?? null;

if (!$db instanceof mysqli) {
    die("Database connection required.\n");
}

echo "Creating messaging system tables...\n";

try {
    // Create message_logs table
    $createMessageLogs = $db->prepare("
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
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_recipient (recipient_type, recipient_id),
            INDEX idx_status (status),
            INDEX idx_scheduled (scheduled_at),
            INDEX idx_created (created_at),
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    if ($createMessageLogs->execute()) {
        echo "✓ message_logs table created/verified\n";
    }
    $createMessageLogs->close();
    
    // Create message_templates table
    $createMessageTemplates = $db->prepare("
        CREATE TABLE IF NOT EXISTS message_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            subject VARCHAR(255),
            message TEXT NOT NULL,
            category VARCHAR(100) DEFAULT 'general',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_name (name),
            INDEX idx_category (category),
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    if ($createMessageTemplates->execute()) {
        echo "✓ message_templates table created/verified\n";
    }
    $createMessageTemplates->close();
    
    // Create fallback messages table (for compatibility)
    $createMessages = $db->prepare("
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
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_recipient (recipient_type, recipient_id),
            INDEX idx_status (status),
            INDEX idx_scheduled (scheduled_at),
            INDEX idx_created (created_at),
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    if ($createMessages->execute()) {
        echo "✓ messages table created/verified\n";
    }
    $createMessages->close();
    
    // Insert some default message templates
    $insertTemplates = $db->prepare("
        INSERT IGNORE INTO message_templates (name, subject, message, category) VALUES
        ('Welcome Message', 'Welcome to Business Manager', 'Hello {username},\n\nWelcome to the Business Manager system! We are excited to have you on board.\n\nBest regards,\nAdmin Team', 'welcome'),
        ('System Maintenance', 'Scheduled System Maintenance', 'Dear Users,\n\nWe will be performing system maintenance on {date} from {start_time} to {end_time}.\n\nDuring this time, the system may be temporarily unavailable.\n\nThank you for your patience.\n\nIT Team', 'notification'),
        ('Password Reset', 'Password Reset Request', 'Hello {username},\n\nA password reset was requested for your account. If this was not you, please contact support immediately.\n\nOtherwise, please reset your password using the link provided.\n\nBest regards,\nSupport Team', 'support'),
        ('New Feature', 'New Feature Available', 'Hello {username},\n\nWe are excited to announce a new feature: {feature_name}\n\n{feature_description}\n\nYou can access this feature from your dashboard.\n\nEnjoy!\nProduct Team', 'marketing'),
        ('Urgent Alert', 'Urgent: System Alert', 'Attention All Users,\n\n{alert_message}\n\nPlease take appropriate action immediately.\n\nSystem Administrator', 'alert')
    ");
    
    if ($insertTemplates->execute()) {
        echo "✓ Default message templates inserted\n";
    }
    $insertTemplates->close();
    
    echo "\n✅ Messaging system setup complete!\n";
    echo "Tables created: message_logs, messages, message_templates\n";
    echo "Default templates added: 5 templates\n";
    
} catch (Exception $e) {
    echo "❌ Error creating tables: " . $e->getMessage() . "\n";
    exit(1);
}
