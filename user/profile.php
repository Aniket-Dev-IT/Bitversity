<?php
require_once __DIR__ . '/../includes/config.php';
requireAuth();

$user = getCurrentUser();
$message = '';
$messageType = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_profile';
    
    if ($action === 'upload_photo') {
        // Handle photo upload
        $uploadDir = 'uploads/users/photos/';
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_photo'];
            $fileType = $file['type'];
            $fileSize = $file['size'];
            $fileName = $file['name'];
            $tmpName = $file['tmp_name'];
            
            // Validate file type
            if (!in_array($fileType, $allowedTypes)) {
                $message = 'Invalid file type. Please upload a JPEG, PNG, or GIF image.';
                $messageType = 'danger';
            } elseif ($fileSize > $maxFileSize) {
                $message = 'File too large. Maximum size is 5MB.';
                $messageType = 'danger';
            } else {
                // Generate unique filename
                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                $newFileName = 'user_' . $user['id'] . '_' . time() . '.' . $fileExtension;
                $uploadPath = $uploadDir . $newFileName;
                
                // Create directory if it doesn't exist
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Move uploaded file
                if (move_uploaded_file($tmpName, $uploadPath)) {
                    // Delete old photo if exists
                    if ($user['profile_photo'] && file_exists($uploadDir . $user['profile_photo'])) {
                        unlink($uploadDir . $user['profile_photo']);
                    }
                    
                    // Update database
                    $stmt = $db->prepare("UPDATE users SET profile_photo = ?, updated_at = NOW() WHERE id = ?");
                    if ($stmt->execute([$newFileName, $user['id']])) {
                        $message = 'Profile photo updated successfully!';
                        $messageType = 'success';
                        $user = getCurrentUser(); // Refresh user data
                    } else {
                        $message = 'Error updating profile photo in database.';
                        $messageType = 'danger';
                        unlink($uploadPath); // Clean up uploaded file
                    }
                } else {
                    $message = 'Error uploading file. Please try again.';
                    $messageType = 'danger';
                }
            }
        } else {
            $message = 'Please select a photo to upload.';
            $messageType = 'danger';
        }
    } elseif ($action === 'remove_photo') {
        // Handle photo removal
        if ($user['profile_photo']) {
            $photoPath = 'uploads/users/photos/' . $user['profile_photo'];
            if (file_exists($photoPath)) {
                unlink($photoPath);
            }
            
            $stmt = $db->prepare("UPDATE users SET profile_photo = NULL, updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$user['id']])) {
                $message = 'Profile photo removed successfully!';
                $messageType = 'success';
                $user = getCurrentUser(); // Refresh user data
            } else {
                $message = 'Error removing profile photo.';
                $messageType = 'danger';
            }
        }
    } else {
        // Handle regular profile update
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $country = trim($_POST['country'] ?? '');
    
    $errors = [];
    
    // Validate required fields
    if (empty($firstName)) $errors[] = 'First name is required';
    if (empty($lastName)) $errors[] = 'Last name is required';
    if (empty($email)) $errors[] = 'Email is required';
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    // Check if email is taken by another user
    if ($email !== $user['email']) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user['id']]);
        if ($stmt->fetch()) {
            $errors[] = 'This email is already taken by another user';
        }
    }
    
    // Handle password change
    $updatePassword = false;
    if (!empty($newPassword)) {
        if (empty($currentPassword)) {
            $errors[] = 'Current password is required to change password';
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $errors[] = 'Current password is incorrect';
        } elseif (strlen($newPassword) < 6) {
            $errors[] = 'New password must be at least 6 characters long';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'New password and confirmation do not match';
        } else {
            $updatePassword = true;
        }
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            if ($updatePassword) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, password = ?, phone = ?, address = ?, city = ?, country = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$firstName, $lastName, $email, $hashedPassword, $phone, $address, $city, $country, $user['id']]);
            } else {
                $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, city = ?, country = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$firstName, $lastName, $email, $phone, $address, $city, $country, $user['id']]);
            }
            
            $db->commit();
            $message = 'Profile updated successfully!';
            $messageType = 'success';
            
            // Refresh user data
            $user = getCurrentUser();
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Error updating profile: ' . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        $message = implode('<br>', $errors);
        $messageType = 'danger';
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo APP_NAME; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    
    <style>
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .profile-avatar-container {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 1rem;
            cursor: pointer;
        }
        
        .profile-avatar-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }
        
        .profile-avatar-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5rem;
            border: 4px solid rgba(255, 255, 255, 0.3);
        }
        
        .profile-photo-overlay {
            position: absolute;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .profile-avatar-container:hover .profile-photo-overlay {
            opacity: 1;
        }
        
        .current-photo-preview {
            width: 200px;
            height: 200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .current-photo-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .no-photo-placeholder {
            width: 100%;
            height: 100%;
            background: #f8f9fa;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .preview-container img {
            max-width: 100%;
            max-height: 200px;
            object-fit: cover;
        }
        
        .profile-card {
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border: none;
            border-radius: 15px;
        }
        
        .form-section {
            border-bottom: 1px solid #eee;
            padding-bottom: 2rem;
            margin-bottom: 2rem;
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .section-title {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        
        .password-toggle {
            cursor: pointer;
        }
    </style>
</head>
<body data-logged-in="true">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <!-- Profile Header -->
    <div class="profile-header">
        <div class="container">
            <div class="row">
                <div class="col-md-4 text-center">
                    <div class="profile-avatar-container">
                        <?php if ($user['profile_photo']): ?>
                            <img src="<?php echo BASE_PATH; ?>/<?php echo htmlspecialchars($user['profile_photo']); ?>" 
                                 alt="Profile Photo" class="profile-avatar-image">
                        <?php else: ?>
                            <div class="profile-avatar-placeholder">
                                <i class="fas fa-user"></i>
                            </div>
                        <?php endif; ?>
                        <div class="profile-photo-overlay">
                            <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#photoModal">
                                <i class="fas fa-camera me-1"></i>Change Photo
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <h1 class="mb-2"><?php echo sanitize($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                    <p class="mb-1"><i class="fas fa-envelope me-2"></i><?php echo sanitize($user['email']); ?></p>
                    <p class="mb-1"><i class="fas fa-calendar me-2"></i>Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
                    <?php if ($user['phone']): ?>
                        <p class="mb-0"><i class="fas fa-phone me-2"></i><?php echo sanitize($user['phone']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container pb-5">
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card profile-card">
                    <div class="card-body p-4">
                        <h3 class="card-title mb-4">
                            <i class="fas fa-user-edit text-primary me-2"></i>Edit Profile
                        </h3>
                        
                        <form method="POST" action="">
                            <!-- Personal Information -->
                            <div class="form-section">
                                <h5 class="section-title">
                                    <i class="fas fa-user me-2"></i>Personal Information
                                </h5>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="first_name" class="form-label">First Name *</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               value="<?php echo sanitize($user['first_name']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="last_name" class="form-label">Last Name *</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               value="<?php echo sanitize($user['last_name']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo sanitize($user['email']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo sanitize($user['phone'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <!-- Address Information -->
                            <div class="form-section">
                                <h5 class="section-title">
                                    <i class="fas fa-map-marker-alt me-2"></i>Address Information
                                </h5>
                                
                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo sanitize($user['address'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="city" class="form-label">City</label>
                                        <input type="text" class="form-control" id="city" name="city" 
                                               value="<?php echo sanitize($user['city'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="country" class="form-label">Country</label>
                                        <input type="text" class="form-control" id="country" name="country" 
                                               value="<?php echo sanitize($user['country'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Password Change -->
                            <div class="form-section">
                                <h5 class="section-title">
                                    <i class="fas fa-lock me-2"></i>Change Password
                                </h5>
                                <p class="text-muted mb-3">Leave password fields empty if you don't want to change your password.</p>
                                
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="current_password" name="current_password">
                                        <button class="btn btn-outline-secondary password-toggle" type="button" data-target="current_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="new_password" name="new_password">
                                            <button class="btn btn-outline-secondary password-toggle" type="button" data-target="new_password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">Minimum 6 characters</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                            <button class="btn btn-outline-secondary password-toggle" type="button" data-target="confirm_password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary btn-lg px-4">
                                    <i class="fas fa-save me-2"></i>Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Photo Upload Modal -->
    <div class="modal fade" id="photoModal" tabindex="-1" aria-labelledby="photoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="photoModalLabel">
                        <i class="fas fa-camera me-2"></i>Profile Photo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Current Photo -->
                    <div class="text-center mb-4">
                        <div class="current-photo-preview">
                            <?php if ($user['profile_photo']): ?>
                                <img src="<?php echo BASE_PATH; ?>/<?php echo htmlspecialchars($user['profile_photo']); ?>" 
                                     alt="Current Profile Photo" class="current-photo-img">
                            <?php else: ?>
                                <div class="no-photo-placeholder">
                                    <i class="fas fa-user fa-4x text-muted"></i>
                                    <p class="text-muted mt-2">No photo uploaded</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Upload Form -->
                    <form method="POST" enctype="multipart/form-data" id="photoUploadForm">
                        <input type="hidden" name="action" value="upload_photo">
                        
                        <div class="mb-3">
                            <label for="profile_photo" class="form-label">Choose New Photo</label>
                            <input type="file" class="form-control" id="profile_photo" name="profile_photo" 
                                   accept="image/jpeg,image/jpg,image/png,image/gif" required>
                            <div class="form-text">
                                Maximum file size: 5MB. Supported formats: JPEG, PNG, GIF
                            </div>
                        </div>
                        
                        <div class="preview-container mb-3" style="display: none;">
                            <img id="imagePreview" src="" alt="Preview" class="img-fluid rounded">
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-fill">
                                <i class="fas fa-upload me-2"></i>Upload Photo
                            </button>
                            
                            <?php if ($user['profile_photo']): ?>
                            <button type="button" class="btn btn-outline-danger" id="removePhotoBtn">
                                <i class="fas fa-trash me-1"></i>Remove
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                    
                    <!-- Remove Photo Form -->
                    <?php if ($user['profile_photo']): ?>
                    <form method="POST" id="removePhotoForm" style="display: none;">
                        <input type="hidden" name="action" value="remove_photo">
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password toggle functionality
        document.querySelectorAll('.password-toggle').forEach(function(button) {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const icon = this.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const currentPassword = document.getElementById('current_password').value;
            
            if (newPassword && !currentPassword) {
                e.preventDefault();
                alert('Please enter your current password to change your password.');
                return false;
            }
            
            if (newPassword && newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New password and confirmation password do not match.');
                return false;
            }
            
            if (newPassword && newPassword.length < 6) {
                e.preventDefault();
                alert('New password must be at least 6 characters long.');
                return false;
            }
        });
        
        // Photo upload functionality
        document.getElementById('profile_photo')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('imagePreview');
                    const container = document.querySelector('.preview-container');
                    preview.src = e.target.result;
                    container.style.display = 'block';
                };
                reader.readAsDataURL(file);
                
                // Validate file size
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB');
                    e.target.value = '';
                    document.querySelector('.preview-container').style.display = 'none';
                    return;
                }
                
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Please select a JPEG, PNG, or GIF image file');
                    e.target.value = '';
                    document.querySelector('.preview-container').style.display = 'none';
                    return;
                }
            }
        });
        
        // Remove photo functionality
        document.getElementById('removePhotoBtn')?.addEventListener('click', function() {
            if (confirm('Are you sure you want to remove your profile photo?')) {
                document.getElementById('removePhotoForm').submit();
            }
        });
        
        // Enhanced form styling
        document.querySelectorAll('.form-control').forEach(function(input) {
            input.addEventListener('focus', function() {
                this.closest('.mb-3').classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                this.closest('.mb-3').classList.remove('focused');
            });
        });
    </script>
    
    <style>
        .mb-3.focused .form-label {
            color: #667eea;
            font-weight: 600;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .form-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: none;
        }
        
        .section-title {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #eee;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
    </style>
</body>
</html>
