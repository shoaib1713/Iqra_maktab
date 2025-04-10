<?php
session_start();
require 'config/db.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';
$validToken = false;
$tokenExpired = false;
$userId = null;

if (!empty($token)) {
    // Verify token
    $stmt = $conn->prepare("SELECT user_id, expires FROM password_reset WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($userId, $expires);
    $stmt->fetch();
    
    if ($stmt->num_rows == 1) {
        $currentTime = new DateTime();
        $expiryTime = new DateTime($expires);
        
        if ($currentTime <= $expiryTime) {
            $validToken = true;
        } else {
            $tokenExpired = true;
        }
    }
    $stmt->close();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['password']) && isset($_POST['token'])) {
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $token = $_POST['token'];
    
    // Validate password
    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        // Verify token again
        $stmt = $conn->prepare("SELECT user_id, expires FROM password_reset WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($userId, $expires);
        $stmt->fetch();
        
        if ($stmt->num_rows == 1) {
            $currentTime = new DateTime();
            $expiryTime = new DateTime($expires);
            
            if ($currentTime <= $expiryTime) {
                // Update password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updateStmt->bind_param("si", $hashedPassword, $userId);
                $updateStmt->execute();
                
                if ($updateStmt->affected_rows > 0) {
                    // Delete used token
                    $deleteStmt = $conn->prepare("DELETE FROM password_reset WHERE token = ?");
                    $deleteStmt->bind_param("s", $token);
                    $deleteStmt->execute();
                    $deleteStmt->close();
                    
                    // Log the password change
                    $logStmt = $conn->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, 'password_reset', 'Password reset successful')");
                    $logStmt->bind_param("i", $userId);
                    $logStmt->execute();
                    $logStmt->close();
                    
                    $success = "Password has been reset successfully. You can now <a href='index.php'>login</a>.";
                } else {
                    $error = "Failed to update password. Please try again.";
                }
                $updateStmt->close();
            } else {
                $error = "Token has expired. Please request a new password reset.";
            }
        } else {
            $error = "Invalid token. Please request a new password reset.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - MAKTAB-E-IQRA</title>
    <link rel="icon" href="assets/images/logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="d-flex justify-content-center align-items-center min-vh-100 bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-card card shadow fade-in">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <img src="assets/images/logo.png" alt="Logo" class="img-fluid mb-3" width="80">
                            <h2 class="fw-bold">Reset Password</h2>
                            <p class="text-muted">Create a new password for your account</p>
                        </div>
                        
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success mb-4" role="alert">
                                <?php echo $success; ?>
                            </div>
                        <?php elseif (isset($error)): ?>
                            <div class="alert alert-danger mb-4" role="alert">
                                <?php echo $error; ?>
                            </div>
                        <?php elseif ($tokenExpired): ?>
                            <div class="alert alert-danger mb-4" role="alert">
                                This password reset link has expired. Please request a new one from the <a href="forgot_password.php">forgot password</a> page.
                            </div>
                        <?php elseif (!$validToken && empty($success)): ?>
                            <div class="alert alert-danger mb-4" role="alert">
                                Invalid or expired password reset link. Please request a new one from the <a href="forgot_password.php">forgot password</a> page.
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($validToken && !isset($success)): ?>
                            <form action="reset_password.php" method="POST">
                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" name="password" class="form-control" placeholder="Enter new password" required>
                                    </div>
                                    <div class="form-text">Password must be at least 8 characters long.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Confirm Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required>
                                    </div>
                                </div>
                                
                                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                                
                                <div class="d-grid mb-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Reset Password
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                        
                        <div class="text-center">
                            <a href="index.php" class="text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i> Back to Login
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-3 text-muted small">
                    &copy; <?php echo date('Y'); ?> MAKTAB-E-IQRA. All rights reserved.
                </div>
            </div>
        </div>
    </div>
</body>
</html> 