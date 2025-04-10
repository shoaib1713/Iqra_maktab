<?php
session_start();
require_once 'config.php';
require 'config/db.php';
require_once 'includes/time_utils.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$role = $_SESSION['role'];
$is_admin = ($role === 'admin');

// Default to current month if not specified
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// For teachers, only show their own data
$teacher_id = $is_admin && isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : $user_id;

// Build date range for the selected month
$start_date = sprintf('%04d-%02d-01', $year, $month);
$end_date = date('Y-m-t', strtotime($start_date));
$days_in_month = date('t', strtotime($start_date));
$month_name = date('F Y', strtotime($start_date));

// Get salary settings
$settingsSql = "SELECT setting_key, setting_value FROM salary_settings";
$settingsResult = $conn->query($settingsSql);
$settings = [];
while ($row = $settingsResult->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$deductionPerMinute = isset($settings['deduction_per_minute']) ? floatval($settings['deduction_per_minute']) : 1.0;

// Get all teachers for admin filter
$teachers = [];
if ($is_admin) {
    $teachersSql = "SELECT id, email, name, name FROM users WHERE role = 'teacher' ORDER BY name";
    $teachersResult = $conn->query($teachersSql);
    while ($teacher = $teachersResult->fetch_assoc()) {
        $teachers[] = $teacher;
    }
}

// Get teacher's active salary rate
$rateSql = "SELECT tsr.*, u.name AS teacher_name, u.email 
           FROM teacher_salary_rates tsr
           JOIN users u ON tsr.user_id = u.id
           WHERE tsr.user_id = ? AND tsr.is_active = 1";
$rateStmt = $conn->prepare($rateSql);
$rateStmt->bind_param("i", $teacher_id);
$rateStmt->execute();
$rateResult = $rateStmt->get_result();
$salaryRate = $rateResult->fetch_assoc();

if (!$salaryRate) {
    // If no active rate, show error message
    $error_message = "No active salary rate found for this teacher.";
}

// Get teacher's class assignments
$classesSql = "SELECT tca.*, COUNT(DISTINCT tca.id) as class_count, SUM(tca.class_hours) as total_hours
               FROM teacher_class_assignments tca
               WHERE tca.teacher_id = ? AND tca.is_active = 1
               GROUP BY tca.teacher_id";
$classesStmt = $conn->prepare($classesSql);
$classesStmt->bind_param("i", $teacher_id);
$classesStmt->execute();
$classesResult = $classesStmt->get_result();
$classData = $classesResult->fetch_assoc();

// Get daily salary data
$dailySalarySql = "SELECT dsc.*, DATE_FORMAT(dsc.calculation_date, '%d') as day_of_month
                  FROM daily_salary_calculations dsc
                  WHERE dsc.teacher_id = ? AND dsc.calculation_date BETWEEN ? AND ?
                  ORDER BY dsc.calculation_date";
$dailySalaryStmt = $conn->prepare($dailySalarySql);
$dailySalaryStmt->bind_param("iss", $teacher_id, $start_date, $end_date);
$dailySalaryStmt->execute();
$dailySalaryResult = $dailySalaryStmt->get_result();
//echo "<pre>"; print_r($dailySalaryResult->fetch_assoc()); die; 
// Build an array for easy access by day
$dailySalaryData = [];
while ($row = $dailySalaryResult->fetch_assoc()) {
    $day = $row['day_of_month'];
    $dailySalaryData[$day] = $row;
}

// Get attendance data for this month
$attendanceSql = "SELECT a.created_at, 
                  TIME_FORMAT(a.punch_in_time, '%H:%i') as punch_in, 
                  TIME_FORMAT(a.punch_out_time, '%H:%i') as punch_out,
                  TIMESTAMPDIFF(MINUTE, a.punch_in_time, IFNULL(a.punch_out_time, NOW())) as minutes_worked,
                  DATE_FORMAT(a.created_at, '%d') as day_of_month
                  FROM attendance_logs a
                  WHERE a.user_id = ? AND a.created_at BETWEEN ? AND ?
                  ORDER BY a.created_at";
$attendanceStmt = $conn->prepare($attendanceSql);
$attendanceStmt->bind_param("iss", $teacher_id, $start_date, $end_date);
$attendanceStmt->execute();
$attendanceResult = $attendanceStmt->get_result();

// Build an array for easy access by day
$attendanceData = [];
while ($row = $attendanceResult->fetch_assoc()) {
    $day = $row['day_of_month'];
    $attendanceData[$day] = $row;
}

// Get all class assignments for details
$detailedClassesSql = "SELECT * FROM teacher_class_assignments WHERE teacher_id = ? AND is_active = 1";
$detailedClassesStmt = $conn->prepare($detailedClassesSql);
$detailedClassesStmt->bind_param("i", $teacher_id);
$detailedClassesStmt->execute();
$detailedClassesResult = $detailedClassesStmt->get_result();

$classAssignments = [];
$totalDailyHours = 0;
while ($class = $detailedClassesResult->fetch_assoc()) {
    $classAssignments[] = $class;
    $totalDailyHours += floatval($class['class_hours']);
}

// Calculate required working minutes per day
$requiredMinutesPerDay = ($salaryRate ? floatval($salaryRate['minimum_working_hours']) : 3) * 60;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Salary - MAKTAB-E-IQRA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/images/logo.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .calendar-day {
            height: 100px;
            font-size: 0.9rem;
            padding: 0.5rem;
            border: 1px solid #e9ecef;
            transition: all 0.2s;
        }
        .calendar-day:hover {
            background-color: #f8f9fa;
        }
        .calendar-day.weekend {
            background-color: #f8f9fa;
        }
        .calendar-day.today {
            background-color: #e8f4ff;
            border: 1px solid #4e73df;
        }
        .calendar-day.empty {
            background-color: #f1f1f1;
            color: #adb5bd;
        }
        .day-number {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.3rem;
        }
        .salary-amount {
            font-weight: 600;
            color: #28a745;
        }
        .salary-deduction {
            font-weight: 600;
            color: #dc3545;
        }
        .attendance-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .assignment-card {
            border-left: 4px solid #4e73df;
        }
        .salary-summary {
            background-color: #f8f9fc;
            border-radius: 0.5rem;
        }
        .summary-value {
            font-size: 1.2rem;
            font-weight: 600;
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
                        <h5 class="fw-bold text-primary">
                            <i class="fas fa-calendar-alt me-2"></i> Daily Salary Calculations
                        </h5>
                        <p class="text-muted mb-0">
                            <?php if (!$is_admin): ?>
                                View your daily salary based on attendance and assignments.
                            <?php else: ?>
                                View daily salary calculations for teachers based on attendance and class assignments.
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-4">
                        <form method="GET" action="" class="d-flex flex-wrap justify-content-end gap-2">
                            <?php if ($is_admin): ?>
                            <select name="teacher_id" class="form-select form-select-sm" style="width: auto;">
                                <?php foreach($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>" <?php echo ($teacher_id == $teacher['id']) ? 'selected' : ''; ?>>
                                    <?php echo $teacher['name']; ?> (<?php echo $teacher['email']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                            
                            <select name="month" class="form-select form-select-sm" style="width: auto;">
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo ($month == $i) ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                            
                            <select name="year" class="form-select form-select-sm" style="width: auto;">
                                <?php for ($i = date('Y') - 1; $i <= date('Y') + 1; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo ($year == $i) ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                            
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="fas fa-filter me-1"></i> Filter
                            </button>
                        </form>
                    </div>
                </div>
                
                <?php if (isset($error_message)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error_message; ?>
                </div>
                <?php elseif (empty($classAssignments)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No class assignments found for this teacher. Please assign classes first.
                </div>
                <?php else: ?>
                
                <!-- Teacher Info and Classes -->
                <div class="row mb-4">
                    <div class="col-md-5">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h6 class="mb-0 fw-bold">
                                    <i class="fas fa-user-tie me-2"></i> Teacher Information
                                </h6>
                            </div>
                            <div class="card-body">
                                <h5 class="mb-3"><?php echo $salaryRate['teacher_name']; ?></h5>
                                <div class="mb-2"><strong>Email:</strong> <?php echo $salaryRate['email']; ?></div>
                                <div class="mb-2"><strong>Hourly Rate:</strong> ₹<?php echo number_format($salaryRate['hourly_rate'], 2); ?></div>
                                <div><strong>Min. Working Hours:</strong> <?php echo number_format($salaryRate['minimum_working_hours'], 1); ?> hrs/day</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h6 class="mb-0 fw-bold">
                                    <i class="fas fa-chalkboard-teacher me-2"></i> Class Assignments
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($classAssignments)): ?>
                                <p class="text-muted">No class assignments found.</p>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Class</th>
                                                <th>Subject</th>
                                                <th class="text-end">Hours per Day</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($classAssignments as $class): ?>
                                            <tr>
                                                <td><?php echo $class['class_name']; ?></td>
                                                <td><?php echo $class['subject']; ?></td>
                                                <td class="text-end">
                                                    <?php echo formatHours($class['class_hours']); ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <tr class="table-light fw-bold">
                                                <td colspan="2">Total Daily Hours</td>
                                                <td class="text-end">
                                                    <?php echo formatHours($totalDailyHours); ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Monthly Summary -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h6 class="mb-0 fw-bold">
                                    <i class="fas fa-calculator me-2"></i> Monthly Salary Summary for <?php echo $month_name; ?>
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php 
                                    // Calculate totals
                                    $totalSalary = 0;
                                    $totalDeductions = 0;
                                    $totalFinalAmount = 0;
                                    $daysWithSalary = 0;
                                  //  print_r($dailySalaryData); die; 
                                    foreach ($dailySalaryData as $day => $data) {
                                        $totalSalary += floatval($data['base_amount']);
                                        $totalDeductions += floatval($data['deduction_amount']);
                                        $totalFinalAmount += floatval($data['final_amount']);
                                        $daysWithSalary++;
                                    }
                                    ?>
                                    <div class="col-md-3 text-center p-3">
                                        <div class="text-muted mb-2">Total Salary Amount</div>
                                        <div class="summary-value text-primary">₹<?php echo number_format($totalSalary, 2); ?></div>
                                    </div>
                                    <div class="col-md-3 text-center p-3">
                                        <div class="text-muted mb-2">Total Deductions</div>
                                        <div class="summary-value text-danger">₹<?php echo number_format($totalDeductions, 2); ?></div>
                                    </div>
                                    <div class="col-md-3 text-center p-3">
                                        <div class="text-muted mb-2">Final Salary</div>
                                        <div class="summary-value text-success">₹<?php echo number_format($totalFinalAmount, 2); ?></div>
                                    </div>
                                    <div class="col-md-3 text-center p-3">
                                        <div class="text-muted mb-2">Days Worked</div>
                                        <div class="summary-value text-info"><?php echo $daysWithSalary; ?> / <?php echo $days_in_month; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Daily Salary Calendar -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h6 class="mb-0 fw-bold">
                                    <i class="fas fa-calendar me-2"></i> Daily Salary for <?php echo $month_name; ?>
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row mb-2">
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between gap-2">
                                            <div class="text-muted small">
                                                <i class="fas fa-info-circle me-1"></i> Daily salary is calculated based on class assignments and attendance.
                                            </div>
                                            <div class="d-flex gap-3">
                                                <div class="small"><i class="fas fa-circle text-success me-1"></i> Salary Amount</div>
                                                <div class="small"><i class="fas fa-circle text-danger me-1"></i> Deduction</div>
                                                <div class="small"><i class="fas fa-circle text-primary me-1"></i> Final Amount</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Calendar Header (Days of week) -->
                                <div class="row text-center py-2 border-bottom">
                                    <div class="col">Sun</div>
                                    <div class="col">Mon</div>
                                    <div class="col">Tue</div>
                                    <div class="col">Wed</div>
                                    <div class="col">Thu</div>
                                    <div class="col">Fri</div>
                                    <div class="col">Sat</div>
                                </div>
                                
                                <!-- Calendar Grid -->
                                <div class="row">
                                    <?php
                                    // Calculate first day of month (0 = Sunday, 6 = Saturday)
                                    $firstDayOfWeek = date('w', strtotime($start_date));
                                    
                                    // Add empty cells for days before first day of month
                                    for ($i = 0; $i < $firstDayOfWeek; $i++) {
                                        echo '<div class="col calendar-day empty"></div>';
                                    }
                                    
                                    // Loop through days of month
                                    for ($day = 1; $day <= $days_in_month; $day++) {
                                        $current_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                                        $isToday = ($current_date === date('Y-m-d'));
                                        $dayOfWeek = date('w', strtotime($current_date));
                                        $isWeekend = ($dayOfWeek == 0 || $dayOfWeek == 6);
                                        $dayClass = $isToday ? 'today' : ($isWeekend ? 'weekend' : '');
                                        
                                        $hasSalary = isset($dailySalaryData[$day]);
                                        $hasAttendance = isset($attendanceData[$day]);
                                        
                                        echo '<div class="col calendar-day ' . $dayClass . '">';
                                        echo '<div class="day-number">' . $day . '</div>';
                                        
                                        if ($hasSalary) {
                                            $salary = $dailySalaryData[$day];
                                            echo '<div class="salary-amount">₹' . number_format($salary['base_amount'], 2) . '</div>';
                                            
                                            if (floatval($salary['deduction_amount']) > 0) {
                                                echo '<div class="salary-deduction">-₹' . number_format($salary['deduction_amount'], 2) . '</div>';
                                            }
                                            
                                            echo '<div class="fw-bold">Net: ₹' . number_format($salary['final_amount'], 2) . '</div>';
                                            
                                            // Add working hours display
                                            if (floatval($salary['working_hours']) > 0) {
                                                echo '<div class="small text-muted">';
                                                echo '<i class="fas fa-business-time me-1"></i>' . formatTime($salary['working_hours']);
                                                echo '</div>';
                                            }
                                        }
                                        
                                        if ($hasAttendance) {
                                            $attendance = $attendanceData[$day];
                                            echo '<div class="attendance-time">';
                                            echo '<i class="fas fa-clock me-1"></i> ' . $attendance['punch_in'] . ' - ';
                                            echo $attendance['punch_out'] ?: 'Active';
                                            echo '</div>';
                                        }
                                        
                                        echo '</div>';
                                        
                                        // Start a new row after Saturday
                                        if (($firstDayOfWeek + $day) % 7 === 0 && $day < $days_in_month) {
                                            echo '</div><div class="row">';
                                        }
                                    }
                                    
                                    // Add empty cells for days after last day of month
                                    $lastDayOfWeek = date('w', strtotime($end_date));
                                    for ($i = $lastDayOfWeek + 1; $i < 7; $i++) {
                                        echo '<div class="col calendar-day empty"></div>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 