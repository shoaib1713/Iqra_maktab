<?php
session_start();
require_once 'config.php';
require 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$role = $_SESSION['role'];

// Only admins can access all reports
$isAdmin = ($role === 'admin');

// Get filter parameters
$periodId = isset($_GET['period_id']) ? intval($_GET['period_id']) : 0;
$teacherId = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;
$status = isset($_GET['status']) ? $_GET['status'] : '';

// If not admin, can only view own reports
if (!$isAdmin) {
    $teacherId = $user_id;
}

// Get all periods for the filter
$periodsSql = "SELECT * FROM salary_periods ORDER BY start_date DESC";
$periodsResult = $conn->query($periodsSql);
$periods = [];
while ($period = $periodsResult->fetch_assoc()) {
    $periods[] = $period;
}

// Get all teachers for the filter (admin only)
$teachers = [];
if ($isAdmin) {
    $teachersSql = "SELECT id, name as full_name FROM users WHERE role = 'teacher' ORDER BY name";
    $teachersResult = $conn->query($teachersSql);
    while ($teacher = $teachersResult->fetch_assoc()) {
        $teachers[] = $teacher;
    }
}

// Build the query for salary calculations
$sql = "SELECT tsc.*, 
        u.name as teacher_name, 
        sp.period_name, sp.start_date, sp.end_date
        FROM teacher_salary_calculations tsc
        JOIN users u ON tsc.teacher_id = u.id
        JOIN salary_periods sp ON tsc.period_id = sp.id
        WHERE 1=1";

$params = [];
$types = "";

// Apply filters
if ($periodId > 0) {
    $sql .= " AND tsc.period_id = ?";
    $params[] = $periodId;
    $types .= "i";
}

if ($teacherId > 0) {
    $sql .= " AND tsc.teacher_id = ?";
    $params[] = $teacherId;
    $types .= "i";
}

if (!empty($status)) {
    $sql .= " AND tsc.status = ?";
    $params[] = $status;
    $types .= "s";
}

// Non-admin users can only see their own reports
if (!$isAdmin) {
    $sql .= " AND tsc.teacher_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

$sql .= " ORDER BY sp.start_date DESC, u.name";

// Prepare and execute query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get salary summary stats (for admin)
$summaryStats = [
    'totalTeachers' => 0,
    'totalSalary' => 0,
    'avgSalary' => 0,
    'minSalary' => 0,
    'maxSalary' => 0
];

if ($isAdmin && $result->num_rows > 0) {
    $sumSql = "SELECT 
               COUNT(DISTINCT teacher_id) as totalTeachers,
               SUM(final_salary) as totalSalary,
               AVG(final_salary) as avgSalary,
               MIN(final_salary) as minSalary,
               MAX(final_salary) as maxSalary
               FROM teacher_salary_calculations
               WHERE 1=1";
    
    $sumParams = [];
    $sumTypes = "";
    
    if ($periodId > 0) {
        $sumSql .= " AND period_id = ?";
        $sumParams[] = $periodId;
        $sumTypes .= "i";
    }
    
    if ($teacherId > 0) {
        $sumSql .= " AND teacher_id = ?";
        $sumParams[] = $teacherId;
        $sumTypes .= "i";
    }
    
    if (!empty($status)) {
        $sumSql .= " AND status = ?";
        $sumParams[] = $status;
        $sumTypes .= "s";
    }
    
    $sumStmt = $conn->prepare($sumSql);
    if (!empty($sumParams)) {
        $sumStmt->bind_param($sumTypes, ...$sumParams);
    }
    $sumStmt->execute();
    $summaryStats = $sumStmt->get_result()->fetch_assoc();
}

// Process export request
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $isAdmin) {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="salary_report_' . date('Y-m-d') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add CSV header
    fputcsv($output, [
        'Teacher Name', 
        'Period', 
        'Hourly Rate', 
        'Total Hours', 
        'Base Salary', 
        'Deductions', 
        'Bonuses', 
        'Final Salary', 
        'Status'
    ]);
    
    // Reset result pointer
    mysqli_data_seek($result, 0);
    
    // Add data rows
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['teacher_name'],
            $row['period_name'],
            $row['hourly_rate'],
            $row['total_hours'],
            $row['base_salary'],
            $row['deductions'],
            $row['bonuses'],
            $row['final_salary'],
            $row['status']
        ]);
    }
    
    // Close the output stream
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Reports - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/logo.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .report-card {
            transition: all 0.3s ease;
        }
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .summary-card {
            border-left: 4px solid #4e73df;
        }
        .stat-value {
            font-size: 1.25rem;
            font-weight: bold;
        }
        .stat-label {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .table-row-border {
            border-left: 3px solid transparent;
        }
        .table-row-paid {
            border-left-color: #1cc88a;
        }
        .table-row-pending {
            border-left-color: #f6c23e;
        }
        .table-row-processed {
            border-left-color: #4e73df;
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
                    <div class="col-md-8">
                        <h5 class="fw-bold text-primary"><i class="fas fa-file-invoice-dollar me-2"></i> Salary Reports</h5>
                        <p class="text-muted">
                            <?php echo $isAdmin ? "View and manage salary reports for all teachers." : "View your salary reports and history."; ?>
                        </p>
                    </div>
                    <?php if ($isAdmin && $result->num_rows > 0): ?>
                    <div class="col-md-4 text-end">
                        <a href="?export=csv<?php 
                            echo $periodId > 0 ? "&period_id={$periodId}" : ""; 
                            echo $teacherId > 0 ? "&teacher_id={$teacherId}" : "";
                            echo !empty($status) ? "&status={$status}" : "";
                        ?>" class="btn btn-success">
                            <i class="fas fa-file-csv me-2"></i> Export to CSV
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Filter Form -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Filter Reports</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="<?php echo $isAdmin ? 'col-md-4' : 'col-md-6'; ?>">
                                <label class="form-label">Salary Period</label>
                                <select class="form-select" name="period_id">
                                    <option value="0">All Periods</option>
                                    <?php foreach ($periods as $period): ?>
                                    <option value="<?php echo $period['id']; ?>" <?php echo $periodId == $period['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($period['period_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <?php if ($isAdmin): ?>
                            <div class="col-md-4">
                                <label class="form-label">Teacher</label>
                                <select class="form-select" name="teacher_id">
                                    <option value="0">All Teachers</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>" <?php echo $teacherId == $teacher['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($teacher['full_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <div class="<?php echo $isAdmin ? 'col-md-4' : 'col-md-6'; ?>">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="processed" <?php echo $status === 'processed' ? 'selected' : ''; ?>>Processed</option>
                                    <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-2"></i> Apply Filters
                                </button>
                                <a href="salary_reports.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo me-2"></i> Reset Filters
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if ($isAdmin && $result->num_rows > 0): ?>
                <!-- Summary Statistics (Admin Only) -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card border-0 shadow-sm summary-card h-100">
                            <div class="card-body">
                                <div class="stat-label">TOTAL TEACHERS</div>
                                <div class="stat-value"><?php echo number_format($summaryStats['totalTeachers']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card border-0 shadow-sm summary-card h-100">
                            <div class="card-body">
                                <div class="stat-label">TOTAL SALARY</div>
                                <div class="stat-value">₹<?php echo number_format($summaryStats['totalSalary'], 2); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card border-0 shadow-sm summary-card h-100">
                            <div class="card-body">
                                <div class="stat-label">AVERAGE SALARY</div>
                                <div class="stat-value">₹<?php echo number_format($summaryStats['avgSalary'], 2); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card border-0 shadow-sm summary-card h-100">
                            <div class="card-body">
                                <div class="stat-label">SALARY RANGE</div>
                                <div class="stat-value">₹<?php echo number_format($summaryStats['minSalary'], 2); ?> - ₹<?php echo number_format($summaryStats['maxSalary'], 2); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Salary Reports Table -->
                <div class="card shadow-sm">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Salary Reports</h6>
                        <span class="badge bg-primary"><?php echo $result->num_rows; ?> Record(s)</span>
                    </div>
                    <div class="card-body">
                        <?php if ($result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <?php if ($isAdmin): ?>
                                        <th>Teacher</th>
                                        <?php endif; ?>
                                        <th>Period</th>
                                        <th>Hourly Rate</th>
                                        <th>Hours</th>
                                        <th>Base Salary</th>
                                        <th>Deductions</th>
                                        <th>Bonuses</th>
                                        <th>Final Salary</th>
                                        <th>Status</th>
                                        <?php if ($isAdmin): ?>
                                        <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): 
                                        $statusClass = '';
                                        $statusBadge = '';
                                        $rowClass = '';
                                        
                                        switch ($row['status']) {
                                            case 'paid':
                                                $statusClass = 'bg-success';
                                                $statusBadge = 'Paid';
                                                $rowClass = 'table-row-paid';
                                                break;
                                            case 'pending':
                                                $statusClass = 'bg-warning';
                                                $statusBadge = 'Pending';
                                                $rowClass = 'table-row-pending';
                                                break;
                                            default:
                                                $statusClass = 'bg-primary';
                                                $statusBadge = 'Processed';
                                                $rowClass = 'table-row-processed';
                                                break;
                                        }
                                    ?>
                                    <tr class="table-row-border <?php echo $rowClass; ?>">
                                        <?php if ($isAdmin): ?>
                                        <td><?php echo htmlspecialchars($row['teacher_name']); ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <div><?php echo htmlspecialchars($row['period_name']); ?></div>
                                            <small class="text-muted">
                                                <?php echo date('M d', strtotime($row['start_date'])); ?> - 
                                                <?php echo date('M d, Y', strtotime($row['end_date'])); ?>
                                            </small>
                                        </td>
                                        <td>₹<?php echo number_format($row['hourly_rate'], 2); ?></td>
                                        <td><?php echo number_format($row['total_hours'], 2); ?></td>
                                        <td>₹<?php echo number_format($row['base_salary'], 2); ?></td>
                                        <td class="text-danger">-₹<?php echo number_format($row['deductions'], 2); ?></td>
                                        <td class="text-success">+₹<?php echo number_format($row['bonuses'], 2); ?></td>
                                        <td class="fw-bold">₹<?php echo number_format($row['final_salary'], 2); ?></td>
                                        <td>
                                            <span class="badge <?php echo $statusClass; ?> status-badge">
                                                <?php echo $statusBadge; ?>
                                            </span>
                                        </td>
                                        <?php if ($isAdmin): ?>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $row['id']; ?>">
                                                <i class="fas fa-info-circle"></i>
                                            </button>
                                            
                                            <?php if ($row['status'] !== 'paid'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-success" 
                                                    data-bs-toggle="modal" data-bs-target="#markPaidModal<?php echo $row['id']; ?>">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No salary reports found with the current filters.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php 
    // Reset result pointer
    if ($result->num_rows > 0) {
        mysqli_data_seek($result, 0);
        
        // Generate modals for each record (admin only)
        if ($isAdmin) {
            while ($row = $result->fetch_assoc()):
    ?>
    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Salary Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="mb-2">Teacher Information</h6>
                            <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($row['teacher_name']); ?></p>
                            <p class="mb-1"><strong>Hourly Rate:</strong> ₹<?php echo number_format($row['hourly_rate'], 2); ?></p>
                            <p class="mb-0"><strong>Total Hours:</strong> <?php echo number_format($row['total_hours'], 2); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-2">Period Information</h6>
                            <p class="mb-1"><strong>Period:</strong> <?php echo htmlspecialchars($row['period_name']); ?></p>
                            <p class="mb-1"><strong>Start Date:</strong> <?php echo date('M d, Y', strtotime($row['start_date'])); ?></p>
                            <p class="mb-0"><strong>End Date:</strong> <?php echo date('M d, Y', strtotime($row['end_date'])); ?></p>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <h6 class="mb-2">Salary Breakdown</h6>
                            <table class="table table-bordered">
                                <tr>
                                    <td><strong>Base Salary</strong></td>
                                    <td>₹<?php echo number_format($row['base_salary'], 2); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Deductions</strong></td>
                                    <td class="text-danger">-₹<?php echo number_format($row['deductions'], 2); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Bonuses</strong></td>
                                    <td class="text-success">+₹<?php echo number_format($row['bonuses'], 2); ?></td>
                                </tr>
                                <tr class="table-primary">
                                    <td><strong>Final Salary</strong></td>
                                    <td class="fw-bold">₹<?php echo number_format($row['final_salary'], 2); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <?php if (!empty($row['notes'])): ?>
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6 class="mb-2">Notes</h6>
                            <p><?php echo nl2br(htmlspecialchars($row['notes'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mark as Paid Modal -->
    <?php if ($row['status'] !== 'paid'): ?>
    <div class="modal fade" id="markPaidModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Mark Salary as Paid</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="mark_salary_paid.php">
                    <div class="modal-body">
                        <p>Are you sure you want to mark this salary as paid for <strong><?php echo htmlspecialchars($row['teacher_name']); ?></strong>?</p>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> This will update the status to "Paid" and send a notification to the teacher.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Payment Date</label>
                            <input type="date" class="form-control" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <select class="form-select" name="payment_method" required>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cash">Cash</option>
                                <option value="cheque">Cheque</option>
                                <option value="online">Online Payment</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reference Number (Optional)</label>
                            <input type="text" class="form-control" name="reference_number" placeholder="Transaction ID, Cheque Number, etc.">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Payment Notes (Optional)</label>
                            <textarea class="form-control" name="payment_notes" rows="3" placeholder="Any additional notes about this payment"></textarea>
                        </div>
                        
                        <input type="hidden" name="salary_id" value="<?php echo $row['id']; ?>">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Mark as Paid</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php 
            endwhile;
        }
    }
    ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 