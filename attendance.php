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
$user_type = $role; // user_type matches role for this application

// Check multiple shifts setting
$multipleShiftsEnabled = false;
$warnIncompleteHours = false;
$settingsSql = "SELECT setting_key, setting_value FROM attendance_settings";
$settingsResult = $conn->query($settingsSql);
$settings = [];
while ($setting = $settingsResult->fetch_assoc()) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
    if ($setting['setting_key'] === 'multiple_shifts_enabled' && $setting['setting_value'] === '1') {
        $multipleShiftsEnabled = true;
    }
    if ($setting['setting_key'] === 'warn_incomplete_hours' && $setting['setting_value'] === '1') {
        $warnIncompleteHours = true;
    }
}

// Get work shifts
$workShifts = [];
if (isset($settings['work_shifts'])) {
    $shiftsData = json_decode($settings['work_shifts'], true);
    if (is_array($shiftsData) && !empty($shiftsData)) {
        $workShifts = $shiftsData;
    }
}

// If no shifts defined, use default
if (empty($workShifts)) {
    $workShifts = [
        ['start' => '09:00', 'end' => '17:00', 'min_hours' => 8]
    ];
}

// Check if there is an active punch-in without a punch-out for today
$today = date('Y-m-d');
$checkSql = "SELECT * FROM attendance_logs 
             WHERE user_id = ? 
             AND user_type = ? 
             AND DATE(punch_in_time) = ? 
             AND punch_out_time IS NULL";
$stmt = $conn->prepare($checkSql);
$stmt->bind_param("iss", $user_id, $user_type, $today);
$stmt->execute();
$result = $stmt->get_result();
$activePunchIn = $result->num_rows > 0 ? $result->fetch_assoc() : null;

// Get all attendance logs for today (for multiple shifts)
$allTodayLogsSql = "SELECT * FROM attendance_logs 
                    WHERE user_id = ? 
                    AND user_type = ? 
                    AND DATE(punch_in_time) = ? 
                    ORDER BY punch_in_time ASC";
$allTodayLogsStmt = $conn->prepare($allTodayLogsSql);
$allTodayLogsStmt->bind_param("iss", $user_id, $user_type, $today);
$allTodayLogsStmt->execute();
$allTodayLogsResult = $allTodayLogsStmt->get_result();
$todayAttendanceLogs = [];
$todayTotalHours = 0;

while ($log = $allTodayLogsResult->fetch_assoc()) {
    $todayAttendanceLogs[] = $log;
    // Calculate total hours for completed punches
    if (!is_null($log['punch_out_time'])) {
        $todayTotalHours += $log['total_hours'];
    }
}

// Find current/next shift
$currentHour = date('H:i');
$currentShift = null;
$nextShift = null;
$shiftsRemaining = [];

foreach ($workShifts as $shift) {
    if ($currentHour >= $shift['start'] && $currentHour <= $shift['end']) {
        $currentShift = $shift;
    } elseif ($currentHour < $shift['start']) {
        $shiftsRemaining[] = $shift;
    }
}

if (!empty($shiftsRemaining)) {
    usort($shiftsRemaining, function($a, $b) {
        return $a['start'] <=> $b['start'];
    });
    $nextShift = $shiftsRemaining[0];
}

// Get office locations for the dropdown
$locationsSql = "SELECT * FROM office_locations WHERE is_active = 1";
$locationsResult = $conn->query($locationsSql);
$locations = [];
while ($loc = $locationsResult->fetch_assoc()) {
    $locations[] = $loc;
}

// Get user's attendance history (last 10 records)
$historySql = "SELECT al.*, ol_in.location_name as punch_in_location, ol_out.location_name as punch_out_location
               FROM attendance_logs al
               LEFT JOIN office_locations ol_in ON al.punch_in_location_id = ol_in.id
               LEFT JOIN office_locations ol_out ON al.punch_out_location_id = ol_out.id
               WHERE al.user_id = ? AND al.user_type = ?
               ORDER BY al.punch_in_time DESC LIMIT 10";
$historyStmt = $conn->prepare($historySql);
$historyStmt->bind_param("is", $user_id, $user_type);
$historyStmt->execute();
$historyResult = $historyStmt->get_result();
$attendanceHistory = [];
while ($record = $historyResult->fetch_assoc()) {
    $attendanceHistory[] = $record;
}

// Get user's current month summary
$month = date('m');
$year = date('Y');
$summarySql = "SELECT 
                COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
                COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
                COUNT(CASE WHEN status = 'leave' THEN 1 END) as leave_count,
                COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count,
                COUNT(CASE WHEN status = 'early_exit' THEN 1 END) as early_exit_count,
                SUM(work_hours) as total_hours
               FROM attendance_summary
               WHERE user_id = ? AND user_type = ? AND month = ? AND year = ?";
$summaryStmt = $conn->prepare($summarySql);
$summaryStmt->bind_param("isii", $user_id, $user_type, $month, $year);
$summaryStmt->execute();
$summaryResult = $summaryStmt->get_result();
$summary = $summaryResult->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/logo.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <style>
        .punch-card {
            transition: all 0.3s ease;
        }
        .punch-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 0.3rem 0.5rem;
        }
        #map {
            height: 300px;
            width: 100%;
            border-radius: 5px;
        }
        .shift-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .shift-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
        }
        .today-log {
            background-color: rgba(0,123,255,0.05);
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
            border-left: 3px solid #007bff;
        }
        .punch-status.active {
            color: #28a745;
        }
        .punch-status.inactive {
            color: #6c757d;
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
            
            <div class="container-fluid p-4">
                <!-- Main Content -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h5 class="fw-bold text-primary"><i class="fas fa-clock me-2"></i> Attendance Management</h5>
                        <p class="text-muted">Track your attendance and manage your time.</p>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <!-- Punch In/Out Card -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card shadow-sm punch-card h-100">
                                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0 fw-bold">
                                            <?php if ($activePunchIn): ?>
                                                <i class="fas fa-sign-out-alt text-danger me-2"></i> Punch Out
                                            <?php else: ?>
                                                <i class="fas fa-sign-in-alt text-success me-2"></i> Punch In
                                            <?php endif; ?>
                                        </h5>
                                        <?php if ($multipleShiftsEnabled): ?>
                                        <span class="badge bg-info">Multiple Shifts Enabled</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($multipleShiftsEnabled): ?>
                                        <div class="mb-3">
                                            <h6 class="fw-bold">Today's Work Shifts</h6>
                                            <?php foreach ($workShifts as $index => $shift): ?>
                                            <div class="shift-info">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <strong>Shift <?php echo $index + 1; ?>: <?php echo $shift['start']; ?> - <?php echo $shift['end']; ?></strong>
                                                    <?php 
                                                    $currentTime = date('H:i');
                                                    $shiftStatus = '';
                                                    $shiftBadgeClass = '';
                                                    
                                                    if ($currentTime < $shift['start']) {
                                                        $shiftStatus = 'Upcoming';
                                                        $shiftBadgeClass = 'bg-secondary';
                                                    } elseif ($currentTime >= $shift['start'] && $currentTime <= $shift['end']) {
                                                        $shiftStatus = 'Current';
                                                        $shiftBadgeClass = 'bg-success';
                                                    } else {
                                                        $shiftStatus = 'Completed';
                                                        $shiftBadgeClass = 'bg-primary';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $shiftBadgeClass; ?> shift-badge"><?php echo $shiftStatus; ?></span>
                                                </div>
                                                <small class="text-muted">Minimum <?php echo $shift['min_hours']; ?> hours</small>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <?php if (!empty($todayAttendanceLogs)): ?>
                                        <div class="mb-3">
                                            <h6 class="fw-bold">Today's Attendance</h6>
                                            <?php foreach ($todayAttendanceLogs as $log): ?>
                                            <div class="today-log">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <span class="punch-status active">
                                                            <i class="fas fa-sign-in-alt me-1"></i> 
                                                            <?php echo date('h:i A', strtotime($log['punch_in_time'])); ?>
                                                        </span>
                                                        <?php if ($log['punch_out_time']): ?>
                                                        <span class="ms-2 me-2">→</span>
                                                        <span class="punch-status active">
                                                            <i class="fas fa-sign-out-alt me-1"></i> 
                                                            <?php echo date('h:i A', strtotime($log['punch_out_time'])); ?>
                                                        </span>
                                                        <?php else: ?>
                                                        <span class="ms-2 me-2">→</span>
                                                        <span class="punch-status inactive">
                                                            <i class="fas fa-hourglass-half me-1"></i> Active
                                                        </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($log['punch_out_time']): ?>
                                                    <span class="badge bg-primary">
                                                        <?php echo number_format($log['total_hours'], 1); ?> hrs
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                            <div class="mt-2 mb-3">
                                                <span class="fw-bold">Total Hours Today:</span> 
                                                <span class="ms-2 badge bg-success"><?php echo number_format($todayTotalHours, 1); ?> hours</span>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <?php if ($activePunchIn): ?>
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i> You punched in at 
                                                <strong><?php echo date('h:i A', strtotime($activePunchIn['punch_in_time'])); ?></strong>
                                                
                                                <?php if ($multipleShiftsEnabled && $currentShift): ?>
                                                <div class="mt-2">
                                                    <small>Current Shift: <?php echo $currentShift['start']; ?> - <?php echo $currentShift['end']; ?></small>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <form id="punchOutForm">
                                                <input type="hidden" name="log_id" value="<?php echo $activePunchIn['id']; ?>">
                                                <input type="hidden" name="shift_min_hours" value="<?php echo $currentShift ? $currentShift['min_hours'] : '0'; ?>">
                                                <input type="hidden" name="warn_incomplete_hours" value="<?php echo $warnIncompleteHours ? '1' : '0'; ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Office Location</label>
                                                    <select name="location_id" class="form-select" required>
                                                        <option value="">Select Location</option>
                                                        <?php foreach ($locations as $location): ?>
                                                        <option value="<?php echo $location['id']; ?>"><?php echo $location['location_name']; ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Notes (Optional)</label>
                                                    <textarea name="notes" class="form-control" rows="2" placeholder="Add any notes about your day..."></textarea>
                                                </div>
                                                
                                                <div class="d-grid">
                                                    <button type="submit" class="btn btn-danger" id="punchOutBtn">
                                                        <i class="fas fa-sign-out-alt me-2"></i> Punch Out
                                                    </button>
                                                </div>
                                            </form>
                                        <?php else: ?>
                                            <?php if ($multipleShiftsEnabled && $currentShift): ?>
                                            <div class="alert alert-info mb-3">
                                                <i class="fas fa-info-circle me-2"></i> Current Shift: <strong><?php echo $currentShift['start']; ?> - <?php echo $currentShift['end']; ?></strong>
                                            </div>
                                            <?php elseif ($multipleShiftsEnabled && $nextShift): ?>
                                            <div class="alert alert-warning mb-3">
                                                <i class="fas fa-clock me-2"></i> Next Shift starts at <strong><?php echo $nextShift['start']; ?></strong>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <form id="punchInForm">
                                                <div class="mb-3">
                                                    <label class="form-label">Office Location</label>
                                                    <select name="location_id" class="form-select" required>
                                                        <option value="">Select Location</option>
                                                        <?php foreach ($locations as $location): ?>
                                                        <option value="<?php echo $location['id']; ?>"><?php echo $location['location_name']; ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                
                                                <div class="d-grid">
                                                    <button type="submit" class="btn btn-success" id="punchInBtn">
                                                        <i class="fas fa-sign-in-alt me-2"></i> Punch In
                                                    </button>
                                                </div>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <!-- Current Month Summary -->
                                <div class="card shadow-sm h-100">
                                    <div class="card-header bg-white py-3">
                                        <h5 class="mb-0 fw-bold">
                                            <i class="fas fa-calendar-check me-2"></i> <?php echo date('F Y'); ?> Summary
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-4">
                                                <div class="bg-light p-3 rounded text-center">
                                                    <h3 class="mb-0 text-success"><?php echo $summary['present_count'] ?? 0; ?></h3>
                                                    <small class="text-muted">Present</small>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="bg-light p-3 rounded text-center">
                                                    <h3 class="mb-0 text-danger"><?php echo $summary['absent_count'] ?? 0; ?></h3>
                                                    <small class="text-muted">Absent</small>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="bg-light p-3 rounded text-center">
                                                    <h3 class="mb-0 text-warning"><?php echo $summary['leave_count'] ?? 0; ?></h3>
                                                    <small class="text-muted">Leave</small>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="bg-light p-3 rounded text-center">
                                                    <h3 class="mb-0 text-info"><?php echo $summary['late_count'] ?? 0; ?></h3>
                                                    <small class="text-muted">Late Arrivals</small>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="bg-light p-3 rounded text-center">
                                                    <h3 class="mb-0 text-primary"><?php echo number_format($summary['total_hours'] ?? 0, 1); ?></h3>
                                                    <small class="text-muted">Total Hours</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-grid mt-3">
                                            <a href="attendance_report.php" class="btn btn-outline-primary">
                                                <i class="fas fa-chart-bar me-2"></i> View Detailed Reports
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <!-- Office Location Map -->
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-map-marker-alt me-2"></i> Office Locations
                                </h5>
                            </div>
                            <div class="card-body">
                                <div id="map" class="mb-3"></div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th>Location</th>
                                                <th>Address</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($locations as $location): ?>
                                            <tr>
                                                <td><?php echo $location['location_name']; ?></td>
                                                <td><?php echo $location['address']; ?></td>
                                                <td>
                                                    <span class="badge bg-success">Active</span>
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
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-history me-2"></i> Recent Attendance
                                </h5>
                                <a href="attendance_history.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Punch In</th>
                                                <th>Punch Out</th>
                                                <th>Location</th>
                                                <th>Hours</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($attendanceHistory)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center">No attendance records found</td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($attendanceHistory as $record): ?>
                                                <tr>
                                                    <td><?php echo date('d M Y', strtotime($record['punch_in_time'])); ?></td>
                                                    <td><?php echo date('h:i A', strtotime($record['punch_in_time'])); ?></td>
                                                    <td>
                                                        <?php 
                                                        echo $record['punch_out_time'] 
                                                            ? date('h:i A', strtotime($record['punch_out_time'])) 
                                                            : '<span class="text-muted">-</span>'; 
                                                        ?>
                                                    </td>
                                                    <td><?php echo $record['punch_in_location']; ?></td>
                                                    <td>
                                                        <?php 
                                                        echo $record['total_hours'] 
                                                            ? number_format($record['total_hours'], 1) 
                                                            : '<span class="text-muted">-</span>';
                                                        ?>
                                                    </td>
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
                                                            default:
                                                                $statusClass = 'bg-primary';
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $statusClass; ?> status-badge">
                                                            <?php echo ucfirst($record['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <!-- Leave Request Card -->
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-calendar-minus me-2"></i> Request Leave
                                </h5>
                            </div>
                            <div class="card-body">
                                <form action="process_leave_request.php" method="POST" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label class="form-label">Leave Type</label>
                                        <select name="leave_type_id" class="form-select" required>
                                            <option value="">Select Leave Type</option>
                                            <?php 
                                            $leaveTypesSql = "SELECT * FROM leave_types";
                                            $leaveTypesResult = $conn->query($leaveTypesSql);
                                            while ($type = $leaveTypesResult->fetch_assoc()):
                                            ?>
                                            <option value="<?php echo $type['id']; ?>"><?php echo $type['type_name']; ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Start Date</label>
                                            <input type="date" name="start_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">End Date</label>
                                            <input type="date" name="end_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Reason</label>
                                        <textarea name="reason" class="form-control" rows="3" required></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Attachment (Optional)</label>
                                        <input type="file" name="attachment" class="form-control">
                                        <small class="text-muted">Upload any supporting documents (PDF, JPG, PNG)</small>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-2"></i> Submit Leave Request
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <!-- Office Location Map -->
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-map-marker-alt me-2"></i> Office Locations
                                </h5>
                            </div>
                            <div class="card-body">
                                <div id="map" class="mb-3"></div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th>Location</th>
                                                <th>Address</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($locations as $location): ?>
                                            <tr>
                                                <td><?php echo $location['location_name']; ?></td>
                                                <td><?php echo $location['address']; ?></td>
                                                <td>
                                                    <span class="badge bg-success">Active</span>
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
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&callback=initMap" async defer></script>
    <script>
        // Map initialization
        function initMap() {
            const mapElement = document.getElementById('map');
            
            if (!mapElement) return;
            
            const map = new google.maps.Map(mapElement, {
                center: { lat: 19.0760, lng: 72.8777 }, // Default to Mumbai
                zoom: 12
            });
            
            // Add markers for office locations
            const locations = <?php echo json_encode($locations); ?>;
            
            locations.forEach(location => {
                const marker = new google.maps.Marker({
                    position: { 
                        lat: parseFloat(location.latitude), 
                        lng: parseFloat(location.longitude) 
                    },
                    map: map,
                    title: location.location_name
                });
                
                // Add circle for geofencing radius
                const circle = new google.maps.Circle({
                    map: map,
                    center: { 
                        lat: parseFloat(location.latitude), 
                        lng: parseFloat(location.longitude) 
                    },
                    radius: parseInt(location.radius_meters),
                    fillColor: '#4285F4',
                    fillOpacity: 0.2,
                    strokeColor: '#4285F4',
                    strokeOpacity: 0.8,
                    strokeWeight: 1
                });
            });
        }
        
        $(document).ready(function() {
            // Calculate expected hours
            function calculateExpectedHours() {
                const punchInTime = new Date('<?php echo $activePunchIn ? $activePunchIn['punch_in_time'] : ''; ?>');
                const currentTime = new Date();
                
                // Return hours difference
                const diffTime = Math.abs(currentTime - punchInTime);
                const diffHours = diffTime / (1000 * 60 * 60);
                
                return diffHours;
            }
            
            // Handle Punch Out form submission
            $('#punchOutForm').submit(function(e) {
                e.preventDefault();
                
                // Check for incomplete hours
                const warnIncompleteHours = $('input[name="warn_incomplete_hours"]').val() === '1';
                const minHours = parseFloat($('input[name="shift_min_hours"]').val()) || 0;
                const hoursWorked = calculateExpectedHours();
                
                if (warnIncompleteHours && minHours > 0 && hoursWorked < minHours) {
                    if (!confirm(`Warning: You've only worked ${hoursWorked.toFixed(1)} hours out of the minimum ${minHours} hours required for this shift. Are you sure you want to punch out?`)) {
                        return;
                    }
                }
                
                // Show loading state
                $('#punchOutBtn').html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Processing...');
                $('#punchOutBtn').prop('disabled', true);
                
                // Get current location
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            // Add location to form data
                            const formData = new FormData();
                            const formInputs = $('#punchOutForm').serializeArray();
                            
                            // Add form fields
                            formInputs.forEach(input => {
                                formData.append(input.name, input.value);
                            });
                            
                            // Add geolocation data
                            formData.append('latitude', position.coords.latitude);
                            formData.append('longitude', position.coords.longitude);
                            formData.append('action', 'punch_out');
                            
                            // Send Ajax request
                            $.ajax({
                                url: 'api/process_attendance.php',
                                type: 'POST',
                                data: formData,
                                processData: false,
                                contentType: false,
                                success: function(response) {
                                    try {
                                        const result = JSON.parse(response);
                                        
                                        if (result.status === 'success') {
                                            alert('Punch out successful!');
                                            window.location.reload();
                                        } else {
                                            alert('Error: ' + result.message);
                                            $('#punchOutBtn').html('<i class="fas fa-sign-out-alt me-2"></i> Punch Out');
                                            $('#punchOutBtn').prop('disabled', false);
                                        }
                                    } catch (e) {
                                        alert('An unexpected error occurred. Please try again.');
                                        $('#punchOutBtn').html('<i class="fas fa-sign-out-alt me-2"></i> Punch Out');
                                        $('#punchOutBtn').prop('disabled', false);
                                    }
                                },
                                error: function() {
                                    alert('An error occurred while processing your request.');
                                    $('#punchOutBtn').html('<i class="fas fa-sign-out-alt me-2"></i> Punch Out');
                                    $('#punchOutBtn').prop('disabled', false);
                                }
                            });
                        },
                        function(error) {
                            // Geolocation error
                            let errorMessage = 'Unknown error';
                            switch(error.code) {
                                case error.PERMISSION_DENIED:
                                    errorMessage = 'Location access denied. Please enable location services to punch out.';
                                    break;
                                case error.POSITION_UNAVAILABLE:
                                    errorMessage = 'Location information is unavailable.';
                                    break;
                                case error.TIMEOUT:
                                    errorMessage = 'Location request timed out.';
                                    break;
                            }
                            
                            alert(errorMessage);
                            $('#punchOutBtn').html('<i class="fas fa-sign-out-alt me-2"></i> Punch Out');
                            $('#punchOutBtn').prop('disabled', false);
                        }
                    );
                } else {
                    alert('Geolocation is not supported by this browser. Please use a modern browser with location services.');
                    $('#punchOutBtn').html('<i class="fas fa-sign-out-alt me-2"></i> Punch Out');
                    $('#punchOutBtn').prop('disabled', false);
                }
            });
            
            // Handle Punch In form submission
            $('#punchInForm').submit(function(e) {
                e.preventDefault();
                
                // Show loading state
                $('#punchInBtn').html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Processing...');
                $('#punchInBtn').prop('disabled', true);
                
                // Get current location
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            // Add location to form data
                            const formData = new FormData();
                            const formInputs = $('#punchInForm').serializeArray();
                            
                            // Add form fields
                            formInputs.forEach(input => {
                                formData.append(input.name, input.value);
                            });
                            
                            // Add geolocation data
                            formData.append('latitude', position.coords.latitude);
                            formData.append('longitude', position.coords.longitude);
                            formData.append('action', 'punch_in');
                            formData.append('multiple_shifts_enabled', '<?php echo $multipleShiftsEnabled ? '1' : '0'; ?>');
                            
                            // Send Ajax request
                            $.ajax({
                                url: 'api/process_attendance.php',
                                type: 'POST',
                                data: formData,
                                processData: false,
                                contentType: false,
                                success: function(response) {
                                    try {
                                        const result = JSON.parse(response);
                                        
                                        if (result.status === 'success') {
                                            alert('Punch in successful!');
                                            window.location.reload();
                                        } else {
                                            alert('Error: ' + result.message);
                                            $('#punchInBtn').html('<i class="fas fa-sign-in-alt me-2"></i> Punch In');
                                            $('#punchInBtn').prop('disabled', false);
                                        }
                                    } catch (e) {
                                        alert('An unexpected error occurred. Please try again.');
                                        $('#punchInBtn').html('<i class="fas fa-sign-in-alt me-2"></i> Punch In');
                                        $('#punchInBtn').prop('disabled', false);
                                    }
                                },
                                error: function() {
                                    alert('An error occurred while processing your request.');
                                    $('#punchInBtn').html('<i class="fas fa-sign-in-alt me-2"></i> Punch In');
                                    $('#punchInBtn').prop('disabled', false);
                                }
                            });
                        },
                        function(error) {
                            // Geolocation error
                            let errorMessage = 'Unknown error';
                            switch(error.code) {
                                case error.PERMISSION_DENIED:
                                    errorMessage = 'Location access denied. Please enable location services to punch in.';
                                    break;
                                case error.POSITION_UNAVAILABLE:
                                    errorMessage = 'Location information is unavailable.';
                                    break;
                                case error.TIMEOUT:
                                    errorMessage = 'Location request timed out.';
                                    break;
                            }
                            
                            alert(errorMessage);
                            $('#punchInBtn').html('<i class="fas fa-sign-in-alt me-2"></i> Punch In');
                            $('#punchInBtn').prop('disabled', false);
                        }
                    );
                } else {
                    alert('Geolocation is not supported by this browser. Please use a modern browser with location services.');
                    $('#punchInBtn').html('<i class="fas fa-sign-in-alt me-2"></i> Punch In');
                    $('#punchInBtn').prop('disabled', false);
                }
            });
        });
    </script>
</body>
</html> 