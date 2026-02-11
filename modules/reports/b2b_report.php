<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_permission('reports.b2b.view');

if (function_exists('audit_log')) {
    audit_log('reports.b2b.view', 'reports', null, "B2B report accessed");
}

$db = $GLOBALS['db'];
$BASE_URL = $GLOBALS['BASE_URL'] ?? '';

$page_title = "B2B Items Report";
$page_subtitle = "External items sold (cost vs sell) + convert profitable items to shopping list";

if (!$db instanceof mysqli) {
  http_response_code(500);
  die("DB not available");
}

$q = trim((string)($_GET['q'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$doc = trim((string)($_GET['doc'] ?? '')); // receipt/invoice/delivery (optional)
function formatB2BMoney(float $amount, string $currency): string {
    if ($currency === 'UGX') {
        return number_format($amount, 0);
    } else {
        return number_format($amount, 2);
    }
}

$limit = 500;

$where = "1=1";
$params = [];
$types = "";

// search
if ($q !== '') {
  $where .= " AND (bi.name LIKE ? OR bi.sku LIKE ?)";
  $like = "%{$q}%";
  $types .= "ss";
  $params[] = $like;
  $params[] = $like;
}

// date range uses sales.created_at
if ($from !== '') {
  $where .= " AND s.created_at >= ?";
  $types .= "s";
  $params[] = $from . " 00:00:00";
}
if ($to !== '') {
  $where .= " AND s.created_at <= ?";
  $types .= "s";
  $params[] = $to . " 23:59:59";
}

// doc type filter (if you use values like receipt/invoice/delivery_note)
if ($doc !== '') {
  $where .= " AND s.doc_type = ?";
  $types .= "s";
  $params[] = $doc;
}

$sql = "SELECT
  bi.id AS b2b_id,
  bi.sale_id,
  bi.name,
  bi.sku,
  bi.qty,
  bi.unit_type,
  bi.unit_name,
  bi.cost_price,
  bi.sell_price,
  bi.currency,
  bi.exchange_rate,
  bi.supplier_name,
  bi.supplier_id,
  bi.note,
  bi.created_at AS b2b_created_at,

  s.doc_type,
  s.doc_no,
  s.customer_id,
  s.selling_location_id,
  s.created_at AS sale_created_at,

  -- totals
  (bi.qty * bi.cost_price) AS cost_total,
  (bi.qty * bi.sell_price) AS sell_total,
  ((bi.qty * bi.sell_price) - (bi.qty * bi.cost_price)) AS profit_total,

  -- normalize totals to UGX for reporting
  CASE WHEN bi.currency='UGX' THEN (bi.qty * bi.cost_price)
       ELSE (bi.qty * bi.cost_price * bi.exchange_rate)
  END AS cost_total_ugx,

  CASE WHEN bi.currency='UGX' THEN (bi.qty * bi.sell_price)
       ELSE (bi.qty * bi.sell_price * bi.exchange_rate)
  END AS sell_total_ugx,

  CASE WHEN bi.currency='UGX' THEN ((bi.qty * bi.sell_price) - (bi.qty * bi.cost_price))
       ELSE (((bi.qty * bi.sell_price) - (bi.qty * bi.cost_price)) * bi.exchange_rate)
  END AS profit_total_ugx,

  -- is already added to shopping list? (disabled - table doesn't exist)
  0 AS in_shopping_list
FROM b2b_sales_items bi
JOIN sales s ON s.id = bi.sale_id
WHERE {$where}
ORDER BY bi.id DESC
LIMIT {$limit}";

$stmt = $db->prepare($sql);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;
$stmt->close();

require_once __DIR__ . '/../../templates/layout/header.php';
?>
<div class="app-shell">
  <?php require_once __DIR__ . '/../../templates/layout/sidebar.php'; ?>

  <div class="app-content">
    <?php require_once __DIR__ . '/../../templates/layout/topbar.php'; ?>

    <main class="page-wrap">

      <div class="d-flex flex-wrap gap-2 align-items-end justify-content-between mb-3">
        <form class="d-flex flex-wrap gap-2 align-items-end" method="get">
          <div>
            <label class="form-label small mb-1">Search</label>
            <input class="form-control form-control-sm" name="q" value="<?= h($q) ?>" placeholder="name or SKU">
          </div>

          <div>
            <label class="form-label small mb-1">From</label>
            <input class="form-control form-control-sm" type="date" name="from" value="<?= h($from) ?>">
          </div>

          <div>
            <label class="form-label small mb-1">To</label>
            <input class="form-control form-control-sm" type="date" name="to" value="<?= h($to) ?>">
          </div>

          <div>
            <label class="form-label small mb-1">Doc Type</label>
            <select class="form-select form-select-sm" name="doc" style="min-width:160px">
              <option value="">All</option>
              <option value="receipt" <?= $doc==='receipt'?'selected':'' ?>>Receipt</option>
              <option value="invoice" <?= $doc==='invoice'?'selected':'' ?>>Invoice</option>
              <option value="delivery_note" <?= $doc==='delivery_note'?'selected':'' ?>>Delivery Note</option>
            </select>
          </div>

          <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary">Filter</button>
            <a class="btn btn-sm btn-outline-secondary" href="<?= h($BASE_URL) ?>/modules/reports/b2b_report.php">Reset</a>
          </div>
        </form>

        <div class="small text-muted">
          Showing: <b><?= count($rows) ?></b> (max <?= (int)$limit ?>)
        </div>
      </div>

      <div class="card shadow-sm rounded-4">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th>#</th>
                  <th>When</th>
                  <th>Document</th>
                  <th>Item</th>
                  <th class="text-end">Qty</th>
                  <th class="text-end">Cost</th>
                  <th class="text-end">Sell</th>
                  <th class="text-end">Profit</th>
                  <th class="text-end">Profit (UGX)</th>
                  <th>Supplier</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$rows): ?>
                  <tr><td colspan="11" class="text-muted">No B2B items found.</td></tr>
                <?php endif; ?>

                <?php foreach ($rows as $r): ?>
                  <?php
                    $unitLabel = $r['unit_type'];
                    if ($r['unit_type'] === 'units' && $r['unit_name']) {
                      $unitLabel .= " (" . h((string)$r['unit_name']) . ")";
                    }

                    $profitUgx = (float)$r['profit_total_ugx'];
                    $profitBadge = $profitUgx >= 0
                      ? '<span class="badge bg-success">+' . number_format($profitUgx, 0) . '</span>'
                      : '<span class="badge bg-danger">' . number_format($profitUgx, 0) . '</span>';

                    $docLabel = h((string)$r['doc_type']) . ' #' . h((string)$r['doc_no']);

                    $supplier = trim((string)($r['supplier_text'] ?? ''));
                    if ($supplier === '') $supplier = '—';

                    $already = ((int)$r['in_shopping_list'] === 1);
                  ?>
                  <tr>
                    <td><?= (int)$r['b2b_id'] ?></td>
                    <td class="small"><?= h((string)$r['sale_created_at']) ?></td>
                    <td class="small"><?= $docLabel ?></td>
                    <td>
                      <div class="fw-semibold"><?= h((string)$r['name']) ?></div>
                      <div class="small text-muted">
                        <?= $r['sku'] ? h((string)$r['sku']) . ' • ' : '' ?>
                        <?= h($unitLabel) ?>
                      </div>
                    </td>
                    <td class="text-end"><?= h((string)$r['qty']) ?></td>

                    <td class="text-end small">
                      <?= formatB2BMoney((float)$r['cost_total'], (string)$r['currency']) ?> <?= h((string)$r['currency']) ?>
                    </td>
                    <td class="text-end small">
                      <?= formatB2BMoney((float)$r['sell_total'], (string)$r['currency']) ?> <?= h((string)$r['currency']) ?>
                    </td>
                    <td class="text-end small">
                      <?= formatB2BMoney((float)$r['profit_total'], (string)$r['currency']) ?> <?= h((string)$r['currency']) ?>
                    </td>

                    <td class="text-end"><?= $profitBadge ?></td>
                    <td class="small"><?= h($supplier) ?></td>

                    <td class="text-end">
                      <?php if ($already): ?>
                        <span class="badge bg-secondary">Added</span>
                      <?php else: ?>
                        <?php if (function_exists('user_has_permission') && user_has_permission('shopping_list.create')): ?>
                          <button class="btn btn-sm btn-outline-primary"
                                  data-add-shopping="<?= (int)$r['b2b_id'] ?>">
                            Add to Shopping List
                          </button>
                        <?php else: ?>
                          <span class="badge bg-light text-muted">No Permission</span>
                        <?php endif; ?>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="small text-muted mt-2">
            This report tracks external items sold through POS. Use "Add to Shopping List" to mark profitable items as wanted stock.
          </div>

        </div>
      </div>

    </main>
  </div>
</div>

<script>
const BASE_URL = <?= json_encode($BASE_URL) ?>;

document.addEventListener('click', async (e)=>{
  const btn = e.target.closest('[data-add-shopping]');
  if(!btn) return;

  const id = btn.getAttribute('data-add-shopping');
  btn.disabled = true;
  btn.textContent = "Adding…";

  try{
    const res = await fetch(BASE_URL + "/api/reports/b2b.php?action=add_to_shopping_list", {
      method: "POST",
      headers: {"Content-Type":"application/json"},
      body: JSON.stringify({ b2b_id: Number(id) })
    });
    const json = await res.json();
    if(!json.ok){
      alert(json.error || "Failed");
      btn.disabled = false;
      btn.textContent = "Add to Shopping List";
      return;
    }
    btn.outerHTML = '<span class="badge bg-secondary">Added</span>';
  }catch(err){
    alert("Network error");
    btn.disabled = false;
    btn.textContent = "Add to Shopping List";
  }
});
</script>

<?php require_once __DIR__ . '/../../templates/layout/footer.php'; ?>
