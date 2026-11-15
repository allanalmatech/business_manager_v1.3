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
}

/* ---------- Sign-in throttling ---------- */

const LOGIN_MAX_FAILURES = 5;      // per identifier, and 20 per IP
const LOGIN_WINDOW_MINUTES = 15;

function login_attempts_ready(mysqli $db): bool
{
    static $ready = null;
    if ($ready === null) {
        $r = $db->query("SHOW TABLES LIKE 'login_attempts'");
        $ready = (bool)($r && $r->num_rows > 0);
    }
    return $ready;
}

/** Seconds the caller must wait before trying again; 0 when allowed. */
function login_throttle_wait(mysqli $db, string $identifier): int
{
    if (!login_attempts_ready($db)) return 0;
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $identifier = mb_strtolower(mb_substr($identifier, 0, 190));
    $stmt = $db->prepare("
        SELECT
          SUM(identifier = ?) AS by_user,
          SUM(ip_address = ?) AS by_ip,
          TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(MAX(created_at), INTERVAL " . LOGIN_WINDOW_MINUTES . " MINUTE)) AS wait_s
        FROM login_attempts
        WHERE success = 0
          AND created_at > DATE_SUB(NOW(), INTERVAL " . LOGIN_WINDOW_MINUTES . " MINUTE)
          AND (identifier = ? OR ip_address = ?)
    ");
    if (!$stmt) return 0;
    $stmt->bind_param('ssss', $identifier, $ip, $identifier, $ip);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    if ((int)($row['by_user'] ?? 0) >= LOGIN_MAX_FAILURES || (int)($row['by_ip'] ?? 0) >= LOGIN_MAX_FAILURES * 4) {
        return max(1, (int)($row['wait_s'] ?? 60));
    }
    return 0;
}

function login_record_attempt(mysqli $db, string $identifier, bool $success): void
{
    if (!login_attempts_ready($db)) return;
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $identifier = mb_strtolower(mb_substr($identifier, 0, 190));
    $ok = $success ? 1 : 0;
    $stmt = $db->prepare("INSERT INTO login_attempts (identifier, ip_address, success) VALUES (?,?,?)");
    if ($stmt) {
        $stmt->bind_param('ssi', $identifier, $ip, $ok);
        $stmt->execute();
        $stmt->close();
    }
    if ($success) {
        // A good sign-in clears that account's failure streak
        $stmt = $db->prepare("DELETE FROM login_attempts WHERE identifier = ? AND success = 0");
        if ($stmt) { $stmt->bind_param('s', $identifier); $stmt->execute(); $stmt->close(); }
    }
    if (random_int(1, 50) === 1) {
        $db->query("DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    }
}
