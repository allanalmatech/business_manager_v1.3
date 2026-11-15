<?php
require_once dirname(__DIR__) . '/_admin_api.php';
require_once dirname(dirname(__DIR__)) . '/includes/updater.php';
require_once dirname(dirname(__DIR__)) . '/includes/migrations.php';
api_require_post();
@set_time_limit(600);

$mode = (string)($_POST['mode'] ?? 'update');
if ($mode === 'migrate') {
    try {
        $done = migrations_run($db);
        audit_log('updates.migrate', 'system', null, 'Database steps: ' . ($done ? implode(', ', $done) : 'none'));
        api_out(['success' => true, 'message' => $done ? 'Database updated.' : 'Database already up to date.', 'migrations' => $done]);
    } catch (Throwable $e) {
        api_out(['success' => false, 'message' => 'A database step failed.', 'detail' => $e->getMessage()], 500);
    }
}

$res = updater_apply($db);
audit_log('updates.apply', 'system', null, $res['message'] ?? '');
api_out($res, $res['success'] ? 200 : 409);
