<?php
/**
 * Daily Salary Calculation Script
 * 
 * This script calculates daily salaries for teachers based on:
 * 1. Class assignments (hours per day)
 * 2. Hourly rates
 * 3. Attendance records (minutes worked)
 * 4. Minimum working hours (for deduction calculation)
 */

// Only run via cron or admin request
if (php_sapi_name() !== 'cli') {
    session_start();
    
    // Only allow admin to run this manually
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        echo "Access denied. This script can only be run by administrators.";
        exit();
    }
}

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Load config
require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/config/db.php';
require_once dirname(__FILE__) . '/includes/time_utils.php';

// Default to yesterday if no date is provided
$processDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d', strtotime('-1 day'));

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $processDate)) {
    echo "Invalid date format. Please use YYYY-MM-DD format.";
    exit();
}

echo "Processing daily salaries for: " . $processDate . "\n";

// Get salary settings
$settingsSql = "SELECT setting_key, setting_value FROM salary_settings";
$settingsResult = $conn->query($settingsSql);
$settings = [];
while ($row = $settingsResult->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$deductionPerMinute = isset($settings['deduction_per_minute']) ? floatval($settings['deduction_per_minute']) : 1.0;

// Get teachers with active salary rates and class assignments
$teachersSql = "SELECT DISTINCT u.id, u.name, u.email 
                FROM users u
                JOIN teacher_salary_rates tsr ON u.id = tsr.user_id
                JOIN teacher_class_assignments tca ON u.id = tca.teacher_id
                WHERE u.role = 'teacher' 
                AND tsr.is_active = 1
                AND tca.is_active = 1";
$teachersResult = $conn->query($teachersSql);

$processedCount = 0;
$errorCount = 0;
$updatedCount = 0;

// Process each teacher
while ($teacher = $teachersResult->fetch_assoc()) {
    $teacherId = $teacher['id'];
    $teacherName = $teacher['name'];
    
    echo "Processing teacher: $teacherName (ID: $teacherId)\n";
    
    // Get teacher's active salary rate
    $rateSql = "SELECT * FROM teacher_salary_rates 
                WHERE user_id = ? AND is_active = 1";
    $rateStmt = $conn->prepare($rateSql);
    $rateStmt->bind_param("i", $teacherId);
    $rateStmt->execute();
    $rateResult = $rateStmt->get_result();
    $salaryRate = $rateResult->fetch_assoc();
    
    if (!$salaryRate) {
        echo "  No active salary rate found for this teacher. Skipping.\n";
        $errorCount++;
        continue;
    }
    
    // Get teacher's class assignments
    $classesSql = "SELECT * FROM teacher_class_assignments 
                   WHERE teacher_id = ? AND is_active = 1";
    $classesStmt = $conn->prepare($classesSql);
    $classesStmt->bind_param("i", $teacherId);
    $classesStmt->execute();
    $classesResult = $classesStmt->get_result();
    
    $totalClassHours = 0;
    $classCount = 0;
    while ($class = $classesResult->fetch_assoc()) {
        $totalClassHours += floatval($class['class_hours']);
        $classCount++;
    }
    
    if ($totalClassHours <= 0) {
        echo "  No active class assignments or hours found for this teacher. Skipping.\n";
        $errorCount++;
        continue;
    }
    
    // Get teacher's attendance record for the processing date
    $attendanceSql = "SELECT * FROM attendance_logs 
                      WHERE user_id = ? AND punch_in_time= ? AND punch_out_time IS NOT NULL";
    $attendanceStmt = $conn->prepare($attendanceSql);
    $attendanceStmt->bind_param("is", $teacherId, $processDate);
    $attendanceStmt->execute();
    $attendanceResult = $attendanceStmt->get_result();
    $attendance = $attendanceResult->fetch_assoc();
    
    // Calculate basic salary amount based on hourly rate and class hours
    $hourlyRate = floatval($salaryRate['hourly_rate']);
    $minimumWorkingHours = floatval($salaryRate['minimum_working_hours']);
    $dailyAmount = $hourlyRate * $totalClassHours;
    
    $minutes_worked = 0;
    $deduction_amount = 0;
    $workingNotes = "";
    
    if ($attendance) {
        // If punch_out_time is NULL, use current time for calculation
        $punch_out_time = $attendance['punch_out_time'] ?? date('Y-m-d H:i:s');
        
        // Calculate minutes worked
        $start_time = strtotime($attendance['punch_in_time']);
        $end_time = strtotime($punch_out_time);
        $minutes_worked = max(0, ($end_time - $start_time) / 60);
        
        // Required working minutes based on minimum hours
        $required_minutes = $minimumWorkingHours * 60;
        
        // Calculate deduction if worked less than required minutes
        if ($minutes_worked < $required_minutes) {
            $missing_minutes = $required_minutes - $minutes_worked;
            $deduction_amount = $missing_minutes * $deductionPerMinute;
            
            // Cap deduction at 90% of daily amount
            $deduction_amount = min($deduction_amount, $dailyAmount * 0.9);
            
            $workingNotes = "Required: " . formatTime($required_minutes) . ", Worked: " . formatTime($minutes_worked) . 
                          ", Missing: " . formatTime($missing_minutes);
        } else {
            $workingNotes = "Required: " . formatTime($required_minutes) . ", Worked: " . formatTime($minutes_worked);
        }
    } else {
        // No attendance record, consider full deduction
        $deduction_amount = $dailyAmount * 0.9; // Cap at 90%
        $workingNotes = "No attendance record found";
    }
    
    // Format total class hours
    $classHours = floor($totalClassHours);
    $classMinutes = round(($totalClassHours - $classHours) * 60);
    $classTime = ($classHours > 0 ? $classHours . " hr" : "") . 
                  (($classHours > 0 && $classMinutes > 0) ? " " : "") . 
                  ($classMinutes > 0 ? $classMinutes . " min" : "");
    
    // Calculate final amount
    $final_amount = max(0, $dailyAmount - $deduction_amount);
    
    // Check if an entry already exists for this teacher and date
    $checkSql = "SELECT id FROM daily_salary_calculations 
                WHERE teacher_id = ? AND calculation_date = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("is", $teacherId, $processDate);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        // Update existing record
        $existingId = $checkResult->fetch_assoc()['id'];
        $updateSql = "UPDATE daily_salary_calculations 
                      SET base_amount = ?,
                          deduction_amount = ?,
                          final_amount = ?,
                          working_hours = ?,
                          required_hours = ?,
                          notes = ?
                      WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $required_minutes = $minimumWorkingHours * 60;
        $updateStmt->bind_param("ddddisi", 
            $dailyAmount, 
            $deduction_amount, 
            $final_amount, 
            $minutes_worked, 
            $required_minutes, 
            $workingNotes, 
            $existingId
        );
        $updateResult = $updateStmt->execute();
        
        if ($updateResult) {
            $updatedCount++;
            echo "  Updated existing salary record. Class hours: " . formatHours($totalClassHours) . 
                 ", Daily Amount: ₹$dailyAmount, Time worked: " . formatTime($minutes_worked) . 
                 ", Deduction: ₹$deduction_amount, Final: ₹$final_amount\n";
        } else {
            $errorCount++;
            echo "  Error updating salary record: " . $conn->error . "\n";
        }
    } else {
        // Insert new record
        $insertSql = "INSERT INTO daily_salary_calculations 
                      (teacher_id, calculation_date, base_amount, deduction_amount, 
                       final_amount, working_hours, required_hours, notes)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertSql);
        $required_hours = $minimumWorkingHours * 60; // Store in minutes
        $insertStmt->bind_param("isddddis", 
            $teacherId, 
            $processDate, 
            $dailyAmount, 
            $deduction_amount, 
            $final_amount, 
            $minutes_worked, 
            $required_hours, 
            $workingNotes
        );
        $insertResult = $insertStmt->execute();
        
        if ($insertResult) {
            $processedCount++;
            echo "  Created new salary record. Class hours: " . formatHours($totalClassHours) . 
                 ", Daily Amount: ₹$dailyAmount, Time worked: " . formatTime($minutes_worked) . 
                 ", Deduction: ₹$deduction_amount, Final: ₹$final_amount\n";
        } else {
            $errorCount++;
            echo "  Error creating salary record: " . $conn->error . "\n";
        }
    }
}

echo "\nSummary:\n";
echo "Date Processed: $processDate\n";
echo "New Records Created: $processedCount\n";
echo "Records Updated: $updatedCount\n";
echo "Errors/Skipped: $errorCount\n";
echo "Process completed.\n";

// For web requests, provide a link back
if (php_sapi_name() !== 'cli') {
    echo "<br><br><a href='daily_salary_calculations.php'>Back to Daily Salary Calculations</a>";
}

$conn->close();
?> 