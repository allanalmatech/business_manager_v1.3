<?php
// includes/auth.php
declare(strict_types=1);

function require_login(): void
{
    if (empty($_SESSION['user'])) {
        header("Location: " . ($GLOBALS['BASE_URL'] ?? '') . "/login.php");
        exit;
    }
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_role(string ...$roles): void
{
    require_login();
    $role = $_SESSION['user']['role'] ?? '';
    if (!in_array($role, $roles, true)) {
        http_response_code(403);
        echo "Forbidden";
        exit;
    }
}

function is_super_admin(): bool
{
    return (($_SESSION['user']['role'] ?? '') === 'super_admin');
}

/**
 * Hard gate: only super_admin can pass.
 * Use this for modules that must NEVER be shown/accessible to non-super-admin,
 * even if a permission is accidentally granted.
 */
function require_super_admin(): void
{
    require_login();
    if (!is_super_admin()) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

function require_admin_login(): void
{
    // Backward-compatible helper used by older modules.
    // New rule: admin access is permission-based (admin.access), not role-name based.
    require_login();

    // super_admin always allowed
    if (is_super_admin()) return;

    // If RBAC is present, enforce admin.exclusive permission
    if (function_exists('user_has_permission')) {
        if (user_has_permission('admin.exclusive')) return;
        if (user_has_permission('admin.access')) return;
    }
    http_response_code(403);
    echo 'Forbidden';
    exit;
    if (!in_array($role, ['admin', 'super_admin'], true)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}
