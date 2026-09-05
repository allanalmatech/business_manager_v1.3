<?php
// modules/products/suppliers.php
// Dedicated supplier management for the product entry workflow:
// suppliers auto-saved from the Add Product form can be enriched here (email, phone, company, ...).
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/audit.php';

require_permission('products.view');

$db = $GLOBALS['db'] ?? null;
if (!($db instanceof mysqli)) die('Database not available');

$page_title    = 'Suppliers';
$page_subtitle = 'Manage the suppliers you enter on products — add contact & payment details later';

$BASE_URL = $GLOBALS['BASE_URL'] ?? '';

$message      = '';
$message_type = '';
$search       = trim((string)($_GET['search'] ?? ''));
$status       = trim((string)($_GET['status'] ?? ''));
$page         = max(1, (int)($_GET['page'] ?? 1));
$per_page     = 50;
$offset       = ($page - 1) * $per_page;

$where = ["1=1"];
$params = [];
$types = '';

if ($search !== '') {
  $where[] = "(name LIKE ? OR company_name LIKE ? OR contact_person LIKE ? OR email LIKE ? OR phone LIKE ?)";
  $like = "%$search%";
  for ($i = 0; $i < 5; $i++) $params[] = $like;
  $types .= 'sssss';
}
if (in_array($status, ['active', 'inactive', 'suspended'], true)) {
  $where[] = "status = ?";
  $params[] = $status;
  $types .= 's';
}
$where_sql = implode(' AND ', $where);

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $act = (string)($_POST['action'] ?? '');

  if ($act === 'update_supplier' && user_has_permission('products.update')) {
    $id = (int)($_POST['supplier_id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $company_name = trim((string)($_POST['company_name'] ?? ''));
    $contact_person = trim((string)($_POST['contact_person'] ?? ''));
    $city = trim((string)($_POST['city'] ?? ''));
    $state = trim((string)($_POST['state'] ?? ''));
    $country = trim((string)($_POST['country'] ?? ''));
    $category = trim((string)($_POST['category'] ?? ''));
    $payment_terms = trim((string)($_POST['payment_terms'] ?? ''));
    $preferred = isset($_POST['preferred']) ? 1 : 0;

    if ($id <= 0 || $name === '') {
      $message = 'Name is required';
      $message_type = 'danger';
    } else {
      if ($email === '') {
        $email = 'noreply-' . strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $name)) . '@local';
      }
      $upd = $db->prepare("UPDATE suppliers SET name=?, email=?, phone=?, company_name=?, contact_person=?, city=?, state=?, country=?, category=?, payment_terms=?, preferred=? WHERE id=?");
      if ($upd) {
        $upd->bind_param('ssssssssssii', $name, $email, $phone, $company_name, $contact_person, $city, $state, $country, $category, $payment_terms, $preferred, $id);
        if ($upd->execute()) {
          audit_log('products.supplier_update', 'supplier', (string)$id, "Updated supplier: $name");
          $message = 'Supplier updated successfully';
          $message_type = 'success';
        } else {
          $message = 'Update failed: ' . $db->error;
          $message_type = 'danger';
        }
        $upd->close();
      } else {
        $message = 'Suppliers table not found.';
        $message_type = 'danger';
      }
    }
  }

  if ($act === 'delete_supplier' && user_has_permission('products.delete')) {
    $id = (int)($_POST['supplier_id'] ?? 0);
    if ($id > 0) {
      $chk = $db->prepare("SELECT COUNT(*) c FROM products WHERE source = (SELECT name FROM suppliers WHERE id=?)");
      if ($chk) {
        $chk->bind_param('i', $id);
        $chk->execute();
        $used = (int)$chk->get_result()->fetch_assoc()['c'];
        $chk->close();
        if ($used > 0) {
          $message = "Cannot delete — this supplier is used by $used product(s).";
          $message_type = 'danger';
        } else {
          $del = $db->prepare("DELETE FROM suppliers WHERE id=?");
          if ($del) {
            $del->bind_param('i', $id);
            $del->execute();
            $del->close();
            audit_log('products.supplier_delete', 'supplier', (string)$id, 'Supplier deleted');
            $message = 'Supplier deleted';
            $message_type = 'success';
          }
        }
      }
    }
  }
}

// Totals
$cnt_sql = "SELECT COUNT(*) c FROM suppliers WHERE $where_sql";
$st = $db->prepare($cnt_sql);
if ($st && $types !== '') $st->bind_param($types, ...$params);
$total = 0;
if ($st) { $st->execute(); $total = (int)$st->get_result()->fetch_assoc()['c']; $st->close(); }
$total_pages = max(1, ceil($total / $per_page));

// Rows
$sql = "SELECT id, name, email, phone, company_name, contact_person, city, state, country, category, payment_terms, status, preferred, rating, created_at FROM suppliers WHERE $where_sql ORDER BY name ASC LIMIT ? OFFSET ?";
$stmt = $db->prepare($sql);
$rows = [];
if ($stmt) {
  $bind = $params;
  array_push($bind, $per_page, $offset);
  $bt = $types . 'ii';
  $stmt->bind_param($bt, ...$bind);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}

// Quick-guide: suppliers that only have a name (created while adding a product)
$incomplete = 0;
$q = $db->query("SELECT COUNT(*) c FROM suppliers WHERE (email='' OR email LIKE 'noreply-%@local')");
if ($q) $incomplete = (int)$q->fetch_assoc()['c'];

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
            <h4 class="mb-1"><?= h($page_title) ?></h4>
            <div class="text-muted small"><?= h($page_subtitle) ?></div>
          </div>
        </div>

        <?php if ($incomplete > 0): ?>
          <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            <strong><?= (int)$incomplete ?></strong> supplier(s) were created automatically while adding products. Select <b>Edit</b> to fill in their email, phone, company and other details.
          </div>
        <?php endif; ?>

        <?php if ($message): ?>
          <div class="alert alert-<?= h($message_type) ?> alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
              <i class="bi bi-<?= $message_type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i>
              <?= h($message) ?>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <!-- Search / Filter -->
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-light">
            <h6 class="mb-0"><i class="bi bi-funnel"></i> Search &amp; Filter</h6>
          </div>
          <div class="card-body">
            <form method="get" class="row g-3">
              <div class="col-md-5">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" value="<?= h($search) ?>" placeholder="Name, company, contact person, email, phone...">
              </div>
              <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                  <option value="">All</option>
                  <?php foreach (['active', 'inactive', 'suspended'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                  <button class="btn btn-primary"><i class="bi bi-search"></i> Search</button>
                  <a href="?" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i> Clear</a>
                </div>
              </div>
            </form>
          </div>
        </div>

        <!-- Table -->
        <div class="card shadow-sm">
          <div class="card-header bg-light">
            <h6 class="mb-0">
              <i class="bi bi-building"></i> Suppliers
              <span class="badge bg-primary rounded-pill float-end"><?= $total ?></span>
            </h6>
          </div>
          <div class="card-body p-0">
            <?php if (empty($rows)): ?>
              <div class="text-center py-5">
                <i class="bi bi-building text-muted" style="font-size: 3rem;"></i>
                <h5 class="mt-3">No suppliers found</h5>
                <p class="text-muted">Suppliers you type into the Add Product form appear here.</p>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Name</th>
                      <th>Contact Person</th>
                      <th>Phone</th>
                      <th>Email</th>
                      <th>Company</th>
                      <th>City</th>
                      <th>Category</th>
                      <th>Payment Terms</th>
                      <th>Status</th>
                      <th class="text-center">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($rows as $r): ?>
                      <tr>
                        <td>
                          <div class="fw-semibold"><?= h($r['name']) ?></div>
                          <?php if ($r['preferred']): ?><span class="badge bg-warning"><i class="bi bi-star"></i> Preferred</span><?php endif; ?>
                          <?php if ($r['email'] === '' || strpos($r['email'], 'noreply-') === 0): ?>
                            <span class="badge bg-light text-muted border">Needs details</span>
                          <?php endif; ?>
                        </td>
                        <td><?= h($r['contact_person'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
                        <td><?= h($r['phone'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
                        <td class="text-truncate" style="max-width:180px;"><?= h($r['email'] ?? '') ?></td>
                        <td><?= h($r['company_name'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
                        <td><?= h($r['city'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
                        <td><?= h($r['category'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
                        <td><?= h($r['payment_terms'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
                        <td>
                          <span class="badge bg-<?= $r['status'] === 'active' ? 'success' : ($r['status'] === 'suspended' ? 'danger' : 'warning') ?>">
                            <?= h(ucfirst($r['status'])) ?>
                          </span>
                        </td>
                        <td class="text-center">
                          <div class="btn-group btn-group-sm">
                            <?php if (user_has_permission('products.update')): ?>
                              <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= (int)$r['id'] ?>" title="Edit details">
                                <i class="bi bi-pencil"></i>
                              </button>
                            <?php endif; ?>
                            <?php if (user_has_permission('products.delete')): ?>
                              <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= (int)$r['id'] ?>" title="Delete">
                                <i class="bi bi-trash"></i>
                              </button>
                            <?php endif; ?>
                          </div>
                        </td>
                      </tr>

                      <!-- Edit Modal -->
                      <div class="modal fade" id="editModal<?= (int)$r['id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                          <div class="modal-content">
                            <form method="post">
                              <input type="hidden" name="action" value="update_supplier">
                              <input type="hidden" name="supplier_id" value="<?= (int)$r['id'] ?>">
                              <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title">Edit Supplier — <?= h($r['name']) ?></h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                              </div>
                              <div class="modal-body">
                                <div class="row g-3">
                                  <div class="col-md-6">
                                    <label class="form-label">Name *</label>
                                    <input class="form-control" name="name" required value="<?= h($r['name']) ?>">
                                  </div>
                                  <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" value="<?= h($r['email']) ?>">
                                  </div>
                                  <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input class="form-control" name="phone" value="<?= h($r['phone'] ?? '') ?>">
                                  </div>
                                  <div class="col-md-6">
                                    <label class="form-label">Company Name</label>
                                    <input class="form-control" name="company_name" value="<?= h($r['company_name'] ?? '') ?>">
                                  </div>
                                  <div class="col-md-6">
                                    <label class="form-label">Contact Person</label>
                                    <input class="form-control" name="contact_person" value="<?= h($r['contact_person'] ?? '') ?>">
                                  </div>
                                  <div class="col-md-6">
                                    <label class="form-label">Category</label>
                                    <input class="form-control" name="category" value="<?= h($r['category'] ?? '') ?>" placeholder="e.g. Electronics, Stationery">
                                  </div>
                                  <div class="col-md-4">
                                    <label class="form-label">City</label>
                                    <input class="form-control" name="city" value="<?= h($r['city'] ?? '') ?>">
                                  </div>
                                  <div class="col-md-4">
                                    <label class="form-label">State</label>
                                    <input class="form-control" name="state" value="<?= h($r['state'] ?? '') ?>">
                                  </div>
                                  <div class="col-md-4">
                                    <label class="form-label">Country</label>
                                    <input class="form-control" name="country" value="<?= h($r['country'] ?? '') ?>">
                                  </div>
                                  <div class="col-md-6">
                                    <label class="form-label">Payment Terms</label>
                                    <input class="form-control" name="payment_terms" value="<?= h($r['payment_terms'] ?? '') ?>" placeholder="e.g. Net 30">
                                  </div>
                                  <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                      <?php foreach (['active', 'inactive', 'suspended'] as $s): ?>
                                        <option value="<?= $s ?>" <?= ($r['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                      <?php endforeach; ?>
                                    </select>
                                  </div>
                                  <div class="col-12">
                                    <div class="form-check form-switch">
                                      <input class="form-check-input" type="checkbox" id="preferred<?= (int)$r['id'] ?>" name="preferred" <?= $r['preferred'] ? 'checked' : '' ?>>
                                      <label class="form-check-label" for="preferred<?= (int)$r['id'] ?>">Preferred supplier</label>
                                    </div>
                                  </div>
                                </div>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button class="btn btn-primary">Save Changes</button>
                              </div>
                            </form>
                          </div>
                        </div>
                      </div>

                      <!-- Delete Modal -->
                      <div class="modal fade" id="deleteModal<?= (int)$r['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                          <div class="modal-content">
                            <form method="post">
                              <input type="hidden" name="action" value="delete_supplier">
                              <input type="hidden" name="supplier_id" value="<?= (int)$r['id'] ?>">
                              <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title">Delete Supplier</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                              </div>
                              <div class="modal-body">
                                <p>Delete <strong><?= h($r['name']) ?></strong>?</p>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button class="btn btn-danger">Delete</button>
                              </div>
                            </form>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>

          <?php if ($total_pages > 1): ?>
            <div class="card-footer">
              <nav>
                <ul class="pagination pagination-sm mb-0 justify-content-center">
                  <?php
                  $qs = $_GET;
                  unset($qs['page']);
                  $base = '?' . http_build_query($qs) . '&page=';
                  ?>
                  <?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="<?= h($base . ($page - 1)) ?>">Prev</a></li><?php endif; ?>
                  <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= h($base . $i) ?>"><?= $i ?></a></li>
                  <?php endfor; ?>
                  <?php if ($page < $total_pages): ?><li class="page-item"><a class="page-link" href="<?= h($base . ($page + 1)) ?>">Next</a></li><?php endif; ?>
                </ul>
              </nav>
            </div>
          <?php endif; ?>
        </div>

      </div>
    </main>
  </div>
</div>

<?php include __DIR__ . '/../../templates/layout/footer.php'; ?>