<?php
// config/db.php
// Real credentials live in config/db.local.php, which setup.php writes and git ignores.
$local = __DIR__ . '/db.local.php';
if (is_file($local)) {
    return require $local;
}
return [
  'host'     => 'localhost',
  'database' => 'business_manager',
  'username' => 'root',
  'password' => '',
  'charset'  => 'utf8mb4',
];
