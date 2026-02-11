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
                  <label class="form-label">Created At</label>
                  <div class="form-control-plaintext">
                    <?= h($brand['created_at'] ? date('M j, Y H:i', strtotime($brand['created_at'])) : 'N/A') ?>
                  </div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Last Updated</label>
                  <div class="form-control-plaintext">
                    <?= h($brand['updated_at'] ? date('M j, Y H:i', strtotime($brand['updated_at'])) : 'N/A') ?>
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
const BASE_URL = <?= json_encode($GLOBALS['BASE_URL'] ?? '') ?>;
const currentBrandId = <?= (int)($brand['id'] ?? 0) ?>;

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
  console.log('DOM loaded, setting up event listeners');
  
  // Auto-generate slug when name changes
  const nameField = document.getElementById('name');
  if (nameField) {
    nameField.addEventListener('input', function() {
      console.log('Name input changed');
      generateSlug();
    });
  }
  
  // Manual slug generation
  const slugField = document.getElementById('slug');
  if (slugField) {
    slugField.addEventListener('input', function() {
      console.log('Slug manually edited');
      // When user manually edits slug, don't auto-update on name change
      this.dataset.manual = 'true';
    });
  }
  
  // Generate initial slug if needed
  if (nameField && slugField) {
    const currentSlug = slugField.value;
    // Only auto-generate if slug is empty or was auto-generated
    if (!currentSlug || currentSlug.includes('-001') || currentSlug === currentSlug.toLowerCase().replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-').trim('-')) {
      generateSlug();
    }
  }
});

function generateSlug() {
  const name = document.getElementById('name').value;
  console.log('Generating slug for:', name);
  
  if (name.trim()) {
    let slug = name.toLowerCase()
      .replace(/[^a-z0-9\s-]/g, '')
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-')
      .trim('-');
    
    console.log('Base slug:', slug);
    checkSlugExists(slug);
  }
}

async function checkSlugExists(slug) {
  console.log('Checking slug exists:', slug);
  
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
    
    console.log('API response status:', response.status);
    const result = await response.json();
    console.log('API result:', result);
    
    if (result.error) {
      console.error('API error:', result.error);
      // Fallback to basic slug
      document.getElementById('slug').value = slug;
      return;
    }
    
    if (result.exists) {
      console.log('Slug exists, finding available...');
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
      
      console.log('Final slug with increment:', finalSlug);
      document.getElementById('slug').value = finalSlug;
    } else {
      console.log('Slug is available:', slug);
      document.getElementById('slug').value = slug;
    }
  } catch (error) {
    console.error('Error checking slug:', error);
    // Fallback to basic slug generation
    document.getElementById('slug').value = slug;
  }
}

// Simple test function
function testSlugGeneration() {
  console.log('Testing slug generation...');
  const testSlug = 'test-brand';
  checkSlugExists(testSlug);
}
</script>

<?php include __DIR__ . '/../../templates/layout/footer.php'; ?>
