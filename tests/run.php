<?php
// tests/run.php — automated checks for the money and stock rules.
//
//   BM_TEST_DB_USER=user BM_TEST_DB_PASS=pass php tests/run.php
//
// Builds a scratch database (BM_TEST_DB_NAME, default bm_selftest), installs the
// schema and every migration, runs the checks, and drops the database again.
// Never point it at a database you care about: it is dropped at the start.
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_OFF);
$root = dirname(__DIR__);
$host = getenv('BM_TEST_DB_HOST') ?: 'localhost';
$user = getenv('BM_TEST_DB_USER') ?: 'root';
$pass = getenv('BM_TEST_DB_PASS') ?: '';
$name = getenv('BM_TEST_DB_NAME') ?: 'bm_selftest';
if (!preg_match('/test/i', $name)) { fwrite(STDERR, "Refusing: the test database name must contain 'test'.\n"); exit(2); }

$db = @new mysqli($host, $user, $pass);
if ($db->connect_error) { fwrite(STDERR, "Cannot connect: {$db->connect_error}\n"); exit(2); }
$db->query("DROP DATABASE IF EXISTS `$name`");
if (!$db->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4") || !$db->select_db($name)) { fwrite(STDERR, "Cannot create $name: {$db->error}\n"); exit(2); }
$db->set_charset('utf8mb4');
$GLOBALS['db'] = $db;
$_SESSION = [];

require_once "$root/includes/migrations.php";
require_once "$root/includes/stock.php";
require_once "$root/includes/tax.php";
require_once "$root/includes/notifications.php";

$pass_n = 0; $fail_n = 0;
function check(string $what, bool $ok, string $detail = ''): void {
    global $pass_n, $fail_n;
    if ($ok) { $pass_n++; echo "  ok    $what\n"; }
    else { $fail_n++; echo "  FAIL  $what" . ($detail !== '' ? "  ($detail)" : '') . "\n"; }
}
function throws(callable $fn, string $class): bool {
    try { $fn(); } catch (Throwable $e) { return $e instanceof $class; }
    return false;
}

echo "Schema and migrations\n";
$sql = file_get_contents("$root/sql/business_manager_clean.sql");
$ok = $db->multi_query($sql);
while ($ok && $db->more_results()) { if ($r = $db->store_result()) $r->free(); $ok = $db->next_result(); }
if ($r = $db->store_result()) $r->free();
check('clean schema imports', $db->errno === 0, $db->error);
try { $done = migrations_run($db); check('all migrations apply', count($done) === count(migrations_all())); }
catch (Throwable $e) { check('all migrations apply', false, $e->getMessage()); }
check('migrations are not applied twice', migrations_run($db) === []);
check('no migrations left pending', migrations_pending($db) === []);

echo "Stock\n";
$db->query("INSERT INTO products (id, name, sku, unit_type, cost_price, wholesale_price, retail_price, is_active) VALUES (9001,'Test item','T-9001','pieces',100,150,200,1)");
$db->begin_transaction();
$r = stock_move($db, 9001, 1, 10, 'stock_in', 'test', null, 'in', 1);
check('stock in records before/after', $r['before'] === 0.0 && $r['after'] === 10.0);
$r = stock_move($db, 9001, 1, -4, 'sale', 'test', '1', 'out', 1);
check('stock out reduces quantity', $r['after'] === 6.0);
check('cannot go below zero', throws(static function () use ($db) { stock_move($db, 9001, 1, -7, 'sale', 'test', '2', 'x', 1); }, 'StockError'));
$db->commit();
$q = (float)$db->query("SELECT qty_base FROM stock_by_location WHERE product_id = 9001 AND location_id = 1")->fetch_row()[0];
check('refused change left stock untouched', $q === 6.0, (string)$q);
$n = (int)$db->query("SELECT COUNT(*) FROM stock_movements WHERE product_id = 9001")->fetch_row()[0];
check('ledger has one row per accepted change', $n === 2, (string)$n);
$sum = (float)$db->query("SELECT SUM(qty_change) FROM stock_movements WHERE product_id = 9001")->fetch_row()[0];
check('ledger total equals stock on hand', abs($sum - $q) < 0.0005);

echo "Tax\n";
$off = ['enabled' => false, 'name' => 'VAT', 'rate' => 18.0, 'inclusive' => true];
$inc = ['enabled' => true, 'name' => 'VAT', 'rate' => 18.0, 'inclusive' => true];
$exc = ['enabled' => true, 'name' => 'VAT', 'rate' => 18.0, 'inclusive' => false];
check('tax off: nothing added', tax_compute(11800, $off) === ['tax' => 0.0, 'total' => 11800.0]);
check('inclusive: total unchanged, tax extracted', tax_compute(11800, $inc) === ['tax' => 1800.0, 'total' => 11800.0]);
check('exclusive: tax added on top', tax_compute(10000, $exc) === ['tax' => 1800.0, 'total' => 11800.0]);
check('zero amount has zero tax', tax_compute(0, $exc)['tax'] === 0.0);

echo "Settings\n";
check('unknown key is refused', setting_save($db, 'not_a_setting', '1') !== null);
check('out-of-range number is refused', setting_save($db, 'tax_rate', '250') !== null);
check('bad select option is refused', setting_save($db, 'receipt_width', '70') !== null);
check('valid value is stored', setting_save($db, 'tax_rate', '16') === null && setting('tax_rate') === '16');
check('switch normalises to 1/0', setting_save($db, 'tax_enabled', 'true') === null && setting('tax_enabled') === '1');
check('blank password keeps the stored one', setting_save($db, 'smtp_pass', 'abc') === null && setting_save($db, 'smtp_pass', '') === null && setting('smtp_pass') === 'abc');

echo "Phone numbers\n";
check('local number gets country code', sms_normalize('0700 123456') === '+256700123456');
check('international number is kept', sms_normalize('+254 711 000000') === '+254711000000');
check('00 prefix becomes +', sms_normalize('00256700123456') === '+256700123456');

echo "Document numbers\n";
require_once "$root/includes/docnum.php";
$next = static function () use ($db): string { return doc_next_number($db, 'receipt', 2099); };
check('a new year starts at 1 and counts up without gaps', [$next(), $next(), $next()] === ['RC-2099-000001', 'RC-2099-000002', 'RC-2099-000003']);

$db->query("DROP DATABASE `$name`");
echo "\n$pass_n passed, $fail_n failed\n";
exit($fail_n === 0 ? 0 : 1);
