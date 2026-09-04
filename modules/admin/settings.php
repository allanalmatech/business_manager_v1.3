<?php
// admin/settings.php
declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/helpers.php';
require_once dirname(dirname(__DIR__)) . '/includes/rbac.php';

require_super_admin();
require_permission('admin.settings');

$db = $GLOBALS['db'] ?? null;
$BASE = $GLOBALS['BASE_URL'] ?? '';
$csrf = $_SESSION['csrf'] ?? '';

$page_title = "Settings";
$page_subtitle = "Manage global system settings";

function table_exists(mysqli $db, string $table): bool {
  $r = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");
  return $r && $r->num_rows > 0;
}

// Fetch settings
$rows = [];
$hasGrouping = false;

if ($db instanceof mysqli) {
  if (!table_exists($db, 'settings')) {
    require_once dirname(dirname(__DIR__)) . '/templates/layout/header.php';
    echo '<div class="container-fluid py-4"><div class="alert alert-warning"><b>settings</b> table not found.</div></div>';
    require_once dirname(dirname(__DIR__)) . '/templates/layout/footer.php';
    exit;
  }

  // detect columns
  $cols = [];
  $rsCols = $db->query("SHOW COLUMNS FROM settings");
  if ($rsCols) {
    while ($c = $rsCols->fetch_assoc()) $cols[] = $c['Field'];
  }
  // Ensure options column exists (for select dropdowns)
  if ($db instanceof mysqli && !in_array('options', $cols, true)) {
    $db->query("ALTER TABLE settings ADD COLUMN `options` TEXT NULL AFTER `type`");
    $cols[] = 'options';
  }
  $hasGrouping = in_array('group', $cols, true) && in_array('type', $cols, true);

  if ($hasGrouping) {
    $hasOptsCol = in_array('options', $cols, true);
    $optSelect = $hasOptsCol ? ', `options`' : ", NULL AS `options`";
    $rs = $db->query("SELECT `group`, `key`, `value`, `type`, `description`, updated_at, sort_order$optSelect
                      FROM settings ORDER BY `group` ASC, sort_order ASC, `key` ASC");
    $rows = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
  } else {
    $rs = $db->query("SELECT `key`, `value`, `description`, updated_at, NULL AS `options` FROM settings ORDER BY `key` ASC");
    $rows = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
    // fallback group by prefix before first dot: pos.*, receipts.*, taxes.* else General
    foreach ($rows as &$r) {
      $k = (string)$r['key'];
      $grp = 'General';
      if (strpos($k, '.') !== false) $grp = ucfirst(substr($k, 0, strpos($k, '.')));
      $r['group'] = $grp;
      $r['type'] = 'text';
      $r['sort_order'] = 0;
    }
    unset($r);
  }
}

// Group rows
$groups = [];
foreach ($rows as $r) {
  $g = (string)($r['group'] ?? 'General');
  if (!isset($groups[$g])) $groups[$g] = [];
  $groups[$g][] = $r;
}

function is_multiline_key(string $key): bool {
  $k = strtolower($key);
  $needles = [
    'address','header','footer','note','notes','terms','policy','policies',
    'description','invoice_footer','receipt_footer','receipt_header',
    'sms_template','email_template','whatsapp_template',
    'about','welcome','message','messages'
  ];
  foreach ($needles as $n) {
    if (strpos($k, $n) !== false) return true;
  }
  return false;
}

// Map keys to appropriate UI types
function guess_ui_type(string $key, string $currentType): string {
  if ($currentType !== 'text') return $currentType;
  $k = strtolower($key);
  if (strpos($k, 'email') !== false) return 'email';
  if (strpos($k, 'website') !== false || strpos($k, 'url') !== false) return 'url';
  if (strpos($k, 'phone') !== false || strpos($k, 'tel') !== false) return 'tel';
  if (strpos($k, 'color') !== false || strpos($k, 'colour') !== false) return 'color';
  if (strpos($k, 'width') !== false || strpos($k, 'height') !== false || strpos($k, 'size') !== false || strpos($k, 'decimal') !== false) return 'number';
  return $currentType;
}

// Get predefined options for known settings
function get_setting_options(string $key): array {
  $k = strtolower($key);
  $opts = [];
  if (strpos($k, 'currency_symbol') !== false) {
    $opts = ['$','€','£','UGX','KES','NGN','GHS','ZAR','INR','¥','₹','R','Fr','₡','₱'];
  } elseif (strpos($k, 'currency_code') !== false) {
    $opts = ['USD','EUR','GBP','UGX','KES','NGN','GHS','ZAR','INR','JPY','CNY','CAD','AUD','CHF'];
  } elseif (strpos($k, 'decimal_places') !== false) {
    $opts = ['0','1','2','3'];
  } elseif (strpos($k, 'thousands_separator') !== false) {
    $opts = [',','.',' ','none'];
  } elseif (strpos($k, 'decimal_point') !== false) {
    $opts = ['.',','];
  } elseif (strpos($k, 'receipt_width') !== false) {
    $opts = ['58','80','112'];
  } elseif (strpos($k, 'app_theme') !== false) {
    $opts = ['default','dark','ocean','forest','sunset','royal','slate','rose','coffee','cyber'];
  }
  return $opts;
}

// Pretty group order (optional)
$preferredOrder = ['General','Business','POS','Receipts','Taxes','Payments','Users','Security','Notifications','Integrations','Printing','Backup'];
uksort($groups, function($a, $b) use ($preferredOrder){
  $ia = array_search($a, $preferredOrder, true); $ib = array_search($b, $preferredOrder, true);
  $ia = ($ia === false) ? 999 : $ia;
  $ib = ($ib === false) ? 999 : $ib;
  if ($ia === $ib) return strcmp($a, $b);
  return $ia <=> $ib;
});

require_once dirname(dirname(__DIR__)) . '/templates/layout/header.php';
?>
<style>
  .settings-header { display:flex; gap:.75rem; align-items:center; justify-content:space-between; flex-wrap:wrap; }
  .settings-tools { display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; }
  .settings-search { max-width: 360px; }
  .settings-accordion .accordion-button { font-weight: 600; }
  .settings-key { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size: .9rem; }
  .settings-desc { font-size: .85rem; color: #6c757d; }
  .settings-row { border-bottom: 1px dashed rgba(0,0,0,.08); padding: .75rem 0; }
  .settings-row:last-child { border-bottom: 0; }
  .settings-actions { display:flex; gap:.4rem; justify-content:flex-end; }
  .badge-soft { background: rgba(13,110,253,.08); color:#0d6efd; border: 1px solid rgba(13,110,253,.15); }
  .drawer { position: fixed; top: 0; right: -420px; width: 420px; height: 100vh; background: #fff; z-index: 1055; transition: right .2s ease; box-shadow: -10px 0 30px rgba(0,0,0,.15); }
  .drawer.open { right: 0; }
  .drawer-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.35); z-index: 1050; display:none; }
  .drawer-backdrop.show { display:block; }
  @media (max-width: 500px){ .drawer{ width: 100%; } }
  .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }
  .toast { min-width: 250px; margin-bottom: 10px; background-color: #fff; border: 1px solid rgba(0,0,0,.1); box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15); }
  .toast-success { background-color: #198754; color: white; border-color: #198754; }
  .toast-error { background-color: #dc3545; color: white; border-color: #dc3545; }
  .toast.fade-in { animation: fadeIn 0.3s ease-in; display: block; }
  .toast.fade-out { animation: fadeOut 0.3s ease-out; }
  @keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
  @keyframes fadeOut { from { opacity: 1; transform: translateY(0); } to { opacity: 0; transform: translateY(-20px); } }
</style>

<div class="app-shell">
  <?php require_once dirname(dirname(__DIR__)) . '/templates/layout/sidebar.php'; ?>

  <div class="app-content">
    <?php require_once dirname(dirname(__DIR__)) . '/templates/layout/topbar.php'; ?>

    <main class="page-wrap">
      <div class="container-fluid py-3">

        <div class="settings-header mb-3">
          <div>
            <div class="h4 mb-1"><?= h($page_title) ?></div>
            <div class="text-muted small"><?= h($page_subtitle) ?></div>
          </div>
          <div class="settings-tools">
            <input id="settingsSearch" class="form-control settings-search" placeholder="Search settings (key, value, description)..." />
            <button class="btn btn-outline-secondary" id="btnCollapseAll" type="button">
              <i class="bi bi-arrows-collapse"></i> Collapse
            </button>
            <button class="btn btn-primary" id="btnOpenAdd" type="button">
              <i class="bi bi-plus-lg"></i> Add Setting
            </button>
          </div>
        </div>

        <?php if (!$db instanceof mysqli): ?>
          <div class="alert alert-danger">Database not available.</div>
        <?php else: ?>

          <div class="accordion settings-accordion" id="settingsAccordion">
            <?php
              $i = 0;
              foreach ($groups as $gName => $items):
                $i++;
                $gid = 'grp_' . preg_replace('/[^a-z0-9_]/i','_', strtolower($gName));
                $open = ($i === 1) ? 'show' : '';
                $collapsed = ($i === 1) ? '' : 'collapsed';
            ?>
              <div class="accordion-item">
                <h2 class="accordion-header" id="<?= h($gid) ?>_h">
                  <button class="accordion-button <?= h($collapsed) ?>" type="button"
                          data-bs-toggle="collapse"
                          data-bs-target="#<?= h($gid) ?>_c"
                          aria-expanded="<?= $i===1 ? 'true':'false' ?>"
                          aria-controls="<?= h($gid) ?>_c">
                    <?= h($gName) ?>
                    <span class="ms-2 badge badge-soft"><?= count($items) ?></span>
                  </button>
                </h2>

                <div id="<?= h($gid) ?>_c" class="accordion-collapse collapse <?= h($open) ?>"
                     aria-labelledby="<?= h($gid) ?>_h" data-bs-parent="#settingsAccordion">
                  <div class="accordion-body">

                    <?php foreach ($items as $r): ?>
                      <?php
                        $key = (string)$r['key'];
                        $val = (string)($r['value'] ?? '');
                        $desc = (string)($r['description'] ?? '');
                        $type = (string)($r['type'] ?? 'text');
                        $updated = (string)($r['updated_at'] ?? '');
                        $options = (string)($r['options'] ?? '');
                        $opts = json_decode($options, true);
                        if (!is_array($opts)) $opts = get_setting_options($key);
                        $uiType = guess_ui_type($key, $type);
                      ?>
                      <div class="settings-row setting-item"
                           data-key="<?= h($key) ?>"
                           data-value="<?= h($val) ?>"
                           data-desc="<?= h($desc) ?>">

                        <div class="row g-2 align-items-start">
                          <div class="col-lg-4">
                            <div class="settings-key"><?= h($key) ?></div>
                            <?php if ($desc !== ''): ?>
                              <div class="settings-desc mt-1"><?= h($desc) ?></div>
                            <?php endif; ?>
                            <?php if ($updated !== ''): ?>
                              <div class="text-muted small mt-1">Updated: <?= h($updated) ?></div>
                            <?php endif; ?>
                          </div>

                          <div class="col-lg-6">
                            <?php if ($uiType === 'bool'): ?>
                              <div class="form-check form-switch mt-1">
                                <input class="form-check-input setting-input" type="checkbox"
                                       data-type="bool"
                                       <?= ($val === '1' || strtolower($val)==='true' || strtolower($val)==='yes') ? 'checked' : '' ?>>
                                <label class="form-check-label text-muted small">Toggle</label>
                              </div>
                            <?php elseif ($uiType === 'select' && !empty($opts)): ?>
                              <select class="form-select form-select-sm setting-input" data-type="select">
                                <?php foreach ($opts as $opt): ?>
                                  <option value="<?= h((string)$opt) ?>" <?= $val === (string)$opt ? 'selected' : '' ?>><?= h((string)$opt) ?></option>
                                <?php endforeach; ?>
                              </select>
                            <?php elseif ($uiType === 'color'): ?>
                              <div class="d-flex align-items-center gap-2">
                                <input type="color" class="form-control form-control-sm setting-input form-control-color"
                                       data-type="color"
                                       value="<?= h($val ?: '#000000') ?>">
                                <input type="text" class="form-control form-control-sm setting-input flex-grow-1"
                                       data-type="color"
                                       value="<?= h($val) ?>"
                                       placeholder="#000000">
                              </div>
                            <?php elseif ($uiType === 'number'): ?>
                              <input type="number" class="form-control form-control-sm setting-input"
                                     data-type="number"
                                     value="<?= h($val) ?>"
                                     step="any">
                            <?php elseif ($uiType === 'email'): ?>
                              <input type="email" class="form-control form-control-sm setting-input"
                                     data-type="email"
                                     value="<?= h($val) ?>"
                                     placeholder="email@example.com">
                            <?php elseif ($uiType === 'url'): ?>
                              <input type="url" class="form-control form-control-sm setting-input"
                                     data-type="url"
                                     value="<?= h($val) ?>"
                                     placeholder="https://example.com">
                            <?php elseif ($uiType === 'tel'): ?>
                              <input type="tel" class="form-control form-control-sm setting-input"
                                     data-type="tel"
                                     value="<?= h($val) ?>"
                                     placeholder="+256 700 000 000">
                            <?php elseif ($uiType === 'json'): ?>
                              <textarea class="form-control form-control-sm setting-input"
                                        data-type="json" rows="6"><?= h($val) ?></textarea>
                              <div class="text-muted small mt-1">JSON value (normal Enter works for new lines)</div>
                            <?php elseif ($uiType === 'textarea' || is_multiline_key($key) || strlen($val) > 80 || strpos($val, "\n") !== false): ?>
                              <textarea class="form-control form-control-sm setting-input"
                                        data-type="text"
                                        rows="4"><?= h($val) ?></textarea>
                            <?php else: ?>
                              <input class="form-control form-control-sm setting-input"
                                     data-type="text"
                                     value="<?= h($val) ?>">
                            <?php endif; ?>
                          </div>

                          <div class="col-lg-2">
                            <div class="settings-actions">
                              <button class="btn btn-sm btn-outline-primary btnSave"
                                      type="button"
                                      data-key="<?= h($key) ?>">
                                <i class="bi bi-save"></i>
                              </button>
                              <button class="btn btn-sm btn-outline-danger btnDelete"
                                      type="button"
                                      data-key="<?= h($key) ?>">
                                <i class="bi bi-trash"></i>
                              </button>
                            </div>
                          </div>
                        </div>

                      </div>
                    <?php endforeach; ?>

                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

        <?php endif; ?>

      </div>
    </main>
  </div>
</div>

<!-- Toast Container - Outside app-shell for proper positioning -->
<div class="toast-container" id="toastContainer"></div>

<!-- Add setting drawer -->
<div class="drawer-backdrop" id="drawerBackdrop"></div>
<div class="drawer" id="drawerAdd">
  <div class="p-3 border-bottom d-flex align-items-center justify-content-between">
    <div class="fw-semibold">Add Setting</div>
    <button class="btn btn-sm btn-outline-secondary" id="btnCloseAdd" type="button">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <div class="p-3">
    <form id="formAddSetting" class="vstack gap-2">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

      <?php if ($hasGrouping): ?>
        <div>
          <label class="form-label small">Group</label>
          <input class="form-control" name="group" placeholder="General / POS / Taxes ...">
        </div>

        <div>
          <label class="form-label small">Type</label>
          <select class="form-select" name="type" id="addSettingType">
            <option value="text">Text</option>
            <option value="textarea">Textarea</option>
            <option value="bool">Boolean (Toggle)</option>
            <option value="number">Number</option>
            <option value="select">Select (Dropdown)</option>
            <option value="email">Email</option>
            <option value="url">URL</option>
            <option value="tel">Phone</option>
            <option value="color">Color</option>
            <option value="json">JSON</option>
          </select>
        </div>

        <div id="addSettingOptionsWrap" class="d-none">
          <label class="form-label small">Options (one per line, for Select type)</label>
          <textarea class="form-control" name="options" id="addSettingOptions" rows="4"
                    placeholder="Option1&#10;Option2&#10;Option3"></textarea>
          <div class="form-text">Enter one option per line. The first option is the default.</div>
        </div>

        <div>
          <label class="form-label small">Sort order</label>
          <input class="form-control" name="sort_order" type="number" value="0">
        </div>
      <?php endif; ?>

      <div>
        <label class="form-label small">Key</label>
        <input class="form-control" name="key" placeholder="e.g. business.name or pos.allow_discounts" required>
      </div>

      <div>
        <label class="form-label small">Value</label>
        <textarea class="form-control" name="value" rows="6" placeholder="Value (multi-line supported)" required></textarea>
      </div>

      <div>
        <label class="form-label small">Description (optional)</label>
        <input class="form-control" name="description" placeholder="Short description">
      </div>

      <button class="btn btn-primary" type="submit">
        <i class="bi bi-check2"></i> Save
      </button>

      <div class="text-muted small">
        Tip: Use dots for grouping in fallback mode: <code>pos.allow_discounts</code>, <code>tax.vat_rate</code><br>
        Text areas: Enter to expand box, Shift+Enter for new line
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  const BASE = <?= json_encode(rtrim((string)$BASE, '/')) ?>;
  const csrf = <?= json_encode((string)$csrf) ?>;

  const post = async (url, fd) => {
    const res = await fetch(url, { method:'POST', body: fd, credentials:'same-origin' });
    const txt = await res.text();
    
    if (txt && txt.trim().startsWith('<')) {
      throw new Error('API returned HTML (likely redirect / permission / PHP error). Check Network tab.');
    }
    
    let j = null;
    try { 
      j = JSON.parse(txt); 
    } catch(e) { 
      j = { success:false, message: txt.substring(0,200) }; 
    }
    if (!res.ok && j && j.message) throw new Error(j.message);
    return j;
  };

  // Toast notification system
  const showToast = (message, type = 'success', duration = 3000) => {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type} fade-in`;
    toast.style.cssText = 'display: block !important; visibility: visible !important; opacity: 1 !important;';
    toast.innerHTML = `
      <div class="toast-body d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
          <i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2"></i>
          <span>${message}</span>
        </div>
        <button type="button" class="btn-close btn-close-white ms-2" onclick="this.closest('.toast').remove()"></button>
      </div>
    `;
    
    container.appendChild(toast);
    
    // Auto remove after duration
    setTimeout(() => {
      toast.classList.remove('fade-in');
      toast.classList.add('fade-out');
      setTimeout(() => toast.remove(), 300);
    }, duration);
  };

  // Search filter
  const q = document.getElementById('settingsSearch');
  q?.addEventListener('input', ()=>{
    const s = (q.value || '').toLowerCase().trim();
    document.querySelectorAll('.setting-item').forEach(el=>{
      const hay = (el.dataset.key + ' ' + el.dataset.value + ' ' + el.dataset.desc).toLowerCase();
      el.style.display = (!s || hay.includes(s)) ? '' : 'none';
    });
  });

  // Collapse all
  document.getElementById('btnCollapseAll')?.addEventListener('click', ()=>{
    document.querySelectorAll('.accordion-collapse.show').forEach(el=>{
      bootstrap.Collapse.getOrCreateInstance(el).hide();
    });
  });

  // Drawer
  const drawer = document.getElementById('drawerAdd');
  const backdrop = document.getElementById('drawerBackdrop');
  const openDrawer = ()=>{ drawer.classList.add('open'); backdrop.classList.add('show'); };
  const closeDrawer = ()=>{ drawer.classList.remove('open'); backdrop.classList.remove('show'); };

  document.getElementById('btnOpenAdd')?.addEventListener('click', openDrawer);
  document.getElementById('btnCloseAdd')?.addEventListener('click', closeDrawer);
  backdrop?.addEventListener('click', closeDrawer);

  // Save inline
  document.querySelectorAll('.btnSave').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      const row = btn.closest('.setting-item');
      const key = btn.dataset.key || '';
      let input = row?.querySelector('.setting-input');
      // For color, prefer the text input (holds the full hex value)
      if (input && input.dataset.type === 'color' && input.type === 'color') {
        input = row.querySelector('input[data-type="color"]:not([type="color"])') || input;
      }
      if (!input) return;

      let val = '';
      const type = input.dataset.type || 'text';
      if (type === 'bool') val = input.checked ? '1' : '0';
      else if (type === 'color') val = (input.value || '').trim();
      else val = (input.value ?? '').replace(/\r\n/g, "\n").trimEnd();

      const fd = new FormData();
      fd.append('csrf', csrf);
      fd.append('key', key);
      fd.append('value', val);

      try {
        const j = await post(`${BASE}/api/settings/upsert.php`, fd);
        
        if (j.success) {
          showToast(`Setting "${key}" saved successfully`, 'success');
          row.dataset.value = val;
          btn.classList.remove('btn-outline-primary');
          btn.classList.add('btn-outline-success');
          setTimeout(()=>{ btn.classList.remove('btn-outline-success'); btn.classList.add('btn-outline-primary'); }, 700);
          
          // If this is a permission-related setting, refresh the session
          if (key.includes('messaging') || key.includes('permission')) {
            // Clear permission cache by forcing session refresh
            if (function_exists('session_regenerate')) {
              session_regenerate(true);
            }
          }
        } else {
          showToast(j.message || 'Failed to save setting', 'error');
        }
      } catch (error) {
        showToast('Save failed: ' + error.message, 'error');
      }
    });
  });

  // Delete
  document.querySelectorAll('.btnDelete').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      if (!confirm('Delete this setting?')) return;
      const key = btn.dataset.key || '';
      const fd = new FormData();
      fd.append('csrf', csrf);
      fd.append('key', key);

      const j = await post(`${BASE}/api/settings/delete.php`, fd);
      
      if (j.success) {
        showToast(`Setting "${key}" deleted successfully`, 'success');
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast(j.message || 'Failed to delete setting', 'error');
      }
    });
  });

  // Add new setting
  document.getElementById('formAddSetting')?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const fd = new FormData(e.target);
    if (!fd.get('csrf')) fd.append('csrf', csrf);

    const j = await post(`${BASE}/api/settings/upsert.php`, fd);
      
      if (j.success) {
        showToast('New setting added successfully', 'success');
        
        // If this is a permission-related setting, refresh the session
        const fdKeys = Array.from(e.target.elements).map(el => el.name);
        if (fdKeys.includes('messaging') || fdKeys.some(key => key.includes('permission'))) {
          if (function_exists('session_regenerate')) {
            session_regenerate(true);
          }
        }
        
        setTimeout(() => location.reload(), 1000);
      } else {
        showToast(j.message || 'Failed to add setting', 'error');
      }
  });

  // Auto-grow textarea
  document.querySelectorAll('textarea.setting-input').forEach(t => {
    const grow = () => { t.style.height = 'auto'; t.style.height = (t.scrollHeight + 2) + 'px'; };
    t.addEventListener('input', grow);
    setTimeout(grow, 0);
  });

  // Toggle options field visibility for select type
  const addTypeSelect = document.getElementById('addSettingType');
  const addOptionsWrap = document.getElementById('addSettingOptionsWrap');
  if (addTypeSelect && addOptionsWrap) {
    addTypeSelect.addEventListener('change', () => {
      addOptionsWrap.classList.toggle('d-none', addTypeSelect.value !== 'select');
    });
  }

  // Sync color inputs
  document.querySelectorAll('input[data-type="color"]').forEach(input => {
    if (input.type === 'color') {
      const textInput = input.parentElement.querySelector('input[data-type="color"]:not([type="color"])');
      if (textInput) {
        input.addEventListener('input', () => { textInput.value = input.value; });
        textInput.addEventListener('input', () => { if (/^#[0-9a-fA-F]{6}$/.test(textInput.value)) input.value = textInput.value; });
      }
    }
  });

})();
</script>

<?php require_once dirname(dirname(__DIR__)) . '/templates/layout/footer.php'; ?>
