<?php
// admin/permissions.php
declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/helpers.php';
require_once dirname(dirname(__DIR__)) . '/includes/rbac.php';


require_super_admin();
require_permission('permissions.manage');

$db = $GLOBALS['db'] ?? null;

function table_exists(mysqli $db, string $table): bool {
  $r = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");
  return $r && $r->num_rows > 0;
}

$page_title = "Permissions";
$page_subtitle = "Assign permissions to roles";

require_once dirname(dirname(__DIR__)) . '/templates/layout/header.php';
?>
<div class="app-shell">
  <?php require_once dirname(dirname(__DIR__)) . '/templates/layout/sidebar.php'; ?>

  <div class="app-content">
    <?php require_once dirname(dirname(__DIR__)) . '/templates/layout/topbar.php'; ?>

    <main class="page-wrap">
      <div class="container-fluid py-3">
        <div class="mb-3">
          <h4 class="mb-1"><?= h($page_title) ?></h4>
          <div class="text-muted small"><?= h($page_subtitle) ?></div>
        </div>

        <?php if (!$db instanceof mysqli): ?>
          <div class="alert alert-danger">Database not available.</div>
        <?php else: ?>
          <?php
            if (!table_exists($db,'roles')) {
              echo '<div class="alert alert-warning"><b>roles</b> table not found.</div>';
              require_once dirname(dirname(__DIR__)) . '/templates/layout/footer.php'; exit;
            }

            if (!table_exists($db,'permissions')) {
              echo '<div class="alert alert-warning"><b>permissions</b> table not found.</div>';
              echo '<div class="small text-muted">Create permissions first, then map to roles.</div>';
              require_once dirname(dirname(__DIR__)) . '/templates/layout/footer.php'; exit;
            }

            // mapping table name flexibility
            $mapTable = table_exists($db,'role_privileges') ? 'role_privileges' : (table_exists($db,'role_permissions') ? 'role_permissions' : '');
            if ($mapTable === '') {
              echo '<div class="alert alert-warning">Mapping table not found. Expected <b>role_privileges</b> or <b>role_permissions</b>.</div>';
              require_once dirname(dirname(__DIR__)) . '/templates/layout/footer.php'; exit;
            }

            $roles = $db->query("SELECT id, name FROM roles ORDER BY name ASC")?->fetch_all(MYSQLI_ASSOC) ?? [];

            $selectedRole = (int)($_GET['role_id'] ?? ($roles[0]['id'] ?? 0));
            $selectedRoleName = '';
            foreach ($roles as $r) {
              if ((int)$r['id'] === $selectedRole) { $selectedRoleName = (string)$r['name']; break; }
            }

            // If permissions table supports super-admin-only flag, hide those when editing non-super_admin roles.
            $hasSuperFlag = false;
            $colCheck = $db->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='permissions' AND COLUMN_NAME='is_super_admin_only' LIMIT 1");
            if ($colCheck && $colCheck->num_rows > 0) $hasSuperFlag = true;

            $permSql = "SELECT id, perm_key, description" . ($hasSuperFlag ? ", is_super_admin_only" : "") . " FROM permissions";
            if ($hasSuperFlag && $selectedRoleName !== 'super_admin') {
              $permSql .= " WHERE is_super_admin_only = 0";
            }
            $permSql .= " ORDER BY perm_key ASC";
            $perms = $db->query($permSql)?->fetch_all(MYSQLI_ASSOC) ?? [];

            // Group permissions by category for better organization
            $groupedPerms = [];
            foreach ($perms as $perm) {
                $category = 'General';
                $subcategory = '';
                
                if (strpos($perm['perm_key'], '.') !== false) {
                    $parts = explode('.', $perm['perm_key']);
                    
                    // Handle multi-level categories like reports.sales.view
                    if (count($parts) >= 3) {
                        $category = ucfirst($parts[0]);
                        $subcategory = ucfirst($parts[1]);
                    } elseif (count($parts) === 2) {
                        $category = ucfirst($parts[0]);
                        $subcategory = ucfirst($parts[1]);
                    }
                }
                
                // Special categorization for better organization
                switch ($category) {
                    case 'Reports':
                        $category = 'Reports';
                        break;
                    case 'Admin':
                        $category = 'Administration';
                        break;
                    case 'Sales':
                        $category = 'Sales & POS';
                        break;
                    case 'Finance':
                        $category = 'Finance & Accounting';
                        break;
                    case 'Products':
                        $category = 'Inventory & Products';
                        break;
                    case 'Contacts':
                        $category = 'Contacts & Customers';
                        break;
                    case 'Installments':
                        $category = 'Installments & Payments';
                        break;
                    case 'Documents':
                        $category = 'Documents & Receipts';
                        break;
                    case 'Stores':
                        $category = 'Stores & Locations';
                        break;
                    case 'Procurement':
                        $category = 'Procurement & Purchasing';
                        break;
                    case 'Messaging':
                        $category = 'Messaging & Communication';
                        break;
                }
                
                $groupedPerms[$category][$subcategory][] = $perm;
            }
            
            // Sort categories and subcategories
            ksort($groupedPerms);
            foreach ($groupedPerms as $category => &$subcategories) {
                ksort($subcategories);
            }

            // $selectedRole already computed above

            $assigned = [];
            if ($selectedRole > 0) {
              // Try both schema styles:
              // role_privileges(role_id, privilege_id) OR role_permissions(role_id, permission_id)
              $colPermId = ($mapTable === 'role_privileges') ? 'privilege_id' : 'permission_id';
              $st = $db->prepare("
                SELECT p.perm_key AS pid 
                FROM $mapTable rp 
                JOIN permissions p ON rp.$colPermId = p.id 
                WHERE rp.role_id = ?
              ");
              $st->bind_param('i', $selectedRole);
              $st->execute();
              $rs = $st->get_result();
              while ($r = $rs->fetch_assoc()) $assigned[$r['pid']] = true;
              $st->close();
            }
          ?>

      <div class="card shadow-sm mb-3">
        <div class="card-body">
          <form class="row g-2 align-items-end" method="get">
            <div class="col-md-4">
              <label class="form-label">Role</label>
              <select class="form-select" name="role_id" onchange="this.form.submit()">
                <?php foreach ($roles as $r): ?>
                  <option value="<?= (int)$r['id'] ?>" <?= (int)$r['id']===$selectedRole?'selected':'' ?>>
                    <?= h($r['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-8 text-muted small">
              Tick permissions then click <b>Save Changes</b>.
            </div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-body">
          <?php if ($selectedRole <= 0): ?>
            <div class="alert alert-warning mb-0">No role selected.</div>
          <?php else: ?>
            <form id="formPerms">
              <input type="hidden" name="role_id" value="<?= (int)$selectedRole ?>">
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                  <thead class="table-light">
                    <tr>
                      <th style="width:60px;">Allow</th>
                      <th>Permission</th>
                      <th>Description</th>
                      <th>Category</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($groupedPerms as $category => $subcategories): ?>
                      <tr class="table-group-header">
                        <td colspan="4" class="bg-light fw-bold text-primary">
                          <i class="bi bi-folder-fill me-2"></i><?= h($category) ?>
                        </td>
                      </tr>
                      <?php foreach ($subcategories as $subcategory => $categoryPerms): ?>
                        <?php if (!empty($subcategory)): ?>
                          <tr class="table-subgroup-header">
                            <td colspan="4" class="bg-light text-muted ps-4">
                              <i class="bi bi-folder me-2"></i><?= h($subcategory) ?>
                            </td>
                          </tr>
                        <?php endif; ?>
                        <?php foreach ($categoryPerms as $p): ?>
                          <?php 
                          $permKey = $p['perm_key'] ?? '';
                          ?>
                          <tr>
                            <td>
                              <input class="form-check-input" type="checkbox" name="perm_ids[]"
                                     value="<?= h($permKey) ?>" <?= isset($assigned[$permKey])?'checked':'' ?>>
                            </td>
                            <td class="fw-semibold"><?= h($permKey) ?></td>
                            <td class="text-muted"><?= h($p['description'] ?? '') ?></td>
                            <td>
                              <span class="badge bg-secondary"><?= h($category) ?></span>
                              <?php if (!empty($subcategory)): ?>
                                <span class="badge bg-info ms-1"><?= h($subcategory) ?></span>
                              <?php endif; ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endforeach; ?>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <div class="mt-3">
                <div class="row">
                  <div class="col-md-6">
                    <div class="card card-body">
                      <h6 class="card-title">Quick Actions</h6>
                      <div class="d-grid gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllPermissions()">
                          <i class="bi bi-check-all me-1"></i>Select All
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllPermissions()">
                          <i class="bi bi-x-square me-1"></i>Deselect All
                        </button>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="card card-body">
                      <h6 class="card-title">Statistics</h6>
                      <div class="small text-muted">
                        <div>Total Permissions: <?= count($perms) ?></div>
                        <div>Categories: <?= count($groupedPerms) ?></div>
                        <div>Selected: <span id="selectedCount">0</span></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="d-flex justify-content-end">
                <button class="btn btn-primary" type="submit">Save Changes</button>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </div>

        <?php endif; ?>
      </div>
    </main>
  </div>
</div>

<style>
.table-group-header td {
  border-bottom: 2px solid #dee2e6 !important;
  font-size: 0.9rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.table-subgroup-header td {
  border-bottom: 1px solid #e9ecef !important;
  font-size: 0.85rem;
  font-style: italic;
}

.table-group-header:hover,
.table-subgroup-header:hover {
  background-color: #f8f9fa !important;
}

.badge {
  font-size: 0.75rem;
}
</style>

<script>
const BASE_URL = <?= json_encode($GLOBALS['BASE_URL'] ?? '') ?>;

// Beautiful Success Dialog
function showSuccessDialog(message) {
  const dialog = document.createElement('div');
  dialog.className = 'modal fade show';
  dialog.style.display = 'block';
  dialog.style.backgroundColor = 'rgba(0,0,0,0.5)';
  dialog.innerHTML = `
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-body text-center p-4">
          <div class="mb-3">
            <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 rounded-circle p-3" style="width: 80px; height: 80px; margin: 0 auto;">
              <i class="bi bi-check-circle text-success" style="font-size: 2.5rem;"></i>
            </div>
          </div>
          <h5 class="modal-title text-success mb-3">Success!</h5>
          <p class="text-muted mb-4">${message}</p>
          <div class="d-flex justify-content-center">
            <button type="button" class="btn btn-success" onclick="this.closest('.modal').remove()">
              <i class="bi bi-check me-2"></i>OK
            </button>
          </div>
        </div>
      </div>
    </div>
  `;
  document.body.appendChild(dialog);
  
  // Auto-remove after 3 seconds
  setTimeout(() => {
    if (dialog.parentNode) {
      dialog.remove();
    }
  }, 3000);
}

// Beautiful Error Dialog
function showErrorDialog(message) {
  const dialog = document.createElement('div');
  dialog.className = 'modal fade show';
  dialog.style.display = 'block';
  dialog.style.backgroundColor = 'rgba(0,0,0,0.5)';
  dialog.innerHTML = `
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-body text-center p-4">
          <div class="mb-3">
            <div class="d-inline-flex align-items-center justify-content-center bg-danger bg-opacity-10 rounded-circle p-3" style="width: 80px; height: 80px; margin: 0 auto;">
              <i class="bi bi-x-circle text-danger" style="font-size: 2.5rem;"></i>
            </div>
          </div>
          <h5 class="modal-title text-danger mb-3">Error!</h5>
          <p class="text-muted mb-4">${message}</p>
          <div class="d-flex justify-content-center">
            <button type="button" class="btn btn-danger" onclick="this.closest('.modal').remove()">
              <i class="bi bi-x me-2"></i>Close
            </button>
          </div>
        </div>
      </div>
    </div>
  `;
  document.body.appendChild(dialog);
}

document.getElementById('formPerms')?.addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  
  try {
    const apiUrl = BASE_URL + '/api/permissions/save_role_permissions.php';
    const res = await fetch(apiUrl, {
      method:'POST', 
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        role_id: Number(fd.get('role_id')),
        permissions: Array.from(fd.getAll('perm_ids[]'))
      })
    });
    
    const ct = res.headers.get('content-type') || '';
    if (!ct.includes('application/json')) {
      const text = await res.text();
      console.error('Expected JSON, got:', ct, text);
      alert('Server error (non-JSON response). Check console.');
      return;
    }

    const j = await res.json();

    if (j.success) {
      showSuccessDialog(j.message || 'Permissions saved successfully');
      setTimeout(() => location.reload(), 1500);
    } else {
      showErrorDialog(j.message || 'Failed to save permissions');
    }
  } catch (error) {
    console.error('Fetch Error:', error);
    alert('Network error. Please try again.');
  }
});

// Update selected count
function updateSelectedCount() {
  const checkboxes = document.querySelectorAll('input[name="perm_ids[]"]:checked');
  document.getElementById('selectedCount').textContent = checkboxes.length;
}

// Select all permissions
function selectAllPermissions() {
  const checkboxes = document.querySelectorAll('input[name="perm_ids[]"]');
  checkboxes.forEach(cb => cb.checked = true);
  updateSelectedCount();
}

// Deselect all permissions
function deselectAllPermissions() {
  const checkboxes = document.querySelectorAll('input[name="perm_ids[]"]');
  checkboxes.forEach(cb => cb.checked = false);
  updateSelectedCount();
}

// Add event listeners to checkboxes
document.addEventListener('DOMContentLoaded', function() {
  const checkboxes = document.querySelectorAll('input[name="perm_ids[]"]');
  checkboxes.forEach(cb => {
    cb.addEventListener('change', updateSelectedCount);
  });
  
  // Initialize count
  updateSelectedCount();
});
</script>

<script>
// Force script reload to break any cached event listeners
</script>

<?php require_once dirname(dirname(__DIR__)) . '/templates/layout/footer.php'; ?>
