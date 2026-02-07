<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/helpers.php';

require_permission('dashboard.view');

$db = $GLOBALS['db'];

// Get today's sales total
$todaySalesSql = "SELECT IFNULL(SUM(grand_total), 0) as total FROM sales 
                  WHERE DATE(created_at) = CURDATE() AND status = 'confirmed'";
$todaySalesResult = $db->query($todaySalesSql);
$todaySales = (float)($todaySalesResult->fetch_assoc()['total'] ?? 0);

// Get unpaid pending sales count
$unpaidSql = "SELECT COUNT(*) as count FROM sales 
              WHERE payment_status = 'unpaid' AND status = 'confirmed'";
$unpaidResult = $db->query($unpaidSql);
$unpaidCount = $unpaidResult->fetch_assoc()['count'] ?? 0;

// Get overdue installments count
$overdueSql = "SELECT COUNT(*) as count FROM installments 
               WHERE due_date < CURDATE() AND status = 'pending'";
$overdueResult = $db->query($overdueSql);
$overdueCount = $overdueResult->fetch_assoc()['count'] ?? 0;

// Get low stock alerts count
$lowStockSql = "SELECT COUNT(DISTINCT p.id) as count FROM products p
               LEFT JOIN stock_by_location s ON p.id = s.product_id
               WHERE p.low_level_base > 0 AND (s.qty_base IS NULL OR s.qty_base <= p.low_level_base)";
$lowStockResult = $db->query($lowStockSql);
$lowStockCount = $lowStockResult->fetch_assoc()['count'] ?? 0;

// Get recent alerts for the system alerts section
$alerts = [];
$alertCount = 0;

// Check for overdue installments
if ($overdueCount > 0) {
    $alerts[] = [
        'type' => 'danger',
        'icon' => 'calendar-x',
        'message' => "$overdueCount overdue installment(s) require attention"
    ];
    $alertCount += $overdueCount;
}

// Check for unpaid sales
if ($unpaidCount > 0) {
    $alerts[] = [
        'type' => 'warning',
        'icon' => 'hourglass-split',
        'message' => "$unpaidCount unpaid sale(s) pending payment"
    ];
    $alertCount += $unpaidCount;
}

// Check for low stock items
if ($lowStockCount > 0) {
    $alerts[] = [
        'type' => 'info',
        'icon' => 'box-seam',
        'message' => "$lowStockCount product(s) below stock threshold"
    ];
    $alertCount += $lowStockCount;
}

// Get pending approvals
$pendingApprovalsSql = "SELECT COUNT(*) as count FROM approvals WHERE status = 'pending'";
$pendingApprovalsResult = $db->query($pendingApprovalsSql);
$pendingApprovals = $pendingApprovalsResult->fetch_assoc()['count'] ?? 0;
if ($pendingApprovals > 0) {
    $alerts[] = [
        'type' => 'primary',
        'icon' => 'check-circle',
        'message' => "$pendingApprovals approval(s) pending review"
    ];
    $alertCount += $pendingApprovals;
}
?>

<div class="row g-4">
  <div class="col-12 col-md-6 col-lg-3">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div class="bg-primary bg-opacity-10 p-3 rounded-4">
            <i class="bi bi-cash-stack text-primary fs-4"></i>
          </div>
          <span class="badge bg-success bg-opacity-10 text-success rounded-pill">Live</span>
        </div>
        <div class="text-muted small fw-medium mb-1">Today's Sales</div>
        <div class="fs-3 fw-bold">UGX <?= number_format($todaySales, 0) ?></div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-6 col-lg-3">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div class="bg-warning bg-opacity-10 p-3 rounded-4">
            <i class="bi bi-hourglass-split text-warning fs-4"></i>
          </div>
          <span class="badge bg-<?= $unpaidCount > 0 ? 'danger' : 'success' ?> bg-opacity-10 text-<?= $unpaidCount > 0 ? 'danger' : 'success' ?> rounded-pill"><?= $unpaidCount > 0 ? 'Alert' : 'Clear' ?></span>
        </div>
        <div class="text-muted small fw-medium mb-1">Unpaid Pending</div>
        <div class="fs-3 fw-bold"><?= $unpaidCount ?></div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-6 col-lg-3">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div class="bg-danger bg-opacity-10 p-3 rounded-4">
            <i class="bi bi-calendar-x text-danger fs-4"></i>
          </div>
          <span class="badge bg-<?= $overdueCount > 0 ? 'danger' : 'success' ?> bg-opacity-10 text-<?= $overdueCount > 0 ? 'danger' : 'success' ?> rounded-pill"><?= $overdueCount > 0 ? 'Urgent' : 'OK' ?></span>
        </div>
        <div class="text-muted small fw-medium mb-1">Overdue Installments</div>
        <div class="fs-3 fw-bold"><?= $overdueCount ?></div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-6 col-lg-3">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div class="bg-info bg-opacity-10 p-3 rounded-4">
            <i class="bi bi-box-seam text-info fs-4"></i>
          </div>
          <span class="badge bg-<?= $lowStockCount > 0 ? 'warning' : 'success' ?> bg-opacity-10 text-<?= $lowStockCount > 0 ? 'warning' : 'success' ?> rounded-pill"><?= $lowStockCount > 0 ? 'Check' : 'Good' ?></span>
        </div>
        <div class="text-muted small fw-medium mb-1">Low Stock Alerts</div>
        <div class="fs-3 fw-bold"><?= $lowStockCount ?></div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-8">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between mb-4">
          <h5 class="fw-bold mb-0">Quick Actions</h5>
          <i class="bi bi-lightning-charge text-primary"></i>
        </div>
        <div class="row g-3">
          <div class="col-6 col-md-3">
            <a class="d-flex flex-column align-items-center gap-2 p-3 rounded-4 border text-decoration-none hover-bg-light" href="<?= rtrim($GLOBALS['BASE_URL'], '/') ?>/modules/pos/pos.php">
              <i class="bi bi-printer fs-4 text-primary"></i>
              <span class="small fw-bold text-dark">Open POS</span>
            </a>
          </div>
          <div class="col-6 col-md-3">
            <a class="d-flex flex-column align-items-center gap-2 p-3 rounded-4 border text-decoration-none hover-bg-light" href="<?= rtrim($GLOBALS['BASE_URL'], '/') ?>/modules/admin/settings.php">
              <i class="bi bi-gear fs-4 text-primary"></i>
              <span class="small fw-bold text-dark">Settings</span>
            </a>
          </div>
          <div class="col-6 col-md-3">
            <a class="d-flex flex-column align-items-center gap-2 p-3 rounded-4 border text-decoration-none hover-bg-light" href="<?= rtrim($GLOBALS['BASE_URL'], '/') ?>/modules/admin/users.php">
              <i class="bi bi-people fs-4 text-primary"></i>
              <span class="small fw-bold text-dark">Users</span>
            </a>
          </div>
          <div class="col-6 col-md-3">
            <a class="d-flex flex-column align-items-center gap-2 p-3 rounded-4 border text-decoration-none hover-bg-light" href="<?= rtrim($GLOBALS['BASE_URL'], '/') ?>/modules/procurement/shopping_list.php">
              <i class="bi bi-list-check fs-4 text-primary"></i>
              <span class="small fw-bold text-dark">Shopping</span>
            </a>
          </div>
        </div>
        
        <div class="row g-3 mt-2">
          <div class="col-6 col-md-3">
            <a class="d-flex flex-column align-items-center gap-2 p-3 rounded-4 border text-decoration-none hover-bg-light" href="<?= rtrim($GLOBALS['BASE_URL'], '/') ?>/modules/reports/b2b_report.php">
              <i class="bi bi-briefcase fs-4 text-primary"></i>
              <span class="small fw-bold text-dark">B2B Report</span>
            </a>
          </div>
          <div class="col-6 col-md-3">
            <a class="d-flex flex-column align-items-center gap-2 p-3 rounded-4 border text-decoration-none hover-bg-light" href="<?= rtrim($GLOBALS['BASE_URL'], '/') ?>/modules/reports/sales.php">
              <i class="bi bi-graph-up-arrow fs-4 text-primary"></i>
              <span class="small fw-bold text-dark">Sales Report</span>
            </a>
          </div>
          <div class="col-6 col-md-3">
            <a class="d-flex flex-column align-items-center gap-2 p-3 rounded-4 border text-decoration-none hover-bg-light" href="<?= rtrim($GLOBALS['BASE_URL'], '/') ?>/modules/brands/index.php">
              <i class="bi bi-tags fs-4 text-primary"></i>
              <span class="small fw-bold text-dark">Brands</span>
            </a>
          </div>
          <div class="col-6 col-md-3">
            <a class="d-flex flex-column align-items-center gap-2 p-3 rounded-4 border text-decoration-none hover-bg-light" href="<?= rtrim($GLOBALS['BASE_URL'], '/') ?>/modules/products/stock_levels.php">
              <i class="bi bi-boxes fs-4 text-primary"></i>
              <span class="small fw-bold text-dark">Stock Levels</span>
            </a>
          </div>
          <div class="col-6 col-md-3">
            <a class="d-flex flex-column align-items-center gap-2 p-3 rounded-4 border text-decoration-none hover-bg-light" href="<?= rtrim($GLOBALS['BASE_URL'], '/') ?>/modules/contacts/customers.php">
              <i class="bi bi-person-check fs-4 text-primary"></i>
              <span class="small fw-bold text-dark">Customers</span>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-4">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between mb-4">
          <h5 class="fw-bold mb-0">System Alerts</h5>
          <span class="badge bg-<?= $alertCount > 0 ? 'danger' : 'success' ?> text-<?= $alertCount > 0 ? 'white' : 'white' ?> rounded-pill"><?= $alertCount ?> New</span>
        </div>
        <?php if (empty($alerts)): ?>
          <div class="text-center py-4">
            <div class="bg-light rounded-circle d-inline-flex p-3 mb-3">
              <i class="bi bi-check-circle fs-3 text-success"></i>
            </div>
            <p class="text-muted small mb-0">System is running smoothly. No urgent alerts at the moment.</p>
          </div>
        <?php else: ?>
          <div class="alert-list">
            <?php foreach ($alerts as $alert): ?>
              <div class="d-flex align-items-start gap-3 mb-3 p-3 bg-<?= $alert['type'] ?> bg-opacity-10 rounded-3">
                <div class="bg-<?= $alert['type'] ?> bg-opacity-20 p-2 rounded-circle">
                  <i class="bi bi-<?= $alert['icon'] ?> text-<?= $alert['type'] ?> fs-5"></i>
                </div>
                <div class="flex-grow-1">
                  <p class="mb-0 small fw-medium text-<?= $alert['type'] ?>"><?= $alert['message'] ?></p>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<style>
.hover-bg-light:hover {
  background-color: var(--light-color);
  border-color: var(--primary-color) !important;
  transition: all 0.2s ease;
}
</style>
