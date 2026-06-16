<?php
// test_db.php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

$configFile = __DIR__ . '/config/db.php';

function test_db_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function test_db_status(string $label, bool $ok, string $detail = ''): void {
    $class = $ok ? 'ok' : 'fail';
    $text = $ok ? 'OK' : 'FAIL';
    echo '<tr><th>' . test_db_h($label) . '</th><td><span class="' . $class . '">' . $text . '</span>';
    if ($detail !== '') {
        echo '<div class="detail">' . test_db_h($detail) . '</div>';
    }
    echo '</td></tr>';
}

$cfg = null;
$connection = null;
$connectError = '';

if (is_file($configFile)) {
    $cfg = require $configFile;
}

if (is_array($cfg) && extension_loaded('mysqli')) {
    mysqli_report(MYSQLI_REPORT_OFF);
    $connection = @new mysqli(
        (string)($cfg['host'] ?? ''),
        (string)($cfg['username'] ?? ''),
        (string)($cfg['password'] ?? ''),
        (string)($cfg['database'] ?? '')
    );

    if ($connection->connect_error) {
        $connectError = $connection->connect_error;
        $connection = null;
    } else {
        $charset = (string)($cfg['charset'] ?? 'utf8mb4');
        @$connection->set_charset($charset);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Database Test</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 32px; background: #f7f7f7; color: #222; }
    .card { max-width: 900px; margin: 0 auto; background: #fff; border: 1px solid #ddd; border-radius: 10px; padding: 24px; }
    table { width: 100%; border-collapse: collapse; margin-top: 16px; }
    th, td { padding: 10px 12px; border-bottom: 1px solid #eee; text-align: left; vertical-align: top; }
    th { width: 240px; background: #fafafa; }
    .ok { color: #0a7f36; font-weight: 700; }
    .fail { color: #b00020; font-weight: 700; }
    .detail { color: #555; margin-top: 4px; font-size: 13px; }
    code { background: #f1f1f1; padding: 2px 5px; border-radius: 4px; }
  </style>
</head>
<body>
  <div class="card">
    <h1>Database Connection Test</h1>
    <p>This tests the credentials in <code>config/db.php</code>. The password is not displayed.</p>

    <table>
      <?php test_db_status('Config file', is_file($configFile), $configFile); ?>
      <?php test_db_status('Config loaded', is_array($cfg)); ?>
      <?php test_db_status('mysqli extension', extension_loaded('mysqli')); ?>

      <?php if (is_array($cfg)): ?>
        <tr><th>Host</th><td><?= test_db_h($cfg['host'] ?? '') ?></td></tr>
        <tr><th>Database</th><td><?= test_db_h($cfg['database'] ?? '') ?></td></tr>
        <tr><th>Username</th><td><?= test_db_h($cfg['username'] ?? '') ?></td></tr>
        <tr><th>Charset</th><td><?= test_db_h($cfg['charset'] ?? '') ?></td></tr>
      <?php endif; ?>

      <?php test_db_status('Connection', $connection instanceof mysqli, $connectError); ?>

      <?php if ($connection instanceof mysqli): ?>
        <?php
          $version = '';
          $database = '';
          $tableCount = '';

          $result = $connection->query('SELECT VERSION() AS version, DATABASE() AS database_name');
          if ($result) {
              $row = $result->fetch_assoc();
              $version = (string)($row['version'] ?? '');
              $database = (string)($row['database_name'] ?? '');
          }

          $result = $connection->query("SELECT COUNT(*) AS table_count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()");
          if ($result) {
              $row = $result->fetch_assoc();
              $tableCount = (string)($row['table_count'] ?? '0');
          }
        ?>
        <tr><th>Server version</th><td><?= test_db_h($version) ?></td></tr>
        <tr><th>Selected database</th><td><?= test_db_h($database) ?></td></tr>
        <tr><th>Tables found</th><td><?= test_db_h($tableCount) ?></td></tr>
      <?php endif; ?>
    </table>

    <p class="detail">Remove this file after testing on a public server.</p>
  </div>
</body>
</html>
<?php
if ($connection instanceof mysqli) {
    $connection->close();
}
