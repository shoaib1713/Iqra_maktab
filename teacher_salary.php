<?php
session_start();
require_once 'config.php';
require 'config/db.php';
require_once 'includes/time_utils.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$role = $_SESSION['role'];

// Get salary settings
$settingsSql = "SELECT setting_key, setting_value FROM salary_settings";
$settingsResult = $conn->query($settingsSql);
$settings = [];
while ($setting = $settingsResult->fetch_assoc()) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

// Get current salary rate
$rateSql = "SELECT * FROM teacher_salary_rates 
            WHERE user_id = ? AND is_active = 1 
            ORDER BY effective_from DESC LIMIT 1";
$rateStmt = $conn->prepare($rateSql);
$rateStmt->bind_param("i", $user_id);
$rateStmt->execute();
$rateResult = $rateStmt->get_result();
$currentRate = $rateResult->fetch_assoc();

// Get latest salary period
$periodSql = "SELECT * FROM salary_periods 
              ORDER BY end_date DESC LIMIT 1";
$periodResult = $conn->query($periodSql);
$currentPeriod = $periodResult->fetch_assoc();

// Get salary calculations for current period if exists
$salaryData = null;
if ($currentPeriod) {
    $salarySql = "SELECT * FROM teacher_salary_calculations 
                  WHERE user_id = ? AND period_id = ?";
    $salaryStmt = $conn->prepare($salarySql);
    $salaryStmt->bind_param("ii", $user_id, $currentPeriod['id']);
    $salaryStmt->execute();
    $salaryResult = $salaryStmt->get_result();
    $salaryData = $salaryResult->fetch_assoc();
}

// Get historical salary data (last 6 periods)
$historySql = "SELECT tsc.*, sp.period_name, sp.start_date, sp.end_date 
               FROM teacher_salary_calculations tsc
               JOIN salary_periods sp ON tsc.period_id = sp.id
               WHERE tsc.user_id = ?
               ORDER BY sp.end_date DESC LIMIT 6";
$historyStmt = $conn->prepare($historySql);
$historyStmt->bind_param("i", $user_id);
$historyStmt->execute();
$historyResult = $historyStmt->get_result();
$salaryHistory = [];
while ($record = $historyResult->fetch_assoc()) {
    $salaryHistory[] = $record;
}

// Get unread notifications
$notificationSql = "SELECT * FROM salary_notifications 
                   WHERE user_id = ? AND is_read = 0
                   ORDER BY created_at DESC";
$notificationStmt = $conn->prepare($notificationSql);
$notificationStmt->bind_param("i", $user_id);
$notificationStmt->execute();
$notificationResult = $notificationStmt->get_result();
$unreadNotifications = [];
while ($notification = $notificationResult->fetch_assoc()) {
    $unreadNotifications[] = $notification;
}

// Mark notifications as read when viewed
if (!empty($unreadNotifications)) {
    $updateSql = "UPDATE salary_notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("i", $user_id);
    $updateStmt->execute();
}

// Get attendance statistics for current period
$attendanceStats = [];
if ($currentPeriod) {
    $attendanceSql = "SELECT 
                    COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
                    COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
                    COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count,
                    COUNT(CASE WHEN status = 'leave' THEN 1 END) as leave_count,
                    SUM(work_hours) as total_hours
                    FROM attendance_summary
                    WHERE user_id = ? AND summary_date BETWEEN ? AND ?";
    $attendanceStmt = $conn->prepare($attendanceSql);
    $attendanceStmt->bind_param("iss", $user_id, $currentPeriod['start_date'], $currentPeriod['end_date']);
    $attendanceStmt->execute();
    $attendanceResult = $attendanceStmt->get_result();
    $attendanceStats = $attendanceResult->fetch_assoc();
}

// Get daily salary calculations for the current month
$dailySalaryData = [];
$currentMonth = date('Y-m');
$dailySalarySql = "SELECT dsc.*, 
                   DATE_FORMAT(dsc.calculation_date, '%d') as day_of_month 
                   FROM daily_salary_calculations dsc 
                   WHERE dsc.teacher_id = ? 
                   AND DATE_FORMAT(dsc.calculation_date, '%Y-%m') = ? 
                   ORDER BY dsc.calculation_date DESC";
$dailySalaryStmt = $conn->prepare($dailySalarySql);
$dailySalaryStmt->bind_param("is", $user_id, $currentMonth);
$dailySalaryStmt->execute();
$dailySalaryResult = $dailySalaryStmt->get_result();
while ($dailyRecord = $dailySalaryResult->fetch_assoc()) {
    $dailySalaryData[] = $dailyRecord;
}

// Get class assignments for the teacher
$assignmentsSql = "SELECT * FROM teacher_class_assignments 
                  WHERE teacher_id = ? AND is_active = 1";
$assignmentsStmt = $conn->prepare($assignmentsSql);
$assignmentsStmt->bind_param("i", $user_id);
$assignmentsStmt->execute();
$assignmentsResult = $assignmentsStmt->get_result();
$classAssignments = [];
$totalClassHours = 0;
while ($assignment = $assignmentsResult->fetch_assoc()) {
    $classAssignments[] = $assignment;
    $totalClassHours += $assignment['class_hours'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Salary - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/logo.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .salary-card {
            transition: all 0.3s ease;
            border-left: 4px solid #4e73df;
        }
        .salary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            font-size: 0.7rem;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 0.3rem 0.5rem;
        }
        .history-item {
            border-left: 3px solid #4e73df;
            background-color: rgba(78, 115, 223, 0.05);
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            transition: all 0.2s ease;
        }
        .history-item:hover {
            background-color: rgba(78, 115, 223, 0.1);
        }
        .notification-card {
            border-left: 4px solid #1cc88a;
            margin-bottom: 10px;
        }
        
        /* Make the content area more responsive */
        @media (max-width: 767.98px) {
            .container-fluid {
                padding-left: 10px !important;
                padding-right: 10px !important;
            }
            
            .row > [class*="col-"] {
                margin-bottom: 20px;
            }
        }
        
        .daily-salary-card {
            border-left: 3px solid #36b9cc;
            transition: all 0.2s ease;
        }
        .daily-salary-card:hover {
            background-color: rgba(54, 185, 204, 0.05);
        }
        .class-badge {
            font-size: 0.75rem;
            background-color: #e5f7fa;
            color: #36b9cc;
            border: 1px solid #36b9cc;
            margin-right: 5px;
            margin-bottom: 5px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Page Content -->
        <div id="page-content-wrapper">
            <?php 
            // Set current page for navbar active state
            $current_page = basename($_SERVER['PHP_SELF']);
            include 'includes/navbar.php'; 
            ?>
            
            <div class="container-fluid px-4 py-4">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h5 class="fw-bold text-primary"><i class="fas fa-money-bill-wave me-2"></i> My Salary</h5>
                        <p class="text-muted">View your salary details and history.</p>
                    </div>
                </div>
                
                <?php if (!empty($unreadNotifications)): ?>
                <!-- Salary Notifications -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold text-success">
                                    <i class="fas fa-bell me-2"></i> New Notifications
                                    <span class="badge bg-danger"><?php echo count($unreadNotifications); ?></span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($unreadNotifications as $notification): ?>
                                <div class="notification-card p-3 rounded">
                                    <h6 class="fw-bold"><?php echo $notification['notification_title']; ?></h6>
                                    <p class="mb-1"><?php echo $notification['notification_text']; ?></p>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i> 
                                        <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                                    </small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Current Salary Period -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card shadow-sm salary-card h-100">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-calculator me-2"></i> 
                                    <?php echo $currentPeriod ? 'Current Salary Period: ' . $currentPeriod['period_name'] : 'No Active Salary Period'; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if ($currentPeriod && $salaryData): ?>
                                <div class="row g-3 mb-4">
                                    <div class="col-6">
                                        <div class="bg-light p-3 rounded text-center h-100">
                                            <h6 class="text-muted mb-2">Base Salary</h6>
                                            <h3 class="mb-0 text-primary">₹<?php echo number_format($salaryData['base_salary'], 2); ?></h3>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="bg-light p-3 rounded text-center h-100">
                                            <h6 class="text-muted mb-2">Final Salary</h6>
                                            <h3 class="mb-0 text-success">₹<?php echo number_format($salaryData['final_salary'], 2); ?></h3>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="bg-light p-3 rounded text-center h-100">
                                            <h6 class="text-muted mb-2">Working Hours</h6>
                                            <h3 class="mb-0 text-info">
                                                <?php echo number_format($salaryData['total_working_hours'], 1); ?> / 
                                                <?php echo number_format($salaryData['expected_working_hours'], 1); ?>
                                            </h3>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="bg-light p-3 rounded text-center h-100">
                                            <h6 class="text-muted mb-2">Status</h6>
                                            <?php
                                            $statusClass = '';
                                            switch($salaryData['status']) {
                                                case 'draft':
                                                    $statusClass = 'bg-warning';
                                                    break;
                                                case 'finalized':
                                                    $statusClass = 'bg-info';
                                                    break;
                                                case 'paid':
                                                    $statusClass = 'bg-success';
                                                    break;
                                            }
                                            ?>
                                            <h3 class="mb-0">
                                                <span class="badge <?php echo $statusClass; ?>">
                                                    <?php echo ucfirst($salaryData['status']); ?>
                                                </span>
                                            </h3>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Deductions:</span>
                                            <span class="text-danger">-₹<?php echo number_format($salaryData['deduction_amount'], 2); ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Bonuses:</span>
                                            <span class="text-success">+₹<?php echo number_format($salaryData['bonus_amount'], 2); ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Period:</span>
                                            <span><?php echo date('M d', strtotime($currentPeriod['start_date'])); ?> - <?php echo date('M d, Y', strtotime($currentPeriod['end_date'])); ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Hourly Rate:</span>
                                            <span>₹<?php echo number_format($currentRate['hourly_rate'], 2); ?>/hr</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php elseif ($currentPeriod): ?>
                                <div class="alert alert-info mb-0">
                                    <i class="fas fa-info-circle me-2"></i> Salary for this period has not been calculated yet.
                                </div>
                                <?php else: ?>
                                <div class="alert alert-warning mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i> No active salary period found. Please contact the administrator.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-chart-line me-2"></i> Attendance & Work Hours
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if ($currentPeriod && $attendanceStats): ?>
                                <div class="row g-3 mb-3">
                                    <div class="col-3">
                                        <div class="bg-light p-2 rounded text-center">
                                            <h3 class="mb-0 text-success"><?php echo $attendanceStats['present_count'] ?? 0; ?></h3>
                                            <small class="text-muted">Present</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="bg-light p-2 rounded text-center">
                                            <h3 class="mb-0 text-danger"><?php echo $attendanceStats['absent_count'] ?? 0; ?></h3>
                                            <small class="text-muted">Absent</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="bg-light p-2 rounded text-center">
                                            <h3 class="mb-0 text-warning"><?php echo $attendanceStats['late_count'] ?? 0; ?></h3>
                                            <small class="text-muted">Late</small>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="bg-light p-2 rounded text-center">
                                            <h3 class="mb-0 text-info"><?php echo $attendanceStats['leave_count'] ?? 0; ?></h3>
                                            <small class="text-muted">Leave</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div style="height: 200px;">
                                    <canvas id="workHoursChart"></canvas>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> No attendance data available for the current period.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Salary History and Rate -->
                <div class="row mb-4">
                    <div class="col-lg-8">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-history me-2"></i> Salary History
                                </h5>
                                <a href="salary_reports.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($salaryHistory)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> No salary history available.
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Period</th>
                                                <th>Working Hours</th>
                                                <th>Base Salary</th>
                                                <th>Final Salary</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($salaryHistory as $history): ?>
                                            <tr>
                                                <td>
                                                    <?php echo $history['period_name']; ?><br>
                                                    <small class="text-muted">
                                                        <?php echo date('M d', strtotime($history['start_date'])); ?> - 
                                                        <?php echo date('M d, Y', strtotime($history['end_date'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php echo number_format($history['total_working_hours'], 1); ?> / 
                                                    <?php echo number_format($history['expected_working_hours'], 1); ?> hrs
                                                </td>
                                                <td>₹<?php echo number_format($history['base_salary'], 2); ?></td>
                                                <td>₹<?php echo number_format($history['final_salary'], 2); ?></td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    switch($history['status']) {
                                                        case 'draft':
                                                            $statusClass = 'bg-warning';
                                                            break;
                                                        case 'finalized':
                                                            $statusClass = 'bg-info';
                                                            break;
                                                        case 'paid':
                                                            $statusClass = 'bg-success';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?> status-badge">
                                                        <?php echo ucfirst($history['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-info-circle me-2"></i> Salary Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <h6 class="fw-bold">Current Hourly Rate</h6>
                                    <h3 class="text-primary">₹<?php echo $currentRate ? number_format($currentRate['hourly_rate'], 2) : '--'; ?></h3>
                                    <p class="text-muted small">
                                        <i class="fas fa-calendar-alt me-1"></i> 
                                        Effective from: <?php echo $currentRate ? date('M d, Y', strtotime($currentRate['effective_from'])) : '--'; ?>
                                    </p>
                                </div>
                                
                                <div class="mb-3">
                                    <h6 class="fw-bold">Salary Calculation</h6>
                                    <p class="mb-1">Your salary is calculated based on your total working hours:</p>
                                    <ul class="small">
                                        <li>Base Salary = Hourly Rate × Total Working Hours</li>
                                        <li>Deductions may apply for incomplete hours</li>
                                        <li>Bonuses may be added for exceptional performance</li>
                                    </ul>
                                </div>
                                
                                <div>
                                    <h6 class="fw-bold">Working Requirements</h6>
                                    <ul class="mb-0 small">
                                        <li>Minimum Hours per Day: <?php echo $settings['minimum_working_hours_per_day'] ?? '8'; ?> hours</li>
                                        <li>Working Days per Week: <?php echo $settings['working_days_per_week'] ?? '5'; ?> days</li>
                                        <li>Overtime Rate: <?php echo $settings['overtime_multiplier'] ?? '1.5'; ?>× regular rate</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Daily Salary and Class Assignments -->
                <div class="row mb-4">
                    <div class="col-lg-8">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-calendar-day me-2"></i> Daily Salary (<?php echo date('F Y'); ?>)
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($dailySalaryData)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> No daily salary data available for the current month.
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Working Hours</th>
                                                <th>Base Amount</th>
                                                <th>Deduction</th>
                                                <th>Final Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($dailySalaryData as $daily): ?>
                                            <tr>
                                                <td>
                                                    <?php echo date('d M, Y (D)', strtotime($daily['calculation_date'])); ?>
                                                </td>
                                                <td>
                                                    <?php echo formatTime($daily['working_minutes']); ?> / 
                                                    <?php echo formatTime($daily['required_minutes']); ?>
                                                </td>
                                                <td>₹<?php echo number_format($daily['base_amount'], 2); ?></td>
                                                <td class="text-danger">
                                                    <?php if($daily['deduction_amount'] > 0): ?>
                                                        -₹<?php echo number_format($daily['deduction_amount'], 2); ?>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td class="fw-bold">₹<?php echo number_format($daily['final_amount'], 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="bg-light">
                                                <td colspan="2" class="fw-bold">Month Total</td>
                                                <td>₹<?php echo number_format(array_sum(array_column($dailySalaryData, 'base_amount')), 2); ?></td>
                                                <td class="text-danger">-₹<?php echo number_format(array_sum(array_column($dailySalaryData, 'deduction_amount')), 2); ?></td>
                                                <td class="fw-bold">₹<?php echo number_format(array_sum(array_column($dailySalaryData, 'final_amount')), 2); ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-chalkboard-teacher me-2"></i> My Class Assignments
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($classAssignments)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> No class assignments available.
                                </div>
                                <?php else: ?>
                                <div class="mb-3">
                                    <h6 class="fw-bold">Total Class Hours</h6>
                                    <h3 class="text-primary"><?php echo formatHours($totalClassHours); ?></h3>
                                </div>
                                
                                <div class="list-group">
                                    <?php foreach ($classAssignments as $assignment): ?>
                                    <div class="list-group-item border-0 daily-salary-card p-3 mb-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-1 fw-bold"><?php echo $assignment['class_name']; ?></h6>
                                            <span class="badge bg-primary"><?php echo formatHours($assignment['class_hours']); ?></span>
                                        </div>
                                        <p class="mb-1 text-muted small"><?php echo $assignment['subject']; ?></p>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
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
            <?php if ($currentPeriod && isset($attendanceStats) && !empty($attendanceStats)): ?>
            // Work Hours Chart
            const workHoursCtx = document.getElementById('workHoursChart').getContext('2d');
            const workHoursChart = new Chart(workHoursCtx, {
                type: 'bar',
                data: {
                    labels: ['Total Hours'],
                    datasets: [
                        {
                            label: 'Worked Hours',
                            data: [<?php echo $attendanceStats['total_hours'] ?? 0; ?>],
                            backgroundColor: 'rgba(78, 115, 223, 0.8)',
                            borderColor: 'rgba(78, 115, 223, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Expected Hours',
                            data: [<?php echo $salaryData ? $salaryData['expected_working_hours'] : 0; ?>],
                            backgroundColor: 'rgba(28, 200, 138, 0.5)',
                            borderColor: 'rgba(28, 200, 138, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Hours'
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
        });
    </script>
</body>
</html> 