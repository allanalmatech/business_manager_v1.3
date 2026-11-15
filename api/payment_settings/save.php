<?php
require_once dirname(__DIR__) . '/_admin_api.php';
api_require_post();

$allowed = ['flutterwave_public_key', 'flutterwave_secret_key', 'flutterwave_env', 'flutterwave_encryption_key'];
$st = $db->prepare("INSERT INTO settings (`key`, `value`, `group`, `type`, `description`) VALUES (?, ?, 'Payments', 'text', 'Payment gateway')
                    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
foreach ($allowed as $k) {
    if (!isset($_POST[$k])) continue;
    $v = trim((string)$_POST[$k]);
    if ($k === 'flutterwave_env' && !in_array($v, ['sandbox', 'live'], true)) $v = 'sandbox';
    // A blank secret field means "keep what is stored"
    if (in_array($k, ['flutterwave_secret_key', 'flutterwave_encryption_key'], true) && $v === '') continue;
    $st->bind_param('ss', $k, $v);
    $st->execute();
}
$st->close();
audit_log('settings.payment.update', 'settings', null, 'Payment gateway settings changed');
api_out(['success' => true, 'message' => 'Payment settings saved']);
