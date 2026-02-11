<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/rbac.php';

require_admin_login();

if (!function_exists('user_has_permission') || !user_has_permission('permissions.update')) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Forbidden']);
    exit;
}

$db = $GLOBALS['db'] ?? null;
if (!$db instanceof mysqli) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'DB unavailable']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Invalid JSON']);
    exit;
}

$role_id = (int)($input['role_id'] ?? 0);
$perms   = $input['permissions'] ?? [];

if ($role_id <= 0) {
    echo json_encode(['ok'=>false,'error'=>'Invalid role']);
    exit;
}

$db->begin_transaction();

try {
    $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id=?");
    $stmt->bind_param("i", $role_id);
    $stmt->execute();
    $stmt->close();

    if (!empty($perms)) {
        $stmt = $db->prepare("
            INSERT INTO role_permissions (role_id, permission_id)
            SELECT ?, id FROM permissions WHERE perm_key=?
        ");

        foreach ($perms as $key) {
            $stmt->bind_param("is", $role_id, $key);
            $stmt->execute();
        }
        $stmt->close();
    }

    $db->commit();
    echo json_encode(['ok'=>true]);
    exit;

} catch (Throwable $e) {
    $db->rollback();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    exit;
}
?>
