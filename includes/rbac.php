<?php
// includes/rbac.php
declare(strict_types=1);

function user_has_permission(string $permKey): bool
{
    if (empty($_SESSION['user'])) return false;

    // super_admin shortcut (optional; still supported by role perms anyway)
    if (($_SESSION['user']['role'] ?? '') === 'super_admin') return true;

    $db = $GLOBALS['db'];
    if (!$db instanceof mysqli) return false;

    $uid = (int)$_SESSION['user']['id'];

    

    // 1) user override deny/grant
    $stmt = $db->prepare("
      SELECT up.is_allowed
      FROM user_permissions up
      JOIN permissions p ON p.id = up.permission_id
      WHERE up.user_id = ? AND p.perm_key = ?
      LIMIT 1
    ");
    $stmt->bind_param("is", $uid, $permKey);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row !== null) return (int)$row['is_allowed'] === 1;

    // 2) role permission
    $roleId = (int)($_SESSION['user']['role_id'] ?? 0);
    $stmt = $db->prepare("
      SELECT 1
      FROM role_permissions rp
      JOIN permissions p ON p.id = rp.permission_id
      WHERE rp.role_id = ? AND p.perm_key = ?
      LIMIT 1
    ");
    $stmt->bind_param("is", $roleId, $permKey);
    $stmt->execute();
    $ok = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();

    return $ok;
}

function require_permission(string $permKey): void
{
    require_login();
    if (!user_has_permission($permKey)) {
        http_response_code(403);
        
        // Include app header and sidebar for consistent styling
        require_once __DIR__ . '/../templates/layout/header.php';
        ?>
        
        <div class="app-shell">
            <?php require_once __DIR__ . '/../templates/layout/sidebar.php'; ?>
            
            <div class="app-content">
                <?php require_once __DIR__ . '/../templates/layout/topbar.php'; ?>
                
                <main class="page-wrap">
                    <div class="container-fluid py-5">
                        <div class="row justify-content-center align-items-center min-vh-100">
                            <div class="col-md-8 col-lg-6 col-xl-5">
                                <div class="card shadow-lg border-0" style="max-width: 500px; margin: 0 auto;">
                                    <div class="card-body text-center p-5">
                                        <div class="mb-4">
                                            <div class="d-inline-flex align-items-center justify-content-center bg-danger bg-opacity-10 rounded-circle p-4 mb-4" style="width: 80px; height: 80px; margin: 0 auto;">
                                                <i class="bi bi-shield-exclamation text-danger" style="font-size: 2.5rem;"></i>
                                            </div>
                                        </div>
                                        <h4 class="card-title text-danger mb-4">Access Denied</h4>
                                        <p class="text-muted mb-4 fs-5">
                                            You don't have permission to access this feature.
                                        </p>
                                        <div class="alert alert-info d-flex align-items-center p-3">
                                            <i class="bi bi-info-circle me-3 fs-4"></i>
                                            <div class="text-start">
                                                <strong>Need Access?</strong><br>
                                                Please contact your system administrator to request permission for this feature.
                                            </div>
                                        </div>
                                        <div class="d-grid gap-3 mt-4" style="grid-template-columns: 1fr 1fr;">
                                            <a href="javascript:history.back()" class="btn btn-outline-secondary btn-lg w-100">
                                                <i class="bi bi-arrow-left me-2"></i>Go Back
                                            </a>
                                            <a href="<?php echo $GLOBALS['BASE_URL'] ?? '/' ?>" class="btn btn-primary btn-lg w-100">
                                                <i class="bi bi-house me-2"></i>Dashboard
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </main>
                </div>
            </div>
        </div>
        <?php
        // Include app footer
        require_once __DIR__ . '/../templates/layout/footer.php';
        exit;
    }
}
