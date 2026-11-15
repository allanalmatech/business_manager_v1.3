<?php
// modules/notifications.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/alerts.php';

require_login();

$BASE_URL = $GLOBALS['BASE_URL'] ?? '';
$alerts = alerts_for_user($GLOBALS['db'], $BASE_URL);
$page_title = 'Notifications';
$page_subtitle = $alerts ? 'Things that need attention' : 'Nothing needs attention';
$current_user_role = $_SESSION['user']['role'] ?? '';

require_once __DIR__ . '/../templates/layout/header.php';
?>
<div class="app-shell">
  <?php require_once __DIR__ . '/../templates/layout/sidebar.php'; ?>
  <div class="app-content">
    <?php require_once __DIR__ . '/../templates/layout/topbar.php'; ?>
    <main class="page-wrap">
      <div class="container-fluid py-3">
        <?php if (!$alerts): ?>
          <div class="bm-empty">
            <i class="bi bi-check2-circle" aria-hidden="true"></i>
            <div class="fw-semibold">All clear</div>
            <div class="text-muted">Low stock, overdue payments and pending returns will show up here.</div>
          </div>
        <?php else: ?>
          <div class="list-group shadow-sm">
            <?php foreach ($alerts as $a): ?>
              <a class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3" href="<?= h($a['href']) ?>">
                <i class="bi <?= h($a['icon']) ?> fs-4 text-<?= h($a['level']) ?>" aria-hidden="true"></i>
                <span class="flex-grow-1">
                  <span class="fw-semibold d-block"><?= h($a['title']) ?></span>
                  <span class="text-muted small"><?= h($a['detail']) ?></span>
                </span>
                <span class="badge rounded-pill text-bg-<?= h($a['level']) ?>"><?= (int)$a['count'] ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>
<?php require_once __DIR__ . '/../templates/layout/footer.php'; ?>
