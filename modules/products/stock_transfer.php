<?php
// modules/products/stock_transfer.php — move stock from one location to another
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/rbac.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_once dirname(__DIR__, 2) . '/includes/audit.php';
require_once dirname(__DIR__, 2) . '/includes/stock.php';

require_login();
require_permission('products.view');

$db = $GLOBALS['db'];
$BASE_URL = $GLOBALS['BASE_URL'] ?? '';
$uid = (int)$_SESSION['user']['id'];
$canMove = user_has_permission('products.update') || user_has_permission('stock.adjust') || is_super_admin();
$error = ''; $ok = $_SESSION['transfer_flash'] ?? ''; unset($_SESSION['transfer_flash']);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $pid = (int)($_POST['product_id'] ?? 0);
    $from = (int)($_POST['from_location_id'] ?? 0);
    $to = (int)($_POST['to_location_id'] ?? 0);
    $qty = round((float)($_POST['qty'] ?? 0), 3);
    $note = trim((string)($_POST['note'] ?? ''));
    try {
        if (!$canMove) throw new StockError('You do not have permission to move stock.');
        if ($pid <= 0) throw new StockError('Choose a product.');
        if ($from <= 0 || $to <= 0 || $from === $to) throw new StockError('Choose two different locations.');
        if ($qty <= 0) throw new StockError('Enter a quantity above zero.');
        $db->begin_transaction();
        $ref = 'TR-' . date('ymdHis') . '-' . $uid;
        // Lock rows in a fixed order so two opposite transfers cannot deadlock
        $steps = [[$from, -$qty], [$to, $qty]];
        usort($steps, static function ($a, $b) { return $a[0] <=> $b[0]; });
        foreach ($steps as [$loc, $delta]) {
            stock_move($db, $pid, $loc, $delta, 'transfer', 'transfer', $ref, $note !== '' ? $note : 'Transfer', $uid, $from, $to);
        }
        $db->commit();
        audit_log('stock.transfer', 'product', (string)$pid, "Moved $qty from location $from to $to ($ref)");
        $_SESSION['transfer_flash'] = 'Stock moved.';
        header('Location: ' . $BASE_URL . '/modules/products/stock_transfer.php');
        exit;
    } catch (StockError $e) {
        $db->rollback();
        $error = $e->getMessage();
    } catch (Throwable $e) {
        $db->rollback();
        error_log('[stock.transfer] ' . $e->getMessage());
        $error = 'The transfer could not be saved. Nothing was changed.';
    }
}

$locations = $db->query("SELECT id, name FROM locations WHERE is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$products = $db->query("SELECT id, name, sku FROM products WHERE is_active = 1 ORDER BY name LIMIT 2000")->fetch_all(MYSQLI_ASSOC);
$recent = $db->query("SELECT m.created_at, m.qty_change, m.reference_id, p.name AS product, lf.name AS from_name, lt.name AS to_name, u.username
                      FROM stock_movements m JOIN products p ON p.id = m.product_id
                      LEFT JOIN locations lf ON lf.id = m.from_location_id LEFT JOIN locations lt ON lt.id = m.to_location_id
                      LEFT JOIN users u ON u.id = m.created_by
                      WHERE m.movement_type = 'transfer' AND m.qty_change > 0 ORDER BY m.id DESC LIMIT 25")->fetch_all(MYSQLI_ASSOC);

$page_title = 'Stock transfers';
$page_subtitle = 'Move stock between locations';
$current_user_role = $_SESSION['user']['role'] ?? '';
require_once dirname(__DIR__, 2) . '/templates/layout/header.php';
?>
<div class="app-shell">
  <?php require_once dirname(__DIR__, 2) . '/templates/layout/sidebar.php'; ?>
  <div class="app-content">
    <?php require_once dirname(__DIR__, 2) . '/templates/layout/topbar.php'; ?>
    <main class="page-wrap">
      <div class="container-fluid">
        <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= h($error) ?></div><?php endif; ?>
        <?php if ($ok): ?><div class="alert alert-success" role="status"><?= h($ok) ?></div><?php endif; ?>

        <?php if (count($locations) < 2): ?>
          <div class="bm-empty mb-3"><i class="bi bi-shop" aria-hidden="true"></i><div class="fw-semibold">You need at least two locations</div><div>Add another store under Inventory → Stores, then come back.</div></div>
        <?php elseif ($canMove): ?>
          <div class="card mb-3"><div class="card-body">
            <form method="post" class="row g-3">
              <div class="col-md-4"><label class="form-label" for="product_id">Product</label>
                <select class="form-select" id="product_id" name="product_id" required><option value="">Choose…</option>
                  <?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>"><?= h($p['name']) ?> (<?= h($p['sku']) ?>)</option><?php endforeach; ?></select></div>
              <div class="col-6 col-md-2"><label class="form-label" for="from_location_id">From</label>
                <select class="form-select" id="from_location_id" name="from_location_id" required><?php foreach ($locations as $l): ?><option value="<?= (int)$l['id'] ?>"><?= h($l['name']) ?></option><?php endforeach; ?></select></div>
              <div class="col-6 col-md-2"><label class="form-label" for="to_location_id">To</label>
                <select class="form-select" id="to_location_id" name="to_location_id" required><?php foreach ($locations as $i => $l): ?><option value="<?= (int)$l['id'] ?>" <?= $i === 1 ? 'selected' : '' ?>><?= h($l['name']) ?></option><?php endforeach; ?></select></div>
              <div class="col-6 col-md-2"><label class="form-label" for="qty">Quantity</label><input class="form-control" type="number" min="0.001" step="0.001" id="qty" name="qty" required></div>
              <div class="col-6 col-md-2"><label class="form-label" for="note">Note</label><input class="form-control" id="note" name="note" maxlength="200"></div>
              <div class="col-12"><button class="btn btn-primary" type="submit"><i class="bi bi-arrow-left-right me-1" aria-hidden="true"></i>Move stock</button></div>
            </form>
          </div></div>
        <?php endif; ?>

        <div class="card">
          <div class="card-header fw-semibold">Recent transfers</div>
          <div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>When</th><th>Product</th><th>From</th><th>To</th><th class="text-end">Quantity</th><th>By</th></tr></thead>
            <tbody>
              <?php if (!$recent): ?><tr><td colspan="6" class="text-center text-muted py-4">No transfers yet.</td></tr><?php endif; ?>
              <?php foreach ($recent as $r): ?>
                <tr><td><?= h(substr((string)$r['created_at'], 0, 16)) ?></td><td><?= h($r['product']) ?></td><td><?= h((string)$r['from_name']) ?></td><td><?= h((string)$r['to_name']) ?></td>
                    <td class="text-end"><?= h(rtrim(rtrim(number_format((float)$r['qty_change'], 3, '.', ''), '0'), '.')) ?></td><td><?= h((string)$r['username']) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        </div>
      </div>
    </main>
  </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/templates/layout/footer.php'; ?>
