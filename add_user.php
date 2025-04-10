<?php
session_start();
require_once 'config.php';
require 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$role = $_SESSION['role'];

// Check if user has administrator rights
if ($role != 'admin') {
    header("Location: dashboard.php");
    exit();
}

$success_message = "";
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $phone = $_POST['phone'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Check if email already exists
    $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ? AND is_deleted = 0");
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    $checkEmail->store_result();

    if ($checkEmail->num_rows > 0) {
        $error_message = "Email already exists! Please use a different email.";
    } else {
        // Insert new user with is_active field
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, phone, is_active, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssiii", $name, $email, $password, $role, $phone, $is_active, $user_id);

        if ($stmt->execute()) {
            $success_message = "User added successfully!";
            // Clear form data after successful submission
            $name = $email = $phone = "";
            $role = "admin";
            $is_active = 1;
        } else {
            $error_message = "Error adding user: " . $stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/logo.png">
    <style>
        .required-field::after {
            content: "*";
            color: red;
            margin-left: 4px;
        }
        .password-field {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
        }
        .card {
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4 rounded">
                <div class="container-fluid">
                    <button class="btn" id="menu-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <span class="navbar-brand ms-2">Add New User</span>
                    <div class="d-flex align-items-center">
                        <div class="dropdown">
                            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="me-2"><i class="fas fa-user-circle fs-5"></i> <?php echo $user_name; ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="dropdownUser">
                                <li><a class="dropdown-item" href="modules/logout.php">Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>
            
            <div class="container-fluid px-4">
                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-lg-8 col-md-10 mx-auto">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold text-primary">
                                    <i class="fas fa-user-plus me-2"></i> Create New User Account
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="addUserForm" class="needs-validation" novalidate>
                                    <div class="row g-3">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label required-field">Full Name</label>
                                            <input type="text" name="name" class="form-control" placeholder="Enter full name" required value="<?php echo $name ?? ''; ?>">
                                            <div class="invalid-feedback">
                                                Please provide a valid name.
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label required-field">Email Address</label>
                                            <input type="email" name="email" class="form-control" placeholder="Enter email address" required value="<?php echo $email ?? ''; ?>">
                                            <div class="invalid-feedback">
                                                Please provide a valid email address.
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label required-field">Password</label>
                                            <div class="password-field">
                                                <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" required minlength="6">
                                                <span class="password-toggle" id="togglePassword">
                                                    <i class="fas fa-eye"></i>
                                                </span>
                                            </div>
                                            <div class="form-text">Password must be at least 6 characters long.</div>
                                            <div class="invalid-feedback">
                                                Please provide a valid password (min. 6 characters).
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label required-field">Phone Number</label>
                                            <input type="tel" name="phone" class="form-control" placeholder="Enter phone number" pattern="[0-9]{10}" required value="<?php echo $phone ?? ''; ?>">
                                            <div class="form-text">Enter 10-digit phone number without spaces or dashes</div>
                                            <div class="invalid-feedback">
                                                Please provide a valid 10-digit phone number.
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label required-field">Role</label>
                                            <select name="role" class="form-select" required>
                                                <option value="">Select Role</option>
                                                <option value="admin" <?php echo (isset($role) && $role == 'admin') ? 'selected' : ''; ?>>Administrator</option>
                                                <option value="teacher" <?php echo (isset($role) && $role == 'teacher') ? 'selected' : ''; ?>>Teacher</option>
                                                <option value="staff" <?php echo (isset($role) && $role == 'staff') ? 'selected' : ''; ?>>Staff</option>
                                            </select>
                                            <div class="invalid-feedback">
                                                Please select a role.
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check form-switch mt-4 ms-2">
                                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?php echo (!isset($is_active) || $is_active == 1) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="is_active">Active Account</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <hr class="my-4">
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="users.php" class="btn btn-secondary me-md-2">
                                            <i class="fas fa-times me-1"></i> Cancel
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i> Create User
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle functionality
            const menuToggle = document.getElementById('menu-toggle');
            const sidebarWrapper = document.getElementById('sidebar-wrapper');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            
            menuToggle.addEventListener('click', function(e) {
                e.preventDefault();
                sidebarWrapper.classList.toggle('toggled');
            });
            
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', function() {
                    sidebarWrapper.classList.remove('toggled');
                });
            }
            
            // Form validation
            const form = document.getElementById('addUserForm');
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            });
            
            // Password visibility toggle
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // Change the eye icon
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
        });
    </script>
</body>
</html>
