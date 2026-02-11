<?php
// modules/messaging/logs.php
declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/helpers.php';
require_once dirname(dirname(__DIR__)) . '/includes/rbac.php';

require_login();
require_permission('messaging.view');

$db   = $GLOBALS['db'] ?? null;
$BASE = $GLOBALS['BASE_URL'] ?? '';

$page_title = 'Message Logs';
$page_subtitle = '';

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

function table_exists(mysqli $db, string $name): bool {
  $safe = $db->real_escape_string($name);
  $r = $db->query("SHOW TABLES LIKE '{$safe}'");
  return ($r && $r->num_rows > 0);
}

$logs = [];
$total = 0;
$currentUser = $_SESSION['user'] ?? null;
$isSuperAdmin = ($currentUser['role'] ?? '') === 'super_admin';
$userId = (int)($currentUser['id'] ?? 0);

// Set page subtitle based on user role
$page_subtitle = $isSuperAdmin ? 'View all messaging logs' : 'View your messaging history';

if ($db instanceof mysqli) {
  $table = '';
  if (table_exists($db, 'message_logs')) {
    $table = 'message_logs';
  } elseif (table_exists($db, 'messages')) {
    $table = 'messages';
  }

  if ($table !== '') {
    // Check if sender_id column exists
    $columnCheck = $db->query("SHOW COLUMNS FROM {$table} LIKE 'sender_id'");
    $hasSenderId = $columnCheck && $columnCheck->num_rows > 0;
    
    if (!$hasSenderId) {
      // Try alternative column names
      $altColumnCheck = $db->query("SHOW COLUMNS FROM {$table} LIKE 'user_id'");
      $hasUserId = $altColumnCheck && $altColumnCheck->num_rows > 0;
      
      if ($hasUserId) {
        $senderColumn = 'user_id';
      } else {
        // If no sender column found, show error
        $error = "Messaging table structure is incomplete. Please run the migration script.";
      }
    } else {
      $senderColumn = 'sender_id';
    }
    
    if (!isset($error)) {
      // Build WHERE clause based on user role
      $whereClause = '';
      $params = [];
      $types = '';
      
      if (!$isSuperAdmin) {
        // Regular users can only see messages they sent or received
        $whereClause = "WHERE ({$senderColumn} = ? OR m.recipient_id = ? OR m.recipient_type = 'all')";
        $params = [$userId, $userId];
        $types = 'ii';
      }
      
      // Count query
      $countSql = "SELECT COUNT(*) AS total FROM {$table} m {$whereClause}";
      $countStmt = $db->prepare($countSql);
      if ($countStmt) {
        if (!empty($params)) {
          $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $total = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
        $countStmt->close();
      }

      // Main query
      $sql = "
        SELECT m.*, u.username AS sender_name,
        CASE
          WHEN m.recipient_type = 'user' THEN (SELECT username FROM users WHERE id = m.recipient_id LIMIT 1)
          WHEN m.recipient_type = 'role' THEN (SELECT name FROM roles WHERE id = m.recipient_id LIMIT 1)
          WHEN m.recipient_type = 'all' THEN 'All Users'
          ELSE 'Unknown'
        END AS recipient_name
        FROM {$table} m
        LEFT JOIN users u ON u.id = m.{$senderColumn}
        {$whereClause}
        ORDER BY m.created_at DESC
        LIMIT ? OFFSET ?
      ";
      
      $stmt = $db->prepare($sql);
      if ($stmt) {
        $allParams = array_merge($params, [$limit, $offset]);
        $allTypes = $types . 'ii';
        $stmt->bind_param($allTypes, ...$allParams);
        $stmt->execute();
        $rs = $stmt->get_result();
        $logs = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
      }
    }
  }
}

$totalPages = ($limit > 0) ? (int)ceil($total / $limit) : 1;
$prevPage = $page - 1;
$nextPage = $page + 1;

require_once dirname(dirname(__DIR__)) . '/templates/layout/header.php';
?>

<style>
.logs-container{max-width:1400px;margin:0 auto}
.logs-table{background:#fff;border-radius:.5rem;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.logs-table th{background:#f8f9fa;border-bottom:2px solid #dee2e6;font-weight:600;color:#495057;position:sticky;top:0;z-index:10}
.logs-table td{vertical-align:middle;border-bottom:1px solid #e9ecef}
.log-row{cursor:pointer;transition:.2s}
.log-row:hover{background:#f8f9fa!important}
.status-badge{padding:.25rem .75rem;border-radius:.375rem;font-size:.75rem;font-weight:600;text-transform:uppercase}
.status-sent{background:#d1ecf1;color:#0f5132}
.status-queued{background:#fff3cd;color:#856404}
.status-failed{background:#f8d7da;color:#721c24}
.status-delivered{background:#d4edda;color:#155724}
.recipient-type{padding:.25rem .5rem;border-radius:.25rem;font-size:.75rem;font-weight:600}
.type-user{background:#e3f2fd;color:#1565c0}
.type-role{background:#f3e5f5;color:#7b1fa2}
.type-all{background:#e8f5e8;color:#2e7d32}
.message-preview{max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.pagination{display:flex;justify-content:center;gap:.5rem;margin-top:1.5rem;flex-wrap:wrap}
.pagination .page-link{padding:.5rem 1rem;border:1px solid #dee2e6;border-radius:.375rem;text-decoration:none;color:#495057}
.pagination .page-link.active{background:#0d6efd;border-color:#0d6efd;color:#fff}
.pagination .page-link.disabled{color:#6c757d;pointer-events:none}
.modal-body .message-details{background:#f8f9fa;border-radius:.375rem;padding:1rem;margin:1rem 0}
.modal-body .message-content{background:#fff;border:1px solid #e9ecef;border-radius:.375rem;padding:1rem;white-space:pre-wrap;line-height:1.6;max-height:400px;overflow:auto}
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
            $active = 'logs'; // Set active page for navigation
            require_once dirname(dirname(__DIR__)) . '/templates/partials/messaging_nav.php'; 
            ?>
          </div>
        </div>

        <?php if (!($db instanceof mysqli)): ?>
          <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Database connection required.</div>
        <?php elseif (empty($logs)): ?>
          <div class="text-center py-5">
            <i class="bi bi-inbox text-muted" style="font-size:3rem;"></i>
            <h5 class="text-muted mt-3">No message logs found</h5>
            <p class="text-muted">Logs appear once messages are sent.</p>
            <a href="<?= h($BASE) ?>/modules/messaging/send.php" class="btn btn-primary">
              <i class="bi bi-send"></i> Send Your First Message
            </a>
          </div>
        <?php else: ?>
          <div class="logs-container">
            <div class="mb-3"><span class="badge bg-primary"><?= (int)$total ?> total messages</span></div>

            <div class="logs-table">
              <table class="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Date & Time</th>
                    <th>Sender</th>
                    <th>Recipient</th>
                    <th>Subject</th>
                    <th>Message Preview</th>
                    <th>Status</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($logs as $log): ?>
                    <tr class="log-row" onclick="viewMessageDetails(<?= (int)$log['id'] ?>)">
                      <td class="text-nowrap">
                        <?= date('M j, Y', strtotime($log['created_at'])) ?><br>
                        <small class="text-muted"><?= date('H:i:s', strtotime($log['created_at'])) ?></small>
                      </td>
                      <td><?= h($log['sender_name'] ?? 'Unknown') ?></td>
                      <td>
                        <div class="d-flex align-items-center gap-2">
                          <span class="recipient-type type-<?= h($log['recipient_type']) ?>"><?= h(ucfirst($log['recipient_type'])) ?></span>
                          <?= h($log['recipient_name'] ?? 'Unknown') ?>
                        </div>
                      </td>
                      <td><?= !empty($log['subject']) ? h($log['subject']) : '<em class="text-muted">No Subject</em>' ?></td>
                      <td>
                        <div class="message-preview" title="<?= h($log['message']) ?>">
                          <?= h(substr((string)$log['message'], 0, 60)) ?><?= (strlen((string)$log['message']) > 60) ? '...' : '' ?>
                        </div>
                      </td>
                      <td>
                        <span class="status-badge status-<?= h($log['status'] ?? 'sent') ?>">
                          <?= h(ucfirst($log['status'] ?? 'sent')) ?>
                        </span>
                      </td>
                      <td>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation();viewMessageDetails(<?= (int)$log['id'] ?>)">
                          <i class="bi bi-eye"></i> View
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <?php if ($totalPages > 1): ?>
              <div class="pagination">
                <a class="page-link <?= $page<=1?'disabled':'' ?>" href="?page=<?= max(1,$prevPage) ?>">Previous</a>
                <?php
                  $start = max(1, $page-2);
                  $end   = min($totalPages, $page+2);
                  if ($start > 1) {
                    echo '<a class="page-link" href="?page=1">1</a>';
                    if ($start > 2) echo '<span class="page-link disabled">...</span>';
                  }
                  for ($i=$start;$i<=$end;$i++){
                    $active = ($i===$page) ? 'active' : '';
                    echo '<a class="page-link '.$active.'" href="?page='.$i.'">'.$i.'</a>';
                  }
                  if ($end < $totalPages) {
                    if ($end < $totalPages-1) echo '<span class="page-link disabled">...</span>';
                    echo '<a class="page-link" href="?page='.$totalPages.'">'.$totalPages.'</a>';
                  }
                ?>
                <a class="page-link <?= $page>=$totalPages?'disabled':'' ?>" href="?page=<?= min($totalPages,$nextPage) ?>">Next</a>
              </div>
            <?php endif; ?>

          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>

<div class="modal fade" id="messageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-envelope"></i> Message Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body"><div id="messageDetails"></div></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="resendMessage" style="display:none;">
          <i class="bi bi-send"></i> Resend
        </button>
      </div>
    </div>
  </div>
</div>

<script>
const messageData = {};
<?php foreach ($logs as $log): ?>
messageData[<?= (int)$log['id'] ?>] = <?= json_encode($log, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
<?php endforeach; ?>

function viewMessageDetails(id){
  const message = messageData[id];
  if (!message) return;

  const modal = new bootstrap.Modal(document.getElementById('messageModal'));
  const detailsDiv = document.getElementById('messageDetails');
  const resendBtn = document.getElementById('resendMessage');

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

  detailsDiv.innerHTML = `
    <div class="message-details">
      <div class="row mb-3">
        <div class="col-md-6"><strong>Date & Time:</strong><br>${new Date(message.created_at).toLocaleString()}</div>
        <div class="col-md-6"><strong>Status:</strong><br>
          <span class="status-badge status-${esc(message.status || 'sent')}">${esc((message.status||'sent').toUpperCase())}</span>
        </div>
      </div>
      <div class="row mb-3">
        <div class="col-md-6"><strong>Sender:</strong><br>${esc(message.sender_name || 'Unknown')}</div>
        <div class="col-md-6"><strong>Recipient:</strong><br>${esc(message.recipient_name || 'Unknown')}</div>
      </div>
      ${message.subject ? `<div class="mb-3"><strong>Subject:</strong><br>${esc(message.subject)}</div>` : ``}
      ${message.scheduled_at ? `<div class="mb-3"><strong>Scheduled For:</strong><br>${new Date(message.scheduled_at).toLocaleString()}</div>` : ``}
    </div>
    <div class="message-content">${esc(message.message)}</div>
  `;

  // allow resend always (optional). If you want only failed, change this condition.
  resendBtn.style.display = 'inline-block';
  resendBtn.onclick = () => resendMessage(id);

  modal.show();
}

function resendMessage(id){
  if (!confirm('Resend this message?')) return;
  const msg = messageData[id];
  if (!msg) return;

  sessionStorage.setItem('messageTemplate', JSON.stringify({
    name: 'Resend: ' + (msg.subject || 'No Subject'),
    subject: msg.subject || '',
    message: msg.message || ''
  }));

  bootstrap.Modal.getInstance(document.getElementById('messageModal')).hide();
  window.location.href = '<?= h($BASE) ?>/modules/messaging/send.php';
}
</script>

<?php require_once dirname(dirname(__DIR__)) . '/templates/layout/footer.php'; ?>
