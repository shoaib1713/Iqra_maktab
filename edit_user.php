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

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: users.php?error=Invalid request!");
    exit();
}

$edit_user_id = $_GET['id'];
$success_message = "";
$error_message = "";

// Fetch user details
$stmt = $conn->prepare("SELECT id, name, email, role, phone, is_active FROM users WHERE id = ? AND is_deleted = 0");
$stmt->bind_param("i", $edit_user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: users.php?error=User not found!");
    exit();
}

// Update user details
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $phone = $_POST['phone'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Check if email already exists (excluding current user)
    $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? AND is_deleted = 0");
    $checkEmail->bind_param("si", $email, $edit_user_id);
    $checkEmail->execute();
    $checkEmail->store_result();

    if ($checkEmail->num_rows > 0) {
        $error_message = "Email already exists! Please use a different email.";
    } else {
        // Handle password update if provided
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $updateQuery = "UPDATE users SET name = ?, email = ?, password = ?, role = ?, phone = ?, is_active = ?, updated_by = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("ssssiiii", $name, $email, $password, $role, $phone, $is_active, $user_id, $edit_user_id);
        } else {
            $updateQuery = "UPDATE users SET name = ?, email = ?, role = ?, phone = ?, is_active = ?, updated_by = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("sssiiii", $name, $email, $role, $phone, $is_active, $user_id, $edit_user_id);
        }

        if ($stmt->execute()) {
            $success_message = "User updated successfully!";
        } else {
            $error_message = "Error updating user: " . $stmt->error;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - MAKTAB-E-IQRA</title>
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
                    <span class="navbar-brand ms-2">Edit User</span>
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
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 fw-bold text-primary">
                                        <i class="fas fa-user-edit me-2"></i> Edit User Account
                                    </h5>
                                    <a href="users.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-arrow-left me-1"></i> Back to Users
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="editUserForm" class="needs-validation" novalidate>
                                    <div class="row g-3">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label required-field">Full Name</label>
                                            <input type="text" name="name" class="form-control" placeholder="Enter full name" required value="<?php echo htmlspecialchars($user['name']); ?>">
                                            <div class="invalid-feedback">
                                                Please provide a valid name.
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label required-field">Email Address</label>
                                            <input type="email" name="email" class="form-control" placeholder="Enter email address" required value="<?php echo htmlspecialchars($user['email']); ?>">
                                            <div class="invalid-feedback">
                                                Please provide a valid email address.
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">New Password</label>
                                            <div class="password-field">
                                                <input type="password" name="password" id="password" class="form-control" placeholder="Leave blank to keep current password" minlength="6">
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
                                            <input type="tel" name="phone" class="form-control" placeholder="Enter phone number" pattern="[0-9]{10}" required value="<?php echo htmlspecialchars($user['phone']); ?>">
                                            <div class="form-text">Enter 10-digit phone number without spaces or dashes</div>
                                            <div class="invalid-feedback">
                                                Please provide a valid 10-digit phone number.
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label required-field">Role</label>
                                            <select name="role" class="form-select" required>
                                                <option value="">Select Role</option>
                                                <option value="admin" <?php echo ($user['role'] == 'admin') ? 'selected' : ''; ?>>Administrator</option>
                                                <option value="teacher" <?php echo ($user['role'] == 'teacher') ? 'selected' : ''; ?>>Teacher</option>
                                                <option value="staff" <?php echo ($user['role'] == 'staff') ? 'selected' : ''; ?>>Staff</option>
                                            </select>
                                            <div class="invalid-feedback">
                                                Please select a role.
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <div class="form-check form-switch mt-4 ms-2">
                                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?php echo ($user['is_active'] == 1) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="is_active">Active Account</label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12 mt-4">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-1"></i> Update User
                                            </button>
                                            <a href="users.php" class="btn btn-outline-secondary ms-2">
                                                <i class="fas fa-times me-1"></i> Cancel
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle functionality
            const menuToggle = document.getElementById('menu-toggle');
            const wrapper = document.getElementById('wrapper');
            
            if (menuToggle) {
                menuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    wrapper.classList.toggle('toggled');
                });
            }
            
            // Password toggle functionality
            const togglePassword = document.getElementById('togglePassword');
            const password = document.getElementById('password');
            
            if (togglePassword && password) {
                togglePassword.addEventListener('click', function() {
                    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                    password.setAttribute('type', type);
                    
                    // Toggle eye icon
                    this.querySelector('i').classList.toggle('fa-eye');
                    this.querySelector('i').classList.toggle('fa-eye-slash');
                });
            }
            
            // Form validation
            const forms = document.querySelectorAll('.needs-validation');
            
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
        });
    </script>
</body>
</html>
