<?php
// migrations/add_read_status_to_messages.php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$db = $GLOBALS['db'] ?? null;

if (!($db instanceof mysqli)) {
    die("Database connection required.\n");
}

echo "Adding read status columns to messaging tables...\n";

// Check and add is_read column to message_logs
$result = $db->query("SHOW COLUMNS FROM message_logs LIKE 'is_read'");
if ($result && $result->num_rows === 0) {
    echo "Adding is_read column to message_logs...\n";
    $db->query("ALTER TABLE message_logs ADD COLUMN is_read TINYINT(1) DEFAULT 0 AFTER status");
    echo "✓ is_read column added to message_logs\n";
} else {
    echo "✓ is_read column already exists in message_logs\n";
}

// Check and add read_at column to message_logs
$result = $db->query("SHOW COLUMNS FROM message_logs LIKE 'read_at'");
if ($result && $result->num_rows === 0) {
    echo "Adding read_at column to message_logs...\n";
    $db->query("ALTER TABLE message_logs ADD COLUMN read_at DATETIME NULL AFTER is_read");
    echo "✓ read_at column added to message_logs\n";
} else {
    echo "✓ read_at column already exists in message_logs\n";
}

// Check and add is_read column to messages (if it exists)
$result = $db->query("SHOW TABLES LIKE 'messages'");
if ($result && $result->num_rows > 0) {
    $result = $db->query("SHOW COLUMNS FROM messages LIKE 'is_read'");
    if ($result && $result->num_rows === 0) {
        echo "Adding is_read column to messages...\n";
        $db->query("ALTER TABLE messages ADD COLUMN is_read TINYINT(1) DEFAULT 0 AFTER status");
        echo "✓ is_read column added to messages\n";
    } else {
        echo "✓ is_read column already exists in messages\n";
    }
    
    // Check and add read_at column to messages
    $result = $db->query("SHOW COLUMNS FROM messages LIKE 'read_at'");
    if ($result && $result->num_rows === 0) {
        echo "Adding read_at column to messages...\n";
        $db->query("ALTER TABLE messages ADD COLUMN read_at DATETIME NULL AFTER is_read");
        echo "✓ read_at column added to messages\n";
    } else {
        echo "✓ read_at column already exists in messages\n";
    }
}

echo "\nMigration completed successfully!\n";
echo "The messaging tables now support read/unread status tracking.\n";
?>
