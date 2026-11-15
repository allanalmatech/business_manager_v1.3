<?php
require_once dirname(__DIR__) . '/_admin_api.php';
require_once dirname(dirname(__DIR__)) . '/includes/updater.php';
require_once dirname(dirname(__DIR__)) . '/includes/migrations.php';

$res = updater_check();
$res['pending_migrations'] = migrations_pending($db);
$res['version'] = defined('APP_VERSION') ? APP_VERSION : '';
api_out($res);
