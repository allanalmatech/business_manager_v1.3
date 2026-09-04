<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/rbac.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_permission('brands.create');

$db = $GLOBALS['db'];

$page_title = 'Create Brand';
$page_subtitle = 'Add a new product brand';

$message = '';
$message_type = '';

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

        // Check if slug already exists (including similar slugs with suffixes)
        $check = $db->prepare("
            SELECT id, slug FROM brands 
            WHERE slug = ? OR slug LIKE ?
            ORDER BY LENGTH(slug) DESC, slug ASC
            LIMIT 5
        ");
        $slugPattern = $slug . '-%';
        $check->bind_param('ss', $slug, $slugPattern);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows > 0) {
            $existing_slugs = [];
            while ($row = $result->fetch_assoc()) {
                $existing_slugs[] = $row['slug'];
            }
            
            // Check if any existing slug is exactly the same
            if (in_array($slug, $existing_slugs)) {
                $message = 'Brand with this slug already exists';
                $message_type = 'danger';
            } else {
                // Insert brand
                $insert = $db->prepare("INSERT INTO brands (name, slug, description, status) VALUES (?, ?, ?, ?)");
                $insert->bind_param('ssss', $name, $slug, $description, $status);
                
                if ($insert->execute()) {
                    $message = 'Brand created successfully';
                    $message_type = 'success';
                    
                    // Log action
                    if (function_exists('audit_log')) {
                        audit_log('brands.create', 'brands', (string)$insert->insert_id, "Created brand: $name");
                    }
                    
                    // Redirect to brands list
                    header('Location: ' . $GLOBALS['BASE_URL'] . '/modules/brands/index.php');
                    exit;
                } else {
                    $message = 'Error creating brand: ' . $db->error;
                    $message_type = 'danger';
                }
            }
        } else {    
            // Insert brand
            $insert = $db->prepare("INSERT INTO brands (name, slug, description, status) VALUES (?, ?, ?, ?)");
            $insert->bind_param('ssss', $name, $slug, $description, $status);
            
            if ($insert->execute()) {
                $message = 'Brand created successfully';
                $message_type = 'success';
                
                // Log action
                if (function_exists('audit_log')) {
                    audit_log('brands.create', 'brands', (string)$insert->insert_id, "Created brand: $name");
                }
                
                // Redirect to brands list
                header('Location: ' . $GLOBALS['BASE_URL'] . '/modules/brands/index.php');
                exit;
            } else {
                $message = 'Error creating brand: ' . $db->error;
                $message_type = 'danger';
            }
        }
    }
}

$page_title = 'Create Brand';
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
              <i class="bi bi-plus-circle"></i> Brand Information
            </h6>
          </div>
          <div class="card-body">
            <form method="post">
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="name" class="form-label">Brand Name *</label>
                  <input type="text" class="form-control" id="name" name="name" 
                         value="<?= h($_POST['name'] ?? '') ?>" required autofocus>
                </div>
                <div class="col-md-6">
                  <label for="slug" class="form-label">Slug</label>
                  <div class="input-group">
                    <input type="text" class="form-control" id="slug" name="slug" 
                           value="<?= h($_POST['slug'] ?? '') ?>" 
                           placeholder="Auto-generated from name">
                    <button type="button" class="btn btn-outline-secondary" onclick="generateSlug()">
                      <i class="bi bi-arrow-clockwise"></i> Generate
                    </button>
                  </div>
                  <small class="text-muted">URL-friendly version of the brand name</small>
                </div>
                <div class="col-12">
                  <label for="description" class="form-label">Description</label>
                  <textarea class="form-control" id="description" name="description" rows="4"><?= h($_POST['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                  <label for="status" class="form-label">Status</label>
                  <select class="form-select" id="status" name="status">
                    <option value="active" <?= (($_POST['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= (($_POST['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                  </select>
                </div>
              </div>
              
              <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-check-circle"></i> Create Brand
                </button>
                <a href="<?= $GLOBALS['BASE_URL'] ?>/modules/brands/index.php" class="btn btn-outline-secondary ms-2">
                  <i class="bi bi-x-circle"></i> Cancel
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
const BASE_URL = <?= json_encode($GLOBALS['BASE_URL'] ?? '') ?>;
const currentBrandId = 0; // Create page has no existing ID

function generateSlug() {
  const name = document.getElementById('name').value;
  
  if (name.trim()) {
    let slug = name.toLowerCase()
      .replace(/[^a-z0-9\s-]/g, '')
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-')
      .trim('-');
    
    checkSlugExists(slug);
  }
}

async function checkSlugExists(slug) {
  try {
    const response = await fetch(`${BASE_URL}/api/brands/check_slug.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        slug: slug,
        exclude_id: currentBrandId
      })
    });
    
    const result = await response.json();
    
    if (result.error) {
      // Fallback to basic slug
      document.getElementById('slug').value = slug;
      return;
    }
    
    if (result.exists) {
      // Slug exists, add incrementing number
      let finalSlug = slug;
      let counter = 1;
      
      // Try up to 100 variations
      while (counter <= 100) {
        finalSlug = slug + '-' + counter.toString().padStart(3, '0');
        
        // Check if this specific slug is in the suggestions
        if (!result.suggestions || !result.suggestions.includes(finalSlug)) {
          break;
        }
        counter++;
      }
      
      document.getElementById('slug').value = finalSlug;
    } else {
      document.getElementById('slug').value = slug;
    }
  } catch (error) {
    // Fallback to basic slug generation
    document.getElementById('slug').value = slug;
  }
}

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
  // Auto-generate slug when name changes
  const nameField = document.getElementById('name');
  if (nameField) {
    nameField.addEventListener('input', function() {
      generateSlug();
    });
  }
  
  // Manual slug generation - prevent auto-update when manually editing
  const slugField = document.getElementById('slug');
  if (slugField) {
    slugField.addEventListener('input', function() {
      this.dataset.manual = 'true';
    });
  }
});
</script>

<?php include __DIR__ . '/../../templates/layout/footer.php'; ?>
