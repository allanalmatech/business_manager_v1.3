<?php
require_once dirname(__DIR__) . '/_admin_api.php';
api_require_post();

$id = (int)($_POST['id'] ?? 0);
$st = $db->prepare("SELECT name FROM roles WHERE id = ? LIMIT 1");
$st->bind_param('i', $id);
$st->execute();
$role = $st->get_result()->fetch_assoc();
$st->close();
if (!$role) api_out(['success' => false, 'message' => 'Role not found'], 404);
if (in_array($role['name'], ['super_admin', 'cashier', 'accountant'], true)) {
    api_out(['success' => false, 'message' => 'Built-in roles cannot be deleted.'], 422);
}
$st = $db->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
$st->bind_param('i', $id);
$st->execute();
$n = (int)$st->get_result()->fetch_row()[0];
$st->close();
if ($n > 0) api_out(['success' => false, 'message' => "$n user(s) still have this role. Move them to another role first."], 422);

$db->begin_transaction();
$st = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
$st->bind_param('i', $id); $st->execute(); $st->close();
$st = $db->prepare("DELETE FROM roles WHERE id = ?");
$st->bind_param('i', $id); $st->execute(); $st->close();
$db->commit();
audit_log('roles.delete', 'role', (string)$id, 'Deleted role ' . $role['name']);
api_out(['success' => true, 'message' => 'Role deleted']);
