<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/audit.php';

require_permission('installments.create');

$db = $GLOBALS['db'];
$user_id = $_SESSION['user_id'] ?? 1;

$page_title = 'Create Installment';
$page_subtitle = 'Add a new installment schedule';

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $contact_id = (int)($_POST['contact_id'] ?? 0);
        $amount_due = (float)($_POST['amount_due'] ?? 0);
        $due_date = trim((string)($_POST['due_date'] ?? ''));
        $reference = trim((string)($_POST['reference'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        // Validation
        if ($contact_id <= 0) {
            throw new Exception('Please select a contact');
        }
        if ($amount_due <= 0) {
            throw new Exception('Amount due must be greater than 0');
        }
        if (empty($due_date)) {
            throw new Exception('Due date is required');
        }

        // Insert installment
        $st = $db->prepare("
            INSERT INTO installments (contact_id, user_id, amount_due, amount_paid, due_date, reference, notes, status, created_at) 
            VALUES (?, ?, ?, 0.00, ?, ?, ?, 'active', NOW())
        ");
        if (!$st) {
            throw new Exception('Database prepare failed: ' . $db->error);
        }
        
        $st->bind_param('iidsss', $contact_id, $user_id, $amount_due, $due_date, $reference, $notes);
        $st->execute();
        $installment_id = $st->insert_id;
        $st->close();

        // Log the action
        audit_log('installments.create', 'installment', (string)$installment_id, "Created installment for contact ID $contact_id");

        $_SESSION['flash_message'] = 'Installment created successfully';
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
        <a href="<?= $GLOBALS['BASE_URL'] ?>/modules/installments/installments.php" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-left"></i> Back to Installments
        </a>
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
            <div class="card-body p-4">
              <form method="POST" action="" id="installmentForm">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label for="contact_id" class="form-label">Contact <span class="text-danger">*</span></label>
                    <?php if ($hasContacts): ?>
                      <select class="form-select" id="contact_id" name="contact_id" required>
                        <option value="">Select Contact</option>
                        <?php foreach ($contacts as $contact): ?>
                          <option value="<?= (int)$contact['id'] ?>"><?= h($contact['name']) ?>
                            <?php if ($contact['phone']): ?> (<?= h($contact['phone']) ?>)<?php endif; ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    <?php else: ?>
                      <input type="text" class="form-control" id="contact_name" name="contact_name" 
                             placeholder="Enter contact name" required>
                      <small class="text-muted">Customers table not found. Enter customer name manually.</small>
                    <?php endif; ?>
                  </div>

                  <div class="col-md-6">
                    <label for="amount_due" class="form-label">Amount Due (UGX) <span class="text-danger">*</span></label>
                    <div class="input-group">
                      <span class="input-group-text">UGX</span>
                      <input type="number" class="form-control" id="amount_due" name="amount_due" 
                             step="0.01" min="0" required value="<?= h($_POST['amount_due'] ?? '') ?>">
                    </div>
                  </div>

                  <div class="col-md-6">
                    <label for="due_date" class="form-label">Due Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="due_date" name="due_date" 
                           required value="<?= h($_POST['due_date'] ?? '') ?>">
                  </div>

                  <div class="col-md-6">
                    <label for="reference" class="form-label">Reference</label>
                    <input type="text" class="form-control" id="reference" name="reference" 
                           placeholder="Invoice number, contract reference, etc." 
                           value="<?= h($_POST['reference'] ?? '') ?>">
                  </div>

                  <div class="col-12">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="4" 
                              placeholder="Additional details about this installment..."><?= h($_POST['notes'] ?? '') ?></textarea>
                  </div>

                  <div class="col-12">
                    <div class="d-flex gap-2">
                      <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Create Installment
                      </button>
                      <a href="<?= $GLOBALS['BASE_URL'] ?>/modules/installments/installments.php" class="btn btn-outline-secondary">
                        Cancel
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
                <i class="bi bi-info-circle text-primary"></i> Quick Help
              </h5>
              <div class="small text-muted">
                <p class="mb-2">
                  <strong>Installments</strong> allow you to track payment schedules for customers who pay in multiple installments.
                </p>
                <ul class="mb-0">
                  <li>Select an existing contact or create a new one</li>
                  <li>Set the total amount due and due date</li>
                  <li>Add a reference for easy tracking</li>
                  <li>Record partial payments as they come in</li>
                </ul>
              </div>
            </div>
          </div>

          <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
              <h5 class="card-title mb-3">
                <i class="bi bi-lightning text-warning"></i> Recent Activity
              </h5>
              <?php
              $recent = $db->query("
                  SELECT i.id, c.name as contact_name, i.amount_due, i.due_date, i.created_at
                  FROM installments i 
                  LEFT JOIN customers c ON c.id = i.contact_id 
                  ORDER BY i.created_at DESC 
                  LIMIT 5
              ");
              if ($recent && $recent->num_rows > 0):
              ?>
                <div class="small">
                  <?php while ($row = $recent->fetch_assoc()): ?>
                    <div class="mb-2 pb-2 border-bottom">
                      <div class="d-flex justify-content-between">
                        <strong><?= h($row['contact_name'] ?? 'Unknown') ?></strong>
                        <span class="text-muted"><?= date('M j', strtotime($row['created_at'])) ?></span>
                      </div>
                      <div class="text-muted">
                        UGX <?= number_format((float)$row['amount_due'], 0) ?> • Due <?= date('M j, Y', strtotime($row['due_date'])) ?>
                      </div>
                    </div>
                  <?php endwhile; ?>
                </div>
              <?php else: ?>
                <p class="text-muted small mb-0">No recent installments found.</p>
              <?php endif; ?>
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
    
    // Format amount input
    const amountInput = document.getElementById('amount_due');
    amountInput.addEventListener('blur', function() {
        if (this.value && !isNaN(this.value)) {
            this.value = parseFloat(this.value).toFixed(2);
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../templates/layout/footer.php'; ?>
