<?php
require_once dirname(__DIR__) . '/_admin_api.php';
require_once dirname(dirname(__DIR__)) . '/includes/settings.php';
api_require_post();

$key = (string)($_POST['key'] ?? '');
$err = setting_save($db, $key, (string)($_POST['value'] ?? ''));
if ($err !== null) api_out(['success' => false, 'message' => $err], 422);

$secret = (settings_fields()[$key]['type'] ?? '') === 'password';
audit_log('settings.update', 'settings', $key, $secret ? 'Changed (hidden)' : 'Set to: ' . mb_substr((string)($_POST['value'] ?? ''), 0, 120));
api_out(['success' => true, 'message' => 'Saved']);
