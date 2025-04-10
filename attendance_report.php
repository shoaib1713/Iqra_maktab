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
$user_type = $role;

// Get filters
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$filter_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : ($role != 'admin' ? $user_id : 0);
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Get users for filter dropdown (if admin)
$users = [];
if ($role == 'admin') {
    $usersSql = "SELECT id, name, role FROM users ORDER BY name";
    $usersResult = $conn->query($usersSql);
    while ($user = $usersResult->fetch_assoc()) {
        $users[] = $user;
    }
    
    // Also add students
    $studentsSql = "SELECT id, name, 'student' as role FROM students ORDER BY name";
    $studentsResult = $conn->query($studentsSql);
    while ($student = $studentsResult->fetch_assoc()) {
        $users[] = $student;
    }
}

// Build query conditions
$conditions = [];
$params = [];
$types = "";

// Always filter by month and year
$conditions[] = "month = ?";
$params[] = $month;
$types .= "i";

$conditions[] = "year = ?";
$params[] = $year;
$types .= "i";

// Filter by user if specified
if ($filter_user_id > 0) {
    $conditions[] = "user_id = ?";
    $params[] = $filter_user_id;
    $types .= "i";
    
    // For non-admin users, always filter by their own ID
} elseif ($role != 'admin') {
    $conditions[] = "user_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

// Filter by status if specified
if (!empty($filter_status)) {
    $conditions[] = "status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

// Get attendance summary
$sql = "SELECT 
          a.*, 
          CASE 
            WHEN a.user_type = 'student' THEN s.name 
            ELSE u.name 
          END AS user_name,
          lt.type_name as leave_type
        FROM attendance_summary a
        LEFT JOIN users u ON a.user_id = u.id AND a.user_type != 'student'
        LEFT JOIN students s ON a.user_id = s.id AND a.user_type = 'student'
        LEFT JOIN leave_types lt ON a.leave_type_id = lt.id
        WHERE " . implode(" AND ", $conditions) . "
        ORDER BY a.summary_date ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$attendance_data = [];
while ($row = $result->fetch_assoc()) {
    $attendance_data[] = $row;
}

// Get summary statistics
$statsSql = "SELECT 
              COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
              COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
              COUNT(CASE WHEN status = 'leave' THEN 1 END) as leave_count,
              COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count,
              COUNT(CASE WHEN status = 'early_exit' THEN 1 END) as early_exit_count,
              SUM(work_hours) as total_hours
            FROM attendance_summary
            WHERE " . implode(" AND ", $conditions);

$statsStmt = $conn->prepare($statsSql);
$statsStmt->bind_param($types, ...$params);
$statsStmt->execute();
$statsResult = $statsStmt->get_result();
$stats = $statsResult->fetch_assoc();

// Convert month number to name
$month_name = date('F', mktime(0, 0, 0, $month, 10));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report - MAKTAB-E-IQRA</title>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .status-badge {
            font-size: 0.8rem;
            padding: 0.3rem 0.5rem;
        }
        .summary-card {
            transition: all 0.3s ease;
        }
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        #chartContainer {
            height: 300px;
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Page Content -->
        <div id="page-content-wrapper">
            <?php include 'includes/navbar.php'; ?>
            
            <div class="container-fluid px-4 py-4">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h5 class="fw-bold text-primary"><i class="fas fa-chart-bar me-2"></i> Attendance Report</h5>
                        <p class="text-muted">View and analyze attendance records.</p>
                    </div>
                </div>
                
                <!-- Filter Form -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-filter me-2"></i> Filter Options
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="GET" action="" class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Month</label>
                                        <select name="month" class="form-select">
                                            <?php for($m = 1; $m <= 12; $m++): ?>
                                                <option value="<?php echo $m; ?>" <?php echo ($m == $month) ? 'selected' : ''; ?>>
                                                    <?php echo date('F', mktime(0, 0, 0, $m, 10)); ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Year</label>
                                        <select name="year" class="form-select">
                                            <?php for($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                                <option value="<?php echo $y; ?>" <?php echo ($y == $year) ? 'selected' : ''; ?>>
                                                    <?php echo $y; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    
                                    <?php if ($role == 'admin'): ?>
                                    <div class="col-md-3">
                                        <label class="form-label">User</label>
                                        <select name="user_id" class="form-select">
                                            <option value="0">All Users</option>
                                            <?php foreach ($users as $u): ?>
                                                <option value="<?php echo $u['id']; ?>" <?php echo ($u['id'] == $filter_user_id) ? 'selected' : ''; ?>>
                                                    <?php echo $u['name'] . ' (' . ucfirst($u['role']) . ')'; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-select">
                                            <option value="">All Statuses</option>
                                            <option value="present" <?php echo ($filter_status == 'present') ? 'selected' : ''; ?>>Present</option>
                                            <option value="absent" <?php echo ($filter_status == 'absent') ? 'selected' : ''; ?>>Absent</option>
                                            <option value="leave" <?php echo ($filter_status == 'leave') ? 'selected' : ''; ?>>Leave</option>
                                            <option value="late" <?php echo ($filter_status == 'late') ? 'selected' : ''; ?>>Late</option>
                                            <option value="early_exit" <?php echo ($filter_status == 'early_exit') ? 'selected' : ''; ?>>Early Exit</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-12 text-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-filter me-2"></i> Apply Filters
                                        </button>
                                        <a href="attendance_report.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-redo me-2"></i> Reset
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Summary Statistics -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h5 class="mb-3">Summary for <?php echo $month_name . ' ' . $year; ?></h5>
                    </div>
                    <div class="col-md-2 col-sm-4 mb-3">
                        <div class="card text-center summary-card h-100">
                            <div class="card-body">
                                <h2 class="text-success"><?php echo $stats['present_count'] ?? 0; ?></h2>
                                <p class="mb-0 text-muted">Present</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 mb-3">
                        <div class="card text-center summary-card h-100">
                            <div class="card-body">
                                <h2 class="text-danger"><?php echo $stats['absent_count'] ?? 0; ?></h2>
                                <p class="mb-0 text-muted">Absent</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 mb-3">
                        <div class="card text-center summary-card h-100">
                            <div class="card-body">
                                <h2 class="text-warning"><?php echo $stats['leave_count'] ?? 0; ?></h2>
                                <p class="mb-0 text-muted">Leave</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 mb-3">
                        <div class="card text-center summary-card h-100">
                            <div class="card-body">
                                <h2 class="text-info"><?php echo $stats['late_count'] ?? 0; ?></h2>
                                <p class="mb-0 text-muted">Late</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 mb-3">
                        <div class="card text-center summary-card h-100">
                            <div class="card-body">
                                <h2 class="text-secondary"><?php echo $stats['early_exit_count'] ?? 0; ?></h2>
                                <p class="mb-0 text-muted">Early Exit</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 mb-3">
                        <div class="card text-center summary-card h-100">
                            <div class="card-body">
                                <h2 class="text-primary"><?php echo number_format($stats['total_hours'] ?? 0, 1); ?></h2>
                                <p class="mb-0 text-muted">Total Hours</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Chart -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-chart-pie me-2"></i> Attendance Breakdown
                                </h5>
                            </div>
                            <div class="card-body">
                                <div id="chartContainer">
                                    <canvas id="attendanceChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Data Table -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-table me-2"></i> Attendance Records
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="attendanceTable" class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <?php if ($role == 'admin' && $filter_user_id == 0): ?>
                                                <th>Name</th>
                                                <th>Role</th>
                                                <?php endif; ?>
                                                <th>Status</th>
                                                <th>Hours</th>
                                                <th>Late</th>
                                                <th>Early Exit</th>
                                                <th>Leave Type</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($attendance_data as $record): ?>
                                            <tr>
                                                <td><?php echo date('d M Y (D)', strtotime($record['summary_date'])); ?></td>
                                                <?php if ($role == 'admin' && $filter_user_id == 0): ?>
                                                <td><?php echo $record['user_name']; ?></td>
                                                <td><?php echo ucfirst($record['user_type']); ?></td>
                                                <?php endif; ?>
                                                <td>
                                                    <?php 
                                                    $statusClass = '';
                                                    switch($record['status']) {
                                                        case 'present':
                                                            $statusClass = 'bg-success';
                                                            break;
                                                        case 'absent':
                                                            $statusClass = 'bg-danger';
                                                            break;
                                                        case 'leave':
                                                            $statusClass = 'bg-warning';
                                                            break;
                                                        case 'late':
                                                            $statusClass = 'bg-info';
                                                            break;
                                                        case 'early_exit':
                                                            $statusClass = 'bg-secondary';
                                                            break;
                                                        case 'holiday':
                                                            $statusClass = 'bg-primary';
                                                            break;
                                                        case 'weekend':
                                                            $statusClass = 'bg-dark';
                                                            break;
                                                        default:
                                                            $statusClass = 'bg-primary';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?> status-badge">
                                                        <?php echo ucfirst($record['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $record['work_hours'] ? number_format($record['work_hours'], 1) : '-'; ?></td>
                                                <td>
                                                    <?php echo $record['is_late'] ? '<i class="fas fa-check text-danger"></i>' : '-'; ?>
                                                </td>
                                                <td>
                                                    <?php echo $record['is_early_exit'] ? '<i class="fas fa-check text-warning"></i>' : '-'; ?>
                                                </td>
                                                <td>
                                                    <?php echo $record['leave_type'] ?: '-'; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable with export buttons
            $('#attendanceTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel me-1"></i> Excel',
                        className: 'btn btn-sm btn-success',
                        title: 'Attendance Report - <?php echo $month_name . ' ' . $year; ?>'
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf me-1"></i> PDF',
                        className: 'btn btn-sm btn-danger',
                        title: 'Attendance Report - <?php echo $month_name . ' ' . $year; ?>'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print me-1"></i> Print',
                        className: 'btn btn-sm btn-secondary',
                        title: 'Attendance Report - <?php echo $month_name . ' ' . $year; ?>'
                    }
                ],
                "pageLength": 25,
                "order": [[ 0, "asc" ]]
            });
            
            // Initialize Chart
            const ctx = document.getElementById('attendanceChart').getContext('2d');
            
            const chartData = {
                labels: ['Present', 'Absent', 'Leave', 'Late', 'Early Exit'],
                datasets: [{
                    data: [
                        <?php echo $stats['present_count'] ?? 0; ?>,
                        <?php echo $stats['absent_count'] ?? 0; ?>,
                        <?php echo $stats['leave_count'] ?? 0; ?>,
                        <?php echo $stats['late_count'] ?? 0; ?>,
                        <?php echo $stats['early_exit_count'] ?? 0; ?>
                    ],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.7)',  // success
                        'rgba(220, 53, 69, 0.7)',  // danger
                        'rgba(255, 193, 7, 0.7)',  // warning
                        'rgba(23, 162, 184, 0.7)', // info
                        'rgba(108, 117, 125, 0.7)' // secondary
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(220, 53, 69, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(23, 162, 184, 1)',
                        'rgba(108, 117, 125, 1)'
                    ],
                    borderWidth: 1
                }]
            };
            
            new Chart(ctx, {
                type: 'doughnut',
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        },
                        title: {
                            display: true,
                            text: 'Attendance Distribution'
                        }
                    }
                }
            });
        });
    </script>
</body>
</html> 