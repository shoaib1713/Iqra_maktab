<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$role = $_SESSION['role'];

// Initialize search filters
$searchQuery = "";
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$month = isset($_GET['month']) ? $_GET['month'] : date('n');
$category = isset($_GET['category']) ? $_GET['category'] : '';

// Apply filters when form is submitted
if (isset($_GET['search']) || (isset($_GET['year']) || isset($_GET['month']) || isset($_GET['category']))) {
    $conditions = [];
    
    if (!empty($year)) $conditions[] = "year = '$year'";
    if (!empty($month)) $conditions[] = "month = '$month'";
    if (!empty($category)) $conditions[] = "category = '$category'";
    
    if (!empty($conditions)) {
        $searchQuery = "WHERE " . implode(" AND ", $conditions);
    }
}

// Pagination setup
$limit = 15; // Number of records per page
$page = isset($_GET['page']) ? $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch maintenance records with pagination
$sql = "SELECT * FROM maintenance 
        $searchQuery
        ORDER BY created_on DESC LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

// Get total records for pagination
$total_records = $conn->query("SELECT COUNT(*) AS total FROM maintenance $searchQuery")->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get summary statistics
$totalQuery = "SELECT 
    COUNT(*) as total_entries,
    SUM(amount) as total_amount,
    COUNT(DISTINCT category) as category_count
FROM maintenance
$searchQuery";
$totalResult = $conn->query($totalQuery);
$summary = $totalResult->fetch_assoc();

// Get categories for filter dropdown
$categoriesQuery = "SELECT DISTINCT category FROM maintenance ORDER BY category";
$categoriesResult = $conn->query($categoriesQuery);
$categories = [];
while ($category = $categoriesResult->fetch_assoc()) {
    $categories[] = $category['category'];
}

// Get monthly maintenance expenses for chart
$chartQuery = "SELECT month, SUM(amount) as total
               FROM maintenance 
               WHERE year = '$year'
               and is_deleted = 0
               GROUP BY month 
               ORDER BY month";
$chartResult = $conn->query($chartQuery);
$chartData = [];
while ($data = $chartResult->fetch_assoc()) {
    $chartData[$data['month']] = $data['total'];
}

// Generate title for export
$export_title = "Maintenance_Records_" . date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance List - MAKTAB-E-IQRA</title>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <span class="navbar-brand ms-2">Maintenance Management</span>
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
                                    <i class="fas fa-filter me-2"></i> Filter Maintenance Records
                                </h5>
                            </div>
                            <div class="card-body">
                                <form action="" method="GET" class="row g-3">
                                    <div class="col-md-3">
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
                                    <div class="col-md-3">
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
                                    <div class="col-md-3">
                                        <label class="form-label">Category</label>
                                        <select name="category" class="form-select">
                                            <option value="">All Categories</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo $cat; ?>" <?php echo ($category == $cat) ? 'selected' : '' ?>>
                                                    <?php echo $cat; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <div class="d-grid gap-2 w-100">
                                            <button type="submit" name="search" value="1" class="btn btn-primary">
                                                <i class="fas fa-search me-1"></i> Search
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Summary Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card text-center h-100 shadow-sm">
                            <div class="card-body">
                                <h5 class="fw-bold text-dark"><?php echo $summary['total_entries']; ?></h5>
                                <p class="text-muted mb-0">Total Records</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center h-100 shadow-sm bg-danger bg-opacity-75 text-white">
                            <div class="card-body">
                                <h5 class="fw-bold">₹ <?php echo number_format($summary['total_amount'] ?? 0); ?></h5>
                                <p class="mb-0">Total Expenses</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center h-100 shadow-sm bg-primary bg-opacity-75 text-white">
                            <div class="card-body">
                                <h5 class="fw-bold"><?php echo $summary['category_count']; ?></h5>
                                <p class="mb-0">Different Categories</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Chart Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold text-primary">
                            <i class="fas fa-chart-bar me-2"></i> Monthly Expenses for <?php echo $year; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="expensesChart" height="100"></canvas>
                    </div>
                </div>
                
                <!-- Maintenance Records Table -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold text-primary">
                            <i class="fas fa-tools me-2"></i> Maintenance Records
                        </h5>
                        <div>
                            <a href="add_maintenance.php" class="btn btn-sm btn-success">
                                <i class="fas fa-plus me-1"></i> Add Maintenance
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="maintenanceTable" class="table table-striped table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Month</th>
                                        <th>Year</th>
                                        <th>Category</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): 
                                        $deleteBtn = $row['is_deleted'] == 0 ? 
                                            "<button class='btn btn-danger btn-sm delete-btn' data-id='{$row['id']}'>
                                                <i class='fas fa-trash-alt'></i>
                                             </button>" : 
                                            "<span class='badge bg-secondary'>Deleted</span>";
                                    ?>
                                    <tr id="row_<?= $row['id'] ?>">
                                        <td><?php echo date("F", mktime(0, 0, 0, $row['month'], 1)); ?></td>
                                        <td><?php echo $row['year']; ?></td>
                                        <td>
                                            <span class="badge bg-info text-dark">
                                                <?php echo $row['category']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-bold">₹ <?php echo number_format($row['amount'], 2); ?></span>
                                        </td>
                                        <td><?php echo date("d M Y, h:i A", strtotime($row['created_on'])); ?></td>
                                        <td>
                                            <?php if ($row['is_deleted'] == 0): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Deleted</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['is_deleted'] == 0): ?>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-danger btn-sm delete-btn" data-id="<?= $row['id'] ?>" title="Delete">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                    <?php if (!empty($row['comment'])): ?>
                                                    <button class="btn btn-info btn-sm view-comment" data-comment="<?= htmlspecialchars($row['comment']) ?>" title="View Comment">
                                                        <i class="fas fa-comment"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No Actions</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Comment Modal -->
    <div class="modal fade" id="commentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Comment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="commentText"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
            $('#maintenanceTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel me-1"></i> Excel',
                        className: 'btn btn-sm btn-success',
                        title: '<?= $export_title ?>'
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf me-1"></i> PDF',
                        className: 'btn btn-sm btn-danger',
                        title: '<?= $export_title ?>'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print me-1"></i> Print',
                        className: 'btn btn-sm btn-secondary',
                        title: '<?= $export_title ?>'
                    }
                ],
                "pageLength": 15,
                "responsive": true
            });
            
            // Initialize Chart
            const ctx = document.getElementById('expensesChart').getContext('2d');
            const chartData = {
                labels: [
                    'January', 'February', 'March', 'April', 'May', 'June', 
                    'July', 'August', 'September', 'October', 'November', 'December'
                ],
                datasets: [{
                    label: 'Monthly Expenses (₹)',
                    data: [
                        <?php
                        for ($m = 1; $m <= 12; $m++) {
                            echo isset($chartData[$m]) ? $chartData[$m] : 0;
                            echo ($m < 12) ? ", " : "";
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            };
            
            new Chart(ctx, {
                type: 'bar',
                data: chartData,
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₹' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
            
            // Delete functionality
            $(".delete-btn").click(function() {
                const id = $(this).data("id");
                const row = $("#row_" + id);
                
                if (confirm("Are you sure you want to delete this maintenance record?")) {
                    $.ajax({
                        url: "delete_maintenance.php",
                        type: "POST",
                        data: { id: id },
                        success: function(response) {
                            response = response.trim();
                            if (response == "success") {
                                row.fadeOut("slow", function() {
                                    $(this).remove();
                                });
                                alert("Record deleted successfully.");
                            } else {
                                alert("Failed to delete the record.");
                            }
                        },
                        error: function() {
                            alert("An error occurred during deletion.");
                        }
                    });
                }
            });
            
            // Comment view
            $(".view-comment").click(function() {
                const comment = $(this).data("comment");
                $("#commentText").text(comment);
                
                const commentModal = new bootstrap.Modal(document.getElementById('commentModal'));
                commentModal.show();
            });
        });
    </script>
</body>
</html>
