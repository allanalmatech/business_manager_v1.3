<?php
// modules/messaging/send.php
declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/helpers.php';
require_once dirname(dirname(__DIR__)) . '/includes/rbac.php';

require_login();
require_permission('messaging.view');

$db   = $GLOBALS['db'] ?? null;
$BASE = $GLOBALS['BASE_URL'] ?? '';

$page_title = 'Send Message';
$page_subtitle = 'Compose and send messages to users or roles';

$errors = [];
$success = '';

$formData = [
  'recipient_type'   => $_POST['recipient_type'] ?? 'user',
  'recipient_id'     => $_POST['recipient_id'] ?? '',
  'subject'          => $_POST['subject'] ?? '',
  'message'          => $_POST['message'] ?? '',
  'send_immediately' => $_POST['send_immediately'] ?? '1',
  'scheduled_at'     => $_POST['scheduled_at'] ?? ''
];

function table_exists(mysqli $db, string $name): bool {
  $safe = $db->real_escape_string($name);
  $r = $db->query("SHOW TABLES LIKE '{$safe}'");
  return ($r && $r->num_rows > 0);
}

// Get users/roles for recipient selection
$users = [];
$roles = [];

if ($db instanceof mysqli) {
  $userResult = $db->query("SELECT id, username, email FROM users ORDER BY username");
  if ($userResult) $users = $userResult->fetch_all(MYSQLI_ASSOC);

  $roleResult = $db->query("SELECT id, name FROM roles ORDER BY name");
  if ($roleResult) $roles = $roleResult->fetch_all(MYSQLI_ASSOC);
}

// Handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    $errors[] = 'Invalid request. Please try again.';
  } else {
    if (empty($formData['recipient_type'])) $errors[] = 'Recipient type is required.';
    if ($formData['recipient_type'] !== 'all' && empty($formData['recipient_id'])) $errors[] = 'Recipient is required.';
    if (empty($formData['message'])) $errors[] = 'Message content is required.';

    if ($formData['send_immediately'] !== '1' && !empty($formData['scheduled_at'])) {
      $scheduledTime = DateTime::createFromFormat('Y-m-d\TH:i', $formData['scheduled_at']);
      if (!$scheduledTime || $scheduledTime <= new DateTime()) $errors[] = 'Scheduled time must be in the future.';
    }

    if (empty($errors)) {
      try {
        if (!($db instanceof mysqli)) throw new Exception('DB unavailable.');

        $table = '';
        if (table_exists($db, 'message_logs')) $table = 'message_logs';
        elseif (table_exists($db, 'messages')) $table = 'messages';
        if ($table === '') throw new Exception('Message storage table not found.');

        $senderId = (int)($_SESSION['user']['id'] ?? 0);
        $status = 'sent'; // Always send immediately now

        $stmt = $db->prepare("
          INSERT INTO {$table} (
            sender_id, recipient_type, recipient_id, subject, message,
            status, created_at
          ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        if (!$stmt) throw new Exception('Prepare failed.');

        $recipientId = ($formData['recipient_type'] === 'all') ? null : (int)$formData['recipient_id'];

        // recipient_id might be INT or nullable; still bind as string-safe
        $recipientIdForBind = ($recipientId === null) ? 0 : $recipientId;

        $stmt->bind_param(
          'isisss',
          $senderId,
          $formData['recipient_type'],
          $recipientIdForBind,
          $formData['subject'],
          $formData['message'],
          $status
        );

        if (!$stmt->execute()) throw new Exception('Failed to save message.');
        $stmt->close();

        $success = 'Message ' . ($status === 'sent' ? 'sent successfully!' : 'queued for delivery.');

        // Clear form
        $formData = [
          'recipient_type' => 'user',
          'recipient_id' => '',
          'subject' => '',
          'message' => '',
          'send_immediately' => '1',
          'scheduled_at' => ''
        ];
      } catch (Exception $e) {
        $errors[] = 'Error: ' . $e->getMessage();
      }
    }
  }
}

require_once dirname(dirname(__DIR__)) . '/templates/layout/header.php';
?>

<style>
.message-form{max-width:900px;margin:0 auto}
.form-section{background:#fff;border:1px solid #e9ecef;border-radius:.5rem;padding:1.5rem;margin-bottom:1.5rem}
.recipient-options{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-top:1rem}
.recipient-option{padding:1rem;border:2px solid #e9ecef;border-radius:.375rem;cursor:pointer;transition:.2s}
.recipient-option:hover{border-color:#0d6efd;background:#f8f9fa}
.recipient-option.active{border-color:#0d6efd;background:#e7f1ff}
.recipient-option input{margin-right:.5rem}
.recipient-details{margin-top:1rem;padding:1rem;background:#f8f9fa;border-radius:.375rem}
.schedule-section{display:none}
.schedule-section.show{display:block}
.message-preview{border:1px solid #e9ecef;border-radius:.375rem;padding:1rem;margin-top:1rem;background:#f8f9fa}
.character-count{text-align:right;font-size:.875rem;color:#6c757d;margin-top:.25rem}
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
            $active = 'send'; // Set active page for navigation
            require_once dirname(dirname(__DIR__)) . '/templates/partials/messaging_nav.php'; 
            ?>
          </div>
        </div>

        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger">
            <strong>Error:</strong>
            <ul class="mb-0 mt-2">
              <?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if ($success): ?>
          <div class="alert alert-success"><?= h($success) ?></div>
        <?php endif; ?>

        <div class="message-form">
          <form method="POST" id="messageForm">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

            <div class="form-section">
              <h5 class="mb-3">Recipient</h5>

              <div class="recipient-options">
                <label class="recipient-option <?= $formData['recipient_type'] === 'user' ? 'active' : '' ?>">
                  <input type="radio" name="recipient_type" value="user"
                    <?= $formData['recipient_type'] === 'user' ? 'checked' : '' ?>
                    onchange="updateRecipientType('user')">
                  <strong>Specific User</strong>
                  <div class="text-muted small">Send to an individual user</div>
                </label>

                <label class="recipient-option <?= $formData['recipient_type'] === 'role' ? 'active' : '' ?>">
                  <input type="radio" name="recipient_type" value="role"
                    <?= $formData['recipient_type'] === 'role' ? 'checked' : '' ?>
                    onchange="updateRecipientType('role')">
                  <strong>User Role</strong>
                  <div class="text-muted small">Send to all users with a specific role</div>
                </label>

                <label class="recipient-option <?= $formData['recipient_type'] === 'all' ? 'active' : '' ?>">
                  <input type="radio" name="recipient_type" value="all"
                    <?= $formData['recipient_type'] === 'all' ? 'checked' : '' ?>
                    onchange="updateRecipientType('all')">
                  <strong>All Users</strong>
                  <div class="text-muted small">Send to all active users</div>
                </label>
              </div>

              <div id="recipientDetails" class="recipient-details">
                <?php if ($formData['recipient_type'] === 'user'): ?>
                  <label for="recipient_id" class="form-label">Select User:</label>
                  <select class="form-select" id="recipient_id" name="recipient_id" required>
                    <option value="">Choose a user...</option>
                    <?php foreach ($users as $user): ?>
                      <option value="<?= (int)$user['id'] ?>" <?= $formData['recipient_id'] == $user['id'] ? 'selected' : '' ?>>
                        <?= h($user['username']) ?> (<?= h($user['email']) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                <?php elseif ($formData['recipient_type'] === 'role'): ?>
                  <label for="recipient_id" class="form-label">Select Role:</label>
                  <select class="form-select" id="recipient_id" name="recipient_id" required>
                    <option value="">Choose a role...</option>
                    <?php foreach ($roles as $role): ?>
                      <option value="<?= (int)$role['id'] ?>" <?= $formData['recipient_id'] == $role['id'] ? 'selected' : '' ?>>
                        <?= h($role['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                <?php else: ?>
                  <div class="text-info">
                    <i class="bi bi-info-circle"></i> This message will be sent to all active users in the system.
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div class="form-section">
              <h5 class="mb-3">Message Content</h5>

              <div class="mb-3">
                <label for="subject" class="form-label">Subject (Optional):</label>
                <input type="text" class="form-control" id="subject" name="subject"
                  value="<?= h($formData['subject']) ?>" placeholder="Enter message subject...">
              </div>

              <div class="mb-3">
                <label for="message" class="form-label">Message: *</label>
                <textarea class="form-control" id="message" name="message" rows="8" required
                  placeholder="Type your message here..." oninput="updateCharacterCount()"><?= h($formData['message']) ?></textarea>
                <div class="character-count"><span id="charCount">0</span> characters</div>
              </div>

              <div class="message-preview" id="messagePreview" style="display:none;">
                <h6>Preview:</h6>
                <div id="previewContent"></div>
              </div>
            </div>

            <div class="d-flex gap-2 justify-content-end">
              <a href="<?= h($BASE) ?>/modules/messaging/inbox.php" class="btn btn-outline-secondary">
                <i class="bi bi-x-lg"></i> Cancel
              </a>
              <button type="button" class="btn btn-outline-primary" onclick="previewMessage()">
                <i class="bi bi-eye"></i> Preview
              </button>
              <button type="submit" class="btn btn-primary" id="submitBtn">
                <i class="bi bi-send"></i> Send Message
              </button>
            </div>

          </form>
        </div>
      </div>
    </main>
  </div>
</div>

<script>
const USERS = <?= json_encode($users, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
const ROLES = <?= json_encode($roles, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;

function toggleScheduleSection() {
  const sendNow = document.getElementById('send_now').checked;
  const scheduleSection = document.getElementById('scheduleSection');
  const submitBtn = document.getElementById('submitBtn');
  if (sendNow) {
    scheduleSection.classList.remove('show');
    submitBtn.innerHTML = '<i class="bi bi-send"></i> Send Message';
  } else {
    scheduleSection.classList.add('show');
    submitBtn.innerHTML = '<i class="bi bi-clock"></i> Schedule Message';
  }
}

function updateRecipientType(type) {
  document.querySelectorAll('.recipient-option').forEach(o => o.classList.remove('active'));
  document.querySelector(`input[name="recipient_type"][value="${type}"]`)?.closest('.recipient-option')?.classList.add('active');

  const detailsDiv = document.getElementById('recipientDetails');

  if (type === 'all') {
    detailsDiv.innerHTML = `
      <div class="text-info">
        <i class="bi bi-info-circle"></i> This message will be sent to all active users in the system.
      </div>
    `;
  } else if (type === 'user') {
    let options = '<option value="">Choose a user...</option>';
    USERS.forEach(u => options += `<option value="${u.id}">${escapeHtml(u.username)} (${escapeHtml(u.email)})</option>`);
    detailsDiv.innerHTML = `
      <label for="recipient_id" class="form-label">Select User:</label>
      <select class="form-select" id="recipient_id" name="recipient_id" required>
        ${options}
      </select>
    `;
  } else {
    let options = '<option value="">Choose a role...</option>';
    ROLES.forEach(r => options += `<option value="${r.id}">${escapeHtml(r.name)}</option>`);
    detailsDiv.innerHTML = `
      <label for="recipient_id" class="form-label">Select Role:</label>
      <select class="form-select" id="recipient_id" name="recipient_id" required>
        ${options}
      </select>
    `;
  }

  if (window.applyMessageTemplate) window.applyMessageTemplate(true);
}

function updateCharacterCount() {
  const message = document.getElementById('message')?.value || '';
  document.getElementById('charCount').textContent = message.length;
}

function previewMessage() {
  const subject = document.getElementById('subject').value;
  const message = document.getElementById('message').value;
  if (!message.trim()) { alert('Please enter a message to preview.'); return; }
  let preview = '';
  if (subject) preview += `<strong>Subject:</strong> ${escapeHtml(subject)}<br><br>`;
  preview += `<strong>Message:</strong><br>${escapeHtml(message).replace(/\n/g,'<br>')}`;
  document.getElementById('previewContent').innerHTML = preview;
  const pv = document.getElementById('messagePreview');
  pv.style.display = 'block';
  pv.scrollIntoView({ behavior: 'smooth' });
}

function escapeHtml(str){
  return String(str ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

function replaceTemplatePlaceholders(text) {
  const now = new Date();
  const recipientType = document.querySelector('input[name="recipient_type"]:checked')?.value;
  const sel = document.getElementById('recipient_id');
  let name = 'User';

  if (recipientType === 'user' && sel?.selectedIndex >= 0) {
    const label = sel.options[sel.selectedIndex].text || '';
    const match = label.match(/^([^(]+)\s+\(/);
    if (match) name = match[1].trim();
  } else if (recipientType === 'role' && sel?.selectedIndex >= 0) {
    name = sel.options[sel.selectedIndex].text || 'Role';
  } else if (recipientType === 'all') {
    name = 'All Users';
  }

  const map = {
    '{username}': name,
    '{date}': now.toLocaleDateString(),
    '{time}': now.toLocaleTimeString(),
    '{datetime}': now.toLocaleString(),
    '{year}': String(now.getFullYear()),
    '{month}': now.toLocaleString('default', { month: 'long' }),
    '{day}': String(now.getDate())
  };

  let out = String(text ?? '');
  for (const [k,v] of Object.entries(map)) out = out.replace(new RegExp(k.replace(/[{}]/g,'\\$&'),'g'), v);
  return out;
}

// ✅ Template auto apply (works even after dropdown rebuild)
(function(){
  const KEY = 'messageTemplate';

  function apply(force=true){
    const msg = document.getElementById('message');
    const subj = document.getElementById('subject');
    if (!msg) return;

    const raw = sessionStorage.getItem(KEY);
    if (!raw) return;

    let tpl;
    try { tpl = JSON.parse(raw); } catch(e){ sessionStorage.removeItem(KEY); return; }

    if (!force && msg.dataset.templateApplied === '1') return;

    if (subj && typeof tpl.subject !== 'undefined') subj.value = tpl.subject || '';

    if (typeof tpl.message !== 'undefined') {
      const withVars = replaceTemplatePlaceholders(tpl.message || '');
      msg.value = withVars;
      msg.dataset.templateApplied = '1';
      msg.dispatchEvent(new Event('input', { bubbles: true }));
      
      // Clear template from sessionStorage after applying
      sessionStorage.removeItem(KEY);
    }
  }

  window.applyMessageTemplate = apply;

  document.addEventListener('DOMContentLoaded', () => {
    // Only auto-apply template if user came from templates page
    if (document.referrer.includes('templates.php')) {
      apply(true);
    }
    updateCharacterCount();

    // Event delegation: recipient changes even after rebuild
    document.addEventListener('change', (e) => {
      if (e.target && e.target.id === 'recipient_id') apply(true);
    });
  });
})();
</script>

<?php require_once dirname(dirname(__DIR__)) . '/templates/layout/footer.php'; ?>
