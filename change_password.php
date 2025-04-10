<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$role = $_SESSION['role'];
$page_title = "Change Password";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/logo.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Page Content -->
        <div id="page-content-wrapper">
            <!-- Navbar -->
            <?php include 'includes/navbar.php'; ?>
            
            <div class="container-fluid px-4">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold text-primary">
                                    <i class="fas fa-key me-2"></i> Change Password
                                </h5>
                            </div>
                            <div class="card-body">
                                <form id="changePasswordForm">
                                    <div class="mb-4">
                                        <label class="form-label">Current Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="current_password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label class="form-label">New Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">Password must be at least 8 characters long.</div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label class="form-label">Confirm New Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-check-double"></i></span>
                                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div id="passwordError" class="alert alert-danger d-none"></div>
                                    <div id="passwordSuccess" class="alert alert-success d-none">Password changed successfully!</div>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="<?php echo $role === 'admin' ? 'dashboard.php' : 'teacher_dashboard.php'; ?>" class="btn btn-light">
                                            <i class="fas fa-arrow-left me-1"></i> Cancel
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i> Update Password
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/notification_styles.php'; ?>
    <?php include 'includes/notification_scripts.php'; ?>
    <script>
        $(document).ready(function() {
            // Sidebar toggle
            $('#menu-toggle').click(function(e) {
                e.preventDefault();
                $('#wrapper').toggleClass('toggled');
            });
            
            // Toggle password visibility
            $('.toggle-password').click(function() {
                const targetId = $(this).data('target');
                const passwordInput = $('#' + targetId);
                const icon = $(this).find('i');
                
                if (passwordInput.attr('type') === 'password') {
                    passwordInput.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    passwordInput.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });
            
            // Form submission
            $('#changePasswordForm').submit(function(e) {
                e.preventDefault();
                
                const currentPassword = $('#current_password').val();
                const newPassword = $('#new_password').val();
                const confirmPassword = $('#confirm_password').val();
                
                // Basic client-side validation
                if (newPassword.length < 8) {
                    $('#passwordError').text('New password must be at least 8 characters long.').removeClass('d-none');
                    return;
                }
                
                if (newPassword !== confirmPassword) {
                    $('#passwordError').text('New passwords do not match.').removeClass('d-none');
                    return;
                }
                
                // Hide any previous error messages
                $('#passwordError').addClass('d-none');
                $('#passwordSuccess').addClass('d-none');
                
                // Submit via AJAX
                $.ajax({
                    url: 'modules/change_password.php',
                    type: 'POST',
                    data: {
                        current_password: currentPassword,
                        new_password: newPassword,
                        confirm_password: confirmPassword
                    },
                    success: function(response) {
                        if (response === 'success') {
                            $('#passwordSuccess').removeClass('d-none');
                            $('#changePasswordForm')[0].reset();
                            
                            // Scroll to the success message
                            $('html, body').animate({
                                scrollTop: $('#passwordSuccess').offset().top - 100
                            }, 200);
                            
                            // Hide success message after 5 seconds
                            setTimeout(function() {
                                $('#passwordSuccess').addClass('d-none');
                            }, 5000);
                        } else {
                            $('#passwordError').text(response).removeClass('d-none');
                            
                            // Scroll to the error message
                            $('html, body').animate({
                                scrollTop: $('#passwordError').offset().top - 100
                            }, 200);
                        }
                    },
                    error: function() {
                        $('#passwordError').text('An error occurred. Please try again.').removeClass('d-none');
                    }
                });
            });
        });
    </script>
</body>
</html> 