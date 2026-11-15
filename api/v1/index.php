<?php
// api/v1/index.php — token API for the mobile app.
//
//   POST ?r=auth/login      {username, password, device_name}      -> {token, user, ...}
//   POST ?r=auth/logout
//   GET  ?r=bootstrap                                              -> user, permissions, settings, sync head
//   GET  ?r=sync/pull&since=N&limit=500                            -> changes since version N
//   POST ?r=sync/push       {mutations:[{uuid, kind, payload, created_at}]}
//   POST ?r=products/image  multipart: product_id, image           -> {url}
//   GET  ?r=sales/recent&limit=50                                  -> this user's recent sales
//   GET  ?r=reports/today                                          -> today's totals
//
// Send the token as "Authorization: Bearer <token>" (or X-Auth-Token on hosts that strip Authorization).
// No cookies, no sessions: every request stands alone, which is what makes retries safe.
declare(strict_types=1);

const BM_STATELESS = true;
const API_REQUEST = true;

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/rbac.php';
require_once dirname(__DIR__, 2) . '/includes/audit.php';
require_once dirname(__DIR__, 2) . '/includes/settings.php';
require_once dirname(__DIR__, 2) . '/includes/sync.php';
require_once dirname(__DIR__, 2) . '/includes/uploads.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function v1_out(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function v1_fail(string $message, int $code = 400, array $extra = []): void
{
    v1_out(['ok' => false, 'error' => $message] + $extra, $code);
}
function v1_json(): array
{
    $j = json_decode((string)file_get_contents('php://input'), true);
    return is_array($j) ? $j : [];
}
function v1_method(string $want): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $want) v1_fail('Method not allowed', 405);
}

$db = $GLOBALS['db'];
$route = trim((string)($_GET['r'] ?? ($_SERVER['PATH_INFO'] ?? '')), '/');

/** What the app needs to know about the business and the signed-in person. */
function v1_context(mysqli $db, array $user): array
{
    $perms = [];
    foreach (['pos.create', 'pos.view', 'pos.apply_discount', 'pos.edit_price', 'pos.allow_debt', 'pos.void', 'products.view', 'products.create', 'products.update',
              'contacts.view', 'contacts.create', 'reports.sales.view', 'reports.inventory.view'] as $p) {
        if (user_has_permission($p)) $perms[] = $p;
    }
    return [
        'user' => ['id' => (int)$user['id'], 'username' => $user['username'], 'name' => $user['full_name'], 'role' => $user['role']],
        'permissions' => $perms,
        'business' => ['name' => setting('business_name'), 'address' => setting('business_address'), 'phone' => setting('business_phone'), 'tax_id' => setting('business_tax_id')],
        'money' => ['currency_code' => setting('currency_code'), 'currency_symbol' => setting('currency_symbol'), 'decimal_places' => (int)setting('decimal_places')],
        'tax' => tax_config(),
        'receipt' => ['header' => setting('receipt_header'), 'footer' => setting('receipt_footer'), 'width' => (int)setting('receipt_width')],
        'sync_head' => sync_head($db),
        'server_time' => date('c'),
        'api_version' => 1,
        'app_version' => defined('APP_VERSION') ? APP_VERSION : '',
    ];
}

/* ---------- sign in (no token yet) ---------- */
if ($route === 'auth/login') {
    v1_method('POST');
    $in = v1_json();
    $username = trim((string)($in['username'] ?? ''));
    $password = (string)($in['password'] ?? '');
    $deviceName = mb_substr(trim((string)($in['device_name'] ?? 'Phone')), 0, 120) ?: 'Phone';
    if ($username === '' || $password === '') v1_fail('Enter your username and password.', 422);

    $wait = login_throttle_wait($db, $username);
    if ($wait > 0) v1_fail('Too many failed attempts. Try again in ' . (int)ceil($wait / 60) . ' minute(s).', 429, ['retry_after' => $wait]);

    $st = $db->prepare("SELECT u.id, u.role_id, u.username, u.full_name, u.password_hash, u.is_active, r.name AS role
                        FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = ? OR u.email = ? LIMIT 1");
    $st->bind_param('ss', $username, $username);
    $st->execute();
    $user = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$user || (int)$user['is_active'] !== 1 || !password_verify($password, (string)$user['password_hash'])) {
        login_record_attempt($db, $username, false);
        v1_fail('Wrong username or password.', 401);
    }
    login_record_attempt($db, $username, true);

    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token); // only the hash is stored; the token itself is shown once
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $uid = (int)$user['id'];
    $st = $db->prepare("INSERT INTO api_devices (user_id, name, token_hash, last_seen_at, last_ip) VALUES (?,?,?,NOW(),?)");
    $st->bind_param('isss', $uid, $deviceName, $hash, $ip);
    $st->execute();
    $deviceId = (int)$st->insert_id;
    $st->close();

    $_SESSION['user'] = ['id' => $uid, 'role_id' => (int)$user['role_id'], 'role' => $user['role'], 'username' => $user['username'], 'full_name' => $user['full_name']];
    audit_log('auth.device_login', 'device', (string)$deviceId, "Signed in on $deviceName");
    v1_out(['ok' => true, 'token' => $token, 'device_id' => $deviceId] + v1_context($db, $user));
}

/* ---------- everything else needs a valid token ---------- */
$token = '';
$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
if (stripos($auth, 'Bearer ') === 0) $token = trim(substr($auth, 7));
if ($token === '') $token = trim((string)($_SERVER['HTTP_X_AUTH_TOKEN'] ?? ''));
if (!preg_match('/^[a-f0-9]{64}$/', $token)) v1_fail('Sign in again.', 401, ['code' => 'unauthenticated']);

$hash = hash('sha256', $token);
$st = $db->prepare("SELECT d.id AS device_id, d.revoked_at, u.id, u.role_id, u.username, u.full_name, u.is_active, r.name AS role
                    FROM api_devices d JOIN users u ON u.id = d.user_id JOIN roles r ON r.id = u.role_id WHERE d.token_hash = ? LIMIT 1");
$st->bind_param('s', $hash);
$st->execute();
$me = $st->get_result()->fetch_assoc();
$st->close();
if (!$me || $me['revoked_at'] !== null || (int)$me['is_active'] !== 1) v1_fail('This device was signed out. Sign in again.', 401, ['code' => 'unauthenticated']);

$deviceId = (int)$me['device_id'];
$uid = (int)$me['id'];
$_SESSION['user'] = ['id' => $uid, 'role_id' => (int)$me['role_id'], 'role' => $me['role'], 'username' => $me['username'], 'full_name' => $me['full_name']];
$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$st = $db->prepare("UPDATE api_devices SET last_seen_at = NOW(), last_ip = ? WHERE id = ?");
$st->bind_param('si', $ip, $deviceId);
$st->execute();
$st->close();

switch ($route) {
    case 'auth/logout':
        v1_method('POST');
        $st = $db->prepare("UPDATE api_devices SET revoked_at = NOW() WHERE id = ?");
        $st->bind_param('i', $deviceId);
        $st->execute();
        $st->close();
        v1_out(['ok' => true]);

    case 'bootstrap':
        v1_out(['ok' => true] + v1_context($db, $me));

    case 'sync/pull':
        $since = max(0, (int)($_GET['since'] ?? 0));
        $res = sync_pull($db, $since, (int)($_GET['limit'] ?? 500));
        $st = $db->prepare("UPDATE api_devices SET last_pull_version = ? WHERE id = ?");
        $st->bind_param('ii', $since, $deviceId);
        $st->execute();
        $st->close();
        v1_out(['ok' => true] + $res + ['server_time' => date('c')]);

    case 'sync/push':
        v1_method('POST');
        $in = v1_json();
        $muts = is_array($in['mutations'] ?? null) ? $in['mutations'] : [];
        if (!$muts) v1_fail('No mutations sent.', 422);
        v1_out(['ok' => true, 'results' => sync_push($db, $deviceId, $uid, $muts), 'sync_head' => sync_head($db)]);

    case 'products/image':
        v1_method('POST');
        if (!user_has_permission('products.update') && !user_has_permission('products.create')) v1_fail('You are not allowed to change product photos.', 403);
        $pid = (int)($_POST['product_id'] ?? 0);
        $f = $_FILES['image'] ?? null;
        if ($f && in_array((int)($f['error'] ?? 0), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) v1_fail('That photo is too large. The app should compress it first.', 413);
        if ($pid <= 0 || !$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) v1_fail('Send product_id and an image.', 422);
        if ((int)$f['size'] > 3 * 1024 * 1024) v1_fail('That photo is too large. The app should compress it first.', 413);
        if (upload_image_ext_for_file($f['tmp_name']) === null) v1_fail('That file is not a photo.', 422);
        $st = $db->prepare("SELECT images FROM products WHERE id = ? LIMIT 1");
        $st->bind_param('i', $pid);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) v1_fail('That product no longer exists.', 404);

        // Re-encode: strips metadata (location, camera) and caps the size whatever the device sent
        $src = @imagecreatefromstring((string)file_get_contents($f['tmp_name']));
        if (!$src) v1_fail('That photo could not be read.', 422);
        $w = imagesx($src); $h = imagesy($src);
        $scale = min(1, 1280 / max($w, $h));
        $nw = max(1, (int)round($w * $scale)); $nh = max(1, (int)round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $dir = dirname(__DIR__, 2) . '/uploads/products';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $name = upload_random_name('product_' . $pid, 'jpg');
        if (!imagejpeg($dst, "$dir/$name", 80)) v1_fail('The photo could not be saved.', 500);
        imagedestroy($src); imagedestroy($dst);

        $url = 'uploads/products/' . $name;
        $images = json_decode((string)($row['images'] ?? '[]'), true);
        if (!is_array($images)) $images = [];
        array_unshift($images, $url); // newest photo becomes the main one
        $images = array_slice($images, 0, 6);
        $json = json_encode($images);
        $st = $db->prepare("UPDATE products SET images = ? WHERE id = ?");
        $st->bind_param('si', $json, $pid);
        $st->execute();
        $st->close();
        audit_log('products.image', 'product', (string)$pid, 'Photo added from a device');
        v1_out(['ok' => true, 'url' => $url, 'bytes' => filesize("$dir/$name")]);

    case 'sales/recent':
        if (!user_has_permission('pos.view') && !user_has_permission('pos.create')) v1_fail('Not allowed.', 403);
        $limit = max(1, min(200, (int)($_GET['limit'] ?? 50)));
        $all = user_has_permission('reports.sales.view');
        $sql = "SELECT s.id, s.doc_no, s.client_uuid, s.client_ref, s.doc_type, s.grand_total, s.amount_paid, s.balance, s.payment_status, s.created_at, c.name AS customer
                FROM sales s LEFT JOIN customers c ON c.id = s.customer_id WHERE s.status <> 'voided' " . ($all ? '' : 'AND s.created_by = ? ') . "ORDER BY s.id DESC LIMIT ?";
        $st = $db->prepare($sql);
        if ($all) $st->bind_param('i', $limit); else $st->bind_param('ii', $uid, $limit);
        $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();
        foreach ($rows as &$r) { $r['id'] = (int)$r['id']; foreach (['grand_total', 'amount_paid', 'balance'] as $k) $r[$k] = (float)$r[$k]; }
        v1_out(['ok' => true, 'sales' => $rows]);

    case 'reports/today':
        $all = user_has_permission('reports.sales.view');
        $where = "status <> 'voided' AND DATE(created_at) = CURDATE()" . ($all ? '' : " AND created_by = $uid");
        $r = $db->query("SELECT COUNT(*) AS n, COALESCE(SUM(grand_total),0) AS total, COALESCE(SUM(amount_paid),0) AS paid, COALESCE(SUM(balance),0) AS credit FROM sales WHERE $where")->fetch_assoc();
        $low = user_has_permission('products.view')
            ? (int)$db->query("SELECT COUNT(*) FROM stock_by_location s JOIN products p ON p.id = s.product_id WHERE p.is_active = 1 AND s.low_level_base > 0 AND s.qty_base <= s.low_level_base")->fetch_row()[0] : 0;
        v1_out(['ok' => true, 'scope' => $all ? 'business' : 'mine', 'sales_count' => (int)$r['n'], 'sales_total' => (float)$r['total'], 'paid' => (float)$r['paid'], 'on_credit' => (float)$r['credit'], 'low_stock' => $low]);
}
v1_fail('Unknown endpoint.', 404);
