<?php
// templates/partials/messaging_nav.php
declare(strict_types=1);

$BASE = $BASE ?? ($GLOBALS['BASE_URL'] ?? '');
$active = $active ?? ''; // inbox|send|templates|logs|queue

$btn = function(string $key, string $href, string $icon, string $label) use ($BASE, $active) {
  $isActive = ($active === $key);
  $cls = $isActive ? 'btn btn-primary' : 'btn btn-outline-secondary';
  echo '<a href="'.h($BASE.$href).'" class="'.h($cls).'">
          <i class="bi '.h($icon).'"></i> '.h($label).'
        </a>';
};
?>
<div class="d-flex gap-2 flex-wrap">
  <?php $btn('inbox',     '/modules/messaging/inbox.php',     'bi-inbox',                 'Inbox'); ?>
  <?php $btn('send',      '/modules/messaging/send.php',      'bi-send',                  'Send'); ?>
  <?php $btn('templates', '/modules/messaging/templates.php', 'bi-layout-text-sidebar',   'Templates'); ?>
  <?php $btn('logs',      '/modules/messaging/logs.php',      'bi-journal-text',          'Logs'); ?>
  <?php $btn('queue',     '/modules/messaging/queue.php',     'bi-clock',                 'Queue'); ?>
</div>
