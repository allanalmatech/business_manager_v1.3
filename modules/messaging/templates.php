<?php
// modules/messaging/templates.php
declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/helpers.php';
require_once dirname(dirname(__DIR__)) . '/includes/rbac.php';

require_login();
require_permission('messaging.view');

$db = $GLOBALS['db'] ?? null;
$BASE = $GLOBALS['BASE_URL'] ?? '';

$page_title = 'Message Templates';
$page_subtitle = 'Manage reusable message templates';

$errors = [];
$success = '';
$editingTemplate = null;

function table_exists(mysqli $db, string $name): bool {
  $safe = $db->real_escape_string($name);
  $r = $db->query("SHOW TABLES LIKE '{$safe}'");
  return ($r && $r->num_rows > 0);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    $errors[] = 'Invalid request. Please try again.';
  } else {
    try {

      if ($action === 'cancel_edit') {
        header("Location: {$BASE}/modules/messaging/templates.php");
        exit;
      }

      if ($action === 'edit') {
        $id = (int)($_POST['template_id'] ?? 0);
        if ($id > 0) {
          $stmt = $db->prepare("SELECT * FROM message_templates WHERE id=?");
          $stmt->bind_param('i', $id);
          $stmt->execute();
          $editingTemplate = $stmt->get_result()->fetch_assoc();
          $stmt->close();
        }
      }

      if ($action === 'create' || $action === 'update') {
        $name = trim((string)($_POST['name'] ?? ''));
        $subject = trim((string)($_POST['subject'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));
        $category = trim((string)($_POST['category'] ?? 'general'));

        if ($name === '') $errors[] = 'Template name is required.';
        if ($message === '') $errors[] = 'Message content is required.';

        if (empty($errors)) {
          if ($action === 'create') {
            $stmt = $db->prepare("INSERT INTO message_templates (name, subject, message, category, created_by) VALUES (?, ?, ?, ?, ?)");
            $uid = (int)($_SESSION['user']['id'] ?? 0);
            $stmt->bind_param('ssssi', $name, $subject, $message, $category, $uid);
            if (!$stmt->execute()) $errors[] = 'Failed to create template.';
            else $success = 'Template created successfully!';
            $stmt->close();
          } else {
            $id = (int)($_POST['template_id'] ?? 0);
            $stmt = $db->prepare("UPDATE message_templates SET name=?, subject=?, message=?, category=? WHERE id=?");
            $stmt->bind_param('ssssi', $name, $subject, $message, $category, $id);
            if (!$stmt->execute()) $errors[] = 'Failed to update template.';
            else $success = 'Template updated successfully!';
            $stmt->close();
            $editingTemplate = null;
          }
        }
      }

      if ($action === 'delete') {
        $id = (int)($_POST['template_id'] ?? 0);
        if ($id > 0) {
          $stmt = $db->prepare("DELETE FROM message_templates WHERE id=?");
          $stmt->bind_param('i', $id);
          if (!$stmt->execute()) $errors[] = 'Failed to delete template.';
          else $success = 'Template deleted successfully!';
          $stmt->close();
        }
      }

    } catch (Exception $e) {
      $errors[] = 'Error: ' . $e->getMessage();
    }
  }
}

// Fetch templates
$templates = [];
$categories = [];

if ($db instanceof mysqli && table_exists($db, 'message_templates')) {
  $rs = $db->query("SELECT * FROM message_templates ORDER BY category, name");
  if ($rs) $templates = $rs->fetch_all(MYSQLI_ASSOC);

  $rs2 = $db->query("SELECT DISTINCT category FROM message_templates ORDER BY category");
  if ($rs2) $categories = array_column($rs2->fetch_all(MYSQLI_ASSOC), 'category');
}

require_once dirname(dirname(__DIR__)) . '/templates/layout/header.php';
?>

<style>
.templates-container{max-width:1100px;margin:0 auto}
.template-card{background:#fff;border:1px solid #e9ecef;border-radius:.5rem;padding:1.5rem;margin-bottom:1rem;transition:.2s}
.template-card:hover{border-color:#0d6efd;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.template-header{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1rem}
.template-title{margin:0;font-size:1.1rem;font-weight:600}
.template-category{display:inline-block;padding:.25rem .75rem;background:#e9ecef;color:#495057;border-radius:.375rem;font-size:.75rem;font-weight:600;text-transform:uppercase}
.template-message{color:#6c757d;line-height:1.5;max-height:120px;overflow:auto;margin-bottom:1rem;white-space:pre-wrap}
.template-actions{display:flex;gap:.5rem;flex-wrap:wrap}
.template-form{background:#fff;border:1px solid #e9ecef;border-radius:.5rem;padding:1.5rem;margin-bottom:2rem}
.category-tabs{display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap;border-bottom:1px solid #e9ecef}
.category-tab{padding:.5rem 1rem;background:none;border:none;border-bottom:2px solid transparent;color:#6c757d;cursor:pointer;transition:.2s}
.category-tab:hover{color:#495057}
.category-tab.active{color:#0d6efd;border-bottom-color:#0d6efd}
.character-count{text-align:right;font-size:.875rem;color:#6c757d;margin-top:.25rem}
</style>

<div class="app-shell">
  <?php require_once dirname(dirname(__DIR__)) . '/templates/layout/sidebar.php'; ?>
  <div class="app-content">
    <?php require_once dirname(dirname(__DIR__)) . '/templates/layout/topbar.php'; ?>

    <main class="page-wrap">
      <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
          <div>
            <h4 class="mb-1"><?= h($page_title) ?></h4>
            <div class="text-muted small"><?= h($page_subtitle) ?></div>
          </div>

          <div>
            <?php 
            $BASE = $BASE ?? ($GLOBALS['BASE_URL'] ?? '');
            $active = 'templates'; // Set active page for navigation
            require_once dirname(dirname(__DIR__)) . '/templates/partials/messaging_nav.php'; 
            ?>
          </div>
        </div>

        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger">
            <strong>Error:</strong>
            <ul class="mb-0 mt-2"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>

        <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

        <div class="templates-container">
          <div class="template-form">
            <h5 class="mb-3"><?= $editingTemplate ? 'Edit Template' : 'Create New Template' ?></h5>

            <form method="POST" id="templateForm">
              <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
              <input type="hidden" name="action" value="<?= $editingTemplate ? 'update' : 'create' ?>">
              <?php if ($editingTemplate): ?>
                <input type="hidden" name="template_id" value="<?= (int)$editingTemplate['id'] ?>">
              <?php endif; ?>

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label">Template Name *</label>
                  <input class="form-control" name="name" required value="<?= h($editingTemplate['name'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label">Category</label>
                  <select class="form-select" name="category">
                    <?php
                      $opts = ['general','welcome','notification','alert','marketing','support'];
                      $cur = (string)($editingTemplate['category'] ?? 'general');
                      foreach ($opts as $o) {
                        $sel = ($cur === $o) ? 'selected' : '';
                        echo '<option value="'.h($o).'" '.$sel.'>'.h(ucfirst($o)).'</option>';
                      }
                    ?>
                  </select>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Subject (Optional)</label>
                <input class="form-control" name="subject" value="<?= h($editingTemplate['subject'] ?? '') ?>">
              </div>

              <div class="mb-3">
                <label class="form-label">Message *</label>
                <textarea class="form-control" id="tpl_message" name="message" rows="6" required oninput="tplCount()"><?= h($editingTemplate['message'] ?? '') ?></textarea>
                <div class="character-count"><span id="tplCharCount">0</span> characters</div>
                <div class="form-text">Placeholders: {username}, {date}, {time}, {datetime}, {year}, {month}, {day}</div>
              </div>

              <div class="d-flex gap-2 justify-content-end">
                <?php if ($editingTemplate): ?>
                  <button type="submit" name="action" value="cancel_edit" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg"></i> Cancel
                  </button>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-<?= $editingTemplate ? 'pencil' : 'plus-lg' ?>"></i>
                  <?= $editingTemplate ? 'Update Template' : 'Create Template' ?>
                </button>
              </div>
            </form>
          </div>

          <?php if (!empty($templates)): ?>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h5 class="mb-0">Existing Templates (<?= count($templates) ?>)</h5>
              <div class="text-muted small"><i class="bi bi-info-circle"></i> Use Template opens Send page</div>
            </div>

            <?php if (!empty($categories)): ?>
              <div class="category-tabs">
                <button class="category-tab active" onclick="filterByCategory('all')">All</button>
                <?php foreach ($categories as $c): ?>
                  <button class="category-tab" onclick="filterByCategory('<?= h($c) ?>')"><?= h(ucfirst($c)) ?></button>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <div id="templatesList">
              <?php foreach ($templates as $t): ?>
                <div class="template-card" data-category="<?= h($t['category']) ?>">
                  <div class="template-header">
                    <div>
                      <h6 class="template-title"><?= h($t['name']) ?></h6>
                      <span class="template-category"><?= h($t['category']) ?></span>
                    </div>

                    <div class="template-actions">
                      <button type="button" class="btn btn-sm btn-success"
                        onclick='useTemplate(<?= json_encode($t, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
                        <i class="bi bi-send"></i> Use Template
                      </button>

                      <button type="button" class="btn btn-sm btn-outline-primary" onclick="editTemplate(<?= (int)$t['id'] ?>)">
                        <i class="bi bi-pencil"></i> Edit
                      </button>

                      <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="template_id" value="<?= (int)$t['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this template?')">
                          <i class="bi bi-trash"></i> Delete
                        </button>
                      </form>
                    </div>
                  </div>

                  <?php if (!empty($t['subject'])): ?>
                    <div class="text-muted"><strong>Subject:</strong> <?= h($t['subject']) ?></div>
                  <?php endif; ?>

                  <div class="template-message"><?= h($t['message']) ?></div>

                  <div class="text-muted small">
                    Created: <?= h($t['created_at']) ?>
                    <?php if (($t['updated_at'] ?? '') !== ($t['created_at'] ?? '')): ?>
                      • Updated: <?= h($t['updated_at']) ?>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

          <?php else: ?>
            <div class="text-center py-5">
              <i class="bi bi-file-text text-muted" style="font-size:3rem;"></i>
              <h5 class="text-muted mt-3">No templates yet</h5>
              <p class="text-muted">Create your first message template to get started.</p>
            </div>
          <?php endif; ?>

        </div>
      </div>
    </main>
  </div>
</div>

<script>
function tplCount(){
  const v = document.getElementById('tpl_message')?.value || '';
  document.getElementById('tplCharCount').textContent = v.length;
}
tplCount();

function useTemplate(tpl){
  sessionStorage.setItem('messageTemplate', JSON.stringify(tpl));
  window.location.href = '<?= h($BASE) ?>/modules/messaging/send.php';
}
function editTemplate(id){
  const f = document.createElement('form');
  f.method = 'POST'; f.style.display='none';

  f.innerHTML = `
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
    <input type="hidden" name="action" value="edit">
    <input type="hidden" name="template_id" value="${id}">
  `;
  document.body.appendChild(f);
  f.submit();
}
function filterByCategory(cat){
  document.querySelectorAll('.category-tab').forEach(t => {
    t.classList.toggle('active', (cat==='all' && t.textContent.trim()==='All') || (t.textContent.trim().toLowerCase()===cat.toLowerCase()));
  });
  document.querySelectorAll('.template-card').forEach(card => {
    card.style.display = (cat==='all' || card.dataset.category===cat) ? 'block' : 'none';
  });
}
</script>

<?php require_once dirname(dirname(__DIR__)) . '/templates/layout/footer.php'; ?>
