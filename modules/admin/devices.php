<?php
// modules/admin/devices.php — phones signed in to the mobile app, and offline sales waiting for review
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/rbac.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_once dirname(__DIR__, 2) . '/includes/audit.php';

require_super_admin();
$db = $GLOBALS['db'];
$BASE_URL = $GLOBALS['BASE_URL'] ?? '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'revoke') {
        $st = $db->prepare("UPDATE api_devices SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL");
        $st->bind_param('i', $id); $st->execute(); $st->close();
        audit_log('device.revoke', 'device', (string)$id, 'Signed out by an administrator');
    } elseif (($_POST['action'] ?? '') === 'reviewed') {
        $st = $db->prepare("UPDATE sales SET reviewed_at = NOW() WHERE id = ? AND review_note IS NOT NULL");
        $st->bind_param('i', $id); $st->execute(); $st->close();
        audit_log('sale.reviewed', 'sale', (string)$id, 'Offline sale marked as reviewed');
    }
    header('Location: ' . $BASE_URL . '/modules/admin/devices.php');
    exit;
}

$devices = $db->query("SELECT d.*, u.username, (SELECT COUNT(*) FROM sync_mutations m WHERE m.device_id = d.id) AS actions
                       FROM api_devices d JOIN users u ON u.id = d.user_id ORDER BY d.revoked_at IS NOT NULL, d.last_seen_at DESC LIMIT 200")->fetch_all(MYSQLI_ASSOC);
$review = $db->query("SELECT s.id, s.doc_no, s.client_ref, s.grand_total, s.review_note, s.created_at, u.username
                      FROM sales s LEFT JOIN users u ON u.id = s.created_by WHERE s.review_note IS NOT NULL AND s.reviewed_at IS NULL ORDER BY s.id DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);
$head = (int)$db->query("SELECT COALESCE(MAX(id),0) FROM sync_changes")->fetch_row()[0];

$page_title = 'Devices';
$page_subtitle = 'Phones using the mobile app';
$current_user_role = $_SESSION['user']['role'] ?? '';
require_once dirname(__DIR__, 2) . '/templates/layout/header.php';
?>
<div class="app-shell">
  <?php require_once dirname(__DIR__, 2) . '/templates/layout/sidebar.php'; ?>
  <div class="app-content">
    <?php require_once dirname(__DIR__, 2) . '/templates/layout/topbar.php'; ?>
    <main class="page-wrap">
      <div class="container-fluid">
        <?php if ($review): ?>
          <div class="card mb-3">
            <div class="card-header fw-semibold"><i class="bi bi-exclamation-triangle text-warning me-2" aria-hidden="true"></i>Offline sales to review (<?= count($review) ?>)</div>
            <div class="table-responsive"><table class="table mb-0">
              <thead><tr><th>Document</th><th>Device no.</th><th>Sold by</th><th>When</th><th class="text-end">Total</th><th>Why</th><th></th></tr></thead>
              <tbody><?php foreach ($review as $r): ?>
                <tr><td><a href="<?= h($BASE_URL) ?>/modules/pos/sale_view.php?id=<?= (int)$r['id'] ?>"><?= h($r['doc_no']) ?></a></td><td><?= h((string)$r['client_ref']) ?></td><td><?= h((string)$r['username']) ?></td>
                    <td><?= h(substr((string)$r['created_at'], 0, 16)) ?></td><td class="text-end"><?= number_format((float)$r['grand_total']) ?></td><td class="small"><?= h($r['review_note']) ?></td>
                    <td class="text-end"><form method="post" class="d-inline"><input type="hidden" name="action" value="reviewed"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-outline-secondary btn-sm">Mark reviewed</button></form></td></tr>
              <?php endforeach; ?></tbody>
            </table></div>
          </div>
        <?php endif; ?>

        <div class="card">
          <div class="card-header fw-semibold">Signed-in devices</div>
          <div class="table-responsive"><table class="table mb-0">
            <thead><tr><th>Device</th><th>User</th><th>Last seen</th><th>Behind by</th><th class="text-end">Actions sent</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php if (!$devices): ?><tr><td colspan="7" class="text-center text-muted py-4">No phone has signed in yet.</td></tr><?php endif; ?>
              <?php foreach ($devices as $d): $behind = max(0, $head - (int)$d['last_pull_version']); ?>
                <tr><td><?= h($d['name']) ?></td><td><?= h($d['username']) ?></td><td><?= h($d['last_seen_at'] ? substr((string)$d['last_seen_at'], 0, 16) : 'Never') ?></td>
                    <td><?= $behind === 0 ? 'Up to date' : $behind . ' change(s)' ?></td><td class="text-end"><?= (int)$d['actions'] ?></td>
                    <td><?= $d['revoked_at'] ? '<span class="badge text-bg-secondary">Signed out</span>' : '<span class="badge text-bg-success">Active</span>' ?></td>
                    <td class="text-end"><?php if (!$d['revoked_at']): ?><form method="post" class="d-inline"><input type="hidden" name="action" value="revoke"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                      <button class="btn btn-outline-danger btn-sm" data-confirm="Sign this device out? Sales it has not sent yet stay on the phone until someone signs in again." data-confirm-text="Sign out">Sign out</button></form><?php endif; ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        </div>
      </div>
    </main>
  </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/templates/layout/footer.php'; ?>
