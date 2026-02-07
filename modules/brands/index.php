<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_permission('brands.view');

$db = $GLOBALS['db'];

$page_title = 'Brands';
$page_subtitle = 'Manage product brands and manufacturers';

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Check if brands table exists
$hasBrands = false;
$res = $db->query("SHOW TABLES LIKE 'brands'");
if ($res && $res->num_rows > 0) {
    $hasBrands = true;
}

// Build WHERE clause
$where = [];
$types = '';
$params = [];

if ($q !== '') {
    $where[] = "(name LIKE CONCAT('%',?,'%') OR description LIKE CONCAT('%',?,'%'))";
    $types .= 'ss';
    $params[] = $q;
    $params[] = $q;
}

if ($status !== '') {
    $where[] = 'status = ?';
    $types .= 's';
    $params[] = $status;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Get total count
$total = 0;
if ($hasBrands) {
    $countSql = "SELECT COUNT(*) AS cnt FROM brands $whereSql";
    $st = $db->prepare($countSql);
    if ($types !== '') {
        $st->bind_param($types, ...$params);
    }
    $st->execute();
    $res = $st->get_result();
    $total = (int)($res->fetch_assoc()['cnt'] ?? 0);
    $st->close();
}

// Get brands
$brands = [];
if ($hasBrands) {
    $sql = "SELECT * FROM brands $whereSql ORDER BY name ASC LIMIT ? OFFSET ?";
    $st = $db->prepare($sql);
    $paramTypes = $types . 'ii';
    $paramValues = [...$params, $perPage, $offset];
    $st->bind_param($paramTypes, ...$paramValues);
    $st->execute();
    $brands = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
}

$totalPages = ceil($total / $perPage);

$page_title = 'Brands';
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
          <div class="gap-2 d-flex">
            <a href="<?= $GLOBALS['BASE_URL'] ?>/modules/brands/create.php" class="btn btn-primary">
              <i class="bi bi-plus-circle"></i> New Brand
            </a>
            <?php if ($hasBrands): ?>
              <a class="btn btn-outline-secondary" href="?<?= h(http_build_query(array_merge($_GET, ['export'=>'csv']))) ?>">
                <i class="bi bi-download"></i> Export CSV
              </a>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!$hasBrands): ?>
          <div class="alert alert-warning">
            <div class="d-flex align-items-center">
              <i class="bi bi-exclamation-triangle-fill me-3" style="font-size: 1.2rem;"></i>
              <div>
                <strong>Brands table not found.</strong> Please run the migration to create it.
              </div>
            </div>
          </div>
        <?php else: ?>

          <!-- Search and Filter -->
          <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
              <h6 class="mb-0"><i class="bi bi-funnel"></i> Search & Filter Brands</h6>
            </div>
            <div class="card-body">
              <form method="get" class="row g-3">
                <div class="col-md-4">
                  <label for="q" class="form-label">Search</label>
                  <input type="text" id="q" name="q" value="<?= h($q) ?>" class="form-control" placeholder="Search brand name or description">
                </div>
                <div class="col-md-3">
                  <label for="status" class="form-label">Status</label>
                  <select id="status" name="status" class="form-select">
                    <option value="">All statuses</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                  </select>
                </div>
                <div class="col-md-5">
                  <label class="form-label">&nbsp;</label>
                  <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                      <i class="bi bi-search"></i> Search
                    </button>
                    <a href="?" class="btn btn-outline-secondary">
                      <i class="bi bi-x-circle"></i> Clear
                    </a>
                  </div>
                </div>
              </form>
            </div>
          </div>

          <!-- Brands Table -->
          <div class="card shadow-sm">
            <div class="card-header bg-light">
              <h6 class="mb-0">
                <i class="bi bi-tags"></i> Brands 
                <span class="badge bg-secondary ms-2"><?= number_format($total) ?></span>
              </h6>
            </div>
            <div class="card-body p-0">
              <?php if (empty($brands)): ?>
                <div class="text-center py-5">
                  <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                  <h5 class="mt-3">No brands found</h5>
                  <p class="text-muted">Try adjusting your search criteria or create a new brand.</p>
                  <a href="<?= $GLOBALS['BASE_URL'] ?>/modules/brands/create.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Create Brand
                  </a>
                </div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-hover mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($brands as $brand): ?>
                        <tr>
                          <td>
                            <div class="fw-semibold"><?= h($brand['name']) ?></div>
                          </td>
                          <td>
                            <code class="text-muted"><?= h($brand['slug']) ?></code>
                          </td>
                          <td>
                            <div class="text-truncate" style="max-width: 200px;" title="<?= h($brand['description'] ?? '') ?>">
                              <?= h(substr($brand['description'] ?? '', 0, 100)) ?>
                              <?php if (strlen($brand['description'] ?? '') > 100): ?>...<?php endif; ?>
                            </div>
                          </td>
                          <td>
                            <span class="badge bg-<?= $brand['status'] === 'active' ? 'success' : 'secondary' ?>">
                              <?= h(ucfirst($brand['status'])) ?>
                            </span>
                          </td>
                          <td>
                            <small class="text-muted"><?= h(date('M j, Y', strtotime($brand['created_at']))) ?></small>
                          </td>
                          <td>
                            <div class="btn-group btn-group-sm" role="group">
                              <a href="<?= $GLOBALS['BASE_URL'] ?>/modules/brands/view.php?id=<?= (int)$brand['id'] ?>" 
                                 class="btn btn-outline-primary" title="View">
                                <i class="bi bi-eye"></i>
                              </a>
                              <a href="<?= $GLOBALS['BASE_URL'] ?>/modules/brands/edit.php?id=<?= (int)$brand['id'] ?>" 
                                 class="btn btn-outline-secondary" title="Edit">
                                <i class="bi bi-pencil"></i>
                              </a>
                              <button type="button" class="btn btn-outline-danger" 
                                      onclick="confirmDelete(<?= (int)$brand['id'] ?>, '<?= h($brand['name']) ?>')" title="Delete">
                                <i class="bi bi-trash"></i>
                              </button>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                  <div class="card-footer">
                    <nav>
                      <ul class="pagination pagination-sm mb-0 justify-content-center">
                        <?php
                        $currentUrl = strtok($_SERVER['REQUEST_URI'], '?');
                        $queryParams = $_GET;
                        unset($queryParams['page']);
                        $baseUrl = $currentUrl . '?' . http_build_query($queryParams);
                        ?>

                        <?php if ($page > 1): ?>
                          <li class="page-item">
                            <a class="page-link" href="<?= h($baseUrl . '&page=' . ($page - 1)) ?>">Previous</a>
                          </li>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                          <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= h($baseUrl . '&page=' . $i) ?>"><?= $i ?></a>
                          </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                          <li class="page-item">
                            <a class="page-link" href="<?= h($baseUrl . '&page=' . ($page + 1)) ?>">Next</a>
                          </li>
                        <?php endif; ?>
                      </ul>
                    </nav>
                  </div>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>

<script>
function confirmDelete(id, name) {
  if (confirm('Are you sure you want to delete the brand "' + name + '"? This action cannot be undone.')) {
    window.location.href = '<?= $GLOBALS['BASE_URL'] ?>/modules/brands/delete.php?id=' + id;
  }
}
</script>

<?php include __DIR__ . '/../../templates/layout/footer.php'; ?>
