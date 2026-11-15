<?php
// templates/layout/header.php
$BASE_URL = $GLOBALS['BASE_URL'] ?? '';
$page_title = $page_title ?? (defined('APP_NAME') ? APP_NAME : 'Business Manager');

// Theme: id from settings, light/dark mode from the generated theme list
$bm_themes = require dirname(__DIR__, 2) . '/includes/themes.php';
$current_theme = 'default';
$db = $GLOBALS['db'] ?? null;
if ($db instanceof mysqli) {
    $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = 'app_theme' LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row && preg_match('/^[a-z0-9_]{1,60}$/', (string)$row['value'])) $current_theme = (string)$row['value'];
        $stmt->close();
    }
}
$is_custom_theme = strpos($current_theme, 'custom_') === 0;
if (!$is_custom_theme && !isset($bm_themes[$current_theme])) $current_theme = 'default';
$theme_mode = $bm_themes[$current_theme]['mode'] ?? 'light';
$A = htmlspecialchars($BASE_URL) . '/assets';
$V = defined('APP_VERSION') ? rawurlencode((string)APP_VERSION) : '1';
?>
<!doctype html>
<html lang="en" data-theme="<?= htmlspecialchars($current_theme) ?>" data-bs-theme="<?= $theme_mode ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="<?= htmlspecialchars($bm_themes[$current_theme]['sidebar'] ?? '#ffffff') ?>">
  <title><?= htmlspecialchars($page_title) ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= $A ?>/img/favicon.svg">
  <meta name="csrf-token" content="<?= htmlspecialchars(function_exists('csrf_token') ? csrf_token() : (string)($_SESSION['csrf'] ?? '')) ?>">
  <meta name="base-url" content="<?= htmlspecialchars($BASE_URL) ?>">
  <script src="<?= $A ?>/js/core.js?v=<?= $V ?>"></script>

  <!-- Everything is served from this server: the till keeps its look without internet -->
  <link rel="stylesheet" href="<?= $A ?>/vendor/bootstrap/bootstrap.min.css">
  <script src="<?= $A ?>/vendor/bootstrap/bootstrap.bundle.min.js"></script>
  <link rel="stylesheet" href="<?= $A ?>/vendor/bootstrap-icons/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= $A ?>/css/themes/themes_bundle.css?v=<?= $V ?>">
  <link rel="stylesheet" href="<?= $A ?>/css/main.css?v=<?= $V ?>">
  <link rel="stylesheet" href="<?= $A ?>/css/sidebar.css?v=<?= $V ?>">
  <link rel="stylesheet" href="<?= $A ?>/css/forms.css?v=<?= $V ?>">
  <link rel="stylesheet" href="<?= $A ?>/css/tables.css?v=<?= $V ?>">
  <link rel="stylesheet" href="<?= $A ?>/css/print.css?v=<?= $V ?>">
  <?php if ($is_custom_theme): ?>
    <link rel="stylesheet" href="<?= $A ?>/css/themes/<?= htmlspecialchars($current_theme) ?>.css">
  <?php endif; ?>
  <script>
    // Apply the saved sidebar state before first paint so the layout does not jump
    try { if (localStorage.getItem('bm.sidebar.collapsed') === '1') document.documentElement.classList.add('sb-pre-collapsed'); } catch (e) {}
  </script>
  <style>html.sb-pre-collapsed .app-sidebar { width: var(--bm-rail-w); } html.sb-pre-collapsed .app-content { margin-left: var(--bm-rail-w); } @media (max-width: 991.98px) { html.sb-pre-collapsed .app-sidebar { width: min(86vw, 320px); } html.sb-pre-collapsed .app-content { margin-left: 0; } }</style>

  <?php if (!empty($extra_css)) : ?>
    <?php foreach ((array)$extra_css as $css): ?>
      <link rel="stylesheet" href="<?= htmlspecialchars($BASE_URL . '/' . ltrim($css, '/')) ?>?v=<?= $V ?>">
    <?php endforeach; ?>
  <?php endif; ?>
</head>
<body>
<a class="visually-hidden-focusable" href="#bm-main">Skip to content</a>
