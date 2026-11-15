<?php
// dashboards/accountant.php
$db = $GLOBALS['db'];
$B  = rtrim((string)($GLOBALS['BASE_URL'] ?? ''), '/');
$cur = function_exists('get_currency_symbol') ? get_currency_symbol($db) : '';

$one = static function (mysqli $db, string $sql): float {
    $r = $db->query($sql);
    $row = $r ? $r->fetch_row() : null;
    return (float)($row[0] ?? 0);
};
$has = static function (mysqli $db, string $t): bool {
    $r = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($t) . "'");
    return (bool)($r && $r->num_rows > 0);
};

$salesToday   = $one($db, "SELECT IFNULL(SUM(grand_total),0) FROM sales WHERE status <> 'voided' AND DATE(created_at) = CURDATE()");
$salesMonth   = $one($db, "SELECT IFNULL(SUM(grand_total),0) FROM sales WHERE status <> 'voided' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$receivables  = $one($db, "SELECT IFNULL(SUM(balance),0) FROM sales WHERE status <> 'voided' AND payment_status IN ('unpaid','partial')");
$unpaidCount  = (int)$one($db, "SELECT COUNT(*) FROM sales WHERE status <> 'voided' AND payment_status IN ('unpaid','partial')");
$overdue      = $has($db, 'installments') ? (int)$one($db, "SELECT COUNT(*) FROM installments WHERE status = 'overdue'") : 0;

$cards = [
    ['Sales today', $cur . number_format($salesToday), 'bi-cash-coin', "$B/modules/reports/sales.php"],
    ['Sales this month', $cur . number_format($salesMonth), 'bi-graph-up', "$B/modules/reports/sales.php"],
    ['Owed by customers', $cur . number_format($receivables), 'bi-hourglass-split', "$B/modules/pos/unpaid.php"],
    ['Unpaid documents', (string)$unpaidCount, 'bi-receipt', "$B/modules/pos/unpaid.php"],
    ['Overdue installments', (string)$overdue, 'bi-calendar-x', "$B/modules/installments/installments.php"],
];
$links = [
    ['Reconciliation', "$B/modules/finance/reconciliation.php"],
    ['Expenses', "$B/modules/finance/expenses.php"],
    ['Profit report', "$B/modules/reports/profit.php"],
    ['Sales report', "$B/modules/reports/sales.php"],
    ['Send reminders', "$B/modules/messaging/send.php"],
];
?>
<div class="row g-3">
  <?php foreach ($cards as [$label, $value, $icon, $href]): ?>
    <div class="col-12 col-sm-6 col-xl-4">
      <a class="card shadow-sm rounded-4 text-decoration-none h-100" href="<?= htmlspecialchars($href) ?>">
        <div class="card-body d-flex align-items-center gap-3">
          <i class="bi <?= $icon ?> fs-3 text-primary" aria-hidden="true"></i>
          <div>
            <div class="text-muted small"><?= htmlspecialchars($label) ?></div>
            <div class="fs-4 fw-bold text-body"><?= htmlspecialchars($value) ?></div>
          </div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>

  <div class="col-12">
    <div class="card shadow-sm rounded-4">
      <div class="card-body">
        <div class="fw-semibold mb-2">Quick links</div>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($links as [$label, $href]): ?>
            <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($href) ?>"><?= htmlspecialchars($label) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>
