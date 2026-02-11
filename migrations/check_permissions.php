<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$db = $GLOBALS['db'] ?? null;
if (!($db instanceof mysqli)) {
    die("Database not available\n");
}

echo "Checking existing permissions in database...\n";

$result = $db->query("SELECT perm_key FROM permissions ORDER BY perm_key");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['perm_key'] . "\n";
    }
} else {
    echo "No permissions found\n";
}
?>
