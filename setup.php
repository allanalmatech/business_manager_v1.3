<?php
// setup.php — install or uninstall Business Manager from the browser.
//
// Install:   runs only while no install exists. Creates the tables, your
//            admin account, and config/db.local.php, then locks itself.
// Uninstall: needs a super admin's username and password. Saves a database
//            backup to storage/backups/, then removes the tables and config.
declare(strict_types=1);

session_name('BMSETUP');
session_start();
mysqli_report(MYSQLI_REPORT_OFF);

const SETUP_MIN_PHP = '8.0.0';
$ROOT      = __DIR__;
$lockFile  = $ROOT . '/config/installed.lock';
$localFile = $ROOT . '/config/db.local.php';
$schema    = $ROOT . '/sql/business_manager_clean.sql';

require_once $ROOT . '/includes/migrations.php';

if (empty($_SESSION['setup_csrf'])) $_SESSION['setup_csrf'] = bin2hex(random_bytes(32));

function s_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function s_connect(array $cfg, bool $withDb = true): ?mysqli
{
    $db = @new mysqli((string)$cfg['host'], (string)$cfg['username'], (string)$cfg['password'], $withDb ? (string)$cfg['database'] : '');
    if ($db->connect_error) return null;
    $db->set_charset('utf8mb4');
    return $db;
}

function s_tables(mysqli $db): array
{
    $out = [];
    $rs = $db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    while ($rs && ($r = $rs->fetch_row())) $out[] = $r[0];
    return $out;
}

function s_requirements(string $root): array
{
    $checks = [
        ['PHP ' . SETUP_MIN_PHP . ' or newer', version_compare(PHP_VERSION, SETUP_MIN_PHP, '>='), 'Found ' . PHP_VERSION],
    ];
    foreach (['mysqli', 'mbstring', 'fileinfo', 'gd', 'json'] as $ext) {
        $checks[] = ["PHP extension: $ext", extension_loaded($ext), ''];
    }
    foreach (['config', 'uploads', 'storage'] as $dir) {
        $path = "$root/$dir";
        if (!is_dir($path)) @mkdir($path, 0755, true);
        $checks[] = ["Folder is writable: $dir/", is_writable($path), ''];
    }
    return $checks;
}

/** Writes every table's structure and rows to a .sql file. */
function s_backup(mysqli $db, string $file): void
{
    $fh = fopen($file, 'w');
    if (!$fh) throw new RuntimeException('Could not write the backup file.');
    fwrite($fh, "-- Business Manager backup " . date('c') . "\nSET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n");
    foreach (s_tables($db) as $t) {
        $q = '`' . str_replace('`', '``', $t) . '`';
        $create = $db->query("SHOW CREATE TABLE $q")->fetch_row()[1];
        fwrite($fh, "DROP TABLE IF EXISTS $q;\n$create;\n\n");
        $rs = $db->query("SELECT * FROM $q", MYSQLI_USE_RESULT);
        while ($rs && ($row = $rs->fetch_row())) {
            $vals = array_map(static function ($v) use ($db) {
                return $v === null ? 'NULL' : "'" . $db->real_escape_string((string)$v) . "'";
            }, $row);
            fwrite($fh, "INSERT INTO $q VALUES (" . implode(',', $vals) . ");\n");
        }
        if ($rs) $rs->free();
        fwrite($fh, "\n");
    }
    fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fh);
}

function s_import(mysqli $db, string $file): void
{
    $sql = file_get_contents($file);
    if ($sql === false) throw new RuntimeException('The schema file is missing.');
    if (!$db->multi_query($sql)) throw new RuntimeException('Schema import failed: ' . $db->error);
    do {
        if ($res = $db->store_result()) $res->free();
        if (!$db->more_results()) break;
        if (!$db->next_result()) throw new RuntimeException('Schema import failed: ' . $db->error);
    } while (true);
}

function s_rrmdir_contents(string $dir, array $keep = ['.htaccess']): void
{
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..' || in_array($f, $keep, true)) continue;
        $p = "$dir/$f";
        if (is_dir($p)) { s_rrmdir_contents($p, []); @rmdir($p); } else { @unlink($p); }
    }
}

/* ---------- Work out the current state ---------- */
$cfg = require $ROOT . '/config/db.php';
$existingDb = s_connect($cfg);
$hasUsers = false;
if ($existingDb) {
    $r = $existingDb->query("SELECT COUNT(*) FROM users");
    $hasUsers = (bool)($r && (int)$r->fetch_row()[0] > 0);
}
$installed = is_file($lockFile) || $hasUsers;

$errors = [];
$notice = '';
$done   = '';
$old = [
    'host' => 'localhost', 'database' => '', 'username' => '', 'business' => '',
    'currency' => 'UGX', 'admin_user' => 'admin', 'admin_name' => '', 'admin_email' => '',
];

/* ---------- Handle a submitted form ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if (!hash_equals($_SESSION['setup_csrf'], (string)($_POST['csrf'] ?? ''))) {
        $errors[] = 'This form expired. Reload the page and try again.';
    } elseif ($action === 'install' && !$installed) {
        foreach ($old as $k => $_) $old[$k] = trim((string)($_POST[$k] ?? $old[$k]));
        $dbPass     = (string)($_POST['password'] ?? '');
        $adminPass  = (string)($_POST['admin_pass'] ?? '');
        $adminPass2 = (string)($_POST['admin_pass2'] ?? '');

        foreach (s_requirements($ROOT) as [$label, $ok]) if (!$ok) $errors[] = "Requirement not met: $label";
        if ($old['database'] === '' || !preg_match('/^[A-Za-z0-9_$-]{1,64}$/', $old['database'])) $errors[] = 'Enter a database name (letters, numbers, underscores).';
        if ($old['username'] === '') $errors[] = 'Enter the database username.';
        if (!preg_match('/^[A-Za-z0-9_.-]{3,40}$/', $old['admin_user'])) $errors[] = 'Admin username must be 3–40 letters, numbers, dots, dashes or underscores.';
        if ($old['admin_email'] !== '' && !filter_var($old['admin_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Admin email is not valid.';
        if (strlen($adminPass) < 8) $errors[] = 'Admin password must be at least 8 characters.';
        if ($adminPass !== $adminPass2) $errors[] = 'The two admin passwords do not match.';
        if (!preg_match('/^[A-Za-z]{3}$/', $old['currency'])) $errors[] = 'Currency must be a 3-letter code such as UGX.';

        if (!$errors) {
            $new = ['host' => $old['host'] ?: 'localhost', 'database' => $old['database'], 'username' => $old['username'], 'password' => $dbPass, 'charset' => 'utf8mb4'];
            try {
                $server = s_connect($new, false);
                if (!$server) throw new RuntimeException('Could not connect to the database server. Check the host, username and password.');
                // Works when the user may create databases; harmless when the database already exists.
                $server->query('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $new['database']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                if (!$server->select_db($new['database'])) throw new RuntimeException('That database does not exist and this user cannot create it. Create it in your hosting panel first.');
                if (s_tables($server)) throw new RuntimeException('That database already contains tables. Use an empty database so nothing is overwritten.');

                s_import($server, $schema);
                $server->query('DELETE FROM users'); // the schema file ships sample accounts; none are kept

                $roleId = (int)($server->query("SELECT id FROM roles WHERE name = 'super_admin' LIMIT 1")->fetch_row()[0] ?? 0);
                if ($roleId <= 0) throw new RuntimeException('The schema did not create the super_admin role.');
                $hash  = password_hash($adminPass, PASSWORD_DEFAULT);
                $name  = $old['admin_name'] !== '' ? $old['admin_name'] : 'Administrator';
                $email = $old['admin_email'] !== '' ? $old['admin_email'] : null;
                $st = $server->prepare("INSERT INTO users (role_id, username, email, full_name, password_hash, is_active) VALUES (?,?,?,?,?,1)");
                $st->bind_param('issss', $roleId, $old['admin_user'], $email, $name, $hash);
                if (!$st->execute()) throw new RuntimeException('Could not create the admin account: ' . $st->error);
                $st->close();

                $set = $server->prepare("UPDATE settings SET `value` = ? WHERE `key` = ?");
                $pairs = ['currency_code' => strtoupper($old['currency']), 'currency_symbol' => strtoupper($old['currency']) . ' '];
                if ($old['business'] !== '') $pairs['business_name'] = $old['business'];
                foreach ($pairs as $k => $v) { $set->bind_param('ss', $v, $k); $set->execute(); }
                $set->close();

                migrations_run($server);

                $body = "<?php\n// Written by setup.php. Not tracked by git.\nreturn " . var_export($new, true) . ";\n";
                if (file_put_contents($localFile, $body, LOCK_EX) === false) throw new RuntimeException('Could not write config/db.local.php.');
                @chmod($localFile, 0640);
                file_put_contents($lockFile, 'Installed ' . date('c') . "\n");

                $installed = true;
                $done = 'installed';
                session_regenerate_id(true);
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
    } elseif ($action === 'uninstall' && $installed) {
        $u = trim((string)($_POST['admin_user'] ?? ''));
        $p = (string)($_POST['admin_pass'] ?? '');
        $_SESSION['setup_fail'] = (int)($_SESSION['setup_fail'] ?? 0);
        if ($_SESSION['setup_fail'] >= 5) {
            $errors[] = 'Too many failed attempts. Close the browser and try again later.';
        } elseif (!$existingDb) {
            $errors[] = 'The database cannot be reached, so the admin account cannot be verified.';
        } elseif (trim((string)($_POST['confirm'] ?? '')) !== 'UNINSTALL') {
            $errors[] = 'Type UNINSTALL to confirm.';
        } else {
            $st = $existingDb->prepare("SELECT u.password_hash FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = ? AND u.is_active = 1 AND r.name = 'super_admin' LIMIT 1");
            $st->bind_param('s', $u);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            if (!$row || !password_verify($p, (string)$row['password_hash'])) {
                $_SESSION['setup_fail']++;
                $errors[] = 'That is not an active super admin account, or the password is wrong.';
            } else {
                try {
                    $dir = $ROOT . '/storage/backups';
                    if (!is_dir($dir)) mkdir($dir, 0750, true);
                    $backup = $dir . '/pre-uninstall-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.sql';
                    s_backup($existingDb, $backup);

                    $existingDb->query('SET FOREIGN_KEY_CHECKS=0');
                    foreach (s_tables($existingDb) as $t) {
                        if (!$existingDb->query('DROP TABLE `' . str_replace('`', '``', $t) . '`')) {
                            throw new RuntimeException("Could not remove table $t: " . $existingDb->error);
                        }
                    }
                    $existingDb->query('SET FOREIGN_KEY_CHECKS=1');
                    if (!empty($_POST['delete_uploads'])) {
                        foreach (['products', 'profiles'] as $sub) {
                            if (is_dir("$ROOT/uploads/$sub")) s_rrmdir_contents("$ROOT/uploads/$sub");
                        }
                    }
                    @unlink($localFile);
                    @unlink($lockFile);
                    $installed = false;
                    $done = 'uninstalled';
                    $notice = 'A backup of the database was saved on the server at storage/backups/' . basename($backup) . '.';
                } catch (Throwable $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }
    }
}

$reqs = s_requirements($ROOT);
$reqOk = !in_array(false, array_column($reqs, 1), true);
$base = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Business Manager setup</title>
<style>
  :root { --bg:#f4f6fa; --card:#fff; --ink:#152033; --muted:#5b6678; --line:#dfe4ec; --brand:#1f5fd0; --ok:#0f7a3d; --bad:#b3261e; --warn:#8a5a00; }
  @media (prefers-color-scheme: dark) { :root { --bg:#0f141c; --card:#182130; --ink:#e8edf5; --muted:#a3aec0; --line:#2b384c; --brand:#6ea0ff; --ok:#57c98a; --bad:#ff8a80; --warn:#f0c060; } }
  * { box-sizing: border-box; }
  body { margin:0; font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; background:var(--bg); color:var(--ink); }
  main { max-width: 760px; margin: 0 auto; padding: clamp(16px, 4vw, 40px); }
  h1 { font-size: clamp(1.4rem, 4vw, 1.9rem); margin: 0 0 4px; }
  h2 { font-size: 1.05rem; margin: 0 0 12px; }
  p.lead { color: var(--muted); margin: 0 0 20px; }
  .card { background: var(--card); border:1px solid var(--line); border-radius:14px; padding: clamp(16px, 3vw, 24px); margin-bottom:16px; }
  .grid { display:grid; gap:12px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
  label { display:block; font-size:.9rem; font-weight:600; margin-bottom:4px; }
  input[type=text], input[type=password], input[type=email] { width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px; background:transparent; color:inherit; font:inherit; }
  input:focus-visible, button:focus-visible, a:focus-visible { outline: 3px solid color-mix(in srgb, var(--brand) 50%, transparent); outline-offset: 2px; }
  .hint { font-size:.82rem; color:var(--muted); margin-top:4px; }
  button, .btn { display:inline-block; border:0; border-radius:10px; padding:11px 18px; font:inherit; font-weight:600; cursor:pointer; background:var(--brand); color:#fff; text-decoration:none; }
  @media (prefers-color-scheme: dark) { button, .btn { color:#0b1220; } }
  button.danger { background: var(--bad); }
  button[disabled] { opacity:.5; cursor:not-allowed; }
  ul.checks { list-style:none; padding:0; margin:0; display:grid; gap:6px; }
  ul.checks li { display:flex; justify-content:space-between; gap:12px; font-size:.92rem; }
  .ok { color:var(--ok); font-weight:700; } .bad { color:var(--bad); font-weight:700; }
  .alert { border-radius:10px; padding:12px 14px; margin-bottom:16px; border:1px solid var(--line); }
  .alert.err { border-color: var(--bad); color: var(--bad); }
  .alert.good { border-color: var(--ok); color: var(--ok); }
  .alert.warn { border-color: var(--warn); color: var(--warn); }
  .row { display:flex; gap:8px; align-items:flex-start; margin: 10px 0; }
  .row input { margin-top:4px; }
</style>
</head>
<body>
<main>
  <h1>Business Manager setup</h1>
  <p class="lead"><?= $installed ? 'This copy is installed.' : 'Install the system on this server in one step.' ?></p>

  <?php foreach ($errors as $e): ?><div class="alert err" role="alert"><?= s_h($e) ?></div><?php endforeach; ?>
  <?php if ($notice): ?><div class="alert warn"><?= s_h($notice) ?></div><?php endif; ?>

  <?php if ($done === 'installed'): ?>
    <div class="alert good" role="status">Installed. Sign in with the admin account you just created.</div>
    <a class="btn" href="<?= s_h($base) ?>/login.php">Go to sign in</a>

  <?php elseif ($done === 'uninstalled'): ?>
    <div class="alert good" role="status">Uninstalled. The tables and the saved database settings were removed.</div>
    <p class="lead">To remove the system completely, delete this folder from the server. To start again, reload this page.</p>
    <a class="btn" href="<?= s_h($base) ?>/setup.php">Install again</a>

  <?php elseif (!$installed): ?>
    <div class="card">
      <h2>1. Server check</h2>
      <ul class="checks">
        <?php foreach ($reqs as [$label, $ok, $note]): ?>
          <li><span><?= s_h($label) ?><?= $note ? ' <span class="hint">(' . s_h($note) . ')</span>' : '' ?></span><span class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? 'OK' : 'Missing' ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= s_h($_SESSION['setup_csrf']) ?>">
      <input type="hidden" name="action" value="install">

      <div class="card">
        <h2>2. Database</h2>
        <div class="grid">
          <div><label for="host">Host</label><input type="text" id="host" name="host" value="<?= s_h($old['host']) ?>" required></div>
          <div><label for="database">Database name</label><input type="text" id="database" name="database" value="<?= s_h($old['database']) ?>" required><div class="hint">Must be empty. It is created if your user is allowed to.</div></div>
          <div><label for="username">Username</label><input type="text" id="username" name="username" value="<?= s_h($old['username']) ?>" required></div>
          <div><label for="password">Password</label><input type="password" id="password" name="password"></div>
        </div>
      </div>

      <div class="card">
        <h2>3. Your business</h2>
        <div class="grid">
          <div><label for="business">Business name</label><input type="text" id="business" name="business" value="<?= s_h($old['business']) ?>"></div>
          <div><label for="currency">Currency code</label><input type="text" id="currency" name="currency" value="<?= s_h($old['currency']) ?>" maxlength="3" required></div>
        </div>
      </div>

      <div class="card">
        <h2>4. Administrator account</h2>
        <div class="grid">
          <div><label for="admin_user">Username</label><input type="text" id="admin_user" name="admin_user" value="<?= s_h($old['admin_user']) ?>" required></div>
          <div><label for="admin_name">Full name</label><input type="text" id="admin_name" name="admin_name" value="<?= s_h($old['admin_name']) ?>"></div>
          <div><label for="admin_email">Email</label><input type="email" id="admin_email" name="admin_email" value="<?= s_h($old['admin_email']) ?>"><div class="hint">Used for password resets.</div></div>
          <div></div>
          <div><label for="admin_pass">Password</label><input type="password" id="admin_pass" name="admin_pass" minlength="8" required><div class="hint">At least 8 characters.</div></div>
          <div><label for="admin_pass2">Repeat password</label><input type="password" id="admin_pass2" name="admin_pass2" minlength="8" required></div>
        </div>
      </div>

      <button type="submit" <?= $reqOk ? '' : 'disabled' ?>>Install</button>
      <?php if (!$reqOk): ?><span class="hint">Fix the items marked Missing first.</span><?php endif; ?>
    </form>

  <?php else: ?>
    <div class="card">
      <h2>Status</h2>
      <ul class="checks">
        <li><span>Database connection</span><span class="<?= $existingDb ? 'ok' : 'bad' ?>"><?= $existingDb ? 'OK' : 'Failed' ?></span></li>
        <?php if ($existingDb): $pending = migrations_pending($existingDb); ?>
          <li><span>Database up to date</span><span class="<?= $pending ? 'bad' : 'ok' ?>"><?= $pending ? count($pending) . ' step(s) pending' : 'OK' ?></span></li>
        <?php endif; ?>
      </ul>
      <p class="hint">Pending database steps are applied from Administration → Updates.</p>
      <a class="btn" href="<?= s_h($base) ?>/login.php">Open the system</a>
    </div>

    <div class="card">
      <h2>Uninstall</h2>
      <p class="hint">Removes every table in this system's database and the saved database settings. A full backup is written to <code>storage/backups/</code> on the server first. The program files stay until you delete the folder.</p>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= s_h($_SESSION['setup_csrf']) ?>">
        <input type="hidden" name="action" value="uninstall">
        <div class="grid">
          <div><label for="u_user">Super admin username</label><input type="text" id="u_user" name="admin_user" required></div>
          <div><label for="u_pass">Password</label><input type="password" id="u_pass" name="admin_pass" required></div>
          <div><label for="u_confirm">Type UNINSTALL to confirm</label><input type="text" id="u_confirm" name="confirm" required></div>
        </div>
        <div class="row"><input type="checkbox" id="delete_uploads" name="delete_uploads" value="1"><label for="delete_uploads" style="font-weight:400">Also delete uploaded product and profile images</label></div>
        <button type="submit" class="danger">Uninstall</button>
      </form>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
