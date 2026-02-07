<?php
// Test database connection
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing database connection...\n";

try {
    $dbCfg = [
        'host' => 'localhost',
        'database' => 'business_manager',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ];
    
    $mysqli = new mysqli($dbCfg['host'], $dbCfg['username'], $dbCfg['password'], $dbCfg['database']);
    
    if ($mysqli->connect_error) {
        echo "Database connection failed: " . $mysqli->connect_error . "\n";
    } else {
        echo "Database connection successful!\n";
        $mysqli->set_charset($dbCfg['charset']);
        
        // Test a simple query
        $result = $mysqli->query("SELECT 1");
        if ($result) {
            echo "Database query test successful!\n";
            $result->close();
        } else {
            echo "Database query failed: " . $mysqli->error . "\n";
        }
        
        $mysqli->close();
    }
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
?>
