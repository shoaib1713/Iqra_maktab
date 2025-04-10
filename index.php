<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MAKTAB-E-IQRA</title>
    <link rel="icon" href="assets/images/logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --error-color: #e74a3b;
        }
        
        body {
            background: linear-gradient(135deg, #f8f9fc 0%, #eef1f9 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            overflow: hidden;
            animation: fadeInUp 0.5s;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-card .card-body {
            padding: 2.5rem;
        }
        
        .app-logo {
            width: 70px;
            height: 70px;
            margin-bottom: 1.25rem;
            transition: transform 0.3s;
        }
        
        .app-logo:hover {
            transform: scale(1.05);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--secondary-color);
            font-size: 0.875rem;
        }
        
        .input-group-text {
            background-color: #f8f9fc;
            border-right: none;
            padding: 0.5rem 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
        }
        
        .input-group-text i {
            color: var(--secondary-color);
            font-size: 0.9rem;
        }
        
        .form-control {
            border-left: none;
            font-size: 0.95rem;
            padding: 0.5rem 0.75rem;
            height: 42px;
        }
        
        .form-control:focus {
            box-shadow: none;
            border-color: #d1d3e2;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            box-shadow: 0 0.125rem 0.25rem rgba(78, 115, 223, 0.4);
            padding: 0.75rem 1rem;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background-color: #3a5fc8;
            border-color: #3a5fc8;
        }
        
        .alert-danger {
            background-color: #fadcda;
            border-color: #f8c9c5;
            color: var(--error-color);
        }
        
        .forgot-password {
            color: var(--primary-color);
            transition: color 0.3s;
        }
        
        .forgot-password:hover {
            color: #3a5fc8;
            text-decoration: underline !important;
        }
        
        .form-floating {
            position: relative;
        }
        
        .toggle-password {
            border-left: none;
            width: 40px;
            padding: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fc;
        }
        
        .toggle-password i {
            font-size: 0.9rem;
            color: var(--secondary-color);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5 col-xl-4">
                <div class="login-card card">
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <img src="assets/images/logo.png" alt="MAKTAB-E-IQRA Logo" class="app-logo">
                            <h2 class="fw-bold text-primary mb-2">MAKTAB-E-IQRA</h2>
                            <p class="text-muted">Sign in to your account</p>
                        </div>
                        
                        <form id="loginForm" action="modules/auth.php" method="POST">
                            <div class="mb-4">
                                <label class="form-label">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
                                    <button class="btn toggle-password" type="button" id="togglePassword">
                                        <i class="fas fa-eye" id="toggleIcon"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i> Sign In
                                </button>
                            </div>
                            
                            <div id="error-msg" class="alert alert-danger mt-3 text-center d-none" role="alert"></div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <a href="forgot_password.php" class="forgot-password text-decoration-none">
                                <i class="fas fa-key me-1"></i> Forgot Password?
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4 text-muted small">
                    &copy; <?php echo date('Y'); ?> MAKTAB-E-IQRA. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Toggle password visibility
            $('#togglePassword').click(function() {
                const passwordField = $('#password');
                const toggleIcon = $('#toggleIcon');
                
                if (passwordField.attr('type') === 'password') {
                    passwordField.attr('type', 'text');
                    toggleIcon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    passwordField.attr('type', 'password');
                    toggleIcon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });
            
            // Handle form submission
            $('#loginForm').submit(function(event) {
                event.preventDefault();
                
                // Show loading state
                const submitBtn = $(this).find('button[type="submit"]');
                const originalText = submitBtn.html();
                submitBtn.html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Signing in...');
                submitBtn.prop('disabled', true);
                
                // Hide any previous error
                $('#error-msg').addClass('d-none');

                $.ajax({
                    url: 'modules/auth.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        if (response === 'admin') {
                            window.location.href = 'dashboard.php';
                        } else if (response === 'teacher') {
                            window.location.href = 'teacher_dashboard.php';
                        } else {
                            // Reset button
                            submitBtn.html(originalText);
                            submitBtn.prop('disabled', false);
                            // Show error
                            $('#error-msg').text(response).removeClass('d-none').hide().fadeIn(500);
                        }
                    },
                    error: function() {
                        // Reset button
                        submitBtn.html(originalText);
                        submitBtn.prop('disabled', false);
                        // Show error
                        $('#error-msg').text('An unexpected error occurred. Please try again.').removeClass('d-none');
                    }
                });
            });
        });
    </script>
</body>
</html>
