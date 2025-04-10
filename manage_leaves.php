<?php
session_start();
require_once 'config.php';
require 'config/db.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: restrict_user.php?page=Manage Leaves&message=This page is restricted to administrators only.");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$role = $_SESSION['role'];

// Process leave request approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $leave_id = isset($_POST['leave_id']) ? intval($_POST['leave_id']) : 0;
    $action = $_POST['action'];
    $rejection_reason = isset($_POST['rejection_reason']) ? $_POST['rejection_reason'] : '';
    
    if ($leave_id > 0) {
        if ($action === 'approve') {
            // Update leave request status to approved
            $updateSql = "UPDATE leave_requests 
                         SET status = 'approved', 
                             approved_by = ?, 
                             updated_at = NOW() 
                         WHERE id = ?";
            $stmt = $conn->prepare($updateSql);
            $stmt->bind_param("ii", $user_id, $leave_id);
            $result = $stmt->execute();
            
            if ($result) {
                // Get leave request details
                $leaveSql = "SELECT user_id, user_type, leave_type_id, start_date, end_date FROM leave_requests WHERE id = ?";
                $leaveStmt = $conn->prepare($leaveSql);
                $leaveStmt->bind_param("i", $leave_id);
                $leaveStmt->execute();
                $leaveResult = $leaveStmt->get_result();
                $leave = $leaveResult->fetch_assoc();
                
                // Update attendance summary for the leave period
                $start = new DateTime($leave['start_date']);
                $end = new DateTime($leave['end_date']);
                $end->modify('+1 day'); // Include end date
                
                $interval = new DateInterval('P1D');
                $date_range = new DatePeriod($start, $interval, $end);
                
                foreach ($date_range as $date) {
                    $date_string = $date->format('Y-m-d');
                    $month = $date->format('m');
                    $year = $date->format('Y');
                    
                    // Skip weekends and holidays
                    $weekendOrHoliday = false;
                    
                    // Check if it's a holiday
                    $holidaySql = "SELECT id FROM holidays WHERE holiday_date = ?";
                    $holidayStmt = $conn->prepare($holidaySql);
                    $holidayStmt->bind_param("s", $date_string);
                    $holidayStmt->execute();
                    
                    if ($holidayStmt->get_result()->num_rows > 0) {
                        $weekendOrHoliday = true;
                    }
                    
                    // Check if it's a weekend
                    $weekendSql = "SELECT setting_value FROM attendance_settings WHERE setting_key = 'weekend_days'";
                    $weekendResult = $conn->query($weekendSql);
                    
                    if ($weekendResult->num_rows > 0) {
                        $weekendSetting = $weekendResult->fetch_assoc();
                        $weekendDays = explode(',', $weekendSetting['setting_value']);
                        
                        // Get day of week (0 = Sunday, 6 = Saturday)
                        $dayOfWeek = $date->format('w');
                        
                        if (in_array($dayOfWeek, $weekendDays)) {
                            $weekendOrHoliday = true;
                        }
                    }
                    
                    // Skip weekends and holidays
                    if ($weekendOrHoliday) {
                        continue;
                    }
                    
                    // Update or insert attendance summary record
                    $checkSql = "SELECT id FROM attendance_summary 
                               WHERE user_id = ? 
                               AND user_type = ? 
                               AND summary_date = ?";
                    
                    $checkStmt = $conn->prepare($checkSql);
                    $checkStmt->bind_param("iss", $leave['user_id'], $leave['user_type'], $date_string);
                    $checkStmt->execute();
                    $checkResult = $checkStmt->get_result();
                    
                    if ($checkResult->num_rows > 0) {
                        // Update existing record
                        $updateSummarySql = "UPDATE attendance_summary 
                                          SET status = 'leave', 
                                              leave_type_id = ? 
                                          WHERE user_id = ? 
                                          AND user_type = ? 
                                          AND summary_date = ?";
                        
                        $updateSummaryStmt = $conn->prepare($updateSummarySql);
                        $updateSummaryStmt->bind_param("iiss", $leave['leave_type_id'], $leave['user_id'], $leave['user_type'], $date_string);
                        $updateSummaryStmt->execute();
                    } else {
                        // Insert new record
                        $insertSummarySql = "INSERT INTO attendance_summary 
                                          (user_id, user_type, summary_date, month, year, status, leave_type_id) 
                                          VALUES (?, ?, ?, ?, ?, 'leave', ?)";
                        
                        $insertSummaryStmt = $conn->prepare($insertSummarySql);
                        $insertSummaryStmt->bind_param("issiii", $leave['user_id'], $leave['user_type'], $date_string, $month, $year, $leave['leave_type_id']);
                        $insertSummaryStmt->execute();
                    }
                }
                
                // Add notification
                $notifSql = "INSERT INTO notifications (user_id, title, content, type) 
                           VALUES (?, 'Leave Approved', 'Your leave request has been approved', 'leave')";
                $notifStmt = $conn->prepare($notifSql);
                $notifStmt->bind_param("i", $leave['user_id']);
                $notifStmt->execute();
                
                $_SESSION['success_message'] = "Leave request has been approved successfully";
            } else {
                $_SESSION['error_message'] = "Failed to approve leave request";
            }
        } elseif ($action === 'reject') {
            // Update leave request status to rejected
            $updateSql = "UPDATE leave_requests 
                         SET status = 'rejected', 
                             approved_by = ?, 
                             rejection_reason = ?, 
                             updated_at = NOW() 
                         WHERE id = ?";
            $stmt = $conn->prepare($updateSql);
            $stmt->bind_param("isi", $user_id, $rejection_reason, $leave_id);
            $result = $stmt->execute();
            
            if ($result) {
                // Get leave request details
                $leaveSql = "SELECT user_id FROM leave_requests WHERE id = ?";
                $leaveStmt = $conn->prepare($leaveSql);
                $leaveStmt->bind_param("i", $leave_id);
                $leaveStmt->execute();
                $leaveResult = $leaveStmt->get_result();
                $leave = $leaveResult->fetch_assoc();
                
                // Add notification
                $notifSql = "INSERT INTO notifications (user_id, title, message, type) 
                           VALUES (?, 'Leave Rejected', 'Your leave request has been rejected: $rejection_reason', 'leave')";
                $notifStmt = $conn->prepare($notifSql);
                $notifStmt->bind_param("i", $leave['user_id']);
                $notifStmt->execute();
                
                $_SESSION['success_message'] = "Leave request has been rejected";
            } else {
                $_SESSION['error_message'] = "Failed to reject leave request";
            }
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: manage_leaves.php");
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$month_filter = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year_filter = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Build query conditions
$conditions = [];
$params = [];
$types = "";

// Apply status filter
if (!empty($status_filter)) {
    $conditions[] = "lr.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Apply date filters
if ($month_filter > 0 && $year_filter > 0) {
    $conditions[] = "(
        (MONTH(lr.start_date) = ? AND YEAR(lr.start_date) = ?) OR 
        (MONTH(lr.end_date) = ? AND YEAR(lr.end_date) = ?) OR
        (lr.start_date <= LAST_DAY(?) AND lr.end_date >= ?)
    )";
    $start_date = "$year_filter-$month_filter-01";
    $params[] = $month_filter;
    $params[] = $year_filter;
    $params[] = $month_filter;
    $params[] = $year_filter;
    $params[] = $start_date;
    $params[] = $start_date;
    $types .= "iiiiss";
}

// Get leave requests
$sql = "SELECT 
          lr.*,
          CASE 
            WHEN lr.user_type = 'student' THEN s.name 
            ELSE u.name 
          END AS user_name,
          lt.type_name as leave_type,
          a.name as approved_by_name
        FROM leave_requests lr
        LEFT JOIN users u ON lr.user_id = u.id AND lr.user_type != 'student'
        LEFT JOIN students s ON lr.user_id = s.id AND lr.user_type = 'student'
        LEFT JOIN leave_types lt ON lr.leave_type_id = lt.id
        LEFT JOIN users a ON lr.approved_by = a.id
        " . (!empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "") . "
        ORDER BY lr.created_at DESC";

if (!empty($conditions)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

$leave_requests = [];
while ($row = $result->fetch_assoc()) {
    $leave_requests[] = $row;
}

// Get leave types for filter
$leaveTypesSql = "SELECT * FROM leave_types";
$leaveTypesResult = $conn->query($leaveTypesSql);
$leaveTypes = [];
while ($type = $leaveTypesResult->fetch_assoc()) {
    $leaveTypes[] = $type;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Leave Requests - MAKTAB-E-IQRA</title>
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
    <style>
        .status-badge {
            font-size: 0.8rem;
            padding: 0.3rem 0.5rem;
        }
        .leave-card {
            transition: all 0.3s ease;
        }
        .leave-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
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
                        <h5 class="fw-bold text-primary"><i class="fas fa-calendar-minus me-2"></i> Manage Leave Requests</h5>
                        <p class="text-muted">Approve or reject leave requests from staff and students.</p>
                    </div>
                </div>
                
                <?php if(isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
                
                <!-- Filter Options -->
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
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-select">
                                            <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                            <option value="approved" <?php echo ($status_filter == 'approved') ? 'selected' : ''; ?>>Approved</option>
                                            <option value="rejected" <?php echo ($status_filter == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                            <option value="" <?php echo ($status_filter == '') ? 'selected' : ''; ?>>All</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label">Month</label>
                                        <select name="month" class="form-select">
                                            <?php for($m = 1; $m <= 12; $m++): ?>
                                                <option value="<?php echo $m; ?>" <?php echo ($m == $month_filter) ? 'selected' : ''; ?>>
                                                    <?php echo date('F', mktime(0, 0, 0, $m, 10)); ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label">Year</label>
                                        <select name="year" class="form-select">
                                            <?php for($y = date('Y')+1; $y >= date('Y')-2; $y--): ?>
                                                <option value="<?php echo $y; ?>" <?php echo ($y == $year_filter) ? 'selected' : ''; ?>>
                                                    <?php echo $y; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-filter me-2"></i> Apply Filters
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Leave Requests -->
                <div class="row">
                    <?php if(empty($leave_requests)): ?>
                    <div class="col-md-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> No leave requests found with the selected filters.
                        </div>
                    </div>
                    <?php else: ?>
                        <?php foreach($leave_requests as $request): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card shadow-sm leave-card">
                                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 fw-bold">
                                        <i class="fas fa-user me-2"></i> <?php echo $request['user_name']; ?>
                                        <small class="text-muted">(<?php echo ucfirst($request['user_type']); ?>)</small>
                                    </h5>
                                    <?php 
                                    $statusClass = '';
                                    switch($request['status']) {
                                        case 'pending':
                                            $statusClass = 'bg-warning';
                                            break;
                                        case 'approved':
                                            $statusClass = 'bg-success';
                                            break;
                                        case 'rejected':
                                            $statusClass = 'bg-danger';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?> status-badge">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Leave Type:</strong></p>
                                            <p class="mb-0"><?php echo $request['leave_type']; ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Duration:</strong></p>
                                            <p class="mb-0">
                                                <?php 
                                                $start = new DateTime($request['start_date']);
                                                $end = new DateTime($request['end_date']);
                                                $days = $start->diff($end)->days + 1;
                                                
                                                echo date('d M Y', strtotime($request['start_date'])) . 
                                                     ' to ' . 
                                                     date('d M Y', strtotime($request['end_date'])) .
                                                     ' (' . $days . ' days)';
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <p class="mb-1"><strong>Reason:</strong></p>
                                        <p class="mb-0"><?php echo $request['reason']; ?></p>
                                    </div>
                                    
                                    <?php if($request['attachment']): ?>
                                    <div class="mb-3">
                                        <p class="mb-1"><strong>Attachment:</strong></p>
                                        <a href="<?php echo $request['attachment']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                            <i class="fas fa-file me-1"></i> View Document
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if($request['status'] === 'rejected' && !empty($request['rejection_reason'])): ?>
                                    <div class="mb-3">
                                        <p class="mb-1"><strong>Rejection Reason:</strong></p>
                                        <p class="mb-0 text-danger"><?php echo $request['rejection_reason']; ?></p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="mb-0">
                                        <p class="mb-1"><strong>Request Date:</strong></p>
                                        <p class="mb-0"><?php echo date('d M Y, h:i A', strtotime($request['created_at'])); ?></p>
                                    </div>
                                    
                                    <?php if($request['status'] === 'pending'): ?>
                                    <hr>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#approveModal<?php echo $request['id']; ?>">
                                                <i class="fas fa-check me-2"></i> Approve
                                            </button>
                                        </div>
                                        <div class="col-md-6">
                                            <button type="button" class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $request['id']; ?>">
                                                <i class="fas fa-times me-2"></i> Reject
                                            </button>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Approve Modal -->
                        <div class="modal fade" id="approveModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Approve Leave Request</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form method="POST" action="">
                                        <div class="modal-body">
                                            <p>Are you sure you want to approve this leave request for <strong><?php echo $request['user_name']; ?></strong>?</p>
                                            <input type="hidden" name="leave_id" value="<?php echo $request['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-success">Approve Leave</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Reject Modal -->
                        <div class="modal fade" id="rejectModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Reject Leave Request</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form method="POST" action="">
                                        <div class="modal-body">
                                            <p>Are you sure you want to reject this leave request for <strong><?php echo $request['user_name']; ?></strong>?</p>
                                            <div class="mb-3">
                                                <label for="rejection_reason" class="form-label">Reason for Rejection</label>
                                                <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3" required></textarea>
                                            </div>
                                            <input type="hidden" name="leave_id" value="<?php echo $request['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-danger">Reject Leave</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Enable tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html> 