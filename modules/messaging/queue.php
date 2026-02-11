<?php
// modules/messaging/queue.php
declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/helpers.php';
require_once dirname(dirname(__DIR__)) . '/includes/rbac.php';

require_login();
require_permission('messaging.view');

$db   = $GLOBALS['db'] ?? null;
$BASE = $GLOBALS['BASE_URL'] ?? '';

$page_title = 'Message Queue';
$page_subtitle = 'Queued and scheduled messages';

function table_exists(mysqli $db, string $name): bool {
  $safe = $db->real_escape_string($name);
  $r = $db->query("SHOW TABLES LIKE '{$safe}'");
  return ($r && $r->num_rows > 0);
}

$items = [];
if ($db instanceof mysqli) {
  $table = '';
  if (table_exists($db, 'message_logs')) $table = 'message_logs';
  elseif (table_exists($db, 'messages')) $table = 'messages';

  if ($table) {
    $sql = "
      SELECT m.*, u.username AS sender_name
      FROM {$table} m
      LEFT JOIN users u ON m.sender_id = u.id
      WHERE m.status = 'queued'
      ORDER BY COALESCE(m.scheduled_at, m.created_at) ASC
      LIMIT 200
    ";
    $rs = $db->query($sql);
    if ($rs) $items = $rs->fetch_all(MYSQLI_ASSOC);
  }
}

require_once dirname(dirname(__DIR__)) . '/templates/layout/header.php';
?>

<style>
.queue-wrap{max-width:1200px;margin:0 auto}
.cardx{background:#fff;border:1px solid #e9ecef;border-radius:.5rem;padding:1rem;margin-bottom:.75rem}
.badge-sm{font-size:.75rem}
.msg-preview{color:#6c757d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:700px}
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
            $active = 'queue'; // Set active page for navigation
            require_once dirname(dirname(__DIR__)) . '/templates/partials/messaging_nav.php'; 
            ?>
          </div>
        </div>

        <div class="queue-wrap">
          <?php if (empty($items)): ?>
            <div class="text-center py-5">
              <i class="bi bi-clock-history text-muted" style="font-size:3rem;"></i>
              <h5 class="text-muted mt-3">No queued messages</h5>
              <p class="text-muted">Scheduled messages will appear here.</p>
            </div>
          <?php else: ?>
            <div class="mb-3">
              <span class="badge bg-warning text-dark"><?= count($items) ?> queued</span>
            </div>

            <?php foreach ($items as $m): ?>
              <div class="cardx">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                  <div>
                    <div class="fw-semibold"><?= h($m['subject'] ?: 'No Subject') ?></div>
                    <div class="text-muted small">
                      Sender: <?= h($m['sender_name'] ?? 'Unknown') ?> •
                      Type: <?= h($m['recipient_type'] ?? '-') ?> •
                      Scheduled: <?= h($m['scheduled_at'] ?? '—') ?>
                    </div>
                  </div>
                  <div>
                    <span class="badge bg-warning text-dark badge-sm">QUEUED</span>
                  </div>
                </div>

                <div class="msg-preview mt-2"><?= h((string)$m['message']) ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/templates/layout/footer.php'; ?>
