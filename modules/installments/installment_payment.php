<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/helpers.php';

$db = $GLOBALS['db'] ?? null;
$base_url = $GLOBALS['BASE_URL'] ?? '/';

if (!($db instanceof mysqli)) {
    die('Database not available');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = (int)($_SESSION['user']['id'] ?? 0);
if ($user_id <= 0) {
  header('Location: ' . rtrim($base_url, '/') . '/login.php');
  exit;
}

require_permission('installments.create');

$message = '';
$message_type = '';
$installment = null;
$payments = [];
$payment_methods = [
    'cash' => 'Cash',
    'bank_transfer' => 'Bank Transfer',
    'cheque' => 'Cheque',
    'mobile_money' => 'Mobile Money',
    'credit_card' => 'Credit Card'
];

$installment_id = (int)($_GET['installment_id'] ?? 0);

// Handle payment recording
// Handle payment recording
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  if ($_POST['action'] === 'record_payment') {
    $result = handle_record_payment($installment_id);
    // If AJAX flag present, return JSON immediately
    if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
      header('Content-Type: application/json');
      echo json_encode($result);
      exit;
    }
    $message = $result['message'];
    $message_type = $result['success'] ? 'success' : 'danger';
  } elseif ($_POST['action'] === 'edit_payment') {
    require_permission('installments.edit');
    $result = handle_edit_payment();
    // If AJAX flag present, return JSON immediately
    if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
      ob_clean(); // Clear any output buffer
      header('Content-Type: application/json');
      echo json_encode($result);
      exit;
    }
    $message = $result['message'];
    $message_type = $result['success'] ? 'success' : 'danger';
  } elseif ($_POST['action'] === 'delete_payment') {
    require_permission('installments.delete');
    $result = handle_delete_payment();
    // If AJAX flag present, return JSON immediately
    if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
      ob_clean(); // Clear any output buffer
      header('Content-Type: application/json');
      echo json_encode($result);
      exit;
    }
    $message = $result['message'];
    $message_type = $result['success'] ? 'success' : 'danger';
  }
}

// Check if tables exist
function table_exists(mysqli $db, string $table): bool {
    $result = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");
    return $result && $result->num_rows > 0;
}

$hasInstallments = table_exists($db, 'installments');
$hasContacts = table_exists($db, 'contacts');
$hasUsers = table_exists($db, 'users');

// Fetch installment details
if ($installment_id > 0 && $hasInstallments) {
    $contactSelect = $hasContacts ? ", c.name" : ", NULL AS name";
    $contactJoin = $hasContacts ? "LEFT JOIN contacts c ON i.contact_id = c.id" : "";
    
    $st = $db->prepare("
        SELECT i.id, i.contact_id $contactSelect, i.amount_due, i.amount_paid, (i.amount_due - i.amount_paid) AS remaining_balance,
               i.due_date, i.reference, i.status, i.created_at, i.updated_at
        FROM installments i
        $contactJoin
        WHERE i.id = ?
        LIMIT 1
    ");
    
    if ($st) {
        $st->bind_param('i', $installment_id);
        $st->execute();
        $installment = $st->get_result()->fetch_assoc();
        $st->close();
    }
    
    if (!$installment) {
        $message = 'Installment not found';
        $message_type = 'danger';
        $installment_id = 0;
    }
}

// Fetch payment history
if ($installment_id > 0 && $installment && $hasInstallments) {
    $userSelect = $hasUsers ? ", u.full_name as user_name" : ", NULL AS user_name";
    $userJoin = $hasUsers ? "LEFT JOIN users u ON ip.user_id = u.id" : "";
    
    $st = $db->prepare("
        SELECT ip.id, ip.amount, ip.method, ip.reference, ip.notes, ip.user_id, ip.payment_date $userSelect
        FROM installment_payments ip
        $userJoin
        WHERE ip.installment_id = ?
        ORDER BY ip.payment_date DESC
    ");
    
    if ($st) {
        $st->bind_param('i', $installment_id);
        $st->execute();
        $payments = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();
    }
}

function h2($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

function handle_edit_payment(): array {
    global $db, $user_id;
    
    $payment_id = (int)($_POST['payment_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $method = trim((string)($_POST['method'] ?? ''));
    $reference = trim((string)($_POST['reference'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $payment_date_input = trim((string)($_POST['payment_date'] ?? ''));
    $payment_date = '';
    if ($payment_date_input && preg_match('/^\d{4}-\d{2}-\d{2}$/', $payment_date_input)) {
        // Use provided date at start of day
        $payment_date = $payment_date_input . ' 00:00:00';
    }
    
    if ($payment_id <= 0 || $amount <= 0 || !$method) {
        return ['success' => false, 'message' => 'Invalid parameters'];
    }
    
    try {
        $db->begin_transaction();
        
        // Get current payment details
        $check = $db->prepare("SELECT installment_id, amount, method, reference, notes, payment_date FROM installment_payments WHERE id = ? LIMIT 1");
        if (!$check) throw new Exception('Prepare failed');
        $check->bind_param('i', $payment_id);
        $check->execute();
        $current_payment = $check->get_result()->fetch_assoc();
        $check->close();
        
        if (!$current_payment) {
            throw new Exception('Payment not found');
        }
        
        // Get installment details to validate amount
        $inst_check = $db->prepare("SELECT amount_due, amount_paid FROM installments WHERE id = ? LIMIT 1");
        if (!$inst_check) throw new Exception('Prepare failed');
        $inst_check->bind_param('i', $current_payment['installment_id']);
        $inst_check->execute();
        $installment = $inst_check->get_result()->fetch_assoc();
        $inst_check->close();
        
        if (!$installment) {
            throw new Exception('Installment not found');
        }
        
        // Calculate new totals
        $old_amount = (float)$current_payment['amount'];
        $amount_difference = $amount - $old_amount;
        $new_paid = (float)$installment['amount_paid'] + $amount_difference;
        
        // Validate new amount doesn't exceed due amount
        if ($new_paid > (float)$installment['amount_due']) {
            throw new Exception('Payment amount cannot exceed installment due amount');
        }
        
        // Update payment
        $update_payment = $db->prepare("UPDATE installment_payments 
            SET amount = ?, method = ?, reference = ?, notes = ?, payment_date = ?, user_id = ?
            WHERE id = ? LIMIT 1");
        if (!$update_payment) throw new Exception('Prepare failed');
        $update_payment->bind_param('dssisii', $amount, $method, $reference, $notes, $payment_date, $user_id, $payment_id);
        $update_payment->execute();
        $update_payment->close();
        
        // Update installment totals
        $update_installment = $db->prepare("UPDATE installments 
            SET amount_paid = ?, updated_at = NOW() 
            WHERE id = ? LIMIT 1");
        if (!$update_installment) throw new Exception('Prepare failed');
        $update_installment->bind_param('di', $new_paid, $current_payment['installment_id']);
        $update_installment->execute();
        $update_installment->close();
        
        $db->commit();
        
        // Log action
        if (function_exists('audit_log')) {
            audit_log('installments.edit_payment', 'installment_payment', (string)$payment_id, 
                "Edited payment: amount=$amount, method=$method, reference=$reference");
        }
        
        return ['success' => true, 'message' => 'Payment updated successfully'];
        
    } catch (Exception $e) {
        $db->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function handle_delete_payment(): array {
    global $db, $user_id;
    
    $payment_id = (int)($_POST['payment_id'] ?? 0);
    
    if ($payment_id <= 0) {
        return ['success' => false, 'message' => 'Invalid payment ID'];
    }
    
    try {
        $db->begin_transaction();
        
        // Get payment details before deletion
        $check = $db->prepare("SELECT installment_id, amount FROM installment_payments WHERE id = ? LIMIT 1");
        if (!$check) throw new Exception('Prepare failed');
        $check->bind_param('i', $payment_id);
        $check->execute();
        $payment = $check->get_result()->fetch_assoc();
        $check->close();
        
        if (!$payment) {
            throw new Exception('Payment not found');
        }
        
        // Get current installment details
        $inst_check = $db->prepare("SELECT amount_paid FROM installments WHERE id = ? LIMIT 1");
        if (!$inst_check) throw new Exception('Prepare failed');
        $inst_check->bind_param('i', $payment['installment_id']);
        $inst_check->execute();
        $installment = $inst_check->get_result()->fetch_assoc();
        $inst_check->close();
        
        if (!$installment) {
            throw new Exception('Installment not found');
        }
        
        // Calculate new amount paid
        $new_paid = (float)$installment['amount_paid'] - (float)$payment['amount'];
        
        // Delete the payment
        $delete_payment = $db->prepare("DELETE FROM installment_payments WHERE id = ? LIMIT 1");
        if (!$delete_payment) throw new Exception('Prepare failed');
        $delete_payment->bind_param('i', $payment_id);
        $delete_payment->execute();
        $delete_payment->close();
        
        // Update installment totals
        $update_installment = $db->prepare("UPDATE installments 
            SET amount_paid = ?, updated_at = NOW() 
            WHERE id = ? LIMIT 1");
        if (!$update_installment) throw new Exception('Prepare failed');
        $update_installment->bind_param('di', $new_paid, $payment['installment_id']);
        $update_installment->execute();
        $update_installment->close();
        
        $db->commit();
        
        // Log action
        if (function_exists('audit_log')) {
            audit_log('installments.delete_payment', 'installment_payment', (string)$payment_id, 
                "Deleted payment: amount={$payment['amount']}");
        }
        
        return ['success' => true, 'message' => 'Payment deleted successfully'];
        
    } catch (Exception $e) {
        $db->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function handle_record_payment(int $id): array {
    global $db, $user_id;
    
    $amount = (float)($_POST['amount'] ?? 0);
    $method = trim((string)($_POST['method'] ?? ''));
    $reference = trim((string)($_POST['reference'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $payment_date_input = trim((string)($_POST['payment_date'] ?? ''));
    $payment_date = '';
    if ($payment_date_input && preg_match('/^\d{4}-\d{2}-\d{2}$/', $payment_date_input)) {
      // Use provided date at start of day
      $payment_date = $payment_date_input . ' 00:00:00';
    }
    
    if ($id <= 0 || $amount <= 0 || !$method) {
        return ['success' => false, 'message' => 'Invalid parameters'];
    }
    
    try {
        $db->begin_transaction();
        
        // Verify installment exists and get current amounts
        $check = $db->prepare("SELECT amount_due, amount_paid, status FROM installments WHERE id = ? LIMIT 1");
        if (!$check) throw new Exception('Prepare failed');
        $check->bind_param('i', $id);
        $check->execute();
        $inst = $check->get_result()->fetch_assoc();
        $check->close();
        
        if (!$inst) {
            throw new Exception('Installment not found');
        }
        
        $remaining_balance = (float)$inst['amount_due'] - (float)$inst['amount_paid'];
        
        // Check if payment exceeds remaining balance
        if ($amount > $remaining_balance) {
            throw new Exception('Payment exceeds remaining balance of ' . number_format($remaining_balance, 2));
        }
        
        $new_paid = (float)$inst['amount_paid'] + $amount;
        $new_remaining = (float)$inst['amount_due'] - $new_paid;
        
        // Record payment
        if ($payment_date !== '') {
          $st = $db->prepare("INSERT INTO installment_payments 
            (installment_id, amount, method, reference, notes, user_id, payment_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
          if (!$st) throw new Exception('Prepare failed');
          $st->bind_param('idsssis', $id, $amount, $method, $reference, $notes, $user_id, $payment_date);
        } else {
          $st = $db->prepare("INSERT INTO installment_payments 
            (installment_id, amount, method, reference, notes, user_id, payment_date) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())");
          if (!$st) throw new Exception('Prepare failed');
          $st->bind_param('idsssi', $id, $amount, $method, $reference, $notes, $user_id);
        }
        $st->execute();
        $payment_id = $st->insert_id;
        $st->close();
        
        // Update installment totals
        $new_status = $new_remaining <= 0 ? 'completed' : $inst['status'];
        $st = $db->prepare("UPDATE installments SET amount_paid = ?, status = ?, updated_at = NOW() WHERE id = ? LIMIT 1");
        if (!$st) throw new Exception('Prepare failed');
        $st->bind_param('dsi', $new_paid, $new_status, $id);
        $st->execute();
        $st->close();
        
        $db->commit();
        
        if (function_exists('audit_log')) {
            audit_log('installments.payment', 'installment_payment', (string)$payment_id, "Payment recorded: $amount via $method");
        }
        
        return ['success' => true, 'message' => 'Payment recorded successfully'];
    } catch (Exception $e) {
        $db->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

$page_title = 'Record Installment Payment';
include __DIR__ . '/../../templates/layout/header.php';
?>
<div class="app-shell">
  <?php require_once __DIR__ . '/../../templates/layout/sidebar.php'; ?>
  <div class="app-content">
    <?php require_once __DIR__ . '/../../templates/layout/topbar.php'; ?>

    <main class="page-wrap">
      <div class="container-fluid py-3">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <h4 class="mb-1">Record Installment Payment</h4>
            <div class="text-muted small">Process payment for installment schedule</div>
          </div>
          <div class="gap-2 d-flex">
            <?php if ($installment_id > 0 && $installment): ?>
              <a href="<?= rtrim($base_url, '/') ?>/modules/installments/installment_view.php?id=<?= $installment_id ?>" class="btn btn-outline-primary">
                <i class="bi bi-eye"></i> View Details
              </a>
            <?php endif; ?>
            <a href="<?= rtrim($base_url, '/') ?>/modules/installments/installments.php" class="btn btn-outline-secondary">
              <i class="bi bi-arrow-left"></i> Back to Installments
            </a>
          </div>
        </div>

        <?php if ($message): ?>
          <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
              <i class="bi bi-<?= $message_type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i>
              <?= h2($message) ?>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <?php if (!$installment || $installment_id <= 0): ?>

  <div class="card shadow-sm">
    <div class="card-body text-center py-3">
      <i class="bi bi-exclamation-triangle text-warning" style="font-size: 2rem;"></i>
      <h5 class="mt-2 mb-1">Select an Installment</h5>
      <p class="text-muted mb-0">Please select an installment from the table below to record a payment.</p>
    </div>
  </div>

  <?php if ($hasInstallments): ?>
    <?php
    $recent_installments = [];
    $recent_st = $db->prepare("
      SELECT i.id, i.amount_due, i.amount_paid, (i.amount_due - i.amount_paid) AS remaining_balance,
             i.due_date, i.reference, i.status, c.name
      FROM installments i
      LEFT JOIN customers c ON i.contact_id = c.id
      WHERE i.status IN ('active', 'overdue')
      ORDER BY i.due_date ASC
      LIMIT 20
    ");
    if ($recent_st) {
      $recent_st->execute();
      $recent_installments = $recent_st->get_result()->fetch_all(MYSQLI_ASSOC);
      $recent_st->close();
    }
    ?>

    <?php if (!empty($recent_installments)): ?>
      <div class="card shadow-sm">
        <div class="card-header">
          <h6 class="mb-0"><i class="bi bi-list-ul"></i> Available Installments</h6>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>ID</th>
                  <th>Customer</th>
                  <th>Due Date</th>
                  <th>Due Amount</th>
                  <th>Remaining</th>
                  <th>Status</th>
                  <th>Recent Payment</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recent_installments as $inst): ?>
                  <tr style="cursor: pointer;" onclick="window.location.href='<?= rtrim($base_url, '/') ?>/modules/installments/installment_payment.php?installment_id=<?= (int)$inst['id'] ?>'">
                    <td><span class="badge bg-primary">#<?= (int)$inst['id'] ?></span></td>
                    <td><?= h($inst['name'] ?? 'Unknown Customer') ?></td>
                    <td><?= h(substr((string)$inst['due_date'], 0, 10)) ?></td>
                    <td><?= h(format_currency_amount((float)$inst['amount_due'], $db)) ?></td>
                    <td><strong><?= h(format_currency_amount((float)$inst['remaining_balance'], $db)) ?></strong></td>
                    <td>
                      <span class="badge bg-<?= $inst['status']==='overdue' ? 'danger' : 'success' ?>">
                        <?= h(ucfirst($inst['status'])) ?>
                      </span>
                    </td>
                    <td>
                      <?php
                      // Get most recent payment for this installment
                      $payment_st = $db->prepare("
                        SELECT amount, payment_date 
                        FROM installment_payments 
                        WHERE installment_id = ? 
                        ORDER BY payment_date DESC 
                        LIMIT 1
                      ");
                      $recent_payment = null;
                      if ($payment_st) {
                        $payment_st->bind_param('i', $inst['id']);
                        $payment_st->execute();
                        $recent_payment = $payment_st->get_result()->fetch_assoc();
                        $payment_st->close();
                      }
                      
                      if ($recent_payment):
                      ?>
                        <div>
                          <strong><?= h(format_currency_amount((float)$recent_payment['amount'], $db)) ?></strong>
                          <br>
                          <small class="text-muted"><?= h(substr((string)$recent_payment['payment_date'], 0, 10)) ?></small>
                        </div>
                      <?php else: ?>
                        <span class="text-muted">No payments</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="card shadow-sm">
        <div class="card-body text-center py-5">
          <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
          <h5 class="mt-3">No Installments Found</h5>
          <p class="text-muted">There are no active or overdue installments available.</p>
          <a href="<?= rtrim($base_url, '/') ?>/modules/installments/installments.php" class="btn btn-outline-primary">
            <i class="bi bi-list-ul"></i> View All Installments
          </a>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>

<?php else: ?>

          <div class="row g-4">
            <!-- Installment Summary -->
            <div class="col-lg-4">
              <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white">
                  <h6 class="mb-0">
                    <i class="bi bi-info-circle"></i> Installment Summary
                  </h6>
                </div>
                <div class="card-body">
                  <div class="mb-3">
                    <label class="form-label text-muted small">Installment ID</label>
                    <div class="fw-semibold">#<?= h2((string)$installment['id']) ?></div>
                  </div>
                  
                  <div class="mb-3">
                    <label class="form-label text-muted small">Contact</label>
                    <div class="fw-semibold"><?= h2($installment['name'] ?? 'Unknown Contact') ?></div>
                  </div>
                  
                  <div class="mb-3">
                    <label class="form-label text-muted small">Status</label>
                    <div>
                      <?php 
                      $statusColor = match($installment['status']) {
                        'completed' => 'success',
                        'active' => 'primary',
                        'overdue' => 'danger',
                        'due_soon' => 'warning',
                        'extended' => 'info',
                        default => 'secondary'
                      };
                      ?>
                      <span class="badge bg-<?= $statusColor ?>">
                        <?= h2(str_replace('_', ' ', ucfirst($installment['status']))) ?>
                      </span>
                    </div>
                  </div>
                  
                  <hr>
                  
                  <div class="row g-3">
                    <div class="col-6">
                      <label class="form-label text-muted small">Amount Due</label>
                      <div class="h5 mb-0"><?= h2(number_format((float)$installment['amount_due'], 2)) ?></div>
                    </div>
                    <div class="col-6">
                      <label class="form-label text-muted small">Paid</label>
                      <div class="h5 mb-0 text-success"><?= h2(number_format((float)$installment['amount_paid'], 2)) ?></div>
                    </div>
                    <div class="col-12">
                      <label class="form-label text-muted small">Remaining Balance</label>
                      <div class="h5 mb-0 text-danger"><?= h2(number_format((float)$installment['remaining_balance'], 2)) ?></div>
                    </div>
                  </div>
                  
                  <div class="mt-3">
                    <label class="form-label text-muted small">Payment Progress</label>
                    <?php 
                    $progress = $installment['amount_due'] > 0 ? ((float)$installment['amount_paid'] / (float)$installment['amount_due']) * 100 : 0;
                    ?>
                    <div class="progress" style="height: 8px;">
                      <div class="progress-bar bg-success" role="progressbar" style="width: <?= round($progress, 1) ?>%">
                      </div>
                    </div>
                    <small class="text-muted"><?= round($progress, 1) ?>% paid</small>
                  </div>
                  
                  <hr>
                  
                  <div class="row g-2">
                    <div class="col-12">
                      <label class="form-label text-muted small">Due Date</label>
                      <div class="small"><?= h2(substr($installment['due_date'], 0, 10)) ?></div>
                    </div>
                    <?php if (!empty($installment['reference'])): ?>
                    <div class="col-12">
                      <label class="form-label text-muted small">Reference</label>
                      <div class="small"><?= h2($installment['reference']) ?></div>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

            <!-- Payment Recording Form -->
            <div class="col-lg-5">
              <div class="card shadow-sm h-100">
                <div class="card-header bg-success text-white">
                  <h6 class="mb-0">
                    <i class="bi bi-cash"></i> Record Payment
                  </h6>
                </div>
                <div class="card-body">
                  <form method="POST">
                    <input type="hidden" name="action" value="record_payment">
                    
                    <div class="mb-3">
                      <label for="amount" class="form-label">Payment Amount *</label>
                      <div class="input-group">
                        <span class="input-group-text"><?= h(get_currency_symbol($db)) ?></span>
                        <input type="number" class="form-control" id="amount" name="amount" 
                               step="1" min="1" max="<?= $installment['remaining_balance'] ?>" 
                               placeholder="0" required>
                      </div>
                      <div class="form-text">
                        <small class="text-muted">Maximum amount: <?= h(format_currency_amount((float)$installment['remaining_balance'], $db)) ?></small>
                      </div>
                    </div>
                    
                    <div class="mb-3">
                      <label for="method" class="form-label">Payment Method *</label>
                      <select class="form-select" id="method" name="method" required>
                        <option value="">-- Select Method --</option>
                        <?php foreach ($payment_methods as $key => $label): ?>
                          <option value="<?= h($key) ?>"><?= h($label) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    
                    <div class="mb-3">
                      <label for="reference" class="form-label">Reference / Receipt #</label>
                      <input type="text" class="form-control" id="reference" name="reference" 
                             placeholder="e.g., CHK-12345 or TXN-ABC123">
                    </div>
                    
                    <div class="mb-4">
                      <label for="notes" class="form-label">Notes / Comments</label>
                      <textarea class="form-control" id="notes" name="notes" rows="3" 
                                placeholder="Additional payment details..."></textarea>
                    </div>
                    
                    <div class="d-grid">
                      <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-check-circle"></i> Record Payment
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <!-- Payment History -->
            <div class="col-lg-3">
              <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                  <h6 class="mb-0">
                    <i class="bi bi-clock-history"></i> Payment History
                    <span class="badge bg-primary rounded-pill float-end"><?= count($payments) ?></span>
                  </h6>
                </div>
                <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                  <?php if (empty($payments)): ?>
                    <div class="text-center text-muted py-4">
                      <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                      <div class="small mt-2">No payments recorded yet</div>
                    </div>
                  <?php else: ?>
                    <?php foreach ($payments as $payment): ?>
                      <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-start">
                          <div>
                            <div class="fw-semibold"><?= h(format_currency_amount((float)$payment['amount'], $db)) ?></div>
                            <small class="text-muted">
                              <i class="bi bi-calendar"></i> <?= h(substr($payment['payment_date'], 0, 10)) ?>
                            </small>
                          </div>
                          <div class="d-flex gap-1">
                            <span class="badge bg-success"><?= h(ucfirst($payment['method'])) ?></span>
                            <?php if (function_exists('user_has_permission') && user_has_permission('installments.edit')): ?>
                              <button type="button" class="btn btn-sm btn-outline-primary" 
                                      data-bs-toggle="modal" data-bs-target="#editPaymentModal"
                                      onclick="loadPaymentForEdit(<?= (int)$payment['id'] ?>, '<?= h($payment['amount']) ?>', '<?= h($payment['method']) ?>', '<?= h($payment['reference'] ?? '') ?>', '<?= h($payment['notes'] ?? '') ?>', '<?= h(substr((string)$payment['payment_date'], 0, 10)) ?>')"
                                      title="Edit Payment">
                                <i class="bi bi-pencil"></i>
                              </button>
                            <?php endif; ?>
                          </div>
                        </div>
                        <?php if (!empty($payment['reference'])): ?>
                          <small class="text-muted d-block mt-2">
                            <i class="bi bi-receipt"></i> Ref: <?= h($payment['reference']) ?>
                          </small>
                        <?php endif; ?>
                        <?php if (!empty($payment['user_name'])): ?>
                          <small class="text-muted d-block">
                            <i class="bi bi-person"></i> By: <?= h($payment['user_name']) ?>
                          </small>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>

          

        </div>
    </main>
  </div>
</div>
<?php endif; ?>

<!-- Edit Payment Modal -->
<div class="modal fade" id="editPaymentModal" tabindex="-1" aria-labelledby="editPaymentModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" id="editPaymentForm">
        <div class="modal-header">
          <h5 class="modal-title" id="editPaymentModalLabel">
            <i class="bi bi-pencil"></i> Edit Payment
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="edit_payment">
          <input type="hidden" name="payment_id" id="edit_payment_id">
          <input type="hidden" name="ajax" value="1">
          
          <div class="row g-3">
            <div class="col-md-6">
              <label for="edit_amount" class="form-label">Amount</label>
              <input type="number" step="0.01" min="0.01" class="form-control" id="edit_amount" name="amount" required>
            </div>
            <div class="col-md-6">
              <label for="edit_payment_date" class="form-label">Payment Date</label>
              <input type="date" class="form-control" id="edit_payment_date" name="payment_date" required>
            </div>
            <div class="col-md-6">
              <label for="edit_method" class="form-label">Payment Method</label>
              <select class="form-select" id="edit_method" name="method" required>
                <option value="">Select Method</option>
                <?php foreach ($payment_methods as $key => $label): ?>
                  <option value="<?= h($key) ?>"><?= h($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label for="edit_reference" class="form-label">Reference</label>
              <input type="text" class="form-control" id="edit_reference" name="reference">
            </div>
            <div class="col-12">
              <label for="edit_notes" class="form-label">Notes</label>
              <textarea class="form-control" id="edit_notes" name="notes" rows="3"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-danger me-auto" id="deletePaymentBtn" onclick="confirmDeletePayment()">
            <i class="bi bi-trash"></i> Delete Payment
          </button>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-circle"></i> Update Payment
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteConfirmModalLabel">
          <i class="bi bi-exclamation-triangle"></i> Confirm Delete
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="text-center mb-4">
          <i class="bi bi-trash text-danger" style="font-size: 3rem;"></i>
        </div>
        <h5 class="text-center mb-3">Delete Payment?</h5>
        <p class="text-center text-muted mb-2">Are you sure you want to delete this payment?</p>
        <div class="alert alert-warning d-flex align-items-center" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          <div>
            <strong>Payment Amount:</strong> <span id="deletePaymentAmount" class="fw-bold"></span>
          </div>
        </div>
        <p class="text-center text-muted small mb-0">This action cannot be undone and will affect the installment balance.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x-circle"></i> Cancel
        </button>
        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
          <i class="bi bi-trash"></i> Delete Payment
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function loadPaymentForEdit(paymentId, amount, method, reference, notes, paymentDate) {
  document.getElementById('edit_payment_id').value = paymentId;
  document.getElementById('edit_amount').value = amount;
  document.getElementById('edit_method').value = method;
  document.getElementById('edit_reference').value = reference;
  document.getElementById('edit_notes').value = notes;
  document.getElementById('edit_payment_date').value = paymentDate;
}

function confirmDeletePayment() {
  const paymentId = document.getElementById('edit_payment_id').value;
  const amount = document.getElementById('edit_amount').value;
  
  // Set the payment amount in the confirmation modal
  document.getElementById('deletePaymentAmount').textContent = amount;
  
  // Store the payment ID for the confirm button
  document.getElementById('confirmDeleteBtn').setAttribute('data-payment-id', paymentId);
  
  // Show the confirmation modal
  const confirmModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
  confirmModal.show();
}

function deletePayment(paymentId) {
  const formData = new FormData();
  formData.append('action', 'delete_payment');
  formData.append('payment_id', paymentId);
  formData.append('ajax', '1');
  
  const confirmBtn = document.getElementById('confirmDeleteBtn');
  const originalText = confirmBtn.innerHTML;
  
  confirmBtn.disabled = true;
  confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Deleting...';
  
  fetch('<?= rtrim($base_url, '/') ?>/modules/installments/installment_payment.php?id=<?= $installment_id ?>', {
    method: 'POST',
    body: formData
  })
  .then(response => {
    return response.text().then(text => {
      console.log('Raw response:', text);
      try {
        return JSON.parse(text);
      } catch (e) {
        console.error('JSON Parse Error:', e);
        throw new Error('Invalid JSON response: ' + text.substring(0, 200));
      }
    });
  })
  .then(data => {
    if (data.success) {
      // Close both modals
      const confirmModal = bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal'));
      const editModal = bootstrap.Modal.getInstance(document.getElementById('editPaymentModal'));
      confirmModal.hide();
      editModal.hide();
      location.reload();
    } else {
      alert('Error: ' + data.message);
      // Close confirmation modal but keep edit modal open
      const confirmModal = bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal'));
      confirmModal.hide();
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while deleting payment: ' + error.message);
    // Close confirmation modal but keep edit modal open
    const confirmModal = bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal'));
    confirmModal.hide();
  })
  .finally(() => {
    confirmBtn.disabled = false;
    confirmBtn.innerHTML = originalText;
  });
}

// Handle form submission with AJAX
document.getElementById('editPaymentForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const formData = new FormData(this);
  const submitBtn = this.querySelector('button[type="submit"]');
  const originalText = submitBtn.innerHTML;
  
  submitBtn.disabled = true;
  submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
  
  fetch('<?= rtrim($base_url, '/') ?>/modules/installments/installment_payment.php?id=<?= $installment_id ?>', {
    method: 'POST',
    body: formData
  })
  .then(response => {
    // Get the response text first to debug
    return response.text().then(text => {
      console.log('Raw response:', text);
      try {
        return JSON.parse(text);
      } catch (e) {
        console.error('JSON Parse Error:', e);
        throw new Error('Invalid JSON response: ' + text.substring(0, 200));
      }
    });
  })
  .then(data => {
    if (data.success) {
      // Close modal and reload page to show updated payment
      const modal = bootstrap.Modal.getInstance(document.getElementById('editPaymentModal'));
      modal.hide();
      location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while updating payment: ' + error.message);
  })
  .finally(() => {
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalText;
  });
});

// Handle confirm delete button click
document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
  const paymentId = this.getAttribute('data-payment-id');
  deletePayment(paymentId);
});

</script>

<?php include __DIR__ . '/../../templates/layout/footer.php'; ?>
