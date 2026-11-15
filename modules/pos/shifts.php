<?php
// modules/pos/shifts.php — open a shift, record cash in/out, count the drawer and close
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/db_feature.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/rbac.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_once dirname(__DIR__, 2) . '/includes/audit.php';
require_once dirname(__DIR__, 2) . '/includes/settings.php';
require_once dirname(__DIR__, 2) . '/includes/shifts.php';

require_login();

require_db_tables($GLOBALS['db'], ['shifts', 'shift_cash_movements'], 'Shifts & cash-up');
require_permission('pos.create');

$db = $GLOBALS['db'];
$BASE_URL = $GLOBALS['BASE_URL'] ?? '';
$uid = (int)$_SESSION['user']['id'];
$cur = setting('currency_symbol');
$money = static function ($v) use ($cur): string { return $cur . number_format((float)$v, (int)setting('decimal_places')); };
$flash = $_SESSION['shift_flash'] ?? null;
unset($_SESSION['shift_flash']);
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $shift = shift_current($db, $uid);
    try {
        if ($action === 'open') {
            if ($shift) throw new RuntimeException('You already have an open shift.');
            $float = round((float)($_POST['opening_float'] ?? 0), 2);
            $loc = (int)($_POST['location_id'] ?? 0);
            if ($float < 0) throw new RuntimeException('Opening float cannot be negative.');
            $st = $db->prepare("SELECT id FROM locations WHERE id = ? AND is_active = 1");
            $st->bind_param('i', $loc); $st->execute();
            if (!$st->get_result()->fetch_row()) throw new RuntimeException('Choose a location.');
            $st->close();
            $st = $db->prepare("INSERT INTO shifts (user_id, location_id, opening_float) VALUES (?,?,?)");
            $st->bind_param('iid', $uid, $loc, $float); $st->execute();
            $id = (int)$st->insert_id; $st->close();
            audit_log('shift.open', 'shift', (string)$id, 'Opening float ' . $float);
            $_SESSION['shift_flash'] = 'Shift opened.';
        } elseif ($action === 'cash') {
            if (!$shift) throw new RuntimeException('Open a shift first.');
            $dir = ($_POST['direction'] ?? '') === 'in' ? 'in' : 'out';
            $amt = round((float)($_POST['amount'] ?? 0), 2);
            $reason = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 190);
            if ($amt <= 0) throw new RuntimeException('Enter an amount.');
            if ($reason === '') throw new RuntimeException('Say what the cash was for.');
            $sid = (int)$shift['id'];
            $st = $db->prepare("INSERT INTO shift_cash_movements (shift_id, direction, amount, reason, created_by) VALUES (?,?,?,?,?)");
            $st->bind_param('isdsi', $sid, $dir, $amt, $reason, $uid); $st->execute(); $st->close();
            audit_log('shift.cash_' . $dir, 'shift', (string)$sid, "$amt: $reason");
            $_SESSION['shift_flash'] = 'Cash ' . ($dir === 'in' ? 'added' : 'taken out') . ' recorded.';
        } elseif ($action === 'close') {
            if (!$shift) throw new RuntimeException('There is no open shift.');
            if (!isset($_POST['counted_cash']) || $_POST['counted_cash'] === '') throw new RuntimeException('Count the cash in the drawer and enter it.');
            $counted = round((float)$_POST['counted_cash'], 2);
            if ($counted < 0) throw new RuntimeException('Counted cash cannot be negative.');
            $notes = mb_substr(trim((string)($_POST['notes'] ?? '')), 0, 255);
            $sum = shift_summary($db, $shift);
            $diff = round($counted - $sum['expected_cash'], 2);
            $sid = (int)$shift['id'];
            $st = $db->prepare("UPDATE shifts SET status = 'closed', expected_cash = ?, counted_cash = ?, difference = ?, notes = ?, closed_at = NOW() WHERE id = ? AND status = 'open'");
            $st->bind_param('dddsi', $sum['expected_cash'], $counted, $diff, $notes, $sid); $st->execute(); $st->close();
            audit_log('shift.close', 'shift', (string)$sid, "Expected {$sum['expected_cash']}, counted $counted, difference $diff");
            header('Location: ' . $BASE_URL . '/modules/pos/shifts.php?report=' . $sid);
            exit;
        }
        header('Location: ' . $BASE_URL . '/modules/pos/shifts.php');
        exit;
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

$shift = shift_current($db, $uid);
$summary = $shift ? shift_summary($db, $shift) : null;

// Z-report for a closed shift (own shifts, or any shift for people who can view reports)
$report = null;
if (isset($_GET['report'])) {
    $rid = (int)$_GET['report'];
    $st = $db->prepare("SELECT s.*, u.full_name, u.username, l.name AS location_name FROM shifts s JOIN users u ON u.id = s.user_id LEFT JOIN locations l ON l.id = s.location_id WHERE s.id = ? LIMIT 1");
    $st->bind_param('i', $rid); $st->execute();
    $report = $st->get_result()->fetch_assoc() ?: null;
    $st->close();
    if ($report && (int)$report['user_id'] !== $uid && !user_has_permission('reports.sales.view')) $report = null;
    if ($report) $report['summary'] = shift_summary($db, $report);
}

$seeAll = user_has_permission('reports.sales.view');
$sql = "SELECT s.*, u.username, l.name AS location_name FROM shifts s JOIN users u ON u.id = s.user_id LEFT JOIN locations l ON l.id = s.location_id "
     . ($seeAll ? '' : 'WHERE s.user_id = ' . $uid . ' ') . "ORDER BY s.id DESC LIMIT 30";
$history = $db->query($sql)->fetch_all(MYSQLI_ASSOC);
$locations = $db->query("SELECT id, name FROM locations WHERE is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$page_title = 'Shifts & cash-up';
$page_subtitle = $shift ? 'Shift open since ' . date('D H:i', strtotime((string)$shift['opened_at'])) : 'No shift open';
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
        <?php if ($flash): ?><div class="alert alert-success" role="status"><?= h($flash) ?></div><?php endif; ?>

        <?php if ($report): $s = $report['summary']; ?>
          <div class="card mb-3" id="zreport">
            <div class="card-body">
              <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                <div>
                  <h2 class="h5 mb-0">Shift report #<?= (int)$report['id'] ?></h2>
                  <div class="text-muted small"><?= h($report['full_name'] ?: $report['username']) ?> · <?= h((string)$report['location_name']) ?> · <?= h((string)$report['opened_at']) ?> to <?= h((string)($report['closed_at'] ?? 'still open')) ?></div>
                </div>
                <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print</button>
              </div>
              <div class="row g-3">
                <div class="col-md-6">
                  <table class="table table-sm" data-no-stack>
                    <tbody>
                      <tr><td>Sales (<?= (int)$s['sales_count'] ?>)</td><td class="text-end"><?= h($money($s['sales_total'])) ?></td></tr>
                      <tr><td>of which tax</td><td class="text-end"><?= h($money($s['tax'])) ?></td></tr>
                      <tr><td>Paid in cash</td><td class="text-end"><?= h($money($s['cash'])) ?></td></tr>
                      <tr><td>Paid by mobile money</td><td class="text-end"><?= h($money($s['mobile_money'])) ?></td></tr>
                      <tr><td>Paid by bank</td><td class="text-end"><?= h($money($s['bank'])) ?></td></tr>
                      <tr><td>Sold on credit</td><td class="text-end"><?= h($money($s['on_credit'])) ?></td></tr>
                    </tbody>
                  </table>
                </div>
                <div class="col-md-6">
                  <table class="table table-sm" data-no-stack>
                    <tbody>
                      <tr><td>Opening float</td><td class="text-end"><?= h($money($s['opening_float'])) ?></td></tr>
                      <tr><td>+ Cash sales</td><td class="text-end"><?= h($money($s['cash'])) ?></td></tr>
                      <tr><td>+ Cash added</td><td class="text-end"><?= h($money($s['cash_in'])) ?></td></tr>
                      <tr><td>− Cash taken out</td><td class="text-end"><?= h($money($s['cash_out'])) ?></td></tr>
                      <tr class="fw-semibold"><td>Expected in drawer</td><td class="text-end"><?= h($money($report['expected_cash'] ?? $s['expected_cash'])) ?></td></tr>
                      <?php if ($report['status'] === 'closed'): $d = (float)$report['difference']; ?>
                        <tr><td>Counted</td><td class="text-end"><?= h($money($report['counted_cash'])) ?></td></tr>
                        <tr class="fw-bold <?= abs($d) < 0.005 ? 'text-success' : 'text-danger' ?>"><td><?= $d < 0 ? 'Short' : ($d > 0 ? 'Over' : 'Balanced') ?></td><td class="text-end"><?= h($money(abs($d))) ?></td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                  <?php if (!empty($report['notes'])): ?><div class="small text-muted">Note: <?= h($report['notes']) ?></div><?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!$shift): ?>
          <div class="card mb-3" style="max-width:560px">
            <div class="card-body">
              <h2 class="h5">Open a shift</h2>
              <p class="text-muted small">Count the cash you are starting with. Sales you make are then tracked against this shift.</p>
              <form method="post" class="row g-3">
                <input type="hidden" name="action" value="open">
                <div class="col-sm-6">
                  <label class="form-label" for="opening_float">Opening float</label>
                  <input class="form-control" type="number" min="0" step="0.01" id="opening_float" name="opening_float" value="0" required>
                </div>
                <div class="col-sm-6">
                  <label class="form-label" for="location_id">Location</label>
                  <select class="form-select" id="location_id" name="location_id" required>
                    <?php foreach ($locations as $l): ?><option value="<?= (int)$l['id'] ?>"><?= h($l['name']) ?></option><?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12"><button class="btn btn-primary" type="submit">Open shift</button></div>
              </form>
            </div>
          </div>
        <?php else: ?>
          <div class="row g-3 mb-3">
            <?php foreach ([['Sales', $money($summary['sales_total']), $summary['sales_count'] . ' sale(s)'], ['Cash sales', $money($summary['cash']), 'Mobile money ' . $money($summary['mobile_money'])],
                            ['Cash in / out', $money($summary['cash_in']) . ' / ' . $money($summary['cash_out']), 'Float ' . $money($summary['opening_float'])], ['Expected in drawer', $money($summary['expected_cash']), 'Count against this']] as [$l, $v, $sub]): ?>
              <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-label"><?= h($l) ?></div><div class="stat-value"><?= h($v) ?></div><div class="text-muted small"><?= h($sub) ?></div></div></div>
            <?php endforeach; ?>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-lg-6">
              <div class="card h-100"><div class="card-body">
                <h2 class="h6">Cash in or out of the drawer</h2>
                <form method="post" class="row g-2">
                  <input type="hidden" name="action" value="cash">
                  <div class="col-sm-4"><label class="form-label" for="direction">Type</label><select class="form-select" id="direction" name="direction"><option value="out">Cash out</option><option value="in">Cash in</option></select></div>
                  <div class="col-sm-4"><label class="form-label" for="amount">Amount</label><input class="form-control" type="number" min="0.01" step="0.01" id="amount" name="amount" required></div>
                  <div class="col-sm-4"><label class="form-label" for="reason">Reason</label><input class="form-control" id="reason" name="reason" maxlength="190" required placeholder="e.g. Bought airtime"></div>
                  <div class="col-12"><button class="btn btn-outline-primary" type="submit">Record</button></div>
                </form>
              </div></div>
            </div>
            <div class="col-lg-6">
              <div class="card h-100"><div class="card-body">
                <h2 class="h6">Close the shift</h2>
                <form method="post" class="row g-2">
                  <input type="hidden" name="action" value="close">
                  <div class="col-sm-5"><label class="form-label" for="counted_cash">Cash counted in drawer</label><input class="form-control" type="number" min="0" step="0.01" id="counted_cash" name="counted_cash" required></div>
                  <div class="col-sm-7"><label class="form-label" for="notes">Note (optional)</label><input class="form-control" id="notes" name="notes" maxlength="255"></div>
                  <div class="col-12"><button class="btn btn-primary" type="submit" data-confirm="Close this shift? You will get the shift report." data-confirm-text="Close shift">Close shift</button></div>
                </form>
              </div></div>
            </div>
          </div>
        <?php endif; ?>

        <div class="card">
          <div class="card-header fw-semibold"><?= $seeAll ? 'Recent shifts' : 'My recent shifts' ?></div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead><tr><th>#</th><th>Cashier</th><th>Location</th><th>Opened</th><th>Closed</th><th class="text-end">Expected</th><th class="text-end">Counted</th><th class="text-end">Difference</th><th></th></tr></thead>
              <tbody>
                <?php if (!$history): ?><tr><td colspan="9" class="text-center text-muted py-4">No shifts yet.</td></tr><?php endif; ?>
                <?php foreach ($history as $r): $d = $r['difference']; ?>
                  <tr>
                    <td><?= (int)$r['id'] ?></td><td><?= h($r['username']) ?></td><td><?= h((string)$r['location_name']) ?></td>
                    <td><?= h(substr((string)$r['opened_at'], 0, 16)) ?></td>
                    <td><?= $r['closed_at'] ? h(substr((string)$r['closed_at'], 0, 16)) : '<span class="badge text-bg-success">Open</span>' ?></td>
                    <td class="text-end"><?= $r['expected_cash'] !== null ? h($money($r['expected_cash'])) : '' ?></td>
                    <td class="text-end"><?= $r['counted_cash'] !== null ? h($money($r['counted_cash'])) : '' ?></td>
                    <td class="text-end <?= $d !== null && abs((float)$d) >= 0.005 ? 'text-danger fw-semibold' : '' ?>"><?= $d !== null ? h($money($d)) : '' ?></td>
                    <td class="text-end"><a class="btn btn-outline-secondary btn-sm" href="?report=<?= (int)$r['id'] ?>">Report</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/templates/layout/footer.php'; ?>
