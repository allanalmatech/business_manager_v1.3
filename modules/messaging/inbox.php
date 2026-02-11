<?php
// modules/messaging/inbox.php
declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/helpers.php';
require_once dirname(dirname(__DIR__)) . '/includes/rbac.php';

require_login();
require_permission('messaging.view');

$db   = $GLOBALS['db'] ?? null;
$BASE = $GLOBALS['BASE_URL'] ?? '';

$page_title = 'Inbox';
$page_subtitle = 'Messages addressed to you (or your role)';

$userId = (int)($_SESSION['user']['id'] ?? 0);

function table_exists(mysqli $db, string $name): bool {
  $safe = $db->real_escape_string($name);
  $r = $db->query("SHOW TABLES LIKE '{$safe}'");
  return ($r && $r->num_rows > 0);
}

// Find table
$table = '';
if ($db instanceof mysqli) {
  if (table_exists($db, 'message_logs')) $table = 'message_logs';
  elseif (table_exists($db, 'messages')) $table = 'messages';
}

// Load my role_id (if you store it on users table)
$myRoleId = null;
if ($db instanceof mysqli && $userId > 0) {
  // Adjust column name if yours differs (role_id / role / roleid)
  $rs = $db->query("SELECT role_id FROM users WHERE id=" . (int)$userId . " LIMIT 1");
  if ($rs && ($row = $rs->fetch_assoc())) $myRoleId = isset($row['role_id']) ? (int)$row['role_id'] : null;
}

$items = [];
if ($db instanceof mysqli && $table !== '') {
  $sql = "
    SELECT m.*, u.username AS sender_name
    FROM {$table} m
    LEFT JOIN users u ON u.id = m.sender_id
    WHERE
      (m.recipient_type='user' AND m.recipient_id = ?)
      OR (m.recipient_type='role' AND m.recipient_id = ?)
      OR (m.recipient_type='all')
    ORDER BY m.created_at DESC
    LIMIT 200
  ";
  $stmt = $db->prepare($sql);
  if ($stmt) {
    $roleIdBind = (int)($myRoleId ?? 0);
    $stmt->bind_param('ii', $userId, $roleIdBind);
    $stmt->execute();
    $rs = $stmt->get_result();
    $items = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
  }
}

// Handle message actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $messageId = (int)($_POST['message_id'] ?? 0);
    
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } elseif ($messageId > 0 && $db instanceof mysqli && $table !== '') {
        switch ($action) {
            case 'mark_read':
                $stmt = $db->prepare("UPDATE {$table} SET is_read = 1, read_at = NOW() WHERE id = ?");
                $stmt->bind_param('i', $messageId);
                $stmt->execute();
                $stmt->close();
                break;
                
            case 'mark_unread':
                $stmt = $db->prepare("UPDATE {$table} SET is_read = 0, read_at = NULL WHERE id = ?");
                $stmt->bind_param('i', $messageId);
                $stmt->execute();
                $stmt->close();
                break;
                
            case 'reply':
                $replyMessage = trim($_POST['reply_message'] ?? '');
                if (!empty($replyMessage)) {
                    // Get original message to get sender info
                    $origStmt = $db->prepare("SELECT sender_id, subject FROM {$table} WHERE id = ?");
                    $origStmt->bind_param('i', $messageId);
                    $origStmt->execute();
                    $origResult = $origStmt->get_result()->fetch_assoc();
                    $origStmt->close();
                    
                    if ($origResult) {
                        $replySubject = 'Re: ' . ($origResult['subject'] ?? 'No Subject');
                        $senderId = (int)$origResult['sender_id'];
                        
                        // Insert reply
                        $replyStmt = $db->prepare("
                            INSERT INTO {$table} (sender_id, recipient_type, recipient_id, subject, message, status, created_at) 
                            VALUES (?, 'user', ?, ?, ?, 'sent', NOW())
                        ");
                        $replyStmt->bind_param('iisss', $userId, $senderId, $replySubject, $replyMessage);
                        $replyStmt->execute();
                        $replyStmt->close();
                        
                        $success = 'Reply sent successfully!';
                    }
                }
                break;
        }
    }
}

require_once dirname(dirname(__DIR__)) . '/templates/layout/header.php';
?>

<style>
.inbox-wrap{max-width:1200px;margin:0 auto}
.msg-table{background:#fff;border-radius:.5rem;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.msg-table th{background:#f8f9fa;border-bottom:2px solid #dee2e6;font-weight:600;color:#495057;position:sticky;top:0;z-index:10}
.msg-table td{vertical-align:middle;border-bottom:1px solid #e9ecef}
.msg-row{cursor:pointer;transition:.2s}
.msg-row:hover{background:#f8f9fa!important}
.msg-row.unread{font-weight:600}
.msg-row.unread td{background:#f8f9fa}
.msg-preview{color:#6c757d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:300px}
.read-indicator{width:8px;height:8px;border-radius:50%;display:inline-block}
.read-indicator.read{background:#28a745}
.read-indicator.unread{background:#ffc107}
.msg-content{white-space:pre-wrap;line-height:1.6}
.modal-header{border-bottom:2px solid #0d6efd}
.modal-body .msg-details{background:#f8f9fa;border-radius:.375rem;padding:1rem;margin:1rem 0}
.modal-body .msg-content{background:#fff;border:1px solid #e9ecef;border-radius:.375rem;padding:1rem;white-space:pre-wrap;line-height:1.6;max-height:400px;overflow:auto}
.reply-section{border-top:1px solid #dee2e6;padding-top:1rem;margin-top:1rem}
.reply-section .form-label{font-weight:600;color:#495057}
.reply-section textarea{border:1px solid #ced4da;border-radius:.375rem;padding:.75rem;resize:vertical}
.reply-section textarea:focus{border-color:#0d6efd;box-shadow:0 0 0 .2rem rgba(13,110,253,.25)}
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
            $active = 'inbox'; // Set active page for navigation
            require_once dirname(dirname(__DIR__)) . '/templates/partials/messaging_nav.php'; 
            ?>
          </div>
        </div>

        <div class="inbox-wrap">
          <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
          <?php endif; ?>
          
          <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= h($success) ?></div>
          <?php endif; ?>

          <?php if (!($db instanceof mysqli) || $table===''): ?>
            <div class="alert alert-warning">
              <i class="bi bi-exclamation-triangle"></i> Messaging storage table not found or DB unavailable.
            </div>
          <?php elseif (empty($items)): ?>
            <div class="text-center py-5">
              <i class="bi bi-inbox text-muted" style="font-size:3rem;"></i>
              <h5 class="text-muted mt-3">Your inbox is empty</h5>
              <p class="text-muted">Messages sent to you, your role, or everyone will show up here.</p>
            </div>
          <?php else: ?>
            <div class="mb-3">
              <span class="badge bg-primary"><?= count($items) ?> messages</span>
            </div>

            <div class="msg-table">
              <table class="table table-hover mb-0">
                <thead>
                  <tr>
                    <th width="30"></th>
                    <th>From</th>
                    <th>Subject</th>
                    <th>Message</th>
                    <th>Date</th>
                    <th width="30"></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($items as $m): ?>
                    <tr class="msg-row <?= (empty($m['is_read']) || $m['is_read'] == 0) ? 'unread' : '' ?>" 
                        onclick="viewMessage(<?= (int)$m['id'] ?>)">
                      <td>
                        <span class="read-indicator <?= (empty($m['is_read']) || $m['is_read'] == 0) ? 'unread' : 'read' ?>"></span>
                      </td>
                      <td>
                        <div class="d-flex align-items-center gap-2">
                          <i class="bi bi-person-circle text-muted"></i>
                          <?= h($m['sender_name'] ?? 'Unknown') ?>
                        </div>
                      </td>
                      <td>
                        <div class="fw-semibold"><?= h($m['subject'] ?: 'No Subject') ?></div>
                      </td>
                      <td>
                        <div class="msg-preview"><?= h(substr((string)$m['message'], 0, 60)) ?><?= (strlen((string)$m['message']) > 60) ? '...' : '' ?></div>
                      </td>
                      <td>
                        <div class="text-muted small"><?= h(date('M j, Y H:i', strtotime($m['created_at']))) ?></div>
                      </td>
                      <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); viewMessage(<?= (int)$m['id'] ?>)">
                          <i class="bi bi-eye"></i>
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>
</div>

<!-- Message Modal -->
<div class="modal fade" id="messageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title mb-0">
          <i class="bi bi-envelope-fill me-2"></i>Message Details
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="messageDetails"></div>
        
        <!-- Reply Section -->
        <div id="replySection" class="reply-section" style="display: none;">
          <div class="d-flex align-items-center mb-3">
            <i class="bi bi-reply-fill text-primary me-2"></i>
            <h6 class="mb-0">Reply to Message</h6>
          </div>
          <form id="replyForm">
            <input type="hidden" name="message_id" id="replyMessageId">
            <input type="hidden" name="action" value="reply">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            
            <div class="mb-3">
              <label class="form-label">Your Reply:</label>
              <textarea class="form-control" id="replyMessage" name="reply_message" rows="4" 
                        placeholder="Type your reply here..." required></textarea>
            </div>
            
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-send me-1"></i> Send Reply
              </button>
              <button type="button" class="btn btn-outline-secondary" onclick="toggleReply()">
                <i class="bi bi-x-lg me-1"></i> Cancel
              </button>
            </div>
          </form>
        </div>
      </div>
      <div class="modal-footer bg-light">
        <div class="d-flex justify-content-between w-100">
          <div>
            <button type="button" class="btn btn-outline-success me-2" id="markReadBtn" onclick="markAsRead()">
              <i class="bi bi-check2-circle me-1"></i> Mark as Read
            </button>
            <button type="button" class="btn btn-outline-warning me-2" id="markUnreadBtn" onclick="markAsUnread()">
              <i class="bi bi-envelope me-1"></i> Mark as Unread
            </button>
          </div>
          <div>
            <button type="button" class="btn btn-primary me-2" onclick="toggleReply()">
              <i class="bi bi-reply me-1"></i> Reply
            </button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
              <i class="bi bi-x-lg me-1"></i> Close
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Store message data
const messageData = {};
<?php foreach ($items as $m): ?>
messageData[<?= (int)$m['id'] ?>] = <?= json_encode($m, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
<?php endforeach; ?>

let currentMessageId = null;

function viewMessage(messageId) {
    const message = messageData[messageId];
    if (!message) return;
    
    currentMessageId = messageId;
    const modal = new bootstrap.Modal(document.getElementById('messageModal'));
    const detailsDiv = document.getElementById('messageDetails');
    
    // Build message details
    detailsDiv.innerHTML = `
        <div class="msg-details">
            <div class="row">
                <div class="col-md-6">
                    <strong>From:</strong> ${escapeHtml(message.sender_name || 'Unknown')}
                </div>
                <div class="col-md-6">
                    <strong>Date:</strong> ${new Date(message.created_at).toLocaleString()}
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-md-12">
                    <strong>Subject:</strong> ${escapeHtml(message.subject || 'No Subject')}
                </div>
            </div>
        </div>
        
        <div class="msg-content">
            <strong>Message:</strong><br>
            <div class="mt-2 p-3 bg-light rounded">${escapeHtml(message.message || '')}</div>
        </div>
    `;
    
    // Update read/unread buttons
    updateReadButtons(message);
    
    // Auto-mark as read immediately when opening modal
    if (message.is_read == 0) {
        markAsReadSilently(messageId);
    }
    
    modal.show();
}

function markAsReadSilently(messageId) {
    // Mark as read without UI updates (since we're opening the modal)
    const formData = new FormData();
    formData.append('csrf_token', '<?= generate_csrf_token() ?>');
    formData.append('action', 'mark_read');
    formData.append('message_id', messageId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    }).then(response => {
        if (response.ok) {
            // Update message data and UI
            if (messageData[messageId]) {
                messageData[messageId].is_read = 1;
            }
            updateTableRowIndicator(messageId, true);
            updateReadButtons(messageData[messageId]);
            
            // Update notification badge in topbar
            updateNotificationBadge();
        }
    }).catch(error => {
        console.error('Error marking as read:', error);
    });
}

function updateNotificationBadge(increase = false) {
    // Update the message notification badge in topbar
    const badge = document.querySelector('a[href*="inbox.php"] .badge');
    if (badge) {
        const currentCount = parseInt(badge.textContent) || 0;
        let newCount;
        
        if (increase) {
            // Marking as unread - increase count
            newCount = currentCount + 1;
            badge.style.display = 'inline-block';
        } else {
            // Marking as read - decrease count
            newCount = Math.max(0, currentCount - 1);
            
            // Hide badge if count reaches 0
            if (newCount === 0) {
                badge.style.display = 'none';
            }
        }
        
        badge.textContent = newCount;
    }
}

function updateReadButtons(message) {
    const isRead = message.is_read == 1;
    document.getElementById('markReadBtn').style.display = isRead ? 'none' : 'inline-block';
    document.getElementById('markUnreadBtn').style.display = isRead ? 'inline-block' : 'none';
}

function markAsRead() {
    if (!currentMessageId) return;
    
    // Show loading state
    const btn = document.getElementById('markReadBtn');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Updating...';
    
    // Use AJAX to avoid page refresh
    const formData = new FormData();
    formData.append('csrf_token', '<?= generate_csrf_token() ?>');
    formData.append('action', 'mark_read');
    formData.append('message_id', currentMessageId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    }).then(response => {
        if (response.ok) {
            // Update button visibility without refresh
            btn.style.display = 'none';
            document.getElementById('markUnreadBtn').style.display = 'inline-block';
            btn.innerHTML = originalHtml;
            btn.disabled = false;
            
            // Update table row indicator
            updateTableRowIndicator(currentMessageId, true);
            
            // Update notification badge
            updateNotificationBadge();
        }
    }).catch(error => {
        console.error('Error:', error);
        btn.innerHTML = originalHtml;
        btn.disabled = false;
    });
}

function markAsUnread() {
    if (!currentMessageId) return;
    
    // Show loading state
    const btn = document.getElementById('markUnreadBtn');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Updating...';
    
    // Use AJAX to avoid page refresh
    const formData = new FormData();
    formData.append('csrf_token', '<?= generate_csrf_token() ?>');
    formData.append('action', 'mark_unread');
    formData.append('message_id', currentMessageId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    }).then(response => {
        if (response.ok) {
            // Update button visibility without refresh
            btn.style.display = 'none';
            document.getElementById('markReadBtn').style.display = 'inline-block';
            btn.innerHTML = originalHtml;
            btn.disabled = false;
            
            // Update table row indicator
            updateTableRowIndicator(currentMessageId, false);
            
            // Update notification badge (increase count)
            updateNotificationBadge(true);
        }
    }).catch(error => {
        console.error('Error:', error);
        btn.innerHTML = originalHtml;
        btn.disabled = false;
    });
}

function updateTableRowIndicator(messageId, isRead) {
    const row = document.querySelector(`tr[onclick*="${messageId}"]`);
    if (row) {
        const indicator = row.querySelector('.read-indicator');
        if (indicator) {
            indicator.className = `read-indicator ${isRead ? 'read' : 'unread'}`;
        }
        
        // Update row styling
        if (isRead) {
            row.classList.remove('unread');
        } else {
            row.classList.add('unread');
        }
    }
}

function toggleReply() {
    const replySection = document.getElementById('replySection');
    const isVisible = replySection.style.display !== 'none';
    
    replySection.style.display = isVisible ? 'none' : 'block';
    
    if (!isVisible) {
        document.getElementById('replyMessageId').value = currentMessageId;
        document.getElementById('replyMessage').focus();
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Handle reply form submission
document.getElementById('replyForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    
    // Disable button to prevent double submission
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Sending...';
    
    // Submit form
    this.submit();
});
</script>

<?php require_once dirname(dirname(__DIR__)) . '/templates/layout/footer.php'; ?>
