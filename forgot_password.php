<!-- Forgot Password Page -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - MAKTAB-E-IQRA</title>
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
                            <h2 class="fw-bold">Forgot Password</h2>
                            <p class="text-muted">Enter your email to reset your password</p>
                        </div>
                        
                        <form id="forgotPasswordForm" action="modules/forgot_password.php" method="POST">
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" name="email" class="form-control" placeholder="Enter your registered email" required>
                                </div>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-key me-1"></i> Reset Password
                                </button>
                            </div>
                            
                            <div id="forgot-msg" class="alert alert-danger mt-3 text-center d-none"></div>
                            
                            <div class="text-center">
                                <a href="index.php" class="text-decoration-none">
                                    <i class="fas fa-arrow-left me-1"></i> Back to Login
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="text-center mt-3 text-muted small">
                    &copy; <?php echo date('Y'); ?> MAKTAB-E-IQRA. All rights reserved.
                </div>
            </div>
        </div>
    </div>
    
    <script>
        $(document).ready(function() {
            $('#forgotPasswordForm').submit(function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'modules/forgot_password.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        if (response.includes("success")) {
                            $('#forgot-msg').removeClass('alert-danger').addClass('alert-success').text('Password reset instructions sent to your email.').removeClass('d-none');
                        } else {
                            $('#forgot-msg').text(response).removeClass('d-none');
                        }
                    },
                    error: function() {
                        $('#forgot-msg').text('An error occurred. Please try again later.').removeClass('d-none');
                    }
                });
            });
        });
    </script>
</body>
</html>
