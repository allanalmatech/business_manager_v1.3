<?php
// Debug test for API
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting debug test...\n";

try {
    require_once __DIR__ . '/../includes/bootstrap.php';
    echo "Bootstrap loaded\n";
    
    $db = $GLOBALS['db'] ?? null;
    if (!($db instanceof mysqli)) {
        echo "DB not available or not mysqli instance\n";
        echo "DB type: " . gettype($db) . "\n";
        if ($db === null) {
            echo "DB is null\n";
        }
    } else {
        echo "DB connection OK\n";
    }
    
    require_once __DIR__ . '/../includes/auth.php';
    echo "Auth loaded\n";
    
    require_once __DIR__ . '/../includes/rbac.php';
    echo "RBAC loaded\n";
    
    require_once __DIR__ . '/../includes/helpers.php';
    echo "Helpers loaded\n";
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        echo "Session started\n";
    } else {
        echo "Session already active\n";
    }
    
    echo "All requires successful\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>
