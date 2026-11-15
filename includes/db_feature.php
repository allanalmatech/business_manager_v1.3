<?php
// includes/db_feature.php
declare(strict_types=1);

/**
 * Some screens need tables that arrive with a database update. The update
 * normally applies itself (see migrations_auto), but a database user without
 * permission to change the structure would leave a screen half-ready. Rather
 * than a blank error page, say plainly what is missing and who can fix it.
 */
function db_tables_exist(mysqli $db, array $tables): bool
{
    foreach ($tables as $t) {
        $st = $db->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
        if (!$st) return false;
        $st->bind_param('s', $t);
        $st->execute();
        $found = (bool)$st->get_result()->fetch_row();
        $st->close();
        if (!$found) return false;
    }
    return true;
}

/** Shows the notice inside the normal layout and stops the page. */
function require_db_tables(mysqli $db, array $tables, string $featureName): void
{
    if (db_tables_exist($db, $tables)) return;

    $root = dirname(__DIR__);
    $BASE_URL = $GLOBALS['BASE_URL'] ?? '';
    $isAdmin = function_exists('is_super_admin') && is_super_admin();
    $page_title = $featureName;
    $page_subtitle = 'Needs a database update';
    $current_user_role = $_SESSION['user']['role'] ?? '';
    $e = static function ($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

    require_once $root . '/templates/layout/header.php';
    echo '<div class="app-shell">';
    require_once $root . '/templates/layout/sidebar.php';
    echo '<div class="app-content">';
    require_once $root . '/templates/layout/topbar.php';
    ?>
    <main class="page-wrap">
      <div class="container-fluid">
        <div class="bm-empty" style="max-width:640px;margin-inline:auto">
          <i class="bi bi-database-gear" aria-hidden="true"></i>
          <div class="fw-semibold"><?= $e($featureName) ?> is not ready yet</div>
          <p class="mb-3">This part of the system needs a database update that has not been applied.</p>
          <?php if ($isAdmin): ?>
            <p class="small mb-3">It usually applies itself. If it keeps coming back, the database user may not be allowed to change the structure — your hosting panel can grant that.</p>
            <a class="btn btn-primary" href="<?= $e($BASE_URL) ?>/modules/admin/updates.php">Open Updates</a>
          <?php else: ?>
            <p class="small mb-0">Ask whoever administers the system to open Administration → Updates and run the database update.</p>
          <?php endif; ?>
        </div>
      </div>
    </main>
    <?php
    echo '</div></div>';
    require_once $root . '/templates/layout/footer.php';
    exit;
}
