<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_permission('brands.view');

$db = $GLOBALS['db'];

$page_title = 'Brand Details';
$page_subtitle = 'View brand information and related products';

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

// Get related products count
$productCount = 0;
$products = [];
$hasProducts = false;

// Check if products table exists
$productsCheck = $db->query("SHOW TABLES LIKE 'products'");
if ($productsCheck && $productsCheck->num_rows > 0) {
    $hasProducts = true;
    
    // Get product count
    $countSt = $db->prepare("SELECT COUNT(*) as count FROM products WHERE brand_id = ?");
    $countSt->bind_param('i', $id);
    $countSt->execute();
    $productCount = (int)($countSt->get_result()->fetch_assoc()['count'] ?? 0);
    $countSt->close();
    
    // Get recent products
    if ($productCount > 0) {
        $productSt = $db->prepare("
            SELECT id, name, sku, price, stock_quantity, status 
            FROM products 
            WHERE brand_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $productSt->bind_param('i', $id);
        $productSt->execute();
        $products = $productSt->get_result()->fetch_all(MYSQLI_ASSOC);
        $productSt->close();
    }
}

$page_title = 'Brand Details';
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
            <h4 class="mb-1"><?= h($page_title) ?> #<?= h((string)$brand['id']) ?></h4>
            <div class="text-muted small"><?= h($page_subtitle) ?></div>
          </div>
          <div class="d-flex gap-2">
            <a href="<?= $GLOBALS['BASE_URL'] ?>/modules/brands/index.php" class="btn btn-outline-secondary btn-sm">
              <i class="bi bi-arrow-left"></i> Back to Brands
            </a>
            <a href="<?= $GLOBALS['BASE_URL'] ?>/modules/brands/edit.php?id=<?= (int)$id ?>" class="btn btn-primary btn-sm">
              <i class="bi bi-pencil"></i> Edit
            </a>
          </div>
        </div>

        <?php if (isset($_SESSION['flash_message'])): ?>
          <div class="alert alert-<?= $_SESSION['flash_type'] ?> alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
              <i class="bi bi-<?= $_SESSION['flash_type'] === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i>
              <?= h($_SESSION['flash_message']) ?>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
          <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
        <?php endif; ?>

        <div class="row g-4">
          <!-- Brand Details -->
          <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="bi bi-info-circle"></i> Brand Information</h6>
              </div>
              <div class="card-body">
                <div class="mb-3">
                  <label class="form-label text-muted small">Brand Name</label>
                  <div class="fw-semibold"><?= h($brand['name']) ?></div>
                </div>
                
                <div class="mb-3">
                  <label class="form-label text-muted small">Slug</label>
                  <div class="font-monospace small bg-light p-2 rounded"><?= h($brand['slug']) ?></div>
                </div>
                
                <?php if (!empty($brand['description'])): ?>
                  <div class="mb-3">
                    <label class="form-label text-muted small">Description</label>
                    <div><?= nl2br(h($brand['description'])) ?></div>
                  </div>
                <?php endif; ?>
                
                <div class="mb-3">
                  <label class="form-label text-muted small">Status</label>
                  <div>
                    <span class="badge bg-<?= $brand['status'] === 'active' ? 'success' : 'secondary' ?>">
                      <?= h(ucfirst($brand['status'])) ?>
                    </span>
                  </div>
                </div>
                
                <div class="row g-2">
                  <div class="col-6">
                    <label class="form-label text-muted small">Created</label>
                    <div class="small"><?= h(date('M j, Y', strtotime($brand['created_at']))) ?></div>
                  </div>
                  <div class="col-6">
                    <label class="form-label text-muted small">Updated</label>
                    <div class="small"><?= h(date('M j, Y', strtotime($brand['updated_at']))) ?></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Products -->
          <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
              <div class="card-header bg-light">
                <div class="d-flex justify-content-between align-items-center">
                  <h6 class="mb-0">
                    <i class="bi bi-box"></i> Products
                    <span class="badge bg-secondary ms-2"><?= number_format($productCount) ?></span>
                  </h6>
                  <?php if ($hasProducts && $productCount > 0): ?>
                    <a href="<?= $GLOBALS['BASE_URL'] ?>/modules/products/index.php?brand_id=<?= (int)$id ?>" 
                       class="btn btn-sm btn-outline-primary">
                      View All Products
                    </a>
                  <?php endif; ?>
                </div>
              </div>
              <div class="card-body">
                <?php if (!$hasProducts): ?>
                  <div class="text-center py-4">
                    <i class="bi bi-inbox text-muted" style="font-size: 2rem;"></i>
                    <p class="text-muted mb-0">Products table not found</p>
                  </div>
                <?php elseif ($productCount === 0): ?>
                  <div class="text-center py-4">
                    <i class="bi bi-box text-muted" style="font-size: 2rem;"></i>
                    <p class="text-muted mb-0">No products found for this brand</p>
                    <?php if (function_exists('user_has_permission') && user_has_permission('products.create')): ?>
                      <a href="<?= $GLOBALS['BASE_URL'] ?>/modules/products/create.php?brand_id=<?= (int)$id ?>" 
                         class="btn btn-primary btn-sm mt-2">
                        <i class="bi bi-plus-circle"></i> Add Product
                      </a>
                    <?php endif; ?>
                  </div>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-sm table-hover">
                      <thead class="table-light">
                        <tr>
                          <th>Product</th>
                          <th>SKU</th>
                          <th>Price</th>
                          <th>Stock</th>
                          <th>Status</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($products as $product): ?>
                          <tr>
                            <td>
                              <div class="fw-semibold"><?= h($product['name']) ?></div>
                            </td>
                            <td>
                              <code class="small"><?= h($product['sku']) ?></code>
                            </td>
                            <td>
                              <?php if (function_exists('format_currency')): ?>
                                <?= format_currency($product['price']) ?>
                              <?php else: ?>
                                $<?= number_format($product['price'], 2) ?>
                              <?php endif; ?>
                            </td>
                            <td>
                              <span class="badge bg-<?= $product['stock_quantity'] > 10 ? 'success' : ($product['stock_quantity'] > 0 ? 'warning' : 'danger') ?>">
                                <?= number_format($product['stock_quantity']) ?>
                              </span>
                            </td>
                            <td>
                              <span class="badge bg-<?= $product['status'] === 'active' ? 'success' : 'secondary' ?>">
                                <?= h(ucfirst($product['status'])) ?>
                              </span>
                            </td>
                            <td>
                              <a href="<?= $GLOBALS['BASE_URL'] ?>/modules/products/view.php?id=<?= (int)$product['id'] ?>" 
                                 class="btn btn-sm btn-outline-primary" title="View">
                                <i class="bi bi-eye"></i>
                              </a>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                  
                  <?php if ($productCount > 10): ?>
                    <div class="text-center mt-3">
                      <a href="<?= $GLOBALS['BASE_URL'] ?>/modules/products/index.php?brand_id=<?= (int)$id ?>" 
                         class="btn btn-outline-primary">
                        View All <?= number_format($productCount) ?> Products
                      </a>
                    </div>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<?php include __DIR__ . '/../../templates/layout/footer.php'; ?>
