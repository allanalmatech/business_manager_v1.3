<?php
$BASE_URL = $GLOBALS['BASE_URL'] ?? '';
// templates/layout/topbar.php
// Expected vars (optional): $current_user_name, $current_user_role

// Count unread messages
$unread_messages = 0;
if (!empty($_SESSION['user']['id']) && ($GLOBALS['db'] ?? null) instanceof mysqli) {
    $db = $GLOBALS['db'];
    $userId = (int)$_SESSION['user']['id'];
    
    // Get user's role_id for role-based messages
    $userRole = null;
    $roleQuery = $db->prepare("SELECT role_id FROM users WHERE id = ? LIMIT 1");
    $roleQuery->bind_param('i', $userId);
    $roleQuery->execute();
    $roleResult = $roleQuery->get_result()->fetch_assoc();
    if ($roleResult) {
        $userRole = (int)($roleResult['role_id'] ?? 0);
    }
    $roleQuery->close();
    
    // Count unread messages (user-specific, role-based, and all users)
    $table = '';
    $result = $db->query("SHOW TABLES LIKE 'message_logs'");
    if ($result && $result->num_rows > 0) {
        $table = 'message_logs';
    } else {
        $result = $db->query("SHOW TABLES LIKE 'messages'");
        if ($result && $result->num_rows > 0) {
            $table = 'messages';
        }
    }
    
    if ($table) {
        $unreadQuery = $db->prepare("
            SELECT COUNT(*) as count FROM {$table} 
            WHERE 
                ((recipient_type = 'user' AND recipient_id = ?) OR
                (recipient_type = 'role' AND recipient_id = ?) OR
                (recipient_type = 'all'))
                AND (is_read = 0 OR is_read IS NULL)
        ");
        $roleIdBind = $userRole ?? 0;
        $unreadQuery->bind_param('ii', $userId, $roleIdBind);
        $unreadQuery->execute();
        $unreadResult = $unreadQuery->get_result()->fetch_assoc();
        $unread_messages = (int)($unreadResult['count'] ?? 0);
        $unreadQuery->close();
    }
}
?>
<header class="app-topbar border-bottom">
  <div class="container-fluid d-flex align-items-center gap-2 py-2">

    <!-- Mobile sidebar toggle -->
    <button class="btn btn-outline-secondary btn-sm d-lg-none" id="btnSidebarOpen" type="button">
      <i class="bi bi-list"></i>
    </button>

    <!-- Desktop collapse toggle -->
    <button class="btn btn-outline-secondary btn-sm d-none d-lg-inline-flex" id="btnSidebarToggle" type="button" title="Collapse sidebar">
      <i class="bi bi-layout-sidebar-inset"></i>
    </button>

    <div class="flex-grow-1">
      <div class="fw-semibold"><?= htmlspecialchars($page_title ?? 'Dashboard') ?></div>
      <div class="text-muted small d-none d-md-block"><?= htmlspecialchars($page_subtitle ?? '') ?></div>
    </div>

    <!-- Global search (compact on mobile) -->
    <form class="d-none d-md-flex" role="search" action="/modules/search.php" method="get">
      <input class="form-control form-control-sm" name="q" placeholder="Search products, contacts, invoices…" />
    </form>

    <!-- Notifications -->
    <a class="btn btn-outline-secondary btn-sm position-relative" href="/modules/notifications.php" title="Notifications">
      <i class="bi bi-bell"></i>
    <?php if (!empty($notif_badge) && (int)$notif_badge > 0): ?>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
          <?= (int)$notif_badge ?>
        </span>
      <?php endif; ?>
    </a>

    <!-- Messages -->
    <a class="btn btn-outline-secondary btn-sm position-relative" href="<?= htmlspecialchars($BASE_URL) ?>/modules/messaging/inbox.php" title="Messages">
      <i class="bi bi-envelope"></i>
    <?php if ($unread_messages > 0): ?>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary">
          <?= $unread_messages ?>
        </span>
      <?php endif; ?>
    </a>

    <!-- Quick actions -->
    <div class="dropdown">
  <button
    class="btn btn-outline-secondary btn-sm dropdown-toggle d-flex align-items-center gap-2"
    data-bs-toggle="dropdown"
    aria-expanded="false"
  >
    <div class="avatar-mini d-flex align-items-center justify-content-center bg-primary text-white overflow-hidden">
      <?php if (!empty($_SESSION['user']['profile_photo'])): ?>
        <img src="<?= htmlspecialchars($GLOBALS['BASE_URL'] . '/' . $_SESSION['user']['profile_photo']) ?>" 
             alt="Profile" 
             class="w-100 h-100 object-fit-cover"
             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
        <i class="bi bi-person" style="display: none;"></i>
      <?php else: ?>
        <i class="bi bi-person"></i>
      <?php endif; ?>
    </div>
    <span class="d-none d-md-inline">
      <?= htmlspecialchars($_SESSION['user']['username'] ?? 'User') ?>
    </span>
  </button>

  <ul class="dropdown-menu dropdown-menu-end">
    <li>
      <div class="dropdown-item-text small text-muted">
        <?= htmlspecialchars($current_user_role ?? '') ?>
      </div>
    </li>

    <li><hr class="dropdown-divider"></li>

    <li>
      <a class="dropdown-item" href="<?= htmlspecialchars($BASE_URL) ?>/modules/profile/my_profile.php">
        My Profile
      </a>
    </li>

    <li>
      <a class="dropdown-item" href="<?= htmlspecialchars($BASE_URL) ?>/modules/profile/change_password.php">
        Change Password
      </a>
    </li>

    <li><hr class="dropdown-divider"></li>

    <li>
      <div class="dropdown-header text-muted small">Messaging</div>
    </li>

    <li>
      <a class="dropdown-item" href="<?= htmlspecialchars($BASE_URL) ?>/modules/messaging/inbox.php">
        <i class="bi bi-inbox me-2"></i> Inbox
      </a>
    </li>

    <li>
      <a class="dropdown-item" href="<?= htmlspecialchars($BASE_URL) ?>/modules/messaging/send.php">
        <i class="bi bi-send me-2"></i> Send Message
      </a>
    </li>

    <li><hr class="dropdown-divider"></li>

    <li>
      <a class="dropdown-item text-danger" href="<?= htmlspecialchars($BASE_URL) ?>/logout.php">
        Logout
      </a>
    </li>
  </ul>
</div>

  </div>
</header>
