<?php
/**
 * Salary Calculation Script
 * 
 * This script calculates teacher salaries based on their working hours
 * It can be run manually by administrators or scheduled to run automatically
 */

// Initialize
if (php_sapi_name() !== 'cli') {
    session_start();
    // Check if user is admin
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: restrict_user.php?page=Salary Processing&message=This page is restricted to administrators only.");
        exit();
    }
}

require_once 'config.php';
require 'config/db.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$role = $_SESSION['role'];

// Process period ID from URL
$period_id = isset($_GET['period_id']) ? intval($_GET['period_id']) : 0;
$force_recalculate = isset($_GET['force']) && $_GET['force'] === 'true';

// Redirect if no period ID provided
if ($period_id <= 0) {
    $_SESSION['error_message'] = "Invalid salary period ID";
    header("Location: salary_periods.php");
    exit();
}

// Check if period exists and get details
$periodSql = "SELECT * FROM salary_periods WHERE id = ?";
$periodStmt = $conn->prepare($periodSql);
$periodStmt->bind_param("i", $period_id);
$periodStmt->execute();
$periodResult = $periodStmt->get_result();

if ($periodResult->num_rows === 0) {
    $_SESSION['error_message'] = "Salary period not found";
    header("Location: salary_periods.php");
    exit();
}

$periodData = $periodResult->fetch_assoc();

// Check if period is locked
if ($periodData['is_locked']) {
    $_SESSION['error_message'] = "This salary period is locked and cannot be processed";
    header("Location: salary_periods.php");
    exit();
}

// Check if period already has calculations and force not enabled
if ($periodData['is_processed'] && !$force_recalculate) {
    $_SESSION['error_message'] = "This period has already been processed. Use the force option to recalculate.";
    header("Location: salary_periods.php");
    exit();
}

// Get all settings
$settingsSql = "SELECT setting_key, setting_value FROM salary_settings";
$settingsResult = $conn->query($settingsSql);
$settings = [];

while ($row = $settingsResult->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get all deduction rules
$deductionRulesSql = "SELECT * FROM salary_deduction_rules WHERE is_active = 1 ORDER BY hours_threshold";
$deductionRulesResult = $conn->query($deductionRulesSql);
$deductionRules = [];

while ($rule = $deductionRulesResult->fetch_assoc()) {
    $deductionRules[] = $rule;
}

// Get all teachers
$teachersSql = "SELECT u.id, u.name as full_name FROM users u WHERE u.role = 'teacher'";
$teachersResult = $conn->query($teachersSql);
$teachers = [];

while ($teacher = $teachersResult->fetch_assoc()) {
    $teachers[] = $teacher;
}

// Calculate and process salaries for each teacher
$calculationLog = [];
$success = 0;
$errors = 0;
$warnings = 0;

// Start transaction
$conn->begin_transaction();

try {
    // If force recalculate, first delete existing calculations
    if ($force_recalculate) {
        $deleteSql = "DELETE FROM teacher_salary_calculations WHERE period_id = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->bind_param("i", $period_id);
        $deleteStmt->execute();
        
        // Also delete related notifications
        $deleteNotifSql = "DELETE FROM salary_notifications WHERE salary_id IN 
                          (SELECT id FROM teacher_salary_calculations WHERE period_id = ?)";
        $deleteNotifStmt = $conn->prepare($deleteNotifSql);
        $deleteNotifStmt->bind_param("i", $period_id);
        $deleteNotifStmt->execute();
    }
    
    // Process each teacher
    foreach ($teachers as $teacher) {
        $teacher_id = $teacher['id'];
        $log = ['teacher' => $teacher['full_name'], 'success' => true, 'messages' => []];
        
        // Check if calculation already exists for this teacher and period
        $checkSql = "SELECT id FROM teacher_salary_calculations WHERE user_id = ? AND period_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("ii", $teacher_id, $period_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0 && !$force_recalculate) {
            $log['success'] = false;
            $log['messages'][] = "Calculation already exists for this teacher and period.";
            $calculationLog[] = $log;
            $warnings++;
            continue;
        }
        
        // Get teacher's hourly rate effective during the period
        $rateSql = "SELECT * FROM teacher_salary_rates 
                   WHERE (user_id = ? OR user_id = ?) AND (effective_from <= ? OR effective_date <= ?) 
                   ORDER BY effective_from DESC, effective_date DESC LIMIT 1";
        $rateStmt = $conn->prepare($rateSql);
        $rateStmt->bind_param("iiss", $teacher_id, $teacher_id, $periodData['end_date'], $periodData['end_date']);
        $rateStmt->execute();
        $rateResult = $rateStmt->get_result();
        
        if ($rateResult->num_rows === 0) {
            $log['success'] = false;
            $log['messages'][] = "No hourly rate found for this teacher.";
            $calculationLog[] = $log;
            $warnings++;
            continue;
        }
        
        $rateData = $rateResult->fetch_assoc();
        $hourly_rate = $rateData['hourly_rate'];
        
        // Get attendance data for this teacher during the period
        $attendanceSql = "SELECT * FROM attendance_logs 
                         WHERE (user_id = ? OR user_id = ?) 
                         AND DATE(punch_in_time) >= ? 
                         AND DATE(punch_in_time) <= ?
                         AND punch_out_time IS NOT NULL";
        $attendanceStmt = $conn->prepare($attendanceSql);
        $attendanceStmt->bind_param("iiss", $teacher_id, $teacher_id, $periodData['start_date'], $periodData['end_date']);
        $attendanceStmt->execute();
        $attendanceResult = $attendanceStmt->get_result();
        
        $total_hours = 0;
        $expected_hours = 0;
        $incomplete_days = 0;
        
        // Get daily summary data
        $summarySql = "SELECT * FROM attendance_summary 
                      WHERE (user_id = ?) 
                      AND (summary_date >= ?) 
                      AND (summary_date <= ?)";
        $summaryStmt = $conn->prepare($summarySql);
        $summaryStmt->bind_param("iss", $teacher_id, 
                                $periodData['start_date'], 
                                $periodData['end_date']);
        $summaryStmt->execute();
        $summaryResult = $summaryStmt->get_result();
        
        $attendance_days = [];
    
        while ($summary = $summaryResult->fetch_assoc()) {
            // Handle column name variations
            $work_hours = isset($summary['work_hours']) ? $summary['work_hours'] : 0;
            $expected_day_hours = isset($summary['expected_hours']) ? $summary['expected_hours'] : 3; // Default 8 hours if not set
            
            $total_hours += $work_hours;
            $expected_hours += $expected_day_hours;
            
            // Check if day had incomplete hours
            if ($work_hours < $expected_day_hours) {
                $incomplete_days++;
            }
            
            $attendance_date = isset($summary['summary_date']) ? $summary['summary_date'] : 
                             (isset($summary['date']) ? $summary['date'] : date('Y-m-d'));
            
            $attendance_days[] = [
                'date' => $attendance_date,
                'work_hours' => $work_hours,
                'expected_hours' => $expected_day_hours
            ];
        }
        
        // Calculate base salary
        $base_salary = $total_hours * $hourly_rate;
        
        // Calculate deductions based on rules
        $deductions = 0;
        $deduction_details = [];
        
        // Check if deductions are enabled
        if (isset($settings['enable_deductions']) && $settings['enable_deductions'] == '1') {
            // Apply deduction rules
            foreach ($deductionRules as $rule) {
                $threshold = isset($rule['threshold_hours']) ? $rule['threshold_hours'] : 
                           (isset($rule['hours_threshold']) ? $rule['hours_threshold'] : 4);
                // echo "Threshold: " . $threshold . "<br>";
                // echo "Expected hours: " . $expected_hours . "<br>";
                // echo "Total hours: " . $total_hours . "<br>"."<br>"."<br>"; 
                if ($expected_hours - $total_hours >= $threshold) {
                    $deduction_amount = 0;
                    $deduction_value = isset($rule['deduction_value']) ? $rule['deduction_value'] : 
                                     (isset($rule['percentage']) ? $rule['percentage'] : 
                                     (isset($rule['fixed_amount']) ? $rule['fixed_amount'] : 0));
                    
                    if ($rule['deduction_type'] === 'percentage') {
                        $deduction_amount = $base_salary * ($deduction_value / 100);
                    } else { // fixed
                        $deduction_amount = $deduction_value;
                    }
                    
                    $deductions += $deduction_amount;
                    $deduction_details[] = [
                        'rule_name' => isset($rule['rule_name']) ? $rule['rule_name'] : 'Deduction Rule',
                        'amount' => $deduction_amount,
                        'reason' => "Missed " . ($expected_hours - $total_hours) . " hours"
                    ];
                }
            }
        }
     // echo $base_salary."<pre>";  print_r($deduction_details); die;// Get any bonuses (placeholder for future implementation)
        $bonuses = 0;
        
        // Calculate final salary
        $final_salary = $base_salary - $deductions + $bonuses;
        
        // Add notes
        $notes = "Total worked hours: " . number_format($total_hours, 2) . " out of expected " . 
                 number_format($expected_hours, 2) . " hours.\n";
        
        if ($incomplete_days > 0) {
            $notes .= "Incomplete days: {$incomplete_days}\n";
        }
        
        if (!empty($deduction_details)) {
            $notes .= "Deductions applied:\n";
            foreach ($deduction_details as $detail) {
                $notes .= "- {$detail['rule_name']}: ₹" . number_format($detail['amount'], 2) . 
                        " ({$detail['reason']})\n";
            }
        }
        
        // Insert or update calculation
        $sql = "INSERT INTO teacher_salary_calculations 
               (teacher_id, user_id, period_id, hourly_rate, total_hours, total_working_hours, 
                expected_hours, expected_working_hours, base_salary, 
                deductions, deduction_amount, bonuses, bonus_amount, final_salary, notes, status, created_at, updated_at) 
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'processed', NOW(), NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiddddddddddss", 
                          $teacher_id, $teacher_id, $period_id, $hourly_rate, 
                          $total_hours, $total_hours, $expected_hours, $expected_hours, 
                          $base_salary, $deductions, $deductions, $bonuses, $bonuses, $final_salary, $notes);
        $result = $stmt->execute();
        
        if (!$result) {
            throw new Exception("Failed to save calculation for " . $teacher['full_name'] . ": " . $conn->error);
        }
        
        $calculation_id = $stmt->insert_id;
        
        // Create notification for the teacher
        $notificationTitle = "Salary Processed";
        $notificationMessage = "Your salary for the period {$periodData['period_name']} has been processed. " . 
                            "Total hours: " . number_format($total_hours, 2) . ", Final salary: ₹" . 
                            number_format($final_salary, 2);
        
        // Check if notifications table has message or content column
        $notifSql = "INSERT INTO notifications (user_id, title, message, content, type, created_at) 
                    VALUES (?, ?, ?, ?, 'salary_processed', NOW())";
        $notifStmt = $conn->prepare($notifSql);
        $notifStmt->bind_param("isss", $teacher_id, $notificationTitle, $notificationMessage, $notificationMessage);
        $notifStmt->execute();
        
        // Add to salary notifications table using the original table structure
        $salaryNotifSql = "INSERT INTO salary_notifications 
                          (user_id, salary_id, notification_title, notification_text, is_read, created_at) 
                          VALUES (?, ?, ?, ?, 0, NOW())";
        $salaryNotifStmt = $conn->prepare($salaryNotifSql);
        $salaryNotifStmt->bind_param("iiss", $teacher_id, $calculation_id, $notificationTitle, $notificationMessage);
        $salaryNotifStmt->execute();
        
        $log['messages'][] = "Salary processed successfully. Total: ₹" . number_format($final_salary, 2);
        $calculationLog[] = $log;
        $success++;
    }
    
    // Update period status
    $updatePeriodSql = "UPDATE salary_periods SET is_processed = 1 WHERE id = ?";
    $updatePeriodStmt = $conn->prepare($updatePeriodSql);
    $updatePeriodStmt->bind_param("i", $period_id);
    $updatePeriodStmt->execute();
    
    // Commit the transaction
    $conn->commit();
    
    $_SESSION['success_message'] = "Salary processing completed successfully for {$success} teachers." . 
                               ($warnings > 0 ? " {$warnings} warnings." : "");
    
    // Store logs for display
    $_SESSION['calculation_log'] = $calculationLog;
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    $_SESSION['error_message'] = "Error processing salaries: " . $e->getMessage();
}

header("Location: salary_periods.php");
exit();

/**
 * Output message based on running environment
 */
function output_message($message, $type = 'info') {
    if (php_sapi_name() === 'cli') {
        echo $message . PHP_EOL;
    } else {
        $_SESSION[$type . '_message'] = $message;
    }
}
?> 