<?php
$BASE_URL = $GLOBALS['BASE_URL'] ?? '';
$A = htmlspecialchars($BASE_URL) . '/assets';
$V = defined('APP_VERSION') ? rawurlencode((string)APP_VERSION) : '1';
?>
  <div class="bm-toasts" id="bmToasts" role="status" aria-live="polite"></div>
  <link rel="stylesheet" href="<?= $A ?>/vendor/cropper/cropper.min.css">
  <script src="<?= $A ?>/vendor/cropper/cropper.min.js"></script>
  <script src="<?= $A ?>/js/ui.js?v=<?= $V ?>"></script>
  <script src="<?= $A ?>/js/sidebar.js?v=<?= $V ?>"></script>

  <?php if (!empty($extra_js)) : ?>
    <?php foreach ((array)$extra_js as $js): ?>
      <script src="<?= htmlspecialchars($BASE_URL . '/' . ltrim($js, '/')) ?>?v=<?= $V ?>"></script>
    <?php endforeach; ?>
  <?php endif; ?>
</body>
</html>
