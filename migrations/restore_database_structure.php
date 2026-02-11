<?php
// migrations/restore_database_structure.php
declare(strict_types=1);

// Direct database connection
$config = [
    'host' => 'localhost',
    'database' => 'business_manager',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

try {
    $db = new mysqli($config['host'], $config['username'], $config['password'], $config['database']);
    
    if ($db->connect_error) {
        die("Connection failed: " . $db->connect_error . "\n");
    }
    
    echo "Restoring database structure...\n\n";
    
    // Drop existing messaging tables if they exist
    echo "Dropping existing messaging tables...\n";
    $tables = ['message_logs', 'messages', 'message_templates'];
    foreach ($tables as $table) {
        $db->query("DROP TABLE IF EXISTS $table");
        echo "✓ Dropped $table\n";
    }
    
    // Create message_logs table
    echo "\nCreating message_logs table...\n";
    $createMessageLogs = "
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    if ($db->query($createMessageLogs)) {
        echo "✓ message_logs table created\n";
    } else {
        echo "✗ Error creating message_logs: " . $db->error . "\n";
    }
    
    // Create message_templates table
    echo "\nCreating message_templates table...\n";
    $createMessageTemplates = "
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    if ($db->query($createMessageTemplates)) {
        echo "✓ message_templates table created\n";
    } else {
        echo "✗ Error creating message_templates: " . $db->error . "\n";
    }
    
    // Insert default template
    echo "\nInserting default template...\n";
    $defaultTemplate = "
        INSERT INTO message_templates (name, subject, message, category, created_by) 
        VALUES (
            'Welcome Message',
            'Welcome to our system',
            'Hello {username},\\n\\nWelcome to our business management system. Your account has been successfully created.\\n\\nBest regards,\\nThe Team',
            'welcome',
            1
        )
    ";
    
    if ($db->query($defaultTemplate)) {
        echo "✓ Default template inserted\n";
    } else {
        echo "✗ Error inserting default template: " . $db->error . "\n";
    }
    
    // Verify table structure
    echo "\nVerifying table structure...\n";
    $tables = ['message_logs', 'message_templates'];
    foreach ($tables as $table) {
        $result = $db->query("DESCRIBE $table");
        if ($result && $result->num_rows > 0) {
            echo "✓ $table structure verified\n";
            
            // Show columns
            while ($row = $result->fetch_assoc()) {
                echo "  - {$row['Field']} ({$row['Type']})\n";
            }
        } else {
            echo "✗ $table not found\n";
        }
    }
    
    echo "\nDatabase structure restoration completed!\n";
    echo "You can now use the messaging system with all features enabled.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
