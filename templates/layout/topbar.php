<?php
// templates/layout/topbar.php
// Optional vars: $page_title, $page_subtitle, $current_user_role
$BASE_URL = $GLOBALS['BASE_URL'] ?? '';
$tb_db = $GLOBALS['db'] ?? null;
$tb_e = static function ($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

$unread_messages = 0;
$tb_alerts = [];
if (!empty($_SESSION['user']['id']) && $tb_db instanceof mysqli) {
    $tb_uid = (int)$_SESSION['user']['id'];
    $tb_rid = (int)($_SESSION['user']['role_id'] ?? 0);
    $tb_q = $tb_db->prepare("SELECT COUNT(*) FROM message_logs
        WHERE ((recipient_type = 'user' AND recipient_id = ?) OR (recipient_type = 'role' AND recipient_id = ?) OR recipient_type = 'all')
          AND (is_read = 0 OR is_read IS NULL)");
    if ($tb_q) {
        $tb_q->bind_param('ii', $tb_uid, $tb_rid);
        if ($tb_q->execute()) $unread_messages = (int)($tb_q->get_result()->fetch_row()[0] ?? 0);
        $tb_q->close();
    }
    require_once dirname(__DIR__, 2) . '/includes/alerts.php';
    $tb_alerts = alerts_for_user($tb_db, $BASE_URL);
}
$tb_alert_total = function_exists('alerts_total') ? alerts_total($tb_alerts) : 0;
$tb_user = $_SESSION['user'] ?? [];
?>
<header class="app-topbar">
  <div class="container-fluid d-flex align-items-center gap-2 py-2">

    <button class="topbar-btn d-lg-none" id="btnSidebarOpen" type="button" aria-label="Open menu" aria-controls="appSidebar" aria-expanded="false">
      <i class="bi bi-list" aria-hidden="true"></i>
    </button>
    <button class="topbar-btn d-none d-lg-inline-flex" id="btnSidebarToggle" type="button" aria-label="Collapse or expand menu" aria-controls="appSidebar">
      <i class="bi bi-layout-sidebar-inset" aria-hidden="true"></i>
    </button>

    <div class="topbar-title flex-grow-1">
      <div class="fw-semibold"><?= $tb_e($page_title ?? 'Dashboard') ?></div>
      <?php if (!empty($page_subtitle)): ?><div class="text-muted small d-none d-md-block"><?= $tb_e($page_subtitle) ?></div><?php endif; ?>
    </div>

    <form class="topbar-search d-none d-md-block" role="search" action="<?= $tb_e($BASE_URL) ?>/modules/search.php" method="get">
      <div class="input-group input-group-sm">
        <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
        <input class="form-control" name="q" type="search" placeholder="Search products, customers, receipts" aria-label="Search" value="<?= $tb_e($_GET['q'] ?? '') ?>">
      </div>
    </form>
    <a class="topbar-btn d-md-none" href="<?= $tb_e($BASE_URL) ?>/modules/search.php" aria-label="Search"><i class="bi bi-search" aria-hidden="true"></i></a>

    <div class="dropdown">
      <button class="topbar-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications<?= $tb_alert_total ? ', ' . $tb_alert_total . ' need attention' : '' ?>">
        <i class="bi bi-bell" aria-hidden="true"></i>
        <?php if ($tb_alert_total > 0): ?><span class="badge rounded-pill text-bg-danger"><?= $tb_alert_total > 99 ? '99+' : $tb_alert_total ?></span><?php endif; ?>
      </button>
      <div class="dropdown-menu dropdown-menu-end p-0" style="width:min(340px,92vw)">
        <div class="px-3 py-2 fw-semibold border-bottom">Notifications</div>
        <?php if (!$tb_alerts): ?>
          <div class="px-3 py-3 text-muted small">Nothing needs attention.</div>
        <?php else: foreach (array_slice($tb_alerts, 0, 6) as $tb_a): ?>
          <a class="dropdown-item d-flex align-items-center gap-2 py-2 text-wrap" href="<?= $tb_e($tb_a['href']) ?>">
            <i class="bi <?= $tb_e($tb_a['icon']) ?> text-<?= $tb_e($tb_a['level']) ?>" aria-hidden="true"></i>
            <span class="flex-grow-1 small"><span class="fw-semibold d-block"><?= $tb_e($tb_a['title']) ?></span><?= $tb_e($tb_a['detail']) ?></span>
          </a>
        <?php endforeach; endif; ?>
        <a class="dropdown-item text-center small border-top py-2" href="<?= $tb_e($BASE_URL) ?>/modules/notifications.php">View all</a>
      </div>
    </div>

    <a class="topbar-btn" href="<?= $tb_e($BASE_URL) ?>/modules/messaging/inbox.php" aria-label="Messages<?= $unread_messages ? ', ' . $unread_messages . ' unread' : '' ?>">
      <i class="bi bi-envelope" aria-hidden="true"></i>
      <?php if ($unread_messages > 0): ?><span class="badge rounded-pill text-bg-primary"><?= $unread_messages > 99 ? '99+' : $unread_messages ?></span><?php endif; ?>
    </a>

    <div class="dropdown">
      <button class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2 ps-1" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
        <span class="avatar-mini d-flex align-items-center justify-content-center overflow-hidden">
          <?php if (!empty($tb_user['profile_photo'])): ?>
            <img src="<?= $tb_e($BASE_URL . '/' . ltrim((string)$tb_user['profile_photo'], '/')) ?>" alt="" class="w-100 h-100 object-fit-cover">
          <?php else: ?>
            <i class="bi bi-person" aria-hidden="true"></i>
          <?php endif; ?>
        </span>
        <span class="d-none d-md-inline"><?= $tb_e($tb_user['username'] ?? 'User') ?></span>
        <i class="bi bi-chevron-down small d-none d-md-inline" aria-hidden="true"></i>
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><div class="dropdown-item-text">
          <div class="fw-semibold"><?= $tb_e($tb_user['full_name'] ?? ($tb_user['username'] ?? '')) ?></div>
          <div class="small text-muted"><?= $tb_e(ucwords(str_replace('_', ' ', (string)($current_user_role ?? ($tb_user['role'] ?? ''))))) ?></div>
        </div></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="<?= $tb_e($BASE_URL) ?>/modules/profile/my_profile.php"><i class="bi bi-person me-2" aria-hidden="true"></i>My profile</a></li>
        <li><a class="dropdown-item" href="<?= $tb_e($BASE_URL) ?>/modules/profile/change_password.php"><i class="bi bi-key me-2" aria-hidden="true"></i>Change password</a></li>
        <li><a class="dropdown-item" href="<?= $tb_e($BASE_URL) ?>/modules/messaging/inbox.php"><i class="bi bi-inbox me-2" aria-hidden="true"></i>Inbox</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="<?= $tb_e($BASE_URL) ?>/logout.php"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Sign out</a></li>
      </ul>
    </div>
  </div>
</header>
<span id="bm-main"></span>
