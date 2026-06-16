<?php
// test_session.php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');

$count = (int)($_SESSION['session_test_count'] ?? 0) + 1;
$_SESSION['session_test_count'] = $count;

$sessionName = session_name();
$cookiePresent = isset($_COOKIE[$sessionName]);
$sessionId = session_id();
$csrf = (string)($_SESSION['csrf'] ?? '');
$backend = (string)($GLOBALS['SESSION_BACKEND'] ?? 'unknown');

function test_session_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Session Test</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 32px; background: #f7f7f7; color: #222; }
    .card { max-width: 850px; margin: 0 auto; background: #fff; border: 1px solid #ddd; border-radius: 10px; padding: 24px; }
    table { width: 100%; border-collapse: collapse; margin-top: 16px; }
    th, td { padding: 10px 12px; border-bottom: 1px solid #eee; text-align: left; vertical-align: top; }
    th { width: 240px; background: #fafafa; }
    .ok { color: #0a7f36; font-weight: 700; }
    .fail { color: #b00020; font-weight: 700; }
    code { background: #f1f1f1; padding: 2px 5px; border-radius: 4px; }
  </style>
</head>
<body>
  <div class="card">
    <h1>Session Test</h1>
    <p>Refresh this page. If sessions are working, the counter should increase and the session ID prefix should stay the same.</p>

    <table>
      <tr><th>Session backend</th><td><?= test_session_h($backend) ?></td></tr>
      <tr><th>Session name</th><td><?= test_session_h($sessionName) ?></td></tr>
      <tr><th>Cookie present on request</th><td><span class="<?= $cookiePresent ? 'ok' : 'fail' ?>"><?= $cookiePresent ? 'Yes' : 'No' ?></span></td></tr>
      <tr><th>Session ID prefix</th><td><code><?= test_session_h(substr($sessionId, 0, 12)) ?></code></td></tr>
      <tr><th>Refresh counter</th><td><?= (int)$count ?></td></tr>
      <tr><th>CSRF token prefix</th><td><code><?= test_session_h(substr($csrf, 0, 12)) ?></code></td></tr>
      <tr><th>HTTPS detected</th><td><?= (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ? 'Yes' : 'No' ?></td></tr>
    </table>

    <p><a href="test_session.php">Refresh test</a></p>
    <p>Delete <code>test_session.php</code> after testing on a public server.</p>
  </div>
</body>
</html>
