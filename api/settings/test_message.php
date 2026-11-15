<?php
require_once dirname(__DIR__) . '/_admin_api.php';
require_once dirname(dirname(__DIR__)) . '/includes/notifications.php';
api_require_post();

$kind = (string)($_POST['kind'] ?? '');
if ($kind === 'email') {
    $st = $db->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
    $uid = (int)$_SESSION['user']['id'];
    $st->bind_param('i', $uid);
    $st->execute();
    $to = (string)($st->get_result()->fetch_row()[0] ?? '');
    $st->close();
    if ($to === '') api_out(['success' => false, 'message' => 'Add an email address to your profile first.'], 422);
    $r = send_email_result($to, 'Test email from ' . setting('business_name'), "This is a test. If you can read it, email is set up correctly.\n");
    api_out(['success' => $r['ok'], 'message' => $r['ok'] ? "Sent to $to. Check the inbox (and spam)." : 'Not sent. ' . $r['error']], $r['ok'] ? 200 : 502);
}
if ($kind === 'sms') {
    $r = send_sms_result((string)($_POST['to'] ?? ''), 'Test message from ' . setting('business_name'));
    api_out(['success' => $r['ok'], 'message' => $r['ok'] ? 'The provider accepted the message.' : 'Not sent. ' . $r['error']], $r['ok'] ? 200 : 502);
}
api_out(['success' => false, 'message' => 'Unknown test'], 400);
