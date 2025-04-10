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

// Function to get user name
        function getUserName($userId, $conn) {
    if (!$userId) return "N/A";
    
    $query = $conn->query("SELECT name FROM users WHERE id = '$userId' LIMIT 1");
    $user = $query->fetch_assoc();
    
    return $user ? $user['name'] : "Unknown";
}

// Initialize filters
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$month = isset($_GET['month']) ? $_GET['month'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build search query conditions
$conditions = ["f.status = 'pending'"]; // Default condition for pending approvals
if (!empty($year)) $conditions[] = "f.Year = '$year'";
if (!empty($month)) $conditions[] = "f.month = '$month'";
if (!empty($search)) {
    $conditions[] = "(f.id LIKE '%$search%' OR s.name LIKE '%$search%' OR s.id LIKE '%$search%')";
}

$whereClause = "WHERE " . implode(" AND ", $conditions);

// Fetch pending fee approvals
$query = "SELECT f.*, s.name as student_name, s.phone, s.id as roll_no, s.class
          FROM fees f 
          LEFT JOIN students s ON s.id = f.student_id 
          $whereClause 
          ORDER BY f.created_at DESC";
$result = $conn->query($query);

// Get summary statistics
$statsSql = "SELECT 
    COUNT(*) as total_pending,
    SUM(amount) as total_amount,
    COUNT(DISTINCT student_id) as unique_students
FROM fees f
WHERE f.status = 'pending'";
$statsResult = $conn->query($statsSql);
$stats = $statsResult->fetch_assoc();

// Get pending fees grouped by class
$classSql = "SELECT s.class, COUNT(*) as count, SUM(f.amount) as total
             FROM fees f
             LEFT JOIN students s ON f.student_id = s.id
             WHERE f.status = 'pending'
             GROUP BY s.class
             ORDER BY s.class";
$classResult = $conn->query($classSql);
$classStats = [];
while ($row = $classResult->fetch_assoc()) {
    $classStats[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Fees - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/logo.png">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap5.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    <style>
        .approve-btn {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .approve-btn:hover {
            transform: scale(1.05);
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
        .class-badge {
            font-size: 0.9rem;
            padding: 5px 10px;
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
                    <span class="navbar-brand ms-2">Fee Approval Management</span>
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
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold text-primary">
                                    <i class="fas fa-filter me-2"></i> Filter Pending Approvals
                                </h5>
                            </div>
                            <div class="card-body">
                                <form action="" method="GET" class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Year</label>
                                        <select name="year" class="form-select">
                                            <option value="">All Years</option>
                                            <?php for ($y = date('Y') - 5; $y <= date('Y'); $y++): ?>
                                                <option value="<?php echo $y; ?>" <?php echo ($year == $y) ? 'selected' : '' ?>>
                                                    <?php echo $y; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Month</label>
                                        <select name="month" class="form-select">
                                            <option value="">All Months</option>
                                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                                <option value="<?php echo $m; ?>" <?php echo ($month == $m) ? 'selected' : '' ?>>
                                                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Search (Student/ID)</label>
                                        <input type="text" name="search" class="form-control" placeholder="Enter search term" value="<?php echo $search; ?>">
                                    </div>
                                    <div class="col-md-12 d-flex justify-content-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search me-1"></i> Apply Filters
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Summary Stats Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm stats-card primary-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Pending Approvals</h6>
                                        <h3 class="fw-bold mb-0"><?php echo $stats['total_pending']; ?></h3>
                                    </div>
                                    <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                                        <i class="fas fa-clock fa-2x text-primary"></i>
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
                                        <h6 class="text-muted mb-1">Total Amount</h6>
                                        <h3 class="fw-bold mb-0">₹ <?php echo number_format($stats['total_amount'] ?? 0); ?></h3>
                                    </div>
                                    <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                        <i class="fas fa-money-bill-wave fa-2x text-success"></i>
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
                                        <h6 class="text-muted mb-1">Unique Students</h6>
                                        <h3 class="fw-bold mb-0"><?php echo $stats['unique_students']; ?></h3>
                                    </div>
                                    <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                                        <i class="fas fa-users fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Class Distribution -->
                <?php if (!empty($classStats)): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold text-primary">
                            <i class="fas fa-chart-pie me-2"></i> Class Distribution
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($classStats as $classStat): ?>
                            <div class="col-md-3 col-sm-6">
                                <div class="card border-0 bg-light">
                                    <div class="card-body text-center">
                                        <h5 class="mb-2"><?php echo $classStat['class_name'] ?? 'Unassigned'; ?></h5>
                                        <div class="mb-2">
                                            <span class="badge bg-primary class-badge">
                                                <?php echo $classStat['count']; ?> Pending
                                            </span>
                                        </div>
                                        <p class="mb-0 text-success fw-bold">
                                            ₹ <?php echo number_format($classStat['total'] ?? 0); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Pending Approvals Table -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold text-primary">
                            <i class="fas fa-clipboard-check me-2"></i> Pending Fee Approvals
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table id="approvalTable" class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Student</th>
                                        <th>Fees Collected By</th>
                                        <th>Amount</th>
                                        <th>Month</th>
                                        <th>Year</th>
                                        <th>Date</th>
                                        <th>Phone</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr id="row_<?= $row['id'] ?>">
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="fw-bold"><?php echo $row['student_name'] ?? 'N/A'; ?></span>
                                                <?php if (!empty($row['id'])): ?>
                                                <small class="text-muted">Roll #: <?php echo $row['roll_no']; ?></small>
                                                <?php endif; ?>
                                                <?php if (!empty($row['class_name'])): ?>
                                                <small class="text-muted">Class: <?php echo $row['class_name']; ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?php echo getUserName($row['created_by'], $conn); ?></td>
                                        <td class="fw-bold">₹ <?php echo number_format($row['amount'], 2); ?></td>
                                        <td><?php echo date("F", mktime(0, 0, 0, $row['month'], 1)); ?></td>
                                        <td><?php echo $row['Year']; ?></td>
                                        <td><?php echo date('d-m-Y h:i A', strtotime($row['created_at'])); ?></td>
                                        <td><?php echo $row['phone']; ?></td>
                                        <td><span class="badge bg-warning text-dark">Pending</span></td>
                                        <td>
                                            <button class="btn btn-success btn-sm approve-btn" data-id="<?= $row['id'] ?>">
                                                <i class="fas fa-check me-1"></i> Approve
                                            </button>
                                            <button class="btn btn-danger btn-sm reject-btn" data-id="<?= $row['id'] ?>">
                                                <i class="fas fa-times me-1"></i> Reject
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No pending fee approvals found.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Fee Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="rejectForm">
                        <input type="hidden" id="reject_id" name="id">
                        <div class="mb-3">
                            <label for="reject_reason" class="form-label">Reason for Rejection</label>
                            <textarea class="form-control" id="reject_reason" name="reason" rows="3" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmReject">
                        <i class="fas fa-times me-1"></i> Confirm Rejection
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
            $('#approvalTable').DataTable({
                "pageLength": 15,
                "responsive": true,
                "order": [[5, 'desc']] // Sort by date column descending
            });
            
            // Approve fee
            document.querySelectorAll('.approve-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const fee_id = this.getAttribute('data-id');
                    const row = document.getElementById(`row_${fee_id}`);
                    
                    if (confirm('Are you sure you want to approve this fee payment?')) {
                        $.ajax({
                            url: "update_table.php",
                            type: "POST",
                            data: {
                                action: "approve_fee",
                                fee_id: fee_id
                            },
                            success: function(response) {
                                response = response.trim();
                                if (response == "success") {
                                    row.remove();
                                    alert("Fee approved successfully.");
                                    
                                    // Update stats if no rows left
                                    if (document.querySelectorAll('#approvalTable tbody tr').length === 0) {
                                        location.reload(); // Refresh to update stats
                                    }
                                } else {
                                    alert("Failed to approve fee.");
                                }
                            }
                        });
                    }
                });
            });
            
            // Initialize reject modal
            const rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));
            
            // Open reject modal
            document.querySelectorAll('.reject-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    document.getElementById('reject_id').value = id;
                    rejectModal.show();
                });
            });
            
            // Confirm rejection
            document.getElementById('confirmReject').addEventListener('click', function() {
                const fee_id = document.getElementById('reject_id').value;
                const reason = document.getElementById('reject_reason').value.trim();
                const row = document.getElementById(`row_${fee_id}`);
                
                if (!reason) {
                    alert('Please provide a reason for rejection.');
                    return;
                }
                
                $.ajax({
                    url: "update_table.php",
                    type: "POST",
                    data: {
                        action: "reject_fee",
                        fee_id: fee_id,
                        reason: reason
                    },
                    success: function(response) {
                        response = response.trim();
                        if (response == "success") {
                            row.remove();
                            alert("Fee rejected successfully.");
                            rejectModal.hide();
                            
                            // Update stats if no rows left
                            if (document.querySelectorAll('#approvalTable tbody tr').length === 0) {
                                location.reload(); // Refresh to update stats
                            }
                        } else {
                            alert("Failed to reject fee.");
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>