<?php
// Kept for old links; the reset flow lives in modules/auth.
require_once __DIR__ . '/includes/bootstrap.php';
header('Location: ' . ($GLOBALS['BASE_URL'] ?? '') . '/modules/auth/forgot_password.php');
exit;
