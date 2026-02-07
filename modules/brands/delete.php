<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_permission('brands.delete');

$db = $GLOBALS['db'];

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

// Check if brand has products
$productCount = 0;
$productsCheck = $db->query("SHOW TABLES LIKE 'products'");
if ($productsCheck && $productsCheck->num_rows > 0) {
    $countSt = $db->prepare("SELECT COUNT(*) as count FROM products WHERE brand_id = ?");
    $countSt->bind_param('i', $id);
    $countSt->execute();
    $productCount = (int)($countSt->get_result()->fetch_assoc()['count'] ?? 0);
    $countSt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    if ($productCount > 0) {
        $_SESSION['flash_message'] = 'Cannot delete brand with associated products. Please reassign or delete the products first.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: ' . $GLOBALS['BASE_URL'] . '/modules/brands/view.php?id=' . $id);
        exit;
    }

    try {
        // Delete brand
        $delete = $db->prepare("DELETE FROM brands WHERE id = ?");
        $delete->bind_param('i', $id);
        
        if ($delete->execute()) {
            // Log action
            if (function_exists('audit_log')) {
                audit_log('brands.delete', 'brands', (string)$id, "Deleted brand: {$brand['name']}");
            }
            
            $_SESSION['flash_message'] = 'Brand deleted successfully';
            $_SESSION['flash_type'] = 'success';
            header('Location: ' . $GLOBALS['BASE_URL'] . '/modules/brands/index.php');
            exit;
        } else {
            $_SESSION['flash_message'] = 'Error deleting brand: ' . $db->error;
            $_SESSION['flash_type'] = 'danger';
            header('Location: ' . $GLOBALS['BASE_URL'] . '/modules/brands/view.php?id=' . $id);
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error deleting brand: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'danger';
        header('Location: ' . $GLOBALS['BASE_URL'] . '/modules/brands/view.php?id=' . $id);
        exit;
    }
}

$page_title = 'Delete Brand';
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
            <div class="text-muted small">Confirm brand deletion</div>
          </div>
          <a href="<?= $GLOBALS['BASE_URL'] ?>/modules/brands/view.php?id=<?= (int)$id ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Brand
          </a>
        </div>

        <?php if ($productCount > 0): ?>
          <div class="alert alert-danger">
            <div class="d-flex align-items-center">
              <i class="bi bi-exclamation-triangle-fill me-3" style="font-size: 1.5rem;"></i>
              <div>
                <h5 class="mb-1">Cannot Delete Brand</h5>
                <p class="mb-0">This brand has <?= number_format($productCount) ?> associated product(s). You must reassign or delete these products before deleting the brand.</p>
              </div>
            </div>
          </div>
          
          <div class="card shadow-sm">
            <div class="card-header bg-light">
              <h6 class="mb-0">Associated Products</h6>
            </div>
            <div class="card-body">
              <p class="text-muted">This brand is currently associated with the following products:</p>
              
              <?php
              $productSt = $db->prepare("SELECT id, name, sku FROM products WHERE brand_id = ? ORDER BY name LIMIT 20");
              $productSt->bind_param('i', $id);
              $productSt->execute();
              $products = $productSt->get_result()->fetch_all(MYSQLI_ASSOC);
              $productSt->close();
              ?>
              
              <div class="table-responsive">
                <table class="table table-sm">
                  <thead>
                    <tr>
                      <th>Product Name</th>
                      <th>SKU</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($products as $product): ?>
                      <tr>
                        <td><?= h($product['name']) ?></td>
                        <td><code><?= h($product['sku']) ?></code></td>
                        <td>
                          <a href="<?= $GLOBALS['BASE_URL'] ?>/modules/products/edit.php?id=<?= (int)$product['id'] ?>" 
                             class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil"></i> Edit Product
                          </a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              
              <?php if ($productCount > 20): ?>
                <p class="text-muted small mb-0">... and <?= number_format($productCount - 20) ?> more products</p>
              <?php endif; ?>
            </div>
          </div>
          
        <?php else: ?>
          <div class="card shadow-sm">
            <div class="card-body text-center py-5">
              <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
              <h5 class="mt-3">Delete Brand?</h5>
              <p class="text-muted">Are you sure you want to delete the brand "<strong><?= h($brand['name']) ?></strong>"?</p>
              <p class="text-muted small">This action cannot be undone.</p>
              
              <form method="post" class="mt-4">
                <input type="hidden" name="confirm_delete" value="1">
                <button type="submit" class="btn btn-danger">
                  <i class="bi bi-trash"></i> Delete Brand
                </button>
                <a href="<?= $GLOBALS['BASE_URL'] ?>/modules/brands/view.php?id=<?= (int)$id ?>" class="btn btn-outline-secondary ms-2">
                  <i class="bi bi-x-circle"></i> Cancel
                </a>
              </form>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>

<?php include __DIR__ . '/../../templates/layout/footer.php'; ?>
