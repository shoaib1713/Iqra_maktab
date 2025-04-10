<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: index.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$role = $_SESSION['role'];
$year = date("Y");

// Set default academic year
$startYear = $year - 1;
$endYear = $year;

// Handle academic year filter
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['academic_year']) && $_POST['academic_year'] !== '') {
    $selectedYear = $_POST['academic_year'];
    list($startYear, $endYear) = explode("-", $selectedYear);
}
$startMonth = ACEDEMIC_START_MONTH;
$endMonth = ACEDEMIC_END_MONTH;
$academicYear = "$startYear-$endYear";

// Get total fees collected for the teacher's students
$sql_collected = "SELECT COALESCE(SUM(f.amount), 0) AS total_collected 
                 FROM fees f 
                 JOIN students s ON f.student_id = s.id 
                 WHERE s.assigned_teacher = ? 
                 AND f.status = 'paid' 
                 AND ((f.year = ? AND f.month >= ?) OR (f.year = ? AND f.month <= ?))";

$stmt = $conn->prepare($sql_collected);
$stmt->bind_param("iiiii", $teacher_id, $startYear, $startMonth, $endYear, $endMonth);
$stmt->execute();
$result = $stmt->get_result();
$total_collected = $result->fetch_assoc()['total_collected'];
$stmt->close();

// Get total students assigned to the teacher
$countQuery = "SELECT COUNT(*) as total 
              FROM students 
              WHERE assigned_teacher = ? 
              AND is_deleted = 0";
$countStmt = $conn->prepare($countQuery);
$countStmt->bind_param("i", $teacher_id);
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalStudents = $countResult->fetch_assoc()['total'];

// Get total yearly fees for students assigned to the teacher
$sql_yearly = "SELECT COALESCE(SUM(s.annual_fees), 0) AS total_yearly 
              FROM students s 
              WHERE s.assigned_teacher = ? 
              AND s.is_deleted = 0";
$stmt = $conn->prepare($sql_yearly);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$total_yearly = $result->fetch_assoc()['total_yearly'];
$stmt->close();

// Calculate pending fees and collection percentages
$total_pending = $total_yearly - $total_collected;
$collected_percentage = ($total_yearly > 0) ? ($total_collected / $total_yearly) * 100 : 0;
$pending_percentage = ($total_yearly > 0) ? ($total_pending / $total_yearly) * 100 : 0;

// Get monthly fee collection data for chart
$monthlyDataQuery = "SELECT 
                    MONTH(CONCAT(year, '-', month, '-01')) as month_num,
                    MONTHNAME(CONCAT(year, '-', month, '-01')) as month_name,
                    SUM(amount) as amount
                    FROM fees
                    JOIN students ON fees.student_id = students.id
                    WHERE students.assigned_teacher = ?
                    AND fees.status = 'paid'
                    AND ((fees.year = ? AND fees.month >= ?) OR (fees.year = ? AND fees.month <= ?))
                    GROUP BY month_num, month_name
                    ORDER BY month_num";
$monthlyStmt = $conn->prepare($monthlyDataQuery);
$monthlyStmt->bind_param("iiiii", $teacher_id, $startYear, $startMonth, $endYear, $endMonth);
$monthlyStmt->execute();
$monthlyResult = $monthlyStmt->get_result();

$months = [];
$amounts = [];
while ($row = $monthlyResult->fetch_assoc()) {
    $months[] = $row['month_name'];
    $amounts[] = $row['amount'];
}

// Get student list with fee status
$studentQuery = "SELECT 
                s.id, 
                s.name, 
                s.id as roll_no, 
                s.annual_fees,
                COALESCE(f.collected, 0) as collected_fees
                FROM students s
                LEFT JOIN (
                    SELECT student_id, SUM(amount) as collected
                    FROM fees
                    WHERE status = 'paid'
                    AND ((year = ? AND month >= ?) OR (year = ? AND month <= ?))
                    GROUP BY student_id
                ) f ON s.id = f.student_id
                WHERE s.assigned_teacher = ? AND s.is_deleted = 0
                ORDER BY s.name";
$studentStmt = $conn->prepare($studentQuery);
$studentStmt->bind_param("iiiii", $startYear, $startMonth, $endYear, $endMonth, $teacher_id);
$studentStmt->execute();
$studentResult = $studentStmt->get_result();

// Get active announcements
$announcementQuery = "SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 5";
$announcementStmt = $conn->prepare($announcementQuery);
$announcementStmt->execute();
$announcementResult = $announcementStmt->get_result();
$announcements = [];
while ($row = $announcementResult->fetch_assoc()) {
    $announcements[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/logo.png">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        .card {
            transition: all 0.3s ease;
            border: none;
            border-radius: 10px;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
        }
        .stat-card {
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            z-index: -1;
        }
        .stat-card .icon {
            font-size: 2rem;
            opacity: 0.8;
        }
        .progress-bar-thin {
            height: 8px;
            border-radius: 4px;
        }
        .student-list tbody tr:hover {
            background-color: rgba(13, 110, 253, 0.05);
        }
        .announcement-card {
            max-height: 300px;
            overflow-y: auto;
        }
        .announcement-item {
            border-left: 3px solid #0d6efd;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        /* Punch Card Styles */
        .punch-card {
            transition: all 0.3s ease;
            border-radius: 10px;
        }
        .punch-card:hover {
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
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
            <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4 rounded">
                <div class="container-fluid">
                    <button class="btn" id="menu-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="ms-2">
                        <?php if (!empty($announcements)): ?>
                        <marquee class="d-block w-100 text-secondary fw-bold"><?php echo htmlspecialchars($announcements[0]['content']); ?></marquee>
                        <?php else: ?>
                        <span class="navbar-brand ms-2">Teacher Dashboard</span>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex align-items-center">
                        <?php include 'includes/notification_bell.php'; ?>
                        <div class="dropdown">
                            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="me-2"><i class="fas fa-user-circle fs-5"></i> <?php echo $user_name; ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="dropdownUser">
                                <li><a class="dropdown-item" href="change_password.php"><i class="fas fa-key me-2"></i> Change Password</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="modules/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container-fluid px-4">
                <!-- Attendance Punch In/Out Card -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold text-primary">
                                    <i class="fas fa-clock me-2"></i> Attendance Tracker
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <?php
                                        // Check if there is an active punch-in without a punch-out for today
                                        $today = date('Y-m-d');
                                        $checkSql = "SELECT * FROM attendance_logs 
                                                    WHERE user_id = ? 
                                                    AND user_type = ? 
                                                    AND DATE(punch_in_time) = ? 
                                                    AND punch_out_time IS NULL";
                                        $stmt = $conn->prepare($checkSql);
                                        $stmt->bind_param("iss", $teacher_id, $role, $today);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        $activePunchIn = $result->num_rows > 0 ? $result->fetch_assoc() : null;

                                        // Get all attendance logs for today
                                        $allTodayLogsSql = "SELECT * FROM attendance_logs 
                                                           WHERE user_id = ? 
                                                           AND user_type = ? 
                                                           AND DATE(punch_in_time) = ? 
                                                           ORDER BY punch_in_time ASC";
                                        $allTodayLogsStmt = $conn->prepare($allTodayLogsSql);
                                        $allTodayLogsStmt->bind_param("iss", $teacher_id, $role, $today);
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

                                        // Get office locations for the dropdown
                                        $locationsSql = "SELECT * FROM office_locations WHERE is_active = 1";
                                        $locationsResult = $conn->query($locationsSql);
                                        $locations = [];
                                        while ($loc = $locationsResult->fetch_assoc()) {
                                            $locations[] = $loc;
                                        }
                                        ?>
                                        <!-- Punch In/Out Form -->
                                        <div class="card punch-card h-100 border">
                                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0 fw-bold">
                                                    <?php if ($activePunchIn): ?>
                                                        <i class="fas fa-sign-out-alt text-danger me-2"></i> Punch Out
                                                    <?php else: ?>
                                                        <i class="fas fa-sign-in-alt text-success me-2"></i> Punch In
                                                    <?php endif; ?>
                                                </h6>
                                            </div>
                                            <div class="card-body">
                                                <?php if ($activePunchIn): ?>
                                                    <div class="alert alert-info">
                                                        <i class="fas fa-info-circle me-2"></i> You punched in at 
                                                        <strong><?php echo date('h:i A', strtotime($activePunchIn['punch_in_time'])); ?></strong>
                                                        <div class="mt-2 small text-muted">Server time now: <?php echo date('h:i A'); ?></div>
                                                        <div class="mt-1 small text-muted">
                                                            <details>
                                                                <summary>Debug Info</summary>
                                                                Raw punch time: <?php echo $activePunchIn['punch_in_time']; ?><br>
                                                                Timezone: <?php echo date_default_timezone_get(); ?>
                                                            </details>
                                                        </div>
                                                    </div>
                                                    <form id="punchOutForm">
                                                        <input type="hidden" name="log_id" value="<?php echo $activePunchIn['id']; ?>">
                                                        
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
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Notes (Optional)</label>
                                                            <textarea name="notes" class="form-control" rows="2" placeholder="Add any notes about your day..."></textarea>
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
                                    
                                    <div class="col-md-8">
                                        <div class="card h-100 border">
                                            <div class="card-header bg-white py-3">
                                                <h6 class="mb-0 fw-bold">Today's Attendance</h6>
                                                <div class="small text-muted">Current Server Time: <?php echo date('h:i A'); ?> (<?php echo date('e'); ?>)</div>
                                            </div>
                                            <div class="card-body">
                                                <?php if (!empty($todayAttendanceLogs)): ?>
                                                <div class="table-responsive">
                                                    <table class="table table-bordered table-hover">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>Punch In</th>
                                                                <th>Punch Out</th>
                                                                <th>Location</th>
                                                                <th>Hours</th>
                                                                <th>Status</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($todayAttendanceLogs as $log): ?>
                                                            <tr>
                                                                <td>
                                                                    <i class="fas fa-sign-in-alt text-success me-1"></i>
                                                                    <?php echo date('h:i A', strtotime($log['punch_in_time'])); ?>
                                                                    <div class="small text-muted">(<?php echo date('H:i', strtotime($log['punch_in_time'])); ?>)</div>
                                                                </td>
                                                                <td>
                                                                    <?php if ($log['punch_out_time']): ?>
                                                                    <i class="fas fa-sign-out-alt text-danger me-1"></i>
                                                                    <?php echo date('h:i A', strtotime($log['punch_out_time'])); ?>
                                                                    <div class="small text-muted">(<?php echo date('H:i', strtotime($log['punch_out_time'])); ?>)</div>
                                                                    <?php else: ?>
                                                                    <span class="text-muted"><i class="fas fa-hourglass-half me-1"></i> Active</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php
                                                                    // Get location name
                                                                    if ($log['punch_in_location_id']) {
                                                                        $locSql = "SELECT location_name FROM office_locations WHERE id = ?";
                                                                        $locStmt = $conn->prepare($locSql);
                                                                        $locStmt->bind_param("i", $log['punch_in_location_id']);
                                                                        $locStmt->execute();
                                                                        $locResult = $locStmt->get_result();
                                                                        if ($locResult->num_rows > 0) {
                                                                            echo $locResult->fetch_assoc()['location_name'];
                                                                        } else {
                                                                            echo "Unknown";
                                                                        }
                                                                    } else {
                                                                        echo "Not specified";
                                                                    }
                                                                    ?>
                                                                </td>
                                                                <td>
                                                                    <?php if ($log['punch_out_time']): ?>
                                                                    <span class="badge bg-primary"><?php echo number_format($log['total_hours'], 1); ?> hrs</span>
                                                                    <?php else: ?>
                                                                    <span class="text-muted">-</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php
                                                                    if ($log['status'] == 'present') {
                                                                        echo '<span class="badge bg-success">Present</span>';
                                                                    } elseif ($log['status'] == 'late') {
                                                                        echo '<span class="badge bg-warning">Late</span>';
                                                                    } else {
                                                                        echo '<span class="badge bg-secondary">Pending</span>';
                                                                    }
                                                                    ?>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="mt-2">
                                                    <strong>Total Hours Today:</strong> 
                                                    <span class="badge bg-success"><?php echo number_format($todayTotalHours, 1); ?> hours</span>
                                                </div>
                                                <?php else: ?>
                                                <div class="alert alert-info text-center">
                                                    <i class="fas fa-info-circle me-2"></i> No attendance records for today.
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Academic Year Filter -->
                <!-- <div class="row mb-4">
                    <div class="col-12">
                        <form method="POST" action="" class="bg-white p-3 rounded shadow-sm">
                            <div class="row g-2">
                                <div class="col-md-10">
                                    <select name="academic_year" class="form-select">
                                        <option value="">Select Academic Year</option>
                                        <?php
                                        // $currentYear = date('Y');
                                        // for ($i = 0; $i < 5; $i++) {
                                        //     $year = $currentYear - $i;
                                        //     $nextYear = $year + 1;
                                        //     $selected = ($startYear == $year) ? 'selected' : '';
                                        //     echo "<option value='$year-$nextYear' $selected>$year-$nextYear</option>";
                                        // }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-filter me-1"></i> Apply
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div> -->
                
                <!-- Stats Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-lg-3 col-md-6">
                        <div class="card stat-card shadow-sm h-100 p-3 bg-primary bg-opacity-10">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Total Students</h6>
                                        <h4 class="fw-bold mb-0"><?php echo $totalStudents; ?></h4>
                                    </div>
                                    <div class="icon text-primary">
                                        <i class="fas fa-user-graduate"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="card stat-card shadow-sm h-100 p-3 bg-danger bg-opacity-10">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Total Fees</h6>
                                        <h4 class="fw-bold mb-0">₹<?php echo number_format($total_yearly); ?></h4>
                                    </div>
                                    <div class="icon text-danger">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="card stat-card shadow-sm h-100 p-3 bg-success bg-opacity-10">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Collected Fees</h6>
                                        <h4 class="fw-bold mb-0">₹<?php echo number_format($total_collected); ?></h4>
                                    </div>
                                    <div class="icon text-success">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="card stat-card shadow-sm h-100 p-3 bg-warning bg-opacity-10">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-1">Pending Fees</h6>
                                        <h4 class="fw-bold mb-0">₹<?php echo number_format($total_pending); ?></h4>
                                    </div>
                                    <div class="icon text-warning">
                                        <i class="fas fa-hourglass-half"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Attendance Stats Card -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold text-primary">
                                    <i class="fas fa-calendar-check me-2"></i> Monthly Attendance Statistics
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php
                                // Get current month's attendance summary
                                $currentMonth = date('m');
                                $currentYear = date('Y');
                                $attendanceSql = "SELECT 
                                                COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
                                                COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
                                                COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count,
                                                COUNT(CASE WHEN status = 'early_exit' THEN 1 END) as early_exit_count,
                                                SUM(work_hours) as total_hours
                                                FROM attendance_summary
                                                WHERE user_id = ? AND user_type = ? AND month = ? AND year = ?";
                                $attendanceStmt = $conn->prepare($attendanceSql);
                                $attendanceStmt->bind_param("isii", $teacher_id, $role, $currentMonth, $currentYear);
                                $attendanceStmt->execute();
                                $attendanceResult = $attendanceStmt->get_result();
                                $monthStats = $attendanceResult->fetch_assoc();
                                
                                // Calculate working days in current month
                                $totalDays = date('t'); // Total days in current month
                                $monthName = date('F');
                                
                                // Get weekend days
                                $weekendSql = "SELECT setting_value FROM attendance_settings WHERE setting_key = 'weekend_days'";
                                $weekendResult = $conn->query($weekendSql);
                                if ($weekendResult->num_rows === 0) {
                                    // Default to Sunday (0) and Saturday (6) if setting not found
                                    $weekendDays = [0, 6];
                                } else {
                                    $weekendSetting = $weekendResult->fetch_assoc();
                                    $weekendDays = explode(',', $weekendSetting['setting_value']);
                                }
                                
                                // Calculate attendance percentage
                                $workDays = 0;
                                for ($i = 1; $i <= $totalDays; $i++) {
                                    $date = date('Y-m-' . str_pad($i, 2, '0', STR_PAD_LEFT));
                                    $dayOfWeek = date('w', strtotime($date));
                                    if (!in_array($dayOfWeek, $weekendDays)) {
                                        $workDays++;
                                    }
                                }
                                
                                $presentDays = $monthStats['present_count'] ?? 0;
                                $absentDays = $monthStats['absent_count'] ?? 0;
                                $lateDays = $monthStats['late_count'] ?? 0;
                                $earlyExitDays = $monthStats['early_exit_count'] ?? 0;
                                $totalHours = $monthStats['total_hours'] ?? 0;
                                
                                $attendancePercentage = ($workDays > 0) ? ($presentDays / $workDays) * 100 : 0;
                                ?>
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="d-flex justify-content-between mb-2">
                                            <h6 class="fw-bold">Attendance Overview (<?php echo $monthName; ?>)</h6>
                                            <span class="badge bg-<?php echo ($attendancePercentage >= 90) ? 'success' : (($attendancePercentage >= 75) ? 'warning' : 'danger'); ?>">
                                                <?php echo round($attendancePercentage, 1); ?>%
                                            </span>
                                        </div>
                                        <div class="progress mb-3" style="height: 10px;">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $attendancePercentage; ?>%" aria-valuenow="<?php echo $attendancePercentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>Working Days:</span>
                                            <span class="fw-bold"><?php echo $workDays; ?> days</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>Present Days:</span>
                                            <span class="fw-bold text-success"><?php echo $presentDays; ?> days</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>Absent Days:</span>
                                            <span class="fw-bold text-danger"><?php echo $absentDays; ?> days</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>Late Arrivals:</span>
                                            <span class="fw-bold text-warning"><?php echo $lateDays; ?> days</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>Early Exits:</span>
                                            <span class="fw-bold text-info"><?php echo $earlyExitDays; ?> days</span>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-8">
                                        <div class="card h-100 bg-light">
                                            <div class="card-body">
                                                <div class="row g-3">
                                                    <div class="col-md-3 col-6">
                                                        <div class="text-center bg-white p-3 rounded shadow-sm">
                                                            <div class="display-6 text-success mb-2"><?php echo $presentDays; ?></div>
                                                            <div class="text-muted small">Present</div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <div class="text-center bg-white p-3 rounded shadow-sm">
                                                            <div class="display-6 text-danger mb-2"><?php echo $absentDays; ?></div>
                                                            <div class="text-muted small">Absent</div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <div class="text-center bg-white p-3 rounded shadow-sm">
                                                            <div class="display-6 text-warning mb-2"><?php echo $lateDays; ?></div>
                                                            <div class="text-muted small">Late</div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3 col-6">
                                                        <div class="text-center bg-white p-3 rounded shadow-sm">
                                                            <div class="display-6 text-info mb-2"><?php echo number_format($totalHours, 1); ?></div>
                                                            <div class="text-muted small">Hours</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="mt-4">
                                                    <h6 class="mb-3">Performance Insights</h6>
                                                    <ul class="list-unstyled">
                                                        <?php if ($attendancePercentage >= 90): ?>
                                                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Your attendance is excellent! Keep up the good work.</li>
                                                        <?php elseif ($attendancePercentage >= 75): ?>
                                                        <li class="mb-2"><i class="fas fa-info-circle text-warning me-2"></i> Your attendance is good but there's room for improvement.</li>
                                                        <?php else: ?>
                                                        <li class="mb-2"><i class="fas fa-exclamation-circle text-danger me-2"></i> Your attendance needs improvement.</li>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($lateDays > 0): ?>
                                                        <li class="mb-2"><i class="fas fa-clock text-warning me-2"></i> You've been late <?php echo $lateDays; ?> times this month.</li>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($earlyExitDays > 0): ?>
                                                        <li class="mb-2"><i class="fas fa-sign-out-alt text-info me-2"></i> You've left early <?php echo $earlyExitDays; ?> times this month.</li>
                                                        <?php endif; ?>
                                                        
                                                        <li class="mb-2"><i class="fas fa-tachometer-alt text-primary me-2"></i> Total working hours: <strong><?php echo number_format($totalHours, 1); ?> hours</strong></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Collection Progress -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold text-primary">
                                    <i class="fas fa-chart-line me-2"></i> Fee Collection Progress
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Overall Progress</span>
                                    <span class="fw-bold"><?php echo round($collected_percentage, 1); ?>%</span>
                                </div>
                                <div class="progress progress-bar-thin mb-4">
                                    <div class="progress-bar" role="progressbar" style="width: <?php echo $collected_percentage; ?>%" aria-valuenow="<?php echo $collected_percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="text-success">Collected: ₹<?php echo number_format($total_collected); ?></span>
                                            <span class="fw-bold text-success"><?php echo round($collected_percentage, 1); ?>%</span>
                                        </div>
                                        <div class="progress progress-bar-thin mb-3">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $collected_percentage; ?>%" aria-valuenow="<?php echo $collected_percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="text-warning">Pending: ₹<?php echo number_format($total_pending); ?></span>
                                            <span class="fw-bold text-warning"><?php echo round($pending_percentage, 1); ?>%</span>
                                        </div>
                                        <div class="progress progress-bar-thin mb-3">
                                            <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $pending_percentage; ?>%" aria-valuenow="<?php echo $pending_percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts and Announcements -->
                <div class="row mb-4">
                    <div class="col-lg-8">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold text-primary">
                                    <i class="fas fa-chart-bar me-2"></i> Monthly Fee Collection (<?php echo $academicYear; ?>)
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="monthlyCollectionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold text-primary">
                                    <i class="fas fa-bullhorn me-2"></i> Announcements
                                </h5>
                            </div>
                            <div class="card-body announcement-card">
                                <?php if (empty($announcements)): ?>
                                    <p class="text-muted text-center">No announcements available</p>
                                <?php else: ?>
                                    <?php foreach ($announcements as $announcement): ?>
                                        <div class="announcement-item">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($announcement['content']); ?></p>
                                            <small class="text-muted"><?php echo date('d M Y', strtotime($announcement['created_at'])); ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Student List with Fee Status -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 fw-bold text-primary">
                                        <i class="fas fa-users me-2"></i> Students & Fee Status
                                    </h5>
                                    <a href="students.php" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-search me-1"></i> View All Students
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover student-list" id="studentTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Roll No</th>
                                                <th>Name</th>
                                                <th>Annual Fees</th>
                                                <th>Collected</th>
                                                <th>Pending</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($studentResult->num_rows > 0): ?>
                                                <?php while ($student = $studentResult->fetch_assoc()): 
                                                    $pendingFees = $student['annual_fees'] - $student['collected_fees'];
                                                    $collectionPercentage = ($student['annual_fees'] > 0) ? ($student['collected_fees'] / $student['annual_fees']) * 100 : 0;
                                                    $statusClass = ($collectionPercentage >= 100) ? 'success' : (($collectionPercentage >= 50) ? 'warning' : 'danger');
                                                ?>
                                                <tr>
                                                    <td><?php echo $student['roll_no']; ?></td>
                                                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                                                    <td>₹<?php echo number_format($student['annual_fees']); ?></td>
                                                    <td>₹<?php echo number_format($student['collected_fees']); ?></td>
                                                    <td>₹<?php echo number_format($pendingFees); ?></td>
                                                    <td>
                                                        <div class="progress" style="height: 6px;">
                                                            <div class="progress-bar bg-<?php echo $statusClass; ?>" role="progressbar" style="width: <?php echo $collectionPercentage; ?>%" aria-valuenow="<?php echo $collectionPercentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                        </div>
                                                        <small class="text-<?php echo $statusClass; ?>"><?php echo round($collectionPercentage, 1); ?>%</small>
                                                    </td>
                                                    <td>
                                                        <a href="student_history.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-outline-info">
                                                            <i class="fas fa-history"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="8" class="text-center">No students assigned</td>
                                                </tr>
                                            <?php endif; ?>
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
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // Sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.getElementById('menu-toggle');
            const wrapper = document.getElementById('wrapper');
            
            if (menuToggle) {
                menuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    wrapper.classList.toggle('toggled');
                });
            }
            
            // Initialize DataTable
            if ($.fn.DataTable) {
                $('#studentTable').DataTable({
                    pageLength: 10,
                    lengthMenu: [5, 10, 25, 50],
                    responsive: true,
                    language: {
                        search: "<i class='fas fa-search'></i>",
                        searchPlaceholder: "Search students..."
                    }
                });
            }
        });
        
        // Chart initialization
        if (document.getElementById('monthlyCollectionChart')) {
            const ctx = document.getElementById('monthlyCollectionChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($months); ?>,
                    datasets: [{
                        label: 'Fee Collection (₹)',
                        data: <?php echo json_encode($amounts); ?>,
                        backgroundColor: 'rgba(13, 110, 253, 0.6)',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₹' + value.toLocaleString();
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Collection: ₹' + context.raw.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>
    <?php include 'includes/notification_styles.php'; ?>
    <?php include 'includes/notification_scripts.php'; ?>
    <script>
        $(document).ready(function() {
            $('#menu-toggle').click(function(e) {
                e.preventDefault();
                $('#wrapper').toggleClass('toggled');
            });
        });
    </script>
    
    <!-- Attendance Punch In/Out Scripts -->
    <script>
        $(document).ready(function() {
            // Show current time for reference
            function updateCurrentTime() {
                const now = new Date();
                const hours = now.getHours();
                const minutes = now.getMinutes();
                const ampm = hours >= 12 ? 'PM' : 'AM';
                const formattedHours = hours % 12 || 12; // Convert to 12-hour format
                const formattedMinutes = minutes < 10 ? '0' + minutes : minutes;
                const timeString = `${formattedHours}:${formattedMinutes} ${ampm}`;
                $('.current-browser-time').text(timeString);
            }
            
            // Add current browser time display
            if(!$('.current-browser-time').length) {
                $('<div class="small text-muted mt-2">Browser Time: <span class="current-browser-time"></span></div>').appendTo('#punchInForm, #punchOutForm');
                updateCurrentTime();
                setInterval(updateCurrentTime, 60000); // Update every minute
            }
            
            // Handle Punch In Form Submission
            $('#punchInForm').submit(function(e) {
                e.preventDefault();
                
                if(confirm('Confirm punch in at ' + $('.current-browser-time').text() + '?')) {
                    // Disable button during processing
                    $('#punchInBtn').prop('disabled', true);
                    $('#punchInBtn').html('<i class="fas fa-spinner fa-spin me-2"></i> Processing...');
                    
                    // Prepare form data
                    var formData = new FormData(this);
                    formData.append('action', 'punch_in');
                    
                    // Get current location if supported
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(
                            function(position) {
                                formData.append('latitude', position.coords.latitude);
                                formData.append('longitude', position.coords.longitude);
                                
                                // Send AJAX request
                                $.ajax({
                                    url: 'api/process_attendance.php',
                                    type: 'POST',
                                    data: formData,
                                    processData: false,
                                    contentType: false,
                                    success: function(response) {
                                        try {
                                            const data = JSON.parse(response);
                                            if (data.status === 'success') {
                                                // Show success message
                                                alert('Successfully punched in!');
                                                // Reload page to refresh data
                                                window.location.reload();
                                            } else {
                                                // Show error message
                                                alert(data.message || 'Failed to punch in.');
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
                }
            });
            
            // Handle Punch Out Form Submission
            $('#punchOutForm').submit(function(e) {
                e.preventDefault();
                
                if(confirm('Confirm punch out at ' + $('.current-browser-time').text() + '?')) {
                    // Disable button during processing
                    $('#punchOutBtn').prop('disabled', true);
                    $('#punchOutBtn').html('<i class="fas fa-spinner fa-spin me-2"></i> Processing...');
                    
                    // Prepare form data
                    var formData = new FormData(this);
                    formData.append('action', 'punch_out');
                    
                    // Get current location if supported
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(
                            function(position) {
                                formData.append('latitude', position.coords.latitude);
                                formData.append('longitude', position.coords.longitude);
                                
                                // Send AJAX request
                                $.ajax({
                                    url: 'api/process_attendance.php',
                                    type: 'POST',
                                    data: formData,
                                    processData: false,
                                    contentType: false,
                                    success: function(response) {
                                        try {
                                            const data = JSON.parse(response);
                                            if (data.status === 'success') {
                                                // Show success message
                                                alert('Successfully punched out!');
                                                // Reload page to refresh data
                                                window.location.reload();
                                            } else {
                                                // Show error message
                                                alert(data.message || 'Failed to punch out.');
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
                }
            });
        });
    </script>
</body>
</html>
