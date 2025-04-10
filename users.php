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
    header("Location: restrict_user.php?page=Users page&message=This page is restricted to administrators only.");
    exit();
}

// Initialize search filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';

// Build search query conditions
$conditions = ["is_deleted = 0"];
if (!empty($search)) {
    $conditions[] = "(name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%')";
}
if (!empty($role_filter)) {
    $conditions[] = "role = '$role_filter'";
}

$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Pagination setup
$limit = 15; // Number of records per page
$page = isset($_GET['page']) ? $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch users with pagination
$sql = "SELECT * FROM users 
        $whereClause
        ORDER BY id DESC 
        LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

// Get total records for pagination
$total_records = $conn->query("SELECT COUNT(*) AS total FROM users $whereClause")->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get statistics
$statsSql = "SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
    SUM(CASE WHEN role = 'teacher' THEN 1 ELSE 0 END) as teacher_count,
    SUM(CASE WHEN role = 'staff' THEN 1 ELSE 0 END) as staff_count,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_count
FROM users 
WHERE is_deleted = 0";
$statsResult = $conn->query($statsSql);
$stats = $statsResult->fetch_assoc();

// Get available roles for dropdown
$roles = ['admin', 'teacher', 'staff'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/logo.png">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <style>
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-weight: bold;
            margin-right: 10px;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 0.35rem 0.65rem;
        }
        .role-badge {
            text-transform: capitalize;
        }
        .stats-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
        }
        .primary-card {
            border-left-color: #0d6efd;
        }
        .success-card {
            border-left-color: #198754;
        }
        .warning-card {
            border-left-color: #ffc107;
        }
        .danger-card {
            border-left-color: #dc3545;
        }
        .info-card {
            border-left-color: #0dcaf0;
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
                    <span class="navbar-brand ms-2">User Management</span>
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
                <!-- Statistics Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm stats-card primary-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Total Users</h6>
                                        <h3 class="fw-bold mb-0"><?php echo $stats['total_users']; ?></h3>
                                    </div>
                                    <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                                        <i class="fas fa-users fa-2x text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm stats-card success-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Active Users</h6>
                                        <h3 class="fw-bold mb-0"><?php echo $stats['active_count']; ?></h3>
                                    </div>
                                    <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                        <i class="fas fa-user-check fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm stats-card danger-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Inactive Users</h6>
                                        <h3 class="fw-bold mb-0"><?php echo $stats['inactive_count']; ?></h3>
                                    </div>
                                    <div class="bg-danger bg-opacity-10 rounded-circle p-3">
                                        <i class="fas fa-user-times fa-2x text-danger"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Role Distribution -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm stats-card info-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Administrators</h6>
                                        <h3 class="fw-bold mb-0"><?php echo $stats['admin_count']; ?></h3>
                                    </div>
                                    <div class="bg-info bg-opacity-10 rounded-circle p-3">
                                        <i class="fas fa-user-shield fa-2x text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm stats-card warning-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Teachers</h6>
                                        <h3 class="fw-bold mb-0"><?php echo $stats['teacher_count']; ?></h3>
                                    </div>
                                    <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                                        <i class="fas fa-chalkboard-teacher fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm stats-card success-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Staff</h6>
                                        <h3 class="fw-bold mb-0"><?php echo $stats['staff_count']; ?></h3>
                                    </div>
                                    <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                        <i class="fas fa-user-tie fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Search and Filter -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold text-primary">
                            <i class="fas fa-filter me-2"></i> Search & Filter Users
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control" placeholder="Search by name, email, phone..." value="<?php echo $search; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Filter by Role</label>
                                <select name="role" class="form-select">
                                    <option value="">All Roles</option>
                                    <?php foreach ($roles as $r): ?>
                                        <option value="<?php echo $r; ?>" <?php echo ($role_filter == $r) ? 'selected' : '' ?>>
                                            <?php echo ucfirst($r); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-1"></i> Apply
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Users Table -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold text-primary">
                            <i class="fas fa-user-cog me-2"></i> User Management
                        </h5>
                        <div>
                            <a href="add_user.php" class="btn btn-sm btn-success">
                                <i class="fas fa-user-plus me-1"></i> Add New User
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="usersTable" class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>User</th>
                                        <th>Contact Info</th>
                            <th>Role</th>
                            <th>Status</th>                            
                                        <th>Last Login</th>
                                        <th>Actions</th>
                        </tr>
                    </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): 
                                        $statusBadge = $row['is_active'] == 1 ? 
                                            '<span class="badge bg-success status-badge">Active</span>' : 
                                            '<span class="badge bg-danger status-badge">Inactive</span>';
                                        
                                        $nameInitials = strtoupper(substr($row['name'], 0, 1));
                                        
                                        $roleBadgeClass = '';
                                        switch ($row['role']) {
                                            case 'admin':
                                                $roleBadgeClass = 'bg-info';
                                                break;
                                            case 'teacher':
                                                $roleBadgeClass = 'bg-warning text-dark';
                                                break;
                                            case 'staff':
                                                $roleBadgeClass = 'bg-success';
                                                break;
                                            default:
                                                $roleBadgeClass = 'bg-secondary';
                                        }
                                    ?>
                                    <tr id="row_<?= $row['id'] ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar">
                                                    <?php echo $nameInitials; ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?php echo $row['name']; ?></div>
                                                    <small class="text-muted">ID: <?php echo $row['id']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <i class="fas fa-envelope me-1 text-muted"></i> <?php echo $row['email']; ?>
                                            </div>
                                            <?php if (!empty($row['phone'])): ?>
                                            <div>
                                                <i class="fas fa-phone me-1 text-muted"></i> <?php echo $row['phone']; ?>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $roleBadgeClass; ?> role-badge">
                                                <?php echo ucfirst($row['role']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $statusBadge; ?></td>
                                        <td>
                                            <?php 
                                            if (!empty($row['last_login'])) {
                                                echo date("d M Y, h:i A", strtotime($row['last_login']));
                                            } else {
                                                echo '<span class="text-muted">Never</span>';
                                            }
                                            ?>
                        </td>
                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="edit_user.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($row['is_active'] == 1): ?>
                                                <button class="btn btn-warning btn-sm toggle-status" data-id="<?= $row['id'] ?>" data-status="0" title="Deactivate">
                                                    <i class="fas fa-user-slash"></i>
                                                </button>
                                                <?php else: ?>
                                                <button class="btn btn-success btn-sm toggle-status" data-id="<?= $row['id'] ?>" data-status="1" title="Activate">
                                                    <i class="fas fa-user-check"></i>
                                                </button>
                                                <?php endif; ?>
                                                <button class="btn btn-danger btn-sm delete-user" data-id="<?= $row['id'] ?>" title="Delete">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                                <button class="btn btn-info btn-sm reset-password" data-id="<?= $row['id'] ?>" title="Reset Password">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                            </div>
                        </td>
                    </tr>
                                    <?php endwhile; ?>
                    </tbody>
                </table>
                        </div>
                    </div>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . $search : '' ?><?= !empty($role_filter) ? '&role=' . $role_filter : '' ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?><?= !empty($search) ? '&search=' . $search : '' ?><?= !empty($role_filter) ? '&role=' . $role_filter : '' ?>">
                                <?= $i ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . $search : '' ?><?= !empty($role_filter) ? '&role=' . $role_filter : '' ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reset User Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="resetPasswordForm">
                        <input type="hidden" id="reset_user_id" name="id">
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <div id="password_match_error" class="text-danger" style="display: none;">
                                Passwords do not match!
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmResetPassword">
                        <i class="fas fa-save me-1"></i> Reset Password
                    </button>
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
            
            // Initialize DataTable
            $('#usersTable').DataTable({
                paging: false,
                "dom": '<"top"f>rt<"bottom">'
            });
            
            // Toggle user status (activate/deactivate)
            document.querySelectorAll('.toggle-status').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const newStatus = this.getAttribute('data-status');
                    const action = newStatus == '1' ? 'activate' : 'deactivate';
                    
                    if (confirm(`Are you sure you want to ${action} this user?`)) {
                        updateUserStatus(id, newStatus);
                    }
                });
            });
            
            // Delete user
            document.querySelectorAll('.delete-user').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    
                    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                        deleteUser(id);
                    }
                });
            });
            
            // Initialize reset password modal
            const resetPasswordModal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
            
            // Open reset password modal
            document.querySelectorAll('.reset-password').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    document.getElementById('reset_user_id').value = id;
                    document.getElementById('new_password').value = '';
                    document.getElementById('confirm_password').value = '';
                    document.getElementById('password_match_error').style.display = 'none';
                    resetPasswordModal.show();
                });
            });
            
            // Password confirmation validation
            document.getElementById('confirm_password').addEventListener('input', function() {
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = this.value;
                
                if (newPassword === confirmPassword) {
                    document.getElementById('password_match_error').style.display = 'none';
                } else {
                    document.getElementById('password_match_error').style.display = 'block';
                }
            });
            
            // Confirm password reset
            document.getElementById('confirmResetPassword').addEventListener('click', function() {
                const id = document.getElementById('reset_user_id').value;
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (!newPassword || !confirmPassword) {
                    alert('Please enter a new password and confirm it.');
                    return;
                }
                
                if (newPassword !== confirmPassword) {
                    document.getElementById('password_match_error').style.display = 'block';
                    return;
                }
                
                resetPassword(id, newPassword);
                resetPasswordModal.hide();
            });
            
            // Function to update user status
            function updateUserStatus(id, status) {
                fetch('update_user_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${id}&status=${status}`
                })
                .then(response => response.text())
                .then(data => {
                    if (data.trim() === 'success') {
                        location.reload();
                    } else {
                        alert('Failed to update user status.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating user status.');
                });
            }
            
            // Function to delete user
            function deleteUser(id) {
                fetch('delete_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${id}`
                })
                .then(response => response.text())
                .then(data => {
                    if (data.trim() === 'success') {
                        document.getElementById(`row_${id}`).remove();
                        alert('User deleted successfully.');
                    } else {
                        alert('Failed to delete user.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting user.');
                });
            }
            
            // Function to reset password
            function resetPassword(id, password) {
                fetch('reset_user_password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${id}&password=${password}`
                })
                .then(response => response.text())
                .then(data => {
                    if (data.trim() === 'success') {
                        alert('Password reset successfully.');
                    } else {
                        alert('Failed to reset password.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while resetting password.');
                });
            }
        });
    </script>
</body>
</html>
