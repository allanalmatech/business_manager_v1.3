<?php
// dashboards/cashier.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();

$db = $GLOBALS['db'] ?? null;
$currentUser = $_SESSION['user'] ?? null;

if (!$currentUser || !$db instanceof mysqli) {
    header('Location: ' . $GLOBALS['BASE_URL'] . '/login.php');
    exit;
}

$page_title = "Cashier Dashboard";
$page_subtitle = "Sales & Operations";

// Get today's sales for current user
$today = date('Y-m-d');
$userId = (int)$currentUser['id'];

// Today's sales summary
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_transactions,
        COALESCE(SUM(grand_total), 0) as total_sales,
        COALESCE(COUNT(DISTINCT customer_id), 0) as unique_customers
    FROM sales 
    WHERE DATE(created_at) = ? AND created_by = ? AND status = 'confirmed'
");
$stmt->bind_param("si", $today, $userId);
$stmt->execute();
$todayStats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Recent transactions
$stmt = $db->prepare("
    SELECT s.id, s.grand_total, s.payment_status, s.created_at,
           c.name as customer_name, c.phone as customer_phone
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE s.created_by = ? 
    ORDER BY s.created_at DESC 
    LIMIT 10
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$recentTransactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// This week's sales
$weekStart = date('Y-m-d', strtotime('monday this week'));
$stmt = $db->prepare("
    SELECT 
        COALESCE(SUM(grand_total), 0) as week_sales,
        COUNT(*) as week_transactions
    FROM sales 
    WHERE DATE(created_at) >= ? AND created_by = ? AND status = 'confirmed'
");
$stmt->bind_param("si", $weekStart, $userId);
$stmt->execute();
$weekStats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Quick stats - low stock items
$stmt = $db->prepare("
    SELECT COUNT(*) as low_stock_count
    FROM products 
    WHERE qty_base <= low_level_base AND is_active = 1
");
$stmt->execute();
$lowStockCount = $stmt->get_result()->fetch_assoc()['low_stock_count'];
$stmt->close();

require_once __DIR__ . '/../templates/layout/header.php';
?>
<div class="app-shell">
  <?php require_once __DIR__ . '/../templates/layout/sidebar.php'; ?>

  <div class="app-content">
    <?php require_once __DIR__ . '/../templates/layout/topbar.php'; ?>

    <main class="page-wrap">
      <div class="container-fluid py-4">
        <!-- Welcome Section -->
        <div class="row mb-4">
          <div class="col-12">
            <div class="card bg-primary text-white">
              <div class="card-body">
                <div class="row align-items-center">
                  <div class="col-md-8">
                    <h4 class="mb-1">Welcome back, <?= h($currentUser['full_name'] ?? $currentUser['username'] ?? 'User') ?>! 👋</h4>
                    <p class="mb-0 opacity-75">Ready to serve customers today?</p>
                  </div>
                  <div class="col-md-4 text-md-end">
                    <div class="fw-semibold opacity-75">Current Time</div>
                    <div class="fs-4" id="currentTime"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="row g-3 mb-4">
          <div class="col-12 col-lg-6">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                  <div class="flex-grow-1">
                    <h6 class="card-title mb-0">Quick Actions</h6>
                  </div>
                  <i class="bi bi-lightning-charge text-primary"></i>
                </div>
                <div class="d-grid gap-2">
                  <a href="<?= h($GLOBALS['BASE_URL']) ?>/modules/pos/pos.php" class="btn btn-primary btn-lg">
                    <i class="bi bi-cash-stack me-2"></i>Start New Sale
                  </a>
                  <div class="row g-2">
                    <div class="col-6">
                      <a href="<?= h($GLOBALS['BASE_URL']) ?>/modules/contacts/customers.php" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-people me-1"></i>Customers
                      </a>
                    </div>
                    <div class="col-6">
                      <a href="<?= h($GLOBALS['BASE_URL']) ?>/modules/products/products.php" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-box me-1"></i>Products
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12 col-lg-6">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                  <div class="flex-grow-1">
                    <h6 class="card-title mb-0">Today's Performance</h6>
                  </div>
                  <i class="bi bi-graph-up text-success"></i>
                </div>
                <div class="row text-center">
                  <div class="col-4">
                    <div class="text-muted small">Sales</div>
                    <div class="fs-4 fw-bold text-primary"><?= number_format($todayStats['total_transactions']) ?></div>
                  </div>
                  <div class="col-4">
                    <div class="text-muted small">Revenue</div>
                    <div class="fs-4 fw-bold text-success">UGX <?= number_format((float)$todayStats['total_sales']) ?></div>
                  </div>
                  <div class="col-4">
                    <div class="text-muted small">Customers</div>
                    <div class="fs-4 fw-bold text-info"><?= number_format($todayStats['unique_customers']) ?></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
          <div class="col-12 col-md-4">
            <div class="card shadow-sm">
              <div class="card-body">
                <div class="d-flex align-items-center">
                  <div class="flex-grow-1">
                    <div class="text-muted small">This Week's Sales</div>
                    <div class="fs-5 fw-bold">UGX <?= number_format((float)$weekStats['week_sales']) ?></div>
                  </div>
                  <div class="text-success">
                    <i class="bi bi-calendar-week" style="font-size: 24px;"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <div class="card shadow-sm">
              <div class="card-body">
                <div class="d-flex align-items-center">
                  <div class="flex-grow-1">
                    <div class="text-muted small">Week Transactions</div>
                    <div class="fs-5 fw-bold"><?= number_format($weekStats['week_transactions']) ?></div>
                  </div>
                  <div class="text-info">
                    <i class="bi bi-receipt" style="font-size: 24px;"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <div class="card shadow-sm">
              <div class="card-body">
                <div class="d-flex align-items-center">
                  <div class="flex-grow-1">
                    <div class="text-muted small">Low Stock Items</div>
                    <div class="fs-5 fw-bold <?= $lowStockCount > 0 ? 'text-warning' : 'text-success' ?>"><?= number_format($lowStockCount) ?></div>
                  </div>
                  <div class="<?= $lowStockCount > 0 ? 'text-warning' : 'text-success' ?>">
                    <i class="bi bi-exclamation-triangle" style="font-size: 24px;"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Transactions -->
        <div class="row">
          <div class="col-12">
            <div class="card shadow-sm">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <h6 class="card-title mb-0">Recent Transactions</h6>
                  <a href="<?= h($GLOBALS['BASE_URL']) ?>/modules/sales/" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-right me-1"></i>View All
                  </a>
                </div>
                
                <?php if (empty($recentTransactions)): ?>
                  <div class="text-center text-muted py-4">
                    <i class="bi bi-receipt" style="font-size: 48px;"></i>
                    <p class="mt-2">No transactions yet today</p>
                    <a href="<?= h($GLOBALS['BASE_URL']) ?>/modules/pos/pos.php" class="btn btn-primary">
                      <i class="bi bi-plus-lg me-1"></i>Start Your First Sale
                    </a>
                  </div>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-sm table-hover">
                      <thead class="table-light">
                        <tr>
                          <th>ID</th>
                          <th>Customer</th>
                          <th>Amount</th>
                          <th>Payment</th>
                          <th>Time</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($recentTransactions as $transaction): ?>
                          <tr>
                            <td><?= str_pad((string)$transaction['id'], 6, '0', STR_PAD_LEFT) ?></td>
                            <td>
                              <?= h($transaction['customer_name'] ?: 'Walk-in Customer') ?>
                              <?php if ($transaction['customer_phone']): ?>
                                <br><small class="text-muted"><?= h($transaction['customer_phone']) ?></small>
                              <?php endif; ?>
                            </td>
                            <td class="fw-semibold">UGX <?= number_format((float)$transaction['grand_total']) ?></td>
                            <td>
                              <span class="badge bg-<?= $transaction['payment_status'] === 'paid' ? 'success' : ($transaction['payment_status'] === 'partial' ? 'warning' : 'secondary') ?>">
                                <?= ucfirst(h($transaction['payment_status'])) ?>
                              </span>
                            </td>
                            <td>
                              <small><?= date('h:i A', strtotime($transaction['created_at'])) ?></small>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<?php require_once __DIR__ . '/../templates/layout/footer.php'; ?>

<script>
// Update current time
function updateTime() {
  const now = new Date();
  document.getElementById('currentTime').textContent = now.toLocaleTimeString();
}

updateTime();
setInterval(updateTime, 1000);

// Auto-refresh dashboard every 5 minutes
setTimeout(() => {
  location.reload();
}, 300000);
</script>
