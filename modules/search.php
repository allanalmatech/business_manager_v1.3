<?php
// modules/search.php — one search box for products, customers and sales documents
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();

$db = $GLOBALS['db'];
$BASE_URL = $GLOBALS['BASE_URL'] ?? '';
$q = trim((string)($_GET['q'] ?? ''));
$page_title = 'Search';
$page_subtitle = $q !== '' ? 'Results for "' . $q . '"' : 'Find products, customers and receipts';
$current_user_role = $_SESSION['user']['role'] ?? '';

$groups = [];
if (mb_strlen($q) >= 2) {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';

    $run = static function (mysqli $db, string $sql, string $like, int $n): array {
        $st = $db->prepare($sql);
        if (!$st) return [];
        $args = array_fill(0, $n, $like);
        $st->bind_param(str_repeat('s', $n), ...$args);
        $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();
        return $rows;
    };

    if (user_has_permission('products.view')) {
        $rows = $run($db, "SELECT id, name, sku, retail_price FROM products WHERE name LIKE ? OR sku LIKE ? ORDER BY name LIMIT 20", $like, 2);
        $groups['Products'] = array_map(static function ($r) use ($BASE_URL) {
            return ['title' => $r['name'], 'meta' => 'SKU ' . $r['sku'] . ' · ' . number_format((float)$r['retail_price']), 'href' => $BASE_URL . '/modules/products/products.php?q=' . urlencode($r['sku'])];
        }, $rows);
    }
    if (user_has_permission('contacts.view')) {
        $rows = $run($db, "SELECT id, name, phone FROM customers WHERE name LIKE ? OR phone LIKE ? ORDER BY name LIMIT 20", $like, 2);
        $groups['Customers'] = array_map(static function ($r) use ($BASE_URL) {
            return ['title' => $r['name'], 'meta' => (string)($r['phone'] ?? ''), 'href' => $BASE_URL . '/modules/contacts/customers.php?q=' . urlencode($r['name'])];
        }, $rows);
    }
    if (user_has_permission('pos.view')) {
        $rows = $run($db, "SELECT id, doc_no, doc_type, grand_total, payment_status, created_at FROM sales WHERE doc_no LIKE ? ORDER BY id DESC LIMIT 20", $like, 1);
        $groups['Sales documents'] = array_map(static function ($r) use ($BASE_URL) {
            return ['title' => $r['doc_no'], 'meta' => ucfirst(str_replace('_', ' ', $r['doc_type'])) . ' · ' . number_format((float)$r['grand_total']) . ' · ' . $r['payment_status'] . ' · ' . substr((string)$r['created_at'], 0, 10), 'href' => $BASE_URL . '/modules/pos/sale_view.php?id=' . (int)$r['id']];
        }, $rows);
    }
}
$total = array_sum(array_map('count', $groups));

require_once __DIR__ . '/../templates/layout/header.php';
?>
<div class="app-shell">
  <?php require_once __DIR__ . '/../templates/layout/sidebar.php'; ?>
  <div class="app-content">
    <?php require_once __DIR__ . '/../templates/layout/topbar.php'; ?>
    <main class="page-wrap">
      <div class="container-fluid py-3">
        <form class="mb-3" method="get" role="search">
          <div class="input-group">
            <input class="form-control" name="q" value="<?= h($q) ?>" placeholder="Search products, customers, receipts…" aria-label="Search" autofocus>
            <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i><span class="visually-hidden">Search</span></button>
          </div>
        </form>

        <?php if (mb_strlen($q) < 2): ?>
          <div class="text-muted">Type at least 2 characters.</div>
        <?php elseif ($total === 0): ?>
          <div class="text-muted">Nothing matches “<?= h($q) ?>”.</div>
        <?php else: foreach ($groups as $label => $items): if (!$items) continue; ?>
          <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold"><?= h($label) ?> <span class="text-muted fw-normal">(<?= count($items) ?>)</span></div>
            <div class="list-group list-group-flush">
              <?php foreach ($items as $it): ?>
                <a class="list-group-item list-group-item-action d-flex flex-wrap justify-content-between gap-2" href="<?= h($it['href']) ?>">
                  <span class="fw-semibold"><?= h($it['title']) ?></span>
                  <span class="text-muted small"><?= h($it['meta']) ?></span>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </main>
  </div>
</div>
<?php require_once __DIR__ . '/../templates/layout/footer.php'; ?>
