<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/audit.php';

require_permission('installments.view');

$db = $GLOBALS['db'];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: " . $GLOBALS['BASE_URL'] . "/modules/installments/installments.php");
    exit;
}

// Check if installments table exists
$hasInstallments = false;
$res = $db->query("SHOW TABLES LIKE 'installments'");
if ($res && $res->num_rows > 0) {
    $hasInstallments = true;
}

if (!$hasInstallments) {
    die("Installments table not found");
}

// Check if installment_payments table exists
$hasPayments = false;
$res = $db->query("SHOW TABLES LIKE 'installment_payments'");
if ($res && $res->num_rows > 0) {
    $hasPayments = true;
}

// Get installment details
$installment = null;
$st = $db->prepare("SELECT i.*, c.name AS contact_name, c.phone FROM installments i 
    LEFT JOIN customers c ON c.id = i.contact_id 
    WHERE i.id = ? LIMIT 1");
if ($st) {
    $st->bind_param('i', $id);
    $st->execute();
    $installment = $st->get_result()->fetch_assoc();
    $st->close();
}

if (!$installment) {
    echo '<div class="alert alert-danger">Installment not found</div>';
    exit;
}

$page_title = 'Installment Details';
$page_subtitle = 'ID #' . $installment['id'];

// Get payment history if payments table exists
$payments = [];
if ($hasPayments) {
    $res = $db->query("SELECT * FROM installment_payments WHERE installment_id = $id ORDER BY payment_date DESC");
    if ($res) {
        while ($payment = $res->fetch_assoc()) {
            $payments[] = $payment;
        }
    }
}

require_once __DIR__ . '/../../templates/layout/header.php';
?>
<div class="app-shell">
  <?php require_once __DIR__ . '/../../templates/layout/sidebar.php'; ?>

  <div class="app-content">
    <?php require_once __DIR__ . '/../../templates/layout/topbar.php'; ?>

    <main class="page-wrap">
      <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mb-4">
        <div class="d-flex gap-2 flex-grow-1" style="max-width: 500px;">
          <div>
            <h1 class="h3 mb-1"><?= h($page_title) ?> #<?= h((string)$installment['id']) ?></h1>
            <p class="text-muted"><?= h($page_subtitle) ?></p>
          </div>
        </div>

        <div class="d-flex gap-2">
          <a href="<?= rtrim($GLOBALS['BASE_URL'], '/') ?>/modules/installments/installments.php" 
                               class="btn btn-outline-primary" title="Back">
            <i class="bi bi-arrow-left"></i> Full list
          </a>
        <!--  <a href="/modules/installments/edit.php?id=<?= (int)$id ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil"></i> Edit
          </a> -->
          <?php if ($installment['status'] == 'active' && ((float)$installment['amount_due'] - (float)$installment['amount_paid']) > 0): ?>
            <a href="<?= rtrim($GLOBALS['BASE_URL'], '/') ?>/modules/installments/installment_payment.php?installment_id=<?= (int)$id ?>" 
               class="btn btn-success btn-sm">
              <i class="bi bi-cash"></i> Record Payment
            </a>
          <?php endif; ?>
        </div>
      </div>

  <div class="row g-4">
    <!-- Main Details Card -->
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-primary text-white">
          <h6 class="mb-0"><i class="bi bi-info-circle"></i> Installment Details</h6>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="small text-muted d-block">Installment ID</label>
            <div class="fw-bold fs-5"><?= h((string)$installment['id']) ?></div>
          </div>
          
          <div class="mb-3">
            <label class="small text-muted d-block">Status</label>
            <?php
            $statusColors = [
                'active' => 'success',
                'completed' => 'info',
                'cancelled' => 'danger',
                'overdue' => 'warning',
                'due_soon' => 'warning'
            ];
            $statusColor = $statusColors[$installment['status']] ?? 'secondary';
            ?>
            <span class="badge bg-<?= $statusColor ?> text-white fs-6">
              <?= h(str_replace('_', ' ', ucfirst($installment['status']))) ?>
            </span>
          </div>

          <div class="mb-3">
            <label class="small text-muted d-block">Contact</label>
            <div class="fw-bold"><?= h($installment['contact_name'] ?? 'N/A') ?></div>
            <?php if ($installment['phone']): ?>
              <div class="small text-secondary">
                <i class="bi bi-telephone"></i> <?= h($installment['phone']) ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="mb-3">
            <label class="small text-muted d-block">Due Date</label>
            <div class="fw-bold">
              <i class="bi bi-calendar"></i> <?= h($installment['due_date']) ?>
            </div>
          </div>

          <?php if ($installment['reference']): ?>
          <div class="mb-3">
            <label class="small text-muted d-block">Reference</label>
            <div class="fw-bold">
              <i class="bi bi-tag"></i> <?= h($installment['reference']) ?>
            </div>
          </div>
          <?php endif; ?>

          <?php if ($installment['notes']): ?>
          <div class="mb-3">
            <label class="small text-muted d-block">Notes</label>
            <div class="p-2 bg-light border rounded small">
              <?= h($installment['notes']) ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Financial Summary Card -->
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-success text-white">
          <h6 class="mb-0"><i class="bi bi-cash-stack"></i> Financial Summary</h6>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="small text-muted d-block">Amount Due</label>
            <div class="fw-bold fs-5">UGX <?= number_format((float)$installment['amount_due'], 0) ?></div>
          </div>
          
          <div class="mb-3">
            <label class="small text-muted d-block">Amount Paid</label>
            <div class="fw-bold fs-5 text-success">UGX <?= number_format((float)$installment['amount_paid'], 0) ?></div>
          </div>

          <div class="mb-3">
            <label class="small text-muted d-block">Remaining Balance</label>
            <?php $remaining = (float)$installment['amount_due'] - (float)$installment['amount_paid']; ?>
            <div class="fw-bold fs-5 <?= $remaining > 0 ? 'text-danger' : 'text-success' ?>">
              UGX <?= number_format($remaining, 0) ?>
            </div>
          </div>

          <!-- Progress Bar -->
          <div class="mb-3">
            <label class="small text-muted d-block">Payment Progress</label>
            <?php
            $paidPct = ($installment['amount_due'] > 0) 
              ? (int)((float)$installment['amount_paid'] / (float)$installment['amount_due'] * 100)
              : 0;
            ?>
            <div class="progress" style="height: 20px;">
              <div class="progress-bar <?= $paidPct >= 100 ? 'bg-success' : ($paidPct >= 50 ? 'bg-warning' : 'bg-danger') ?>" 
                   style="width: <?= min(100, $paidPct) ?>%">
              </div>
            </div>
            <div class="text-center mt-1">
              <small class="fw-bold"><?= $paidPct ?>% Paid</small>
            </div>
          </div>

          <div class="alert alert-<?= $remaining > 0 ? 'warning' : 'success' ?> small mb-0">
            <i class="bi bi-info-circle"></i>
            <?php if ($remaining > 0): ?>
              UGX <?= number_format($remaining, 0) ?> outstanding
            <?php else: ?>
              Fully paid
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Payment History Card -->
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-info text-white">
          <h6 class="mb-0">
            <i class="bi bi-clock-history"></i> Payment History 
            <span class="badge bg-light text-dark ms-2"><?= count($payments) ?></span>
          </h6>
        </div>
        <div class="card-body p-0">
          <?php if (!empty($payments)): ?>
            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
              <table class="table table-sm table-hover mb-0">
                <thead class="table-light sticky-top">
                  <tr>
                    <th>Date</th>
                    <th class="text-end">Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($payments as $p): ?>
                    <tr>
                      <td><?= h(date('M j, Y', strtotime($p['payment_date']))) ?></td>
                      <td class="text-end fw-bold">UGX <?= number_format((float)$p['amount'], 0) ?></td>
                      <td><?= h($p['method'] ?? '-') ?></td>
                      <td><?= h($p['reference'] ?? '-') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="p-4 text-center text-muted">
              <i class="bi bi-clock-history fs-1"></i>
              <p class="mb-0 mt-2">No payments recorded yet</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Metadata Section -->
 <!-- <div class="row mt-4">
    <div class="col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-secondary text-white">
          <h6 class="mb-0"><i class="bi bi-gear"></i> System Information</h6>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-3">
              <div class="small text-muted">Created</div>
              <div><?= date('M j, Y H:i', strtotime($installment['created_at'])) ?></div>
            </div>
            <div class="col-md-3">
              <div class="small text-muted">Last Updated</div>
              <div><?= $installment['updated_at'] ? date('M j, Y H:i', strtotime($installment['updated_at'])) : 'Never' ?></div>
            </div>
            <div class="col-md-3">
              <div class="small text-muted">Created By</div>
              <div>User ID #<?= h((string)$installment['user_id']) ?></div>
            </div>
            <div class="col-md-3">
              <div class="small text-muted">Actions</div>
              <div class="d-flex gap-1">
                <a href="/modules/installments/edit.php?id=<?= (int)$id ?>" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-pencil"></i>
                </a>
                <?php if (function_exists('user_has_permission') && user_has_permission('installments.delete')): ?>
                  <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal">
                    <i class="bi bi-trash"></i>
                  </button>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div> -->

</main>
  </div>
</div>

  <!-- Confirm Delete Modal -->
  <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="confirmDeleteModalLabel">Confirm Delete</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <!-- Payment Modal -->
        <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-cash"></i> Record Payment - Installment #<?= (int)$id ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <form id="paymentForm">
                <div class="modal-body">
                  <input type="hidden" name="action" value="record_payment">
                  <input type="hidden" name="installment_id" value="<?= (int)$id ?>">

                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label">Amount Due</label>
                      <div class="input-group">
                        <span class="input-group-text"><?= h(get_currency_symbol($db)) ?></span>
                        <input type="text" class="form-control" value="<?= h(format_currency_amount((float)$installment['amount_due'], $db)) ?>" readonly>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Remaining Balance</label>
                      <div class="input-group">
                        <span class="input-group-text"><?= h(get_currency_symbol($db)) ?></span>
                        <input type="text" class="form-control" value="<?= h(format_currency_amount($remaining, $db)) ?>" readonly>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Payment Amount *</label>
                      <div class="input-group">
                        <span class="input-group-text"><?= h(get_currency_symbol($db)) ?></span>
                        <input type="number" id="payment_amount" name="amount" class="form-control" step="<?= max(0.01, pow(10, -get_currency_decimals($db))) ?>" min="0.01" max="<?= $remaining ?>" required>
                      </div>
                      <small class="form-text">Payment amount in <?= h(get_currency_code($db)) ?></small>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Payment Date</label>
                      <input type="date" id="payment_date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-12">
                      <label class="form-label">Payment Method *</label>
                      <select name="method" class="form-select" required>
                        <option value="">Select Method</option>
                        <option value="cash">Cash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cheque">Cheque</option>
                        <option value="mobile_money">Mobile Money</option>
                        <option value="credit_card">Credit Card</option>
                      </select>
                    </div>
                    <div class="col-12">
                      <label class="form-label">Reference</label>
                      <input type="text" name="reference" class="form-control" placeholder="Transaction reference">
                    </div>
                    <div class="col-12">
                      <label class="form-label">Notes</label>
                      <textarea name="notes" class="form-control" rows="2" placeholder="Payment notes"></textarea>
                    </div>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> Record Payment</button>
                </div>
              </form>
            </div>
          </div>
        </div>
        <div class="modal-body">
          Are you sure you want to delete installment #<?= (int)$id ?>? This action cannot be undone.
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" id="confirmDeleteBtn" class="btn btn-danger">Delete</button>
        </div>
      </div>
    </div>
  </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap modal with basic configuration
    const paymentModalElement = document.getElementById('paymentModal');
    if (paymentModalElement) {
        const paymentModal = new bootstrap.Modal(paymentModalElement);
        
        // Set maximum date to today
        const paymentDateInput = document.getElementById('payment_date');
        const today = new Date().toISOString().split('T')[0];
        paymentDateInput.max = today;
        
        // Format amount input
        const amountInput = document.getElementById('payment_amount');
        amountInput.addEventListener('blur', function() {
            if (this.value && !isNaN(this.value)) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
        
        // Auto-select max amount on double-click
        amountInput.addEventListener('dblclick', function() {
            const maxValue = this.getAttribute('max');
            if (maxValue) {
                this.value = maxValue;
            }
        });
        
        // Handle form submission via AJAX
        const paymentForm = document.getElementById('paymentForm');
        paymentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            // Mark request as AJAX so the server returns JSON
            formData.append('ajax', '1');
            
            fetch('<?= $GLOBALS['BASE_URL'] ?>/modules/installments/installment_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close modal
                    paymentModal.hide();
                    
                    // Show success message
                    showAlert('Payment recorded successfully', 'success');
                    
                    // Reload page after 2 seconds to show updated data
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error loading payment history:', error);
                showAlert('An error occurred while processing payment', 'danger');
            });
        });
    }
});

function showAlert(message, type) {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.alert');
    existingAlerts.forEach(alert => alert.remove());
    
    // Create new alert
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.parentNode.removeChild(alertDiv);
        }
    }, 5000);
}

// Bootstrap modal confirm delete handler
document.addEventListener('DOMContentLoaded', function() {
  const confirmBtn = document.getElementById('confirmDeleteBtn');
  if (confirmBtn) {
    confirmBtn.addEventListener('click', function() {
      // Close modal first
      const modalEl = document.getElementById('confirmDeleteModal');
      const bsModal = bootstrap.Modal.getInstance(modalEl);
      if (bsModal) bsModal.hide();

      // Redirect to delete action (actions.php accepts GET)
      window.location.href = '<?= rtrim($GLOBALS['BASE_URL'], '/') ?>/modules/installments/actions.php?action=delete&id=<?= (int)$id ?>';
    });
  }
});
</script>

<?php require_once __DIR__ . '/../../templates/layout/footer.php'; ?>
