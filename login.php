<?php
// login.php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/audit.php';

$BASE_URL = $GLOBALS['BASE_URL'] ?? '';

$error = '';
$notice = isset($_GET['database_emptied'])
    ? 'Database was emptied successfully. Re-run setup or restore a backup before signing in.'
    : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
        $error = "Invalid session. Refresh and try again.";
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        $db = $GLOBALS['db'];
        $wait = login_throttle_wait($db, $username);
        if ($wait > 0) {
            $error = 'Too many failed attempts. Try again in ' . (int)ceil($wait / 60) . ' minute(s).';
        } else {

        $stmt = $db->prepare("
          SELECT u.id, u.role_id, r.name AS role, u.username, u.full_name, u.password_hash, u.is_active
          FROM users u
          JOIN roles r ON r.id = u.role_id
          WHERE u.username = ? OR u.email = ?
          LIMIT 1
        ");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user || (int)$user['is_active'] !== 1 || !password_verify($password, $user['password_hash'])) {
            $error = "Invalid credentials.";
            login_record_attempt($db, $username, false);
            audit_log('auth.login_failed', 'user', $username, 'Invalid credentials');
        } else {
            login_record_attempt($db, $username, true);
            session_regenerate_id(true);
            unset($_SESSION['csrf']);
            csrf_token();

            $_SESSION['user'] = [
                'id'      => (int)$user['id'],
                'role_id' => (int)$user['role_id'],
                'role'    => (string)$user['role'],
                'username'=> (string)$user['username'],
                'name'    => (string)$user['full_name'],
                'full_name' => (string)$user['full_name'],
            ];

            // update last login
            $stmt = $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
            $uid = (int)$user['id'];
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $stmt->close();

            audit_log('auth.login_success', 'user', (string)$user['id'], 'Login successful');

            header("Location: {$BASE_URL}/index.php");
            exit;
        }
        } // throttle
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($GLOBALS['BASE_URL'] ?? '') ?>/assets/vendor/bootstrap-icons/bootstrap-icons.min.css">

  <link href="<?= htmlspecialchars($GLOBALS['BASE_URL'] ?? '') ?>/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<style>
  .auth-wrap{
    min-height: 100vh;
    display:flex;
    align-items:center;
    padding: 2.5rem 0;
    background:
      radial-gradient(900px 500px at 15% 10%, rgba(13,110,253,.12), transparent 55%),
      radial-gradient(900px 500px at 85% 20%, rgba(111,66,193,.12), transparent 55%),
      radial-gradient(900px 500px at 50% 95%, rgba(25,135,84,.10), transparent 55%),
      #f8f9fa;
  }
  .auth-card{
    border:0;
    border-radius: 18px;
    overflow:hidden;
  }
  .auth-aside{
    background: linear-gradient(135deg, #1f5fd0, #5b3cc4);
    color:#fff;
    padding: 1.75rem;
  }
  .auth-badge{
    display:inline-flex;
    align-items:center;
    gap:.5rem;
    padding:.35rem .7rem;
    border-radius: 999px;
    background: rgba(255,255,255,.18);
    border: 1px solid rgba(255,255,255,.22);
    font-size:.85rem;
  }
  .auth-body{
    padding: 1.75rem;
  }
  .auth-title{
    font-weight: 800;
    letter-spacing: .2px;
    margin-bottom: .25rem;
  }
  .auth-sub{
    color: rgba(255,255,255,.85);
    margin: 0;
    font-size: .95rem;
  }
  .input-icon{
    position: relative;
  }
  .input-icon i{
    position:absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    pointer-events:none;
  }
  .input-icon .form-control{
    padding-left: 38px;
  }
  .btn-auth{
    border-radius: 12px;
    padding: .7rem 1rem;
    font-weight: 700;
  }
  .demo-box{
    border-radius: 14px;
    background: #f8f9ff;
    border: 1px solid #e7e9ff;
    padding: .9rem;
  }
  .demo-row{
    display:flex;
    justify-content: space-between;
    gap: 10px;
    padding: .45rem .5rem;
    border-radius: 10px;
  }
  .demo-row:hover{ background: rgba(13,110,253,.06); }
  .demo-k{ font-size: .85rem; color:#6c757d; }
  .demo-v{ font-weight: 700; }
  .copy-btn{
    border-radius: 10px;
    padding: .25rem .55rem;
    transition: all 0.2s ease;
    border: 1px solid #cfe2ff;
    background: #e7f1ff;
    color: #0d47a1;
    min-width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    position: relative;
    z-index: 10;
  }
  .copy-btn:hover{
    background: #0d6efd;
    border-color: #0d6efd;
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(13,110,253,0.2);
  }
  .copy-btn.copied{
    background: #198754;
    border-color: #198754;
    color: #fff;
  }
  .copy-btn i{
    font-size: 1rem;
    transition: transform 0.2s ease;
  }
  .copy-btn:hover i{
    transform: scale(1.1);
  }
  .input-icon i {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
}

</style>

<div class="auth-wrap">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12">

        <div class="card shadow auth-card">
          <div class="row g-0">

            <!-- Left / Brand -->
            <div class="col-lg-5">
              <div class="auth-aside h-100 d-flex flex-column justify-content-between">
                <div>
                  <div class="auth-badge mb-3">
                    <i class="bi bi-shield-lock"></i>
                    Secure Access
                  </div>

                  <h3 class="auth-title">Business Manager</h3>
                  <p class="auth-sub">Sign in to continue to your dashboard.</p>

                  <div class="mt-4 small" style="opacity:.95;">
                    <div class="d-flex gap-2 align-items-center mb-2">
                      <i class="bi bi-check2-circle"></i> Role-based access
                    </div>
                    <div class="d-flex gap-2 align-items-center mb-2">
                      <i class="bi bi-check2-circle"></i> Audit logs & permissions
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                      <i class="bi bi-check2-circle"></i> POS & Inventory ready
                    </div>
                  </div>
                </div>

                <div class="small mt-4" style="opacity:.85;">
                  <div class="d-flex gap-2 align-items-center">
                    <i class="bi bi-info-circle"></i>
                    Sign in with the account your administrator gave you.
                  </div>
                </div>
              </div>
            </div>

            <!-- Right / Form -->
            <div class="col-lg-7">
              <div class="auth-body">

                <div class="d-flex align-items-center justify-content-between mb-3">
                  <div>
                    <h5 class="mb-0 fw-bold">Sign in</h5>
                    <div class="text-muted small">Enter your credentials to continue</div>
                  </div>
                  <span class="badge bg-light text-dark border">v<?= htmlspecialchars(defined("APP_VERSION") ? APP_VERSION : "") ?></span>
                </div>

                <?php if ($error): ?>
                  <div class="alert alert-danger d-flex align-items-start gap-2">
                    <i class="bi bi-exclamation-triangle mt-1"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                  </div>
                <?php endif; ?>

                <?php if ($notice): ?>
                  <div class="alert alert-warning d-flex align-items-start gap-2">
                    <i class="bi bi-info-circle mt-1"></i>
                    <div><?= htmlspecialchars($notice) ?></div>
                  </div>
                <?php endif; ?>

                <form method="post" autocomplete="on">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">

                 <div class="mb-3">
  <label class="form-label">Username or Email</label>
  <div class="input-icon">
    <i class="bi bi-person"></i>
    <input class="form-control" name="username" required>
  </div>
</div>

<div class="mb-3">
  <label class="form-label">Password</label>
  <div class="input-icon">
    <i class="bi bi-key"></i>
    <input class="form-control" name="password" type="password" required>
  </div>
</div>


                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="rememberMe" name="remember" value="1">
                      <label class="form-check-label small" for="rememberMe">Remember me</label>
                    </div>
                    <a class="small" href="<?= htmlspecialchars($BASE_URL) ?>/modules/auth/forgot_password.php">Forgot password?</a>
                  </div>

                  <button class="btn btn-primary w-100 btn-auth">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Login
                  </button>
                </form>

                <!-- Demo Users -->
                

              </div>
            </div>

          </div>
        </div>

        <div class="text-center small text-muted mt-3">
          © <?= date('Y') ?> Business Manager • Powered by Alma Tech Labs Inc.
        </div>

      </div>
    </div>
  </div>
</div>

<script>
  // Auto-fill demo account on click
  (function(){
    const u = document.querySelector('input[name="username"]');
    const p = document.querySelector('input[name="password"]');

    document.querySelectorAll('[data-copy-btn]').forEach(btn => {
      btn.addEventListener('click', async () => {
        const user = btn.dataset.u || '';
        const pass = btn.dataset.p || '';
        
        // Fill form fields
        if (u) u.value = user;
        if (p) p.value = pass;

        // Visual feedback
        const originalIcon = btn.innerHTML;
        btn.classList.add('copied');
        btn.innerHTML = '<i class="bi bi-check-lg"></i>';
        
        // Optional clipboard copy
        try {
          await navigator.clipboard.writeText(`Username: ${user}\nPassword: ${pass}`);
        } catch (_) {
          // ignore if clipboard blocked
        }

        // Reset after 2 seconds
        setTimeout(() => {
          btn.classList.remove('copied');
          btn.innerHTML = originalIcon;
        }, 2000);
      });
    });
  })();
</script>

</body>

</html>
