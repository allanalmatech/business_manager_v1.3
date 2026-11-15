<?php
// api/_admin_api.php — shared preamble for super-admin JSON endpoints
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/rbac.php';
require_once dirname(__DIR__) . '/includes/audit.php';

header('Content-Type: application/json; charset=utf-8');

function api_out(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

if (empty($_SESSION['user'])) api_out(['success' => false, 'message' => 'Not signed in'], 401);
if (!is_super_admin()) api_out(['success' => false, 'message' => 'Permission denied'], 403);

$db = $GLOBALS['db'];

function api_require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') api_out(['success' => false, 'message' => 'Method not allowed'], 405);
}
