<?php
declare(strict_types=1);

ob_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/bootstrap.php';

$_SESSION = [];
$params = session_get_cookie_params();
setcookie(
    session_name(),
    '',
    time() - 42000,
    $params['path'],
    $params['domain'],
    (bool)$params['secure'],
    (bool)$params['httponly']
);
session_destroy();

if (ob_get_length()) ob_clean();
echo json_encode(['success' => true]);
