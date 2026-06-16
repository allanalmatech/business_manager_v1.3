<?php
// admin/database_reset.php
declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/helpers.php';
require_once dirname(dirname(__DIR__)) . '/includes/rbac.php';

require_super_admin();

$db = $GLOBALS['db'] ?? null;
$BASE = $GLOBALS['BASE_URL'] ?? '';
$csrfToken = (string)($_SESSION['csrf'] ?? '');

$page_title = 'Database Reset';
$page_subtitle = 'Empty all data from the current database';

function database_reset_quote_identifier(string $identifier): string {
  return '`' . str_replace('`', '``', $identifier) . '`';
}

function database_reset_current_database(mysqli $db): string {
  $result = $db->query('SELECT DATABASE() AS db_name');
  if (!$result) return '';
  $row = $result->fetch_assoc();
  return (string)($row['db_name'] ?? '');
}

function database_reset_table_info(mysqli $db): array {
  $tables = [];
  $sql = "
    SELECT TABLE_NAME, TABLE_ROWS
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_TYPE = 'BASE TABLE'
    ORDER BY TABLE_NAME ASC
  ";
  $result = $db->query($sql);

  if (!$result) return $tables;

  while ($row = $result->fetch_assoc()) {
    $name = (string)($row['TABLE_NAME'] ?? '');
    if ($name === '') continue;

    $tables[] = [
      'name' => $name,
      'rows' => $row['TABLE_ROWS'] === null ? null : (int)$row['TABLE_ROWS'],
    ];
  }

  return $tables;
}

function database_reset_table_names(array $tableInfo): array {
  $names = [];
  foreach ($tableInfo as $table) {
    $name = (string)($table['name'] ?? '');
    if ($name !== '') $names[] = $name;
  }
  return $names;
}

function database_reset_close_current_session(): void {
  if (session_status() !== PHP_SESSION_ACTIVE) return;

  $_SESSION = [];

  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
      session_name(),
      '',
      time() - 42000,
      $params['path'] ?? '/',
      $params['domain'] ?? '',
      (bool)($params['secure'] ?? false),
      (bool)($params['httponly'] ?? true)
    );
  }

  session_write_close();
}

function database_reset_empty_tables(mysqli $db, array $tables): void {
  if (!$db->query('SET FOREIGN_KEY_CHECKS=0')) {
    throw new RuntimeException('Unable to disable foreign key checks: ' . $db->error);
  }

  try {
    foreach ($tables as $table) {
      $table = (string)$table;
      if ($table === '') continue;

      $sql = 'TRUNCATE TABLE ' . database_reset_quote_identifier($table);
      if (!$db->query($sql)) {
        $truncateError = $db->error;
        $deleteSql = 'DELETE FROM ' . database_reset_quote_identifier($table);

        if (!$db->query($deleteSql)) {
          throw new RuntimeException('Failed to empty table ' . $table . ': ' . $truncateError . '; delete fallback failed: ' . $db->error);
        }

        $db->query('ALTER TABLE ' . database_reset_quote_identifier($table) . ' AUTO_INCREMENT = 1');
      }
    }
  } finally {
    $db->query('SET FOREIGN_KEY_CHECKS=1');
  }
}

$errors = [];
$databaseName = $db instanceof mysqli ? database_reset_current_database($db) : '';
$tableInfo = $db instanceof mysqli ? database_reset_table_info($db) : [];
$tableNames = database_reset_table_names($tableInfo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postedCsrf = (string)($_POST['csrf'] ?? '');
  $confirmDatabase = trim((string)($_POST['confirm_database'] ?? ''));
  $confirmPhrase = trim((string)($_POST['confirm_phrase'] ?? ''));

  if (!$db instanceof mysqli) {
    $errors[] = 'Database is not available.';
  } elseif (!hash_equals($csrfToken, $postedCsrf)) {
    $errors[] = 'Invalid session. Refresh and try again.';
  } elseif ($databaseName === '') {
    $errors[] = 'Unable to determine the current database name.';
  } else {
    if ($confirmDatabase !== $databaseName) {
      $errors[] = 'Database name confirmation does not match.';
    }

    if ($confirmPhrase !== 'EMPTY DATABASE') {
      $errors[] = 'Confirmation phrase must be exactly EMPTY DATABASE.';
    }
  }

  if (!$errors && $db instanceof mysqli) {
    $tableInfo = database_reset_table_info($db);
    $tableNames = database_reset_table_names($tableInfo);

    try {
      database_reset_close_current_session();
      database_reset_empty_tables($db, $tableNames);

      header('Location: ' . $BASE . '/login.php?database_emptied=1');
      exit;
    } catch (Throwable $e) {
      $errors[] = $e->getMessage();
    }
  }
}

$tableCount = count($tableInfo);
$approxRows = 0;
$hasRowEstimate = false;
foreach ($tableInfo as $table) {
  if ($table['rows'] !== null) {
    $hasRowEstimate = true;
    $approxRows += (int)$table['rows'];
  }
}

require_once dirname(dirname(__DIR__)) . '/templates/layout/header.php';
?>
<div class="app-shell">
  <?php require_once dirname(dirname(__DIR__)) . '/templates/layout/sidebar.php'; ?>

  <div class="app-content">
    <?php require_once dirname(dirname(__DIR__)) . '/templates/layout/topbar.php'; ?>

    <main class="page-wrap">
      <div class="container-fluid py-3">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
          <div>
            <h4 class="mb-1"><?= h($page_title) ?></h4>
            <div class="text-muted small"><?= h($page_subtitle) ?></div>
          </div>
        </div>

        <?php foreach ($errors as $error): ?>
          <div class="alert alert-danger d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-triangle mt-1"></i>
            <div><?= h($error) ?></div>
          </div>
        <?php endforeach; ?>

        <?php if (!$db instanceof mysqli): ?>
          <div class="alert alert-danger">Database not available.</div>
        <?php else: ?>
          <div class="row g-3">
            <div class="col-lg-5">
              <div class="card border-danger shadow-sm">
                <div class="card-header bg-danger text-white">
                  <div class="fw-semibold"><i class="bi bi-database-x me-1"></i> Empty Database</div>
                </div>
                <div class="card-body">
                  <div class="alert alert-warning small">
                    This will delete every row from every table in <strong><?= h($databaseName) ?></strong>.
                    It keeps the table structure, but removes users, sessions, settings, products, sales, logs, and all other data.
                  </div>

                  <dl class="row small mb-3">
                    <dt class="col-sm-5">Database</dt>
                    <dd class="col-sm-7"><code><?= h($databaseName) ?></code></dd>

                    <dt class="col-sm-5">Tables found</dt>
                    <dd class="col-sm-7"><?= (int)$tableCount ?></dd>

                    <dt class="col-sm-5">Estimated rows</dt>
                    <dd class="col-sm-7"><?= $hasRowEstimate ? number_format($approxRows) : 'Unknown' ?></dd>
                  </dl>

                  <form method="post" id="databaseResetForm">
                    <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">

                    <div class="mb-3">
                      <label class="form-label">Type the database name</label>
                      <input class="form-control" name="confirm_database" id="confirmDatabase" data-expected="<?= h($databaseName) ?>" autocomplete="off" required>
                    </div>

                    <div class="mb-3">
                      <label class="form-label">Type <code>EMPTY DATABASE</code></label>
                      <input class="form-control" name="confirm_phrase" id="confirmPhrase" autocomplete="off" required>
                    </div>

                    <button class="btn btn-danger w-100" id="btnEmptyDatabase" type="submit" disabled>
                      <i class="bi bi-trash3 me-1"></i> Empty Database Permanently
                    </button>
                  </form>
                </div>
              </div>
            </div>

            <div class="col-lg-7">
              <div class="card shadow-sm">
                <div class="card-header bg-light">
                  <div class="fw-semibold">Tables That Will Be Emptied</div>
                </div>
                <div class="card-body p-0">
                  <?php if (!$tableInfo): ?>
                    <div class="p-3 text-muted">No base tables found in this database.</div>
                  <?php else: ?>
                    <div class="table-responsive">
                      <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                          <tr>
                            <th>Table</th>
                            <th class="text-end">Estimated Rows</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($tableInfo as $table): ?>
                            <tr>
                              <td><code><?= h($table['name']) ?></code></td>
                              <td class="text-end text-muted">
                                <?= $table['rows'] === null ? 'Unknown' : number_format((int)$table['rows']) ?>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>

<script>
(function(){
  const form = document.getElementById('databaseResetForm');
  if (!form) return;

  const databaseInput = document.getElementById('confirmDatabase');
  const phraseInput = document.getElementById('confirmPhrase');
  const submitButton = document.getElementById('btnEmptyDatabase');

  function updateSubmitState() {
    submitButton.disabled = !(
      databaseInput.value === databaseInput.dataset.expected &&
      phraseInput.value === 'EMPTY DATABASE'
    );
  }

  databaseInput.addEventListener('input', updateSubmitState);
  phraseInput.addEventListener('input', updateSubmitState);

  form.addEventListener('submit', function(event) {
    if (!confirm('This will permanently empty the entire database. Continue?')) {
      event.preventDefault();
    }
  });
})();
</script>

<?php require_once dirname(dirname(__DIR__)) . '/templates/layout/footer.php'; ?>
