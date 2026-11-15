<?php
// Shared by create.php and update.php
require_once dirname(__DIR__) . '/_admin_api.php';
api_require_post();

const PROTECTED_ROLES = ['super_admin', 'cashier', 'accountant'];

$id   = (int)($_POST['id'] ?? 0);
$name = strtolower(trim((string)($_POST['name'] ?? '')));
$name = preg_replace('/[^a-z0-9_]+/', '_', $name);
$desc = trim((string)($_POST['description'] ?? ''));

if ($name === '' || strlen($name) > 50) api_out(['success' => false, 'message' => 'Enter a role name (letters, numbers, underscores).'], 422);

$st = $db->prepare("SELECT id FROM roles WHERE name = ? AND id <> ? LIMIT 1");
$st->bind_param('si', $name, $id);
$st->execute();
if ($st->get_result()->fetch_row()) api_out(['success' => false, 'message' => 'A role with that name already exists.'], 422);
$st->close();

if (ROLE_SAVE_MODE === 'create') {
    $st = $db->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
    $st->bind_param('ss', $name, $desc);
    $st->execute();
    $id = (int)$st->insert_id;
    $st->close();
    audit_log('roles.create', 'role', (string)$id, "Created role $name");
    api_out(['success' => true, 'message' => 'Role created', 'id' => $id]);
}

$st = $db->prepare("SELECT name FROM roles WHERE id = ? LIMIT 1");
$st->bind_param('i', $id);
$st->execute();
$cur = $st->get_result()->fetch_assoc();
$st->close();
if (!$cur) api_out(['success' => false, 'message' => 'Role not found'], 404);
if (in_array($cur['name'], PROTECTED_ROLES, true) && $cur['name'] !== $name) {
    api_out(['success' => false, 'message' => 'Built-in roles cannot be renamed.'], 422);
}
$st = $db->prepare("UPDATE roles SET name = ?, description = ? WHERE id = ?");
$st->bind_param('ssi', $name, $desc, $id);
$st->execute();
$st->close();
audit_log('roles.update', 'role', (string)$id, "Updated role $name");
api_out(['success' => true, 'message' => 'Role updated']);
