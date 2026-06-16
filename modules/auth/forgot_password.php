<?php
// modules/auth/forgot_password.php
declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/helpers.php';
require_once dirname(dirname(__DIR__)) . '/includes/rbac.php';

// Initialize variables
$errors = [];
$success = '';
$step = 1;
$token = '';
$email = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        $errors[] = 'Invalid request. Please try again.';
    } elseif ($action === 'request_reset') {
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email)) {
            $errors[] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            // Check if user exists
            $db = $GLOBALS['db'] ?? null;
            if ($db instanceof mysqli) {
                $stmt = $db->prepare("SELECT id, username FROM users WHERE email = ? LIMIT 1");
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                if ($user) {
                    // Generate reset token
                    $reset_token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Store reset token
                    $stmt = $db->prepare("
                        INSERT INTO password_resets (email, token, expires_at, created_at) 
                        VALUES (?, ?, ?, NOW())
                    ");
                    $stmt->bind_param('sss', $email, $reset_token, $expires_at);
                    $stmt->execute();
                    $stmt->close();
                    
                    $success = 'Password reset link has been sent to your email.';
                    $step = 2;
                    $token = $reset_token;
                } else {
                    $errors[] = 'No account found with that email address.';
                }
            } else {
                $errors[] = 'Database connection error.';
            }
        }
    } elseif ($action === 'reset_password') {
        $token = $_POST['token'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($token)) {
            $errors[] = 'Reset token is required.';
        } elseif (empty($new_password)) {
            $errors[] = 'New password is required.';
        } elseif (strlen($new_password) < 6) {
            $errors[] = 'Password must be at least 6 characters long.';
        } elseif ($new_password !== $confirm_password) {
            $errors[] = 'Passwords do not match.';
        } else {
            // Validate token
            $db = $GLOBALS['db'] ?? null;
            if ($db instanceof mysqli) {
                $stmt = $db->prepare("
                    SELECT pr.id, pr.email, pr.token, pr.expires_at 
                    FROM password_resets pr 
                    WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = 0 
                    ORDER BY pr.created_at DESC LIMIT 1
                ");
                $stmt->bind_param('s', $token);
                $stmt->execute();
                $reset_request = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                if ($reset_request) {
                    // Update password
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    $stmt = $db->prepare("
                        UPDATE users SET password_hash = ?, must_change_password = 0, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->bind_param('si', $password_hash, $reset_request['id']);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Mark token as used
                    $stmt = $db->prepare("
                        UPDATE password_resets SET used = 1, used_at = NOW() 
                        WHERE token = ?
                    ");
                    $stmt->bind_param('s', $token);
                    $stmt->execute();
                    $stmt->close();
                    
                    $success = 'Password has been reset successfully!';
                    $step = 3;
                } else {
                    $errors[] = 'Invalid or expired reset token.';
                }
            } else {
                $errors[] = 'Database connection error.';
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = $_GET['token'] ?? '';
    if (!empty($token)) {
        $step = 2;
    }
}

$page_title = "Forgot Password";
$page_subtitle = $step === 1 ? "Enter your email address" : ($step === 2 ? "Check your email" : "Reset your password");

require_once dirname(dirname(__DIR__)) . '/templates/layout/header.php';
?>

<style>
.forgot-password-container {
    max-width: 450px;
    margin: 0 auto;
    padding: 2rem;
}

.reset-steps {
    display: flex;
    justify-content: center;
    margin-bottom: 2rem;
}

.step {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #e9ecef;
    color: #6c757d;
    font-weight: 600;
    font-size: 0.875rem;
}

.step.active {
    background: #0d6efd;
    color: white;
}

.form-floating {
    margin-bottom: 1rem;
}

.password-strength {
    height: 5px;
    border-radius: 3px;
    margin-top: 0.5rem;
    transition: all 0.3s ease;
}

.strength-weak { background-color: #dc3545; width: 33%; }
.strength-medium { background-color: #ffc107; width: 66%; }
.strength-strong { background-color: #28a745; width: 100%; }

.password-requirements {
    font-size: 0.875rem;
    color: #6c757d;
    margin-top: 0.5rem;
}

.requirement-met { color: #28a745; }
.requirement-not-met { color: #dc3545; }
</style>

<div class="app-shell">
  <?php require_once dirname(dirname(__DIR__)) . '/templates/layout/sidebar.php'; ?>
  <div class="app-content">
    <?php require_once dirname(dirname(__DIR__)) . '/templates/layout/topbar.php'; ?>

    <main class="page-wrap">
      <div class="container-fluid py-4">
        <div class="forgot-password-container">
          <div class="text-center mb-4">
            <h4 class="mb-1"><?= h($page_title) ?></h4>
            <div class="text-muted"><?= h($page_subtitle) ?></div>
            
            <!-- Progress Steps -->
            <div class="reset-steps">
              <div class="step <?= $step === 1 ? 'active' : '' ?>">1</div>
              <div class="step <?= $step === 2 ? 'active' : '' ?>">2</div>
              <div class="step <?= $step === 3 ? 'active' : '' ?>">3</div>
            </div>
          </div>

          <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
              <strong>Error:</strong>
              <ul class="mb-0 mt-2">
                <?php foreach ($errors as $error): ?>
                  <li><?= h($error) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <?php if (!empty($success)): ?>
            <div class="alert alert-success">
              <?= h($success) ?>
            </div>
          <?php endif; ?>

          <?php if ($step === 1): ?>
            <!-- Step 1: Request Reset -->
            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
              <input type="hidden" name="action" value="request_reset">
              
              <div class="form-floating mb-3">
                <input type="email" class="form-control" id="email" name="email" 
                       value="<?= h($email) ?>" placeholder="Enter your email address" required>
                <label for="email">Email Address</label>
              </div>
              
              <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-envelope"></i> Send Reset Link
                </button>
                <a href="<?= h($GLOBALS['BASE_URL']) ?>/login.php" class="btn btn-outline-secondary">
                  <i class="bi bi-arrow-left"></i> Back to Login
                </a>
              </div>
            </form>

          <?php elseif ($step === 2): ?>
            <!-- Step 2: Email Sent -->
            <div class="text-center">
              <div class="mb-4">
                <i class="bi bi-envelope-check text-success" style="font-size: 3rem;"></i>
              </div>
              <h5 class="text-success">Check Your Email</h5>
              <p class="text-muted">
                We've sent a password reset link to:<br>
                <strong><?= h($email) ?></strong>
              </p>
              <p class="text-muted small">
                The link will expire in 1 hour. If you don't receive it, check your spam folder.
              </p>
              <div class="d-grid gap-2">
                <a href="<?= h($GLOBALS['BASE_URL']) ?>/login.php" class="btn btn-primary">
                  <i class="bi bi-arrow-left"></i> Back to Login
                </a>
                <button type="submit" form="resend-form" class="btn btn-outline-secondary">
                  <i class="bi bi-arrow-repeat"></i> Resend Email
                </button>
              </div>
              
              <form method="POST" id="resend-form" style="display: none;">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="request_reset">
                <input type="hidden" name="email" value="<?= h($email) ?>">
              </form>
            </div>

          <?php elseif ($step === 3): ?>
            <!-- Step 3: Reset Password -->
            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
              <input type="hidden" name="action" value="reset_password">
              <input type="hidden" name="token" value="<?= h($token) ?>">
              
              <div class="form-floating mb-3">
                <input type="password" class="form-control" id="new_password" name="new_password" 
                       placeholder="Enter new password" required minlength="6">
                <label for="new_password">New Password</label>
              </div>
              
              <div class="form-floating mb-3">
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                       placeholder="Confirm new password" required minlength="6">
                <label for="confirm_password">Confirm Password</label>
              </div>
              
              <div class="password-strength" id="passwordStrength"></div>
              
              <div class="password-requirements">
                <small>Password must:</small>
                <ul>
                  <li id="req-length" class="requirement-not-met">Be at least 6 characters long</li>
                  <li id="req-match" class="requirement-not-met">Match confirmation password</li>
                </ul>
              </div>
              
              <div class="d-grid gap-2">
                <a href="<?= h($GLOBALS['BASE_URL']) ?>/login.php" class="btn btn-outline-secondary">
                  <i class="bi bi-arrow-left"></i> Back to Login
                </a>
                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-shield-lock"></i> Reset Password
                </button>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>
</div>

<script>
// Password strength checker
document.getElementById('new_password')?.addEventListener('input', checkPasswordStrength);
document.getElementById('confirm_password')?.addEventListener('input', checkPasswordMatch);

function checkPasswordStrength() {
    const password = document.getElementById('new_password').value;
    const strengthBar = document.getElementById('passwordStrength');
    const lengthReq = document.getElementById('req-length');
    
    let strength = 0;
    if (password.length >= 6) strength++;
    if (password.length >= 8) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    
    strengthBar.className = 'password-strength';
    if (strength <= 2) {
        strengthBar.classList.add('strength-weak');
    } else if (strength === 3) {
        strengthBar.classList.add('strength-medium');
    } else {
        strengthBar.classList.add('strength-strong');
    }
    
    // Update requirement indicators
    lengthReq.className = password.length >= 6 ? 'requirement-met' : 'requirement-not-met';
}

function checkPasswordMatch() {
    const password = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const matchReq = document.getElementById('req-match');
    
    matchReq.className = password === confirmPassword && password.length > 0 ? 'requirement-met' : 'requirement-not-met';
}

// Resend email functionality
document.querySelector('button[form="resend-form"]')?.addEventListener('click', function() {
    document.getElementById('resend-form').submit();
});
</script>

<?php require_once dirname(dirname(__DIR__)) . '/templates/layout/footer.php'; ?>
