<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_permission('brands.edit');

$db = $GLOBALS['db'];

$page_title = 'Edit Brand';
$page_subtitle = 'Update brand information';

$message = '';
$message_type = '';

$id = (int)($_GET['id'] ?? 0);

// Validate ID
if ($id <= 0) {
    $_SESSION['flash_message'] = 'Invalid brand ID';
    $_SESSION['flash_type'] = 'danger';
    header('Location: ' . $GLOBALS['BASE_URL'] . '/modules/brands/index.php');
    exit;
}

// Get brand data
$brand = null;
$st = $db->prepare("SELECT * FROM brands WHERE id = ? LIMIT 1");
if ($st) {
    $st->bind_param('i', $id);
    $st->execute();
    $result = $st->get_result();
    $brand = $result->fetch_assoc();
    $st->close();
}

if (!$brand) {
    $_SESSION['flash_message'] = 'Brand not found';
    $_SESSION['flash_type'] = 'danger';
    header('Location: ' . $GLOBALS['BASE_URL'] . '/modules/brands/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $slug = trim((string)($_POST['slug'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $status = trim((string)($_POST['status'] ?? 'active'));

    // Validation
    if (empty($name)) {
        $message = 'Brand name is required';
        $message_type = 'danger';
    } else {
        // Generate slug if not provided
        if (empty($slug)) {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
            $slug = trim($slug, '-');
        }

        // Check if slug already exists (excluding current brand)
        $check = $db->prepare("SELECT id FROM brands WHERE slug = ? AND id != ? LIMIT 1");
        $check->bind_param('si', $slug, $id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $message = 'Brand with this slug already exists';
            $message_type = 'danger';
        } else {
            // Update brand
            $update = $db->prepare("UPDATE brands SET name = ?, slug = ?, description = ?, status = ?, updated_at = NOW() WHERE id = ?");
            $update->bind_param('ssssi', $name, $slug, $description, $status, $id);
            
            if ($update->execute()) {
                $message = 'Brand updated successfully';
                $message_type = 'success';
                
                // Log action
                if (function_exists('audit_log')) {
                    audit_log('brands.edit', 'brands', (string)$id, "Updated brand: $name");
                }
                
                // Redirect to brands list
                header('Location: ' . $GLOBALS['BASE_URL'] . '/modules/brands/index.php');
                exit;
            } else {
                $message = 'Error updating brand: ' . $db->error;
                $message_type = 'danger';
            }
        }
    }
}

$page_title = 'Edit Brand';
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
          <a href="<?= $GLOBALS['BASE_URL'] ?>/modules/brands/index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Brands
          </a>
        </div>

        <?php if ($message): ?>
          <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
              <i class="bi bi-<?= $message_type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i>
              <?= h($message) ?>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <div class="card shadow-sm">
          <div class="card-header bg-primary text-white">
            <h6 class="mb-0">
              <i class="bi bi-pencil"></i> Edit Brand Information
            </h6>
          </div>
          <div class="card-body">
            <form method="post">
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="name" class="form-label">Brand Name *</label>
                  <input type="text" class="form-control" id="name" name="name" 
                         value="<?= h($_POST['name'] ?? $brand['name']) ?>" required>
                </div>
                <div class="col-md-6">
                  <label for="slug" class="form-label">Slug</label>
                  <div class="input-group">
                    <input type="text" class="form-control" id="slug" name="slug" 
                           value="<?= h($_POST['slug'] ?? $brand['slug']) ?>" 
                           placeholder="Auto-generated from name">
                    <button type="button" class="btn btn-outline-secondary" onclick="generateSlug()">
                      <i class="bi bi-arrow-clockwise"></i> Generate
                    </button>
                  </div>
                  <small class="text-muted">URL-friendly version of the brand name</small>
                </div>
                <div class="col-12">
                  <label for="description" class="form-label">Description</label>
                  <textarea class="form-control" id="description" name="description" rows="4"><?= h($_POST['description'] ?? $brand['description']) ?></textarea>
                </div>
                <div class="col-md-6">
                  <label for="status" class="form-label">Status</label>
                  <select class="form-select" id="status" name="status">
                    <option value="active" <?= (($_POST['status'] ?? $brand['status']) === 'active') ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= (($_POST['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Brand ID</label>
                  <div class="form-control-plaintext">
                    #<?= (int)$brand['id'] ?>
                  </div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Created</label>
                  <div class="form-control-plaintext">
                    <?= h(date('M j, Y H:i', strtotime($brand['created_at']))) ?>
                  </div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Last Updated</label>
                  <div class="form-control-plaintext">
                    <?= h(date('M j, Y H:i', strtotime($brand['updated_at']))) ?>
                  </div>
                </div>
              </div>
              
              <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-check-circle"></i> Update Brand
                </button>
                <a href="<?= $GLOBALS['BASE_URL'] ?>/modules/brands/index.php" class="btn btn-outline-secondary ms-2">
                  <i class="bi bi-x-circle"></i> Cancel
                </a>
                <a href="<?= $GLOBALS['BASE_URL'] ?>/modules/brands/view.php?id=<?= (int)$brand['id'] ?>" class="btn btn-outline-info ms-2">
                  <i class="bi bi-eye"></i> View Brand
                </a>
              </div>
            </form>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<script>
function generateSlug() {
  const name = document.getElementById('name').value;
  if (name.trim()) {
    const slug = name.toLowerCase()
      .replace(/[^a-z0-9\s-]/g, '')
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-')
      .trim('-');
    document.getElementById('slug').value = slug;
  }
}

// Auto-generate slug when name changes
document.getElementById('name').addEventListener('input', function() {
  if (!document.getElementById('slug').value) {
    generateSlug();
  }
});
</script>

<?php include __DIR__ . '/../../templates/layout/footer.php'; ?>
