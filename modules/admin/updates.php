<?php
// admin/updates.php
declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/helpers.php';
require_once dirname(dirname(__DIR__)) . '/includes/rbac.php';

require_super_admin();
require_permission('updates.manage');

$db = $GLOBALS['db'] ?? null;

$page_title = "Updates";
$page_subtitle = "Check and apply system updates";

require_once dirname(dirname(__DIR__)) . '/templates/layout/header.php';

$currentVersion = defined('APP_VERSION') ? (string)APP_VERSION : '1.0.0';
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

        <div class="row g-3">
          <div class="col-lg-7">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                  <div>
                    <div class="text-muted small">Installed version</div>
                    <div class="fs-5 fw-semibold"><?= h($currentVersion) ?> <span class="text-muted small" id="updCommit"></span></div>
                  </div>
                  <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" id="btnCheck"><i class="bi bi-arrow-repeat me-1"></i>Check for updates</button>
                    <button class="btn btn-primary" id="btnApply" disabled><i class="bi bi-cloud-download me-1"></i>Update now</button>
                  </div>
                </div>
                <div id="updStatus" class="alert alert-secondary mb-3">Press “Check for updates” to see what is new.</div>
                <ul class="list-group list-group-flush small" id="updChanges"></ul>
                <div id="updMigrate" class="d-none mt-3">
                  <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-0">
                    <span>The database needs <b id="updMigCount">0</b> pending step(s).</span>
                    <button class="btn btn-sm btn-warning" id="btnMigrate">Update database</button>
                  </div>
                </div>
                <details class="mt-3 small text-muted"><summary>Technical details</summary><pre class="bg-light border rounded p-2 mt-2 mb-0" id="logBox" style="white-space:pre-wrap"></pre></details>
              </div>
            </div>
          </div>
          <div class="col-lg-5">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="fw-semibold mb-2">How updates work</div>
                <ul class="small text-muted ps-3 mb-0">
                  <li>Updates are pulled from the project's git repository, on the branch this server was installed from.</li>
                  <li>Your data, uploads and database settings are never overwritten.</li>
                  <li>If files on the server were edited by hand and clash with an update, the update stops and nothing changes.</li>
                  <li>Database changes that ship with an update are applied automatically afterwards.</li>
                  <li>Every update is recorded under Update History.</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<script>
(function(){
  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const status = (msg, kind) => { $('updStatus').className = 'alert mb-3 alert-' + kind; $('updStatus').textContent = msg; };
  const busy = (b, on) => { b.disabled = on; };

  async function call(path, opts) {
    const res = await fetch(BM.url(path), opts || {});
    let j;
    try { j = await res.json(); } catch (e) { j = {success:false, message:'The server returned an unexpected response.'}; }
    $('logBox').textContent = JSON.stringify(j, null, 2);
    return j;
  }

  function showMigrations(list) {
    const n = (list || []).length;
    $('updMigCount').textContent = n;
    $('updMigrate').classList.toggle('d-none', n === 0);
  }

  $('btnCheck').addEventListener('click', async () => {
    busy($('btnCheck'), true); status('Checking…', 'secondary'); $('updChanges').innerHTML = '';
    const j = await call('api/updates/check.php');
    busy($('btnCheck'), false);
    showMigrations(j.pending_migrations);
    if (!j.success) { status(j.message || 'Could not check for updates.', 'warning'); $('btnApply').disabled = true; return; }
    $('updCommit').textContent = j.current ? '(' + j.current + ', ' + j.branch + ')' : '';
    status(j.message, j.update_available ? 'info' : 'success');
    $('updChanges').innerHTML = (j.changes || []).map(c => '<li class="list-group-item px-0">' + esc(c) + '</li>').join('');
    if ((j.local_changes || []).length) {
      $('updChanges').insertAdjacentHTML('afterbegin', '<li class="list-group-item px-0 text-warning">' + j.local_changes.length + ' file(s) on this server were edited by hand. The update will stop if they clash.</li>');
    }
    $('btnApply').disabled = !j.update_available;
  });

  $('btnApply').addEventListener('click', async () => {
    if (!confirm('Update the system now? Ask staff to pause sales for a minute.')) return;
    busy($('btnApply'), true); status('Updating… do not close this page.', 'secondary');
    const j = await call('api/updates/apply.php', {method:'POST'});
    status(j.message || (j.success ? 'Updated.' : 'Update failed.'), j.success ? 'success' : 'danger');
    if (j.success) { $('updChanges').innerHTML = ''; showMigrations([]); }
  });

  $('btnMigrate').addEventListener('click', async () => {
    busy($('btnMigrate'), true);
    const fd = new FormData(); fd.append('mode', 'migrate');
    const j = await call('api/updates/apply.php', {method:'POST', body: fd});
    busy($('btnMigrate'), false);
    status(j.message || 'Done.', j.success ? 'success' : 'danger');
    if (j.success) showMigrations([]);
  });
})();
</script>

<?php require_once dirname(dirname(__DIR__)) . '/templates/layout/footer.php'; ?>