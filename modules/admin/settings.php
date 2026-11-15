<?php
// modules/admin/settings.php — settings grouped by purpose; every change saves itself
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_once dirname(__DIR__, 2) . '/includes/rbac.php';
require_once dirname(__DIR__, 2) . '/includes/settings.php';

require_super_admin();

$BASE_URL = $GLOBALS['BASE_URL'] ?? '';
$schema = settings_schema();
$themes = require dirname(__DIR__, 2) . '/includes/themes.php';
$page_title = 'Settings';
$page_subtitle = 'Changes save as you make them';
$current_user_role = $_SESSION['user']['role'] ?? '';

require_once dirname(__DIR__, 2) . '/templates/layout/header.php';
?>
<div class="app-shell">
  <?php require_once dirname(__DIR__, 2) . '/templates/layout/sidebar.php'; ?>
  <div class="app-content">
    <?php require_once dirname(__DIR__, 2) . '/templates/layout/topbar.php'; ?>
    <main class="page-wrap">
      <div class="container-fluid">
        <div class="bm-settings">
          <nav class="bm-settings-nav" aria-label="Settings sections">
            <?php $first = true; foreach ($schema as $id => $sec): ?>
              <a href="#set-<?= h($id) ?>" class="<?= $first ? 'active' : '' ?>" data-section="<?= h($id) ?>"><i class="bi <?= h($sec['icon']) ?>" aria-hidden="true"></i><?= h($sec['label']) ?></a>
            <?php $first = false; endforeach; ?>
            <a href="#set-system" data-section="system"><i class="bi bi-hdd-rack" aria-hidden="true"></i>System</a>
          </nav>

          <div class="d-grid gap-3">
            <?php foreach ($schema as $id => $sec): ?>
              <section class="card" id="set-<?= h($id) ?>" data-section-card="<?= h($id) ?>">
                <div class="card-body">
                  <h2 class="h5 mb-1"><i class="bi <?= h($sec['icon']) ?> me-2 text-primary" aria-hidden="true"></i><?= h($sec['label']) ?></h2>
                  <p class="text-muted small mb-3"><?= h($sec['intro']) ?></p>
                  <?php foreach ($sec['fields'] as $key => $f):
                      $val = setting($key);
                      $cid = 'f_' . $key;
                      $showIf = isset($f['show_if']) ? json_encode($f['show_if']) : '';
                  ?>
                    <div class="bm-setting-row" <?= $showIf ? 'data-show-if="' . h($showIf) . '"' : '' ?>>
                      <div>
                        <label class="bm-setting-name d-block" for="<?= h($cid) ?>"><?= h($f['label']) ?></label>
                        <?php if (!empty($f['help'])): ?><div class="bm-setting-help"><?= h($f['help']) ?></div><?php endif; ?>
                      </div>
                      <div class="bm-setting-control">
                        <?php if ($f['type'] === 'switch'): ?>
                          <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="<?= h($cid) ?>" data-key="<?= h($key) ?>" <?= $val === '1' ? 'checked' : '' ?>>
                          </div>
                        <?php elseif ($f['type'] === 'select'): ?>
                          <select class="form-select" id="<?= h($cid) ?>" data-key="<?= h($key) ?>">
                            <?php foreach ($f['options'] as $ov => $ol): ?>
                              <option value="<?= h((string)$ov) ?>" <?= (string)$ov === $val ? 'selected' : '' ?>><?= h($ol) ?></option>
                            <?php endforeach; ?>
                          </select>
                        <?php elseif ($f['type'] === 'textarea'): ?>
                          <textarea class="form-control" rows="2" id="<?= h($cid) ?>" data-key="<?= h($key) ?>"><?= h($val) ?></textarea>
                        <?php elseif ($f['type'] === 'password'): ?>
                          <input class="form-control" type="password" autocomplete="new-password" id="<?= h($cid) ?>" data-key="<?= h($key) ?>" value="" placeholder="<?= $val !== '' ? 'Saved. Type to replace' : 'Not set' ?>">
                        <?php elseif ($f['type'] === 'theme'): ?>
                          <div class="bm-swatches" role="radiogroup" aria-label="Theme" id="<?= h($cid) ?>">
                            <?php foreach ($themes as $tid => $t): ?>
                              <button type="button" class="bm-swatch <?= $tid === $val ? 'active' : '' ?>" role="radio" aria-checked="<?= $tid === $val ? 'true' : 'false' ?>"
                                      data-theme-id="<?= h($tid) ?>" data-mode="<?= h($t['mode']) ?>" title="<?= h($t['name']) ?>">
                                <span class="bm-swatch-chip" style="background:<?= h($t['bg']) ?>"><span style="background:<?= h($t['sidebar']) ?>"></span><span style="background:<?= h($t['color']) ?>"></span></span>
                                <span class="bm-swatch-name"><?= h($t['name']) ?></span>
                              </button>
                            <?php endforeach; ?>
                          </div>
                        <?php else: ?>
                          <input class="form-control" type="<?= $f['type'] === 'number' ? 'number' : 'text' ?>" id="<?= h($cid) ?>" data-key="<?= h($key) ?>" value="<?= h($val) ?>"
                            <?= isset($f['min']) ? 'min="' . h((string)$f['min']) . '"' : '' ?> <?= isset($f['max']) && $f['type'] === 'number' ? 'max="' . h((string)$f['max']) . '"' : '' ?>
                            <?= isset($f['step']) ? 'step="' . h((string)$f['step']) . '"' : '' ?> <?= isset($f['max']) && $f['type'] !== 'number' ? 'maxlength="' . (int)$f['max'] . '"' : '' ?>>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                  <?php if ($id === 'appearance'): ?>
                    <a class="btn btn-outline-secondary btn-sm mt-3" href="<?= h($BASE_URL) ?>/modules/admin/themes.php">Upload a custom theme</a>
                  <?php elseif ($id === 'email'): ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm mt-3" id="btnTestEmail">Send a test email to me</button>
                  <?php elseif ($id === 'sms'): ?>
                    <div class="input-group input-group-sm mt-3" style="max-width:360px">
                      <input class="form-control" id="testSmsTo" placeholder="Phone number, e.g. 0700123456" aria-label="Phone number for test SMS">
                      <button type="button" class="btn btn-outline-secondary" id="btnTestSms">Send test SMS</button>
                    </div>
                  <?php endif; ?>
                </div>
              </section>
            <?php endforeach; ?>

            <section class="card" id="set-system">
              <div class="card-body">
                <h2 class="h5 mb-1"><i class="bi bi-hdd-rack me-2 text-primary" aria-hidden="true"></i>System</h2>
                <p class="text-muted small mb-3">Version <?= h(defined('APP_VERSION') ? APP_VERSION : '') ?></p>
                <div class="d-flex flex-wrap gap-2">
                  <a class="btn btn-outline-secondary btn-sm" href="<?= h($BASE_URL) ?>/modules/admin/updates.php"><i class="bi bi-cloud-download me-1" aria-hidden="true"></i>Updates</a>
                  <a class="btn btn-outline-secondary btn-sm" href="<?= h($BASE_URL) ?>/modules/admin/payment_settings.php"><i class="bi bi-credit-card me-1" aria-hidden="true"></i>Payment gateway</a>
                  <a class="btn btn-outline-secondary btn-sm" href="<?= h($BASE_URL) ?>/modules/admin/users.php"><i class="bi bi-people me-1" aria-hidden="true"></i>Users</a>
                  <a class="btn btn-outline-secondary btn-sm" href="<?= h($BASE_URL) ?>/modules/admin/settings_advanced.php"><i class="bi bi-braces me-1" aria-hidden="true"></i>Advanced keys</a>
                  <a class="btn btn-outline-secondary btn-sm" href="<?= h($BASE_URL) ?>/setup.php"><i class="bi bi-box-arrow-in-down me-1" aria-hidden="true"></i>Install / uninstall</a>
                </div>
              </div>
            </section>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<style>
  .bm-swatch { display: grid; gap: 6px; justify-items: center; width: 96px; padding: 8px; border-radius: 12px; border: 2px solid var(--bm-border); background: var(--bm-surface); color: var(--bm-text); font-size: .75rem; transition: border-color var(--bm-dur) var(--bm-ease), transform var(--bm-dur) var(--bm-ease); }
  .bm-swatch:hover { transform: translateY(-2px); }
  .bm-swatch.active { border-color: var(--bm-primary); }
  .bm-swatch-chip { display: flex; width: 100%; height: 44px; border-radius: 8px; overflow: hidden; border: 1px solid var(--bm-border); align-items: stretch; }
  .bm-swatch-chip span:first-child { width: 30%; } .bm-swatch-chip span:last-child { width: 26%; margin: 12px 0 12px auto; border-radius: 6px 0 0 6px; }
  .bm-setting-row.is-saving { opacity: .6; }
  [id^="set-"] { scroll-margin-top: calc(var(--bm-topbar-h) + 12px); }
</style>
<script>
(function () {
  function save(key, value, row) {
    var fd = new FormData(); fd.append('key', key); fd.append('value', value);
    if (row) row.classList.add('is-saving');
    return fetch(BM.url('api/settings/save.php'), { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .catch(function () { return { success: false, message: 'Could not reach the server.' }; })
      .then(function (j) {
        if (row) row.classList.remove('is-saving');
        BM.toast(j.success ? 'Saved' : (j.message || 'Not saved'), j.success ? 'success' : 'danger', j.success ? 1500 : 6000);
        return j.success;
      });
  }

  // Fields that only matter for a chosen option (e.g. SMTP details) hide until needed
  function applyConditions() {
    document.querySelectorAll('[data-show-if]').forEach(function (row) {
      var cond = JSON.parse(row.dataset.showIf), show = true;
      Object.keys(cond).forEach(function (k) {
        var el = document.querySelector('[data-key="' + k + '"]');
        var want = [].concat(cond[k]);
        if (!el || want.indexOf(el.value) === -1) show = false;
      });
      row.hidden = !show;
    });
  }
  applyConditions();

  document.querySelectorAll('[data-key]').forEach(function (el) {
    var row = el.closest('.bm-setting-row');
    var last = el.type === 'checkbox' ? el.checked : el.value;
    function commit() {
      var now = el.type === 'checkbox' ? el.checked : el.value;
      if (now === last) return;
      if (el.type === 'password' && now === '') return;
      save(el.dataset.key, el.type === 'checkbox' ? (now ? '1' : '0') : now, row).then(function (ok) {
        if (ok) { last = now; if (el.type === 'password') { el.value = ''; last = ''; el.placeholder = 'Saved. Type to replace'; } }
        else if (el.type === 'checkbox') el.checked = last; else if (el.type !== 'password') el.value = last;
        applyConditions();
      });
    }
    el.addEventListener('change', commit);
    if (el.tagName === 'INPUT' && el.type !== 'checkbox') el.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); el.blur(); } });
  });

  // Themes apply instantly so the choice can be judged before moving on
  document.querySelectorAll('.bm-swatch').forEach(function (b) {
    b.addEventListener('click', function () {
      save('app_theme', b.dataset.themeId).then(function (ok) {
        if (!ok) return;
        document.documentElement.setAttribute('data-theme', b.dataset.themeId);
        document.documentElement.setAttribute('data-bs-theme', b.dataset.mode);
        document.querySelectorAll('.bm-swatch').forEach(function (x) { x.classList.toggle('active', x === b); x.setAttribute('aria-checked', x === b ? 'true' : 'false'); });
      });
    });
  });

  function test(kind, extra) {
    var fd = new FormData(); fd.append('kind', kind); if (extra) fd.append('to', extra);
    fetch(BM.url('api/settings/test_message.php'), { method: 'POST', body: fd }).then(function (r) { return r.json(); })
      .then(function (j) { BM.toast(j.message || (j.success ? 'Sent' : 'Failed'), j.success ? 'success' : 'danger', 8000); })
      .catch(function () { BM.toast('Could not reach the server.', 'danger'); });
  }
  var te = document.getElementById('btnTestEmail'); if (te) te.addEventListener('click', function () { test('email'); });
  var ts = document.getElementById('btnTestSms'); if (ts) ts.addEventListener('click', function () { test('sms', document.getElementById('testSmsTo').value); });

  // Highlight the section in view
  var links = document.querySelectorAll('.bm-settings-nav a');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        links.forEach(function (a) { a.classList.toggle('active', a.getAttribute('href') === '#' + en.target.id); });
      });
    }, { rootMargin: '-20% 0px -70% 0px' });
    document.querySelectorAll('section[id^="set-"]').forEach(function (s) { io.observe(s); });
  }
})();
</script>
<?php require_once dirname(__DIR__, 2) . '/templates/layout/footer.php'; ?>
