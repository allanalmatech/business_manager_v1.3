<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/audit.php';

require_permission('installments.edit');

$db = $GLOBALS['db'];
$user_id = $_SESSION['user_id'] ?? 1;

$page_title = 'Edit Installment';
$page_subtitle = 'Update installment details';

$message = '';
$message_type = '';

$id = (int)($_GET['id'] ?? 0);

// Validate ID
if ($id <= 0) {
    $_SESSION['flash_message'] = 'Invalid installment ID';
    $_SESSION['flash_type'] = 'danger';
    header('Location: ' . $GLOBALS['BASE_URL'] . '/modules/installments/installments.php');
    exit;
}

// Get existing installment data
$installment = null;
$st = $db->prepare("
    SELECT i.id, i.contact_id, i.amount_due, i.amount_paid, i.due_date, i.reference, i.notes, i.status, i.created_at,
           c.name as contact_name, c.phone as contact_phone
    FROM installments i
    LEFT JOIN customers c ON c.id = i.contact_id
    WHERE i.id = ? LIMIT 1
");
if ($st) {
    $st->bind_param('i', $id);
    $st->execute();
    $result = $st->get_result();
    $installment = $result->fetch_assoc();
    $st->close();
}

if (!$installment) {
    $_SESSION['flash_message'] = 'Installment not found';
    $_SESSION['flash_type'] = 'danger';
    header('Location: ' . $GLOBALS['BASE_URL'] . '/modules/installments/installments.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $contact_id = (int)($_POST['contact_id'] ?? 0);
        $amount_due = (float)($_POST['amount_due'] ?? 0);
        $amount_paid = (float)($_POST['amount_paid'] ?? 0);
        $due_date = trim((string)($_POST['due_date'] ?? ''));
        $reference = trim((string)($_POST['reference'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $status = trim((string)($_POST['status'] ?? ''));

        // Validation
        if ($contact_id <= 0) {
            throw new Exception('Please select a contact');
        }
        if ($amount_due <= 0) {
            throw new Exception('Amount due must be greater than 0');
        }
        if ($amount_paid < 0) {
            throw new Exception('Amount paid cannot be negative');
        }
        if (empty($due_date)) {
            throw new Exception('Due date is required');
        }
        if (!in_array($status, ['active', 'completed', 'cancelled'])) {
            throw new Exception('Invalid status');
        }

        // Update installment
        $st = $db->prepare("
            UPDATE installments 
            SET contact_id = ?, amount_due = ?, amount_paid = ?, due_date = ?, reference = ?, notes = ?, status = ?, updated_at = NOW()
            WHERE id = ? LIMIT 1
        ");
        if (!$st) {
            throw new Exception('Database prepare failed: ' . $db->error);
        }
        
        $st->bind_param('iddssssi', $contact_id, $amount_due, $amount_paid, $due_date, $reference, $notes, $status, $id);
        $st->execute();
        $st->close();

        // Log action
        audit_log('installments.edit', 'installment', (string)$id, "Updated installment for contact ID $contact_id");

        $_SESSION['flash_message'] = 'Installment updated successfully';
        $_SESSION['flash_type'] = 'success';
        
        header('Location: ' . $GLOBALS['BASE_URL'] . '/modules/installments/installments.php');
        exit;

    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'danger';
    }
}

// Get contacts for dropdown
$contacts = [];
$hasContacts = false;
$res = $db->query("SHOW TABLES LIKE 'customers'");
if ($res && $res->num_rows > 0) {
    $hasContacts = true;
    $result = $db->query("SELECT id, name, phone FROM customers WHERE is_active = 1 ORDER BY name");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $contacts[] = $row;
        }
    }
}

require_once __DIR__ . '/../../templates/layout/header.php';
?>

<div class="app-shell">
  <?php require_once __DIR__ . '/../../templates/layout/sidebar.php'; ?>
  <div class="app-content">
    <?php require_once __DIR__ . '/../../templates/layout/topbar.php'; ?>

    <div class="container-fluid p-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h1 class="h3 mb-1"><?= h($page_title) ?></h1>
          <div class="text-muted small"><?= h($page_subtitle) ?></div>
        </div>
        <div class="d-flex gap-2">
          <a href="<?= $GLOBALS['BASE_URL'] ?>/modules/installments/installment_view.php?id=<?= (int)$id ?>" class="btn btn-outline-info">
            <i class="bi bi-eye"></i> View
          </a>
          <a href="<?= $GLOBALS['BASE_URL'] ?>/modules/installments/installments.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to List
          </a>
        </div>
      </div>

      <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
          <?= h($message) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="row">
        <div class="col-lg-8">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
              <h5 class="mb-0"><i class="bi bi-info-circle"></i> Installment Details</h5>
            </div>
            <div class="card-body">
              <?php
$amount_due_val = number_format((float)$installment['amount_due'], 2, '.', '');
$amount_paid_val = number_format((float)$installment['amount_paid'], 2, '.', '');

$due_date_val = (string)($installment['due_date'] ?? '');
if ($due_date_val !== '' && strlen($due_date_val) > 10) {
    $due_date_val = substr($due_date_val, 0, 10); // handle DATETIME
}
?>

              <form method="POST" action="" id="installmentEditForm">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label for="contact_id" class="form-label">Contact <span class="text-danger">*</span></label>
                    <?php if ($hasContacts): ?>
                      <select class="form-select" id="contact_id" name="contact_id" required>
                        <option value="">Select Contact</option>
                        <?php foreach ($contacts as $contact): ?>
                          <option value="<?= (int)$contact['id'] ?>" <?= ($contact['id'] == $installment['contact_id']) ? 'selected' : '' ?>>
                            <?= h($contact['name']) ?>
                            <?php if ($contact['phone']): ?> (<?= h($contact['phone']) ?>)<?php endif; ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    <?php else: ?>
                      <input type="text" class="form-control" id="contact_name" name="contact_name" 
                             placeholder="Enter contact name" required value="<?= h($installment['contact_name'] ?? '') ?>">
                      <small class="text-muted">Customers table not found. Enter customer name manually.</small>
                    <?php endif; ?>
                  </div>

                  <div class="col-md-6">
                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                    <select class="form-select" id="status" name="status" required>
                      <option value="active" <?= ($installment['status'] == 'active') ? 'selected' : '' ?>>Active</option>
                      <option value="completed" <?= ($installment['status'] == 'completed') ? 'selected' : '' ?>>Completed</option>
                      <option value="cancelled" <?= ($installment['status'] == 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                  </div>

                  <div class="col-md-6">
                    <label for="amount_due" class="form-label">Amount Due (UGX) <span class="text-danger">*</span></label>
                    <div class="input-group">
                      <span class="input-group-text">UGX</span>
                      <input type="number" class="form-control" id="amount_due" name="amount_due" 
                             step="0.01" min="0" required value="<?= h($amount_due_val) ?>">
                    </div>
                    <small class="text-muted">Current: UGX <?= number_format((float)$installment['amount_due'], 0) ?></small>
                  </div>

                  <div class="col-md-6">
                    <label for="amount_paid" class="form-label">Amount Paid (UGX)</label>
                    <div class="input-group">
                      <span class="input-group-text">UGX</span>
                      <input type="number" class="form-control" id="amount_paid" name="amount_paid" 
                             step="0.01" min="0" value="<?= h($amount_paid_val) ?>">
                    </div>
                    <small class="text-muted">Current: UGX <?= number_format((float)$installment['amount_paid'], 0) ?></small>
                  </div>

                  <div class="col-md-6">
                    <label for="due_date" class="form-label">Due Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="due_date" name="due_date" 
                           required value="<?= h($due_date_val) ?>">
                  </div>

                  <div class="col-md-6">
                    <label for="reference" class="form-label">Reference</label>
                    <input type="text" class="form-control" id="reference" name="reference" 
                           placeholder="Invoice number, contract reference, etc." 
                           value="<?= h($installment['reference'] ?? '') ?>">
                  </div>

                  <div class="col-12">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="4" 
                              placeholder="Additional details about this installment..."><?= h($installment['notes'] ?? '') ?></textarea>
                  </div>

                  <div class="col-12">
                    <div class="d-flex gap-2">
                      <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Update Installment
                      </button>
                      <a href="<?= $GLOBALS['BASE_URL'] ?>/modules/installments/installment_view.php?id=<?= (int)$id ?>" class="btn btn-outline-info">
                        <i class="bi bi-eye"></i> View Details
                      </a>
                      <a href="<?= $GLOBALS['BASE_URL'] ?>/modules/installments/installments.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to List
                      </a>
                    </div>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="card border-0 shadow-sm">
            <div class="card-body">
              <h5 class="card-title mb-3">
                <i class="bi bi-info-circle text-primary"></i> Quick Actions
              </h5>
              <div class="d-grid gap-2">
                <?php if ($installment['status'] == 'active' && $installment['amount_due'] > $installment['amount_paid']): ?>
                  <a href="<?= $GLOBALS['BASE_URL'] ?>/modules/installments/installment_payment.php?installment_id=<?= (int)$id ?>" class="btn btn-success">
                    <i class="bi bi-cash"></i> Record Payment
                  </a>
                <?php endif; ?>
                
                <?php if (function_exists('user_has_permission') && user_has_permission('installments.delete')): ?>
                  <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                    <i class="bi bi-trash"></i> Delete Installment
                  </button>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
              <h5 class="card-title mb-3">
                <i class="bi bi-calculator text-info"></i> Payment Summary
              </h5>
              <div class="small">
                <div class="d-flex justify-content-between mb-2">
                  <span>Total Due:</span>
                  <strong>UGX <?= number_format((float)$installment['amount_due'], 0) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span>Amount Paid:</span>
                  <strong>UGX <?= number_format((float)$installment['amount_paid'], 0) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span>Remaining:</span>
                  <strong class="<?= ($installment['amount_due'] - $installment['amount_paid']) > 0 ? 'text-danger' : 'text-success' ?>">
                    UGX <?= number_format($installment['amount_due'] - $installment['amount_paid'], 0) ?>
                  </strong>
                </div>
                <div class="progress mb-2" style="height: 8px;">
                  <?php 
                  $percentage = $installment['amount_due'] > 0 ? ($installment['amount_paid'] / $installment['amount_due']) * 100 : 0;
                  $percentage = min(100, max(0, $percentage));
                  ?>
                  <div class="progress-bar <?= $percentage >= 100 ? 'bg-success' : ($percentage >= 50 ? 'bg-warning' : 'bg-danger') ?>" 
                       role="progressbar" style="width: <?= $percentage ?>%">
                  </div>
                </div>
                <div class="text-center">
                  <small class="text-muted"><?= round($percentage) ?>% Paid</small>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set minimum date to today
    const dueDateInput = document.getElementById('due_date');
    const today = new Date().toISOString().split('T')[0];
    dueDateInput.min = today;
    
    // Format amount inputs
    const amountInputs = ['amount_due', 'amount_paid'];
    amountInputs.forEach(id => {
        const input = document.getElementById(id);
        input.addEventListener('blur', function() {
            if (this.value && !isNaN(this.value)) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
    });
});

function confirmDelete() {
    if (confirm('Are you sure you want to delete this installment? This action cannot be undone.')) {
        window.location.href = '<?= $GLOBALS['BASE_URL'] ?>/modules/installments/actions.php?action=delete&id=<?= (int)$id ?>';
    }
}
</script>

<?php require_once __DIR__ . '/../../templates/layout/footer.php'; ?>
