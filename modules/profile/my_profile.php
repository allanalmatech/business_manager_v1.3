<?php
// modules/profile/my_profile.php
declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/helpers.php';
require_once dirname(dirname(__DIR__)) . '/includes/rbac.php';

require_login();

$db = $GLOBALS['db'] ?? null;
$currentUser = $_SESSION['user'] ?? null;

if (!$currentUser || !$db instanceof mysqli) {
    header('Location: ' . $GLOBALS['BASE_URL'] . '/login.php');
    exit;
}

$page_title = "My Profile";
$page_subtitle = "Manage your personal information";

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    $errors = [];
    
    if (empty($fullName)) {
        $errors[] = "Full name is required";
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    
    if (empty($errors)) {
        $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("sssi", $fullName, $email, $phone, $currentUser['id']);
        $stmt->execute();
        $stmt->close();
        
        // Update session
        $_SESSION['user']['full_name'] = $fullName;
        $_SESSION['user']['email'] = $email;
        $_SESSION['user']['phone'] = $phone;
        
        $success = "Profile updated successfully!";
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    $passwordErrors = [];
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $passwordErrors[] = "All password fields are required";
    }
    
    if ($newPassword !== $confirmPassword) {
        $passwordErrors[] = "New passwords do not match";
    }
    
    if (strlen($newPassword) < 6) {
        $passwordErrors[] = "Password must be at least 6 characters";
    }
    
    if (empty($passwordErrors)) {
        // Verify current password
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->bind_param("i", $currentUser['id']);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($result && password_verify($currentPassword, $result['password_hash'])) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $newHash, $currentUser['id']);
            $stmt->execute();
            $stmt->close();
            
            $passwordSuccess = "Password changed successfully!";
        } else {
            $passwordErrors[] = "Current password is incorrect";
        }
    }
}

// Handle profile photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photo'])) {
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_photo'];
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            $photoErrors[] = "Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed";
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $photoErrors[] = "File size too large. Maximum 2MB allowed";
        } else {
            // Create uploads directory if it doesn't exist
            $uploadDir = __DIR__ . '/../../uploads/profiles/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'user_' . $currentUser['id'] . '_' . time() . '.' . $extension;
            $filepath = $uploadDir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Delete old photo if exists
                $stmt = $db->prepare("SELECT profile_photo FROM users WHERE id = ?");
                $stmt->bind_param("i", $currentUser['id']);
                $stmt->execute();
                $oldPhoto = $stmt->get_result()->fetch_assoc()['profile_photo'];
                $stmt->close();
                
                if ($oldPhoto && file_exists(__DIR__ . '/../../' . $oldPhoto)) {
                    unlink(__DIR__ . '/../../' . $oldPhoto);
                }
                
                // Update database
                $photoPath = 'uploads/profiles/' . $filename;
                $stmt = $db->prepare("UPDATE users SET profile_photo = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("si", $photoPath, $currentUser['id']);
                $stmt->execute();
                $stmt->close();
                
                // Update session
                $_SESSION['user']['profile_photo'] = $photoPath;
                
                $photoSuccess = "Profile photo updated successfully!";
            } else {
                $photoErrors[] = "Failed to upload photo";
            }
        }
    } else {
        $photoErrors[] = "Please select a photo to upload";
    }
}

// Get current user data
$stmt = $db->prepare("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
$stmt->bind_param("i", $currentUser['id']);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$stmt->close();

require_once dirname(dirname(__DIR__)) . '/templates/layout/header.php';
?>
<div class="app-shell">
  <?php require_once dirname(dirname(__DIR__)) . '/templates/layout/sidebar.php'; ?>

  <div class="app-content">
    <?php require_once dirname(dirname(__DIR__)) . '/templates/layout/topbar.php'; ?>

    <main class="page-wrap">
      <div class="container-fluid py-4">
        <div class="row">
          <div class="col-lg-4">
            <!-- Profile Card -->
            <div class="card shadow-sm">
              <div class="card-body text-center">
                <div class="mb-3">
                  <?php if (!empty($userData['profile_photo'])): ?>
                    <img src="<?= h($GLOBALS['BASE_URL'] . '/' . $userData['profile_photo']) ?>" 
                         class="rounded-circle" 
                         style="width: 120px; height: 120px; object-fit: cover; border: 3px solid #0d6efd;">
                  <?php else: ?>
                    <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center" 
                         style="width: 120px; height: 120px; margin: 0 auto;">
                      <i class="bi bi-person text-white" style="font-size: 48px;"></i>
                    </div>
                  <?php endif; ?>
                </div>
                
                <h5 class="card-title mb-1"><?= h($userData['full_name']) ?></h5>
                <p class="text-muted mb-2"><?= h($userData['role_name']) ?></p>
                <p class="text-muted small mb-3">
                  <i class="bi bi-envelope me-1"></i> <?= h($userData['email'] ?? 'Not set') ?><br>
                  <i class="bi bi-phone me-1"></i> <?= h($userData['phone'] ?? 'Not set') ?>
                </p>
                
                <form method="post" enctype="multipart/form-data" class="mb-3">
                  <div class="input-group input-group-sm">
                    <input type="file" name="profile_photo" class="form-control" accept="image/*" id="photoInput">
                    <button class="btn btn-outline-primary" type="submit" name="upload_photo">
                      <i class="bi bi-upload"></i> Upload
                    </button>
                  </div>
                  <?php if (isset($photoErrors)): ?>
                    <div class="alert alert-danger alert-sm mt-2">
                      <?php foreach ($photoErrors as $error): ?>
                        <small><?= h($error) ?></small><br>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                  <?php if (isset($photoSuccess)): ?>
                    <div class="alert alert-success alert-sm mt-2">
                      <small><?= h($photoSuccess) ?></small>
                    </div>
                  <?php endif; ?>
                </form>
                
                <div class="text-muted small">
                  <i class="bi bi-calendar me-1"></i> Member since: <?= date('M j, Y', strtotime($userData['created_at'])) ?><br>
                  <?php if ($userData['last_login_at']): ?>
                    <i class="bi bi-clock me-1"></i> Last login: <?= date('M j, Y g:i A', strtotime($userData['last_login_at'])) ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
          
          <div class="col-lg-8">
            <!-- Personal Information -->
            <div class="card shadow-sm mb-4">
              <div class="card-header">
                <h6 class="card-title mb-0">
                  <i class="bi bi-person me-2"></i>Personal Information
                </h6>
              </div>
              <div class="card-body">
                <?php if (isset($success)): ?>
                  <div class="alert alert-success">
                    <?= h($success) ?>
                  </div>
                <?php endif; ?>
                
                <form method="post">
                  <div class="row">
                    <div class="col-md-6">
                      <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="full_name" 
                               value="<?= h($userData['full_name']) ?>" required>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" 
                               value="<?= h($userData['email']) ?>">
                      </div>
                    </div>
                  </div>
                  
                  <div class="row">
                    <div class="col-md-6">
                      <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" class="form-control" name="phone" 
                               value="<?= h($userData['phone']) ?>">
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="<?= h($userData['username']) ?>" readonly>
                        <small class="text-muted">Username cannot be changed</small>
                      </div>
                    </div>
                  </div>
                  
                  <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Update Profile
                  </button>
                </form>
              </div>
            </div>
            
            <!-- Change Password -->
            <div class="card shadow-sm">
              <div class="card-header">
                <h6 class="card-title mb-0">
                  <i class="bi bi-shield-lock me-2"></i>Change Password
                </h6>
              </div>
              <div class="card-body">
                <?php if (isset($passwordSuccess)): ?>
                  <div class="alert alert-success">
                    <?= h($passwordSuccess) ?>
                  </div>
                <?php endif; ?>
                
                <?php if (isset($passwordErrors)): ?>
                  <div class="alert alert-danger">
                    <?php foreach ($passwordErrors as $error): ?>
                      <?= h($error) ?><br>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <!-- add method for the form to work -->
                <form>
                  <input type="hidden" name="change_password" value="1">
                  
                  <div class="row">
                    <div class="col-md-4">
                      <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" class="form-control" name="current_password" required>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" class="form-control" name="new_password" required minlength="6">
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" name="confirm_password" required minlength="6">
                      </div>
                    </div>
                  </div>
                  
                  <p class="small-text">This is disabled for now</p>
                  <button type="submit" class="btn btn-warning" disabled="true">
                    <i class="bi bi-shield-check me-1"></i> Change Password
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<?php require_once dirname(dirname(__DIR__)) . '/templates/layout/footer.php'; ?>

<script>
document.getElementById('photoInput')?.addEventListener('change', function(e) {
  const file = e.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = function(e) {
      // You could add a preview here if needed
    };
    reader.readAsDataURL(file);
  }
});
</script>