<?php
// modules/profile/change_password.php
declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/helpers.php';
require_once dirname(dirname(__DIR__)) . '/includes/rbac.php';

require_login();

$db = $GLOBALS['db'] ?? null;
$BASE = $GLOBALS['BASE_URL'] ?? '';
$currentUser = $_SESSION['user'] ?? null;

if (!$currentUser || !$db instanceof mysqli) {
    header('Location: ' . $GLOBALS['BASE_URL'] . '/login.php');
    exit;
}

$page_title = "Change Password";
$page_subtitle = "Update your account password";

$errors = [];
$success = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($currentPassword)) {
            $errors[] = 'Current password is required.';
        }
        
        if (empty($newPassword)) {
            $errors[] = 'New password is required.';
        } elseif (strlen($newPassword) < 6) {
            $errors[] = 'New password must be at least 6 characters long.';
        }
        
        if (empty($confirmPassword)) {
            $errors[] = 'Please confirm your new password.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'New passwords do not match.';
        }
        
        if (empty($errors)) {
            try {
                // Verify current password
                $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
                $stmt->bind_param('i', $currentUser['id']);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                if (!$result || !password_verify($currentPassword, $result['password_hash'])) {
                    $errors[] = 'Current password is incorrect.';
                } else {
                    // Update password
                    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    
                    $stmt = $db->prepare("UPDATE users SET password_hash = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?");
                    $stmt->bind_param('si', $newPasswordHash, $currentUser['id']);
                    
                    if ($stmt->execute()) {
                        $success = 'Password changed successfully!';
                        
                        // Clear any password change flags
                        unset($_SESSION['user']['must_change_password']);
                    } else {
                        $errors[] = 'Failed to update password. Please try again.';
                    }
                    
                    $stmt->close();
                }
                
            } catch (Exception $e) {
                $errors[] = 'Error: ' . $e->getMessage();
            }
        }
    }
}

require_once dirname(dirname(__DIR__)) . '/templates/layout/header.php';
?>

<style>
.change-password-container {
    max-width: 600px;
    margin: 0 auto;
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

.password-requirements ul {
    margin: 0.25rem 0;
    padding-left: 1.25rem;
}

.requirement-met {
    color: #28a745;
}

.requirement-not-met {
    color: #dc3545;
}
</style>

<div class="app-shell">
  <?php require_once dirname(dirname(__DIR__)) . '/templates/layout/sidebar.php'; ?>
  <div class="app-content">
    <?php require_once dirname(dirname(__DIR__)) . '/templates/layout/topbar.php'; ?>

    <main class="page-wrap">
      <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
          <div>
            <h4 class="mb-1"><?= h($page_title) ?></h4>
            <div class="text-muted small"><?= h($page_subtitle) ?></div>
          </div>

          <div class="d-flex gap-2 flex-wrap">
           <!-- <a href="<?= h($BASE) ?>/modules/profile/my_profile.php" class="btn btn-outline-secondary">
              <i class="bi bi-arrow-left"></i> Back to Profile
            </a> -->
          </div>
        </div>

        <div class="change-password-container">
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

          <?php if ($success): ?>
            <div class="alert alert-success">
              <?= h($success) ?>
            </div>
          <?php endif; ?>

          <div class="card">
            <div class="card-body">
              <form method="POST" id="changePasswordForm">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                <div class="mb-3">
                  <label for="current_password" class="form-label">Current Password *</label>
                  <div class="input-group">
                    <input type="password" class="form-control" id="current_password" 
                           name="current_password" required 
                           placeholder="Enter your current password">
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                      <i class="bi bi-eye" id="current_password_icon"></i>
                    </button>
                  </div>
                </div>

                <div class="mb-3">
                  <label for="new_password" class="form-label">New Password *</label>
                  <div class="input-group">
                    <input type="password" class="form-control" id="new_password" 
                           name="new_password" required 
                           placeholder="Enter your new password"
                           oninput="checkPasswordStrength()">
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                      <i class="bi bi-eye" id="new_password_icon"></i>
                    </button>
                  </div>
                  <div class="password-strength" id="passwordStrength" style="display: none;"></div>
                  <div class="password-requirements">
                    <small>Password must:</small>
                    <ul>
                      <li id="req-length" class="requirement-not-met">Be at least 6 characters long</li>
                      <li id="req-match" class="requirement-not-met">Match confirmation password</li>
                    </ul>
                  </div>
                </div>

                <div class="mb-4">
                  <label for="confirm_password" class="form-label">Confirm New Password *</label>
                  <div class="input-group">
                    <input type="password" class="form-control" id="confirm_password" 
                           name="confirm_password" required 
                           placeholder="Confirm your new password"
                           oninput="checkPasswordMatch()">
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                      <i class="bi bi-eye" id="confirm_password_icon"></i>
                    </button>
                  </div>
                </div>
                <p class="small-text">This is disabled for now</p>
                <div class="d-flex gap-2">
                    <!--- Button disabled for demo -->
                    
                  <button type="submit" class="btn btn-primary" id="submitBtn" disabled="true">
                    <i class="bi bi-shield-lock"></i> Change Password
                  </button>
                  <a href="<?= h($BASE) ?>/index.php" class="btn btn-outline-secondary">
                    Cancel
                  </a>
                </div>
              </form>
            </div>
          </div>

          <div class="alert alert-info mt-4">
            <h6 class="alert-heading">
              <i class="bi bi-info-circle"></i> Security Tips
            </h6>
            <ul class="mb-0">
              <li>Choose a strong password with letters, numbers, and symbols</li>
              <li>Don't reuse passwords from other accounts</li>
              <li>Change your password regularly</li>
              <li>Never share your password with anyone</li>
            </ul>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '_icon');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        field.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

function checkPasswordStrength() {
    const password = document.getElementById('new_password').value;
    const strengthBar = document.getElementById('passwordStrength');
    const lengthReq = document.getElementById('req-length');
    
    // Show strength bar
    strengthBar.style.display = 'block';
    
    // Check length
    if (password.length >= 6) {
        lengthReq.className = 'requirement-met';
    } else {
        lengthReq.className = 'requirement-not-met';
    }
    
    // Calculate strength
    let strength = 0;
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
    if (password.match(/[0-9]/)) strength++;
    if (password.match(/[^a-zA-Z0-9]/)) strength++;
    
    // Update strength bar
    strengthBar.className = 'password-strength';
    if (strength <= 1) {
        strengthBar.classList.add('strength-weak');
    } else if (strength === 2) {
        strengthBar.classList.add('strength-medium');
    } else {
        strengthBar.classList.add('strength-strong');
    }
    
    checkPasswordMatch();
}

function checkPasswordMatch() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const matchReq = document.getElementById('req-match');
    
    if (confirmPassword && newPassword === confirmPassword) {
        matchReq.className = 'requirement-met';
    } else {
        matchReq.className = 'requirement-not-met';
    }
}

// Form validation
document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('New passwords do not match. Please check and try again.');
        return false;
    }
    
    if (newPassword.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long.');
        return false;
    }
    
    // Disable submit button to prevent double submission
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Updating...';
});

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    checkPasswordStrength();
});
</script>

<?php require_once dirname(dirname(__DIR__)) . '/templates/layout/footer.php'; ?>