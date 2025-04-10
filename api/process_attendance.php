<?php
session_start();
require_once '../config.php';
require '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated']);
    exit();
}

// Get user info
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['role']; // Map role to user_type

// Get action
$action = $_POST['action'] ?? '';

// Function to get distance between two points in meters
function getDistanceInMeters($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // meters
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
         sin($dLon/2) * sin($dLon/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c;
}

// Function to check if user is within the office location radius
function isWithinOfficeRadius($userLat, $userLon, $officeId, $conn) {
    // Get office location details
    $sql = "SELECT latitude, longitude, radius_meters FROM office_locations WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $officeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    $office = $result->fetch_assoc();
    
    // Calculate distance
    $distance = getDistanceInMeters(
        $userLat, 
        $userLon, 
        $office['latitude'], 
        $office['longitude']
    );
    
    // Check if within radius
    return $distance <= $office['radius_meters'];
}

// Function to check if geofencing is enabled
function isGeofencingEnabled($conn) {
    $sql = "SELECT setting_value FROM attendance_settings WHERE setting_key = 'geofencing_enabled'";
    $result = $conn->query($sql);
    
    if ($result->num_rows === 0) {
        return true; // Default to enabled if setting not found
    }
    
    $setting = $result->fetch_assoc();
    return $setting['setting_value'] == '1';
}

// Function to check if multiple shifts are enabled
function isMultipleShiftsEnabled($conn) {
    $sql = "SELECT setting_value FROM attendance_settings WHERE setting_key = 'multiple_shifts_enabled'";
    $result = $conn->query($sql);
    
    if ($result->num_rows === 0) {
        return false; // Default to disabled if setting not found
    }
    
    $setting = $result->fetch_assoc();
    return $setting['setting_value'] == '1';
}

// Function to get work shifts
function getWorkShifts($conn) {
    $sql = "SELECT setting_value FROM attendance_settings WHERE setting_key = 'work_shifts'";
    $result = $conn->query($sql);
    
    if ($result->num_rows === 0) {
        // Default shift if not found
        return [
            [
                'start' => '09:00',
                'end' => '17:00',
                'min_hours' => 8
            ]
        ];
    }
    
    $setting = $result->fetch_assoc();
    $shifts = json_decode($setting['setting_value'], true);
    
    if (!is_array($shifts) || empty($shifts)) {
        // Default shift if invalid
        return [
            [
                'start' => '09:00',
                'end' => '17:00',
                'min_hours' => 8
            ]
        ];
    }
    
    return $shifts;
}

// Function to find current shift based on time
function getCurrentShift($time, $shifts) {
    $currentTime = date('H:i', strtotime($time));
    
    foreach ($shifts as $shift) {
        if ($currentTime >= $shift['start'] && $currentTime <= $shift['end']) {
            return $shift;
        }
    }
    
    // If no matching shift, return the closest one
    usort($shifts, function($a, $b) use ($currentTime) {
        $diffA = abs(strtotime($currentTime) - strtotime($a['start']));
        $diffB = abs(strtotime($currentTime) - strtotime($b['start']));
        return $diffA - $diffB;
    });
    
    return $shifts[0];
}

// Function to determine attendance status based on time
function getAttendanceStatus($punchInTime, $conn) {
    // Get work shifts
    $shifts = getWorkShifts($conn);
    $currentShift = getCurrentShift($punchInTime, $shifts);
    
    // Get late threshold
    $sql = "SELECT setting_value FROM attendance_settings WHERE setting_key = 'late_threshold_minutes'";
    $result = $conn->query($sql);
    
    if ($result->num_rows === 0) {
        $lateThreshold = 15; // Default to 15 minutes if setting not found
    } else {
        $lateThresholdSetting = $result->fetch_assoc();
        $lateThreshold = intval($lateThresholdSetting['setting_value']);
    }
    
    // Convert punch in time to timestamp
    $punchInTimestamp = strtotime($punchInTime);
    
    // Get date from punch in time
    $punchInDate = date('Y-m-d', $punchInTimestamp);
    
    // Create timestamp for shift start time today
    $shiftStartTimestamp = strtotime($punchInDate . ' ' . $currentShift['start']);
    
    // Add late threshold
    $lateThresholdTimestamp = $shiftStartTimestamp + ($lateThreshold * 60);
    
    // Check if punch in is after late threshold
    if ($punchInTimestamp > $lateThresholdTimestamp) {
        return 'late';
    }
    
    return 'present';
}

// Check if it's a weekend or holiday
function isWeekendOrHoliday($date, $conn) {
    // Format date to Y-m-d
    $formattedDate = date('Y-m-d', strtotime($date));
    
    // Check if it's a holiday
    $holidaySql = "SELECT id FROM holidays WHERE holiday_date = ?";
    $holidayStmt = $conn->prepare($holidaySql);
    $holidayStmt->bind_param("s", $formattedDate);
    $holidayStmt->execute();
    $holidayResult = $holidayStmt->get_result();
    
    if ($holidayResult->num_rows > 0) {
        return ['status' => true, 'type' => 'holiday'];
    }
    
    // Check if it's a weekend
    $weekendSql = "SELECT setting_value FROM attendance_settings WHERE setting_key = 'weekend_days'";
    $weekendResult = $conn->query($weekendSql);
    
    if ($weekendResult->num_rows === 0) {
        // Default to Sunday (0) and Saturday (6) if setting not found
        $weekendDays = [0, 6];
    } else {
        $weekendSetting = $weekendResult->fetch_assoc();
        $weekendDays = explode(',', $weekendSetting['setting_value']);
    }
    
    // Get day of week (0 = Sunday, 6 = Saturday)
    $dayOfWeek = date('w', strtotime($formattedDate));
    
    if (in_array($dayOfWeek, $weekendDays)) {
        return ['status' => true, 'type' => 'weekend'];
    }
    
    return ['status' => false];
}

// Process attendance based on action
if ($action === 'punch_in') {
    // Get form data
    $locationId = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;
    $latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : 0;
    $longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : 0;
    $multipleShiftsEnabled = isset($_POST['multiple_shifts_enabled']) && $_POST['multiple_shifts_enabled'] === '1';
    
    // Get current time
    $currentTime = date('Y-m-d H:i:s');
    
    // Log the current time for debugging
    error_log("Punch In Time: " . $currentTime . " (" . date_default_timezone_get() . ")");
    
    // Check if multiple shifts are enabled from database settings
    $dbMultipleShiftsEnabled = isMultipleShiftsEnabled($conn);
    $multipleShiftsEnabled = $multipleShiftsEnabled || $dbMultipleShiftsEnabled;
    
    // Check if already punched in today without punching out (for single shift mode)
    if (!$multipleShiftsEnabled) {
        $checkSql = "SELECT id FROM attendance_logs 
                    WHERE user_id = ? 
                    AND user_type = ? 
                    AND DATE(punch_in_time) = CURDATE() 
                    AND punch_out_time IS NULL";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("is", $user_id, $user_type);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            echo json_encode([
                'status' => 'error', 
                'message' => 'You are already punched in for today'
            ]);
            exit();
        }
    }
    
    // Check if geofencing is enabled
    $geofencingEnabled = isGeofencingEnabled($conn);
    
    // If geofencing is enabled, verify user's location
    if ($geofencingEnabled) {
        if (!isWithinOfficeRadius($latitude, $longitude, $locationId, $conn)) {
            echo json_encode([
                'status' => 'error', 
                'message' => 'You must be within the office premises to punch in'
            ]);
            exit();
        }
    }
    
    // Check if it's a weekend or holiday
    $dateCheck = isWeekendOrHoliday($currentTime, $conn);
    $status = 'present';
    
    if ($dateCheck['status']) {
        $status = $dateCheck['type']; // 'holiday' or 'weekend'
    } else {
        // Determine attendance status based on time
        $status = getAttendanceStatus($currentTime, $conn);
    }
    
    // Get current shift
    $shifts = getWorkShifts($conn);
    $currentShift = getCurrentShift($currentTime, $shifts);
    $shiftStart = $currentShift['start'];
    $shiftEnd = $currentShift['end'];
    
    // Insert attendance record
    $insertSql = "INSERT INTO attendance_logs 
                 (user_id, user_type, punch_in_time, punch_in_location_id, 
                  punch_in_latitude, punch_in_longitude, punch_in_ip, status,
                  shift_start, shift_end) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $ip = $_SERVER['REMOTE_ADDR'];
    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->bind_param(
        "issiidssss", 
        $user_id, 
        $user_type, 
        $currentTime, 
        $locationId, 
        $latitude, 
        $longitude, 
        $ip,
        $status,
        $shiftStart,
        $shiftEnd
    );
    
    $insertResult = $insertStmt->execute();
    
    if ($insertResult) {
        // Get the inserted record ID
        $logId = $conn->insert_id;
        
        // Create attendance summary record for reporting (only if it doesn't exist)
        $today = date('Y-m-d');
        $month = date('m');
        $year = date('Y');
        
        // Check if summary already exists for today
        $checkSummarySql = "SELECT id FROM attendance_summary 
                           WHERE user_id = ? 
                           AND user_type = ? 
                           AND summary_date = ?";
        $checkSummaryStmt = $conn->prepare($checkSummarySql);
        $checkSummaryStmt->bind_param("iss", $user_id, $user_type, $today);
        $checkSummaryStmt->execute();
        $checkSummaryResult = $checkSummaryStmt->get_result();
        
        if ($checkSummaryResult->num_rows === 0) {
            // Create new summary record
            $summarySql = "INSERT INTO attendance_summary 
                          (user_id, user_type, summary_date, month, year, status, is_late) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $isLate = ($status === 'late') ? 1 : 0;
            $summaryStmt = $conn->prepare($summarySql);
            $summaryStmt->bind_param(
                "issiiis", 
                $user_id, 
                $user_type, 
                $today, 
                $month, 
                $year, 
                $status,
                $isLate
            );
            
            $summaryStmt->execute();
        }
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'Successfully punched in',
            'log_id' => $logId,
            'time' => $currentTime,
            'attendance_status' => $status,
            'shift' => [
                'start' => $shiftStart,
                'end' => $shiftEnd
            ]
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Failed to record punch in'
        ]);
    }
} elseif ($action === 'punch_out') {
    // Get form data
    $logId = isset($_POST['log_id']) ? intval($_POST['log_id']) : 0;
    $locationId = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;
    $latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : 0;
    $longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : 0;
    $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
    
    // Get current time
    $currentTime = date('Y-m-d H:i:s');
    
    // Log the current time for debugging
    error_log("Punch Out Time: " . $currentTime . " (" . date_default_timezone_get() . ")");
    
    // Check if log record exists and belongs to the user
    $checkSql = "SELECT * FROM attendance_logs 
                 WHERE id = ? 
                 AND user_id = ? 
                 AND user_type = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("iis", $logId, $user_id, $user_type);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Invalid punch in record'
        ]);
        exit();
    }
    
    $log = $checkResult->fetch_assoc();
    
    // Check if already punched out
    if ($log['punch_out_time'] !== null) {
        echo json_encode([
            'status' => 'error', 
            'message' => 'You have already punched out for this record'
        ]);
        exit();
    }
    
    // Check if geofencing is enabled
    $geofencingEnabled = isGeofencingEnabled($conn);
    
    // If geofencing is enabled, verify user's location
    if ($geofencingEnabled) {
        if (!isWithinOfficeRadius($latitude, $longitude, $locationId, $conn)) {
            echo json_encode([
                'status' => 'error', 
                'message' => 'You must be within the office premises to punch out'
            ]);
            exit();
        }
    }
    
    // Calculate total hours worked
    $punchInTime = strtotime($log['punch_in_time']);
    $punchOutTime = strtotime($currentTime);
    $totalHours = round(($punchOutTime - $punchInTime) / 3600, 2); // Convert seconds to hours
    
    // Check if early exit
    $status = $log['status'];
    $isEarlyExit = 0;
    
    // Only check for early exit if status is 'present' or 'late'
    if ($status === 'present' || $status === 'late') {
        // Get the shift end time from the log
        $shiftEnd = isset($log['shift_end']) ? $log['shift_end'] : '17:00:00';
        
        // Get early exit threshold
        $sql = "SELECT setting_value FROM attendance_settings WHERE setting_key = 'early_exit_threshold_minutes'";
        $result = $conn->query($sql);
        
        if ($result->num_rows === 0) {
            $earlyExitThreshold = 15; // Default to 15 minutes if setting not found
        } else {
            $earlyExitThresholdSetting = $result->fetch_assoc();
            $earlyExitThreshold = intval($earlyExitThresholdSetting['setting_value']);
        }
        
        // Get date from punch in time
        $punchInDate = date('Y-m-d', $punchInTime);
        
        // Create timestamp for shift end time today
        $shiftEndTimestamp = strtotime($punchInDate . ' ' . $shiftEnd);
        
        // Subtract early exit threshold
        $earlyExitThresholdTimestamp = $shiftEndTimestamp - ($earlyExitThreshold * 60);
        
        // Check if punch out is before early exit threshold
        if ($punchOutTime < $earlyExitThresholdTimestamp) {
            $status = 'early_exit';
            $isEarlyExit = 1;
        }
    }
    
    // Update attendance record
    $updateSql = "UPDATE attendance_logs 
                  SET punch_out_time = ?, 
                      punch_out_location_id = ?, 
                      punch_out_latitude = ?, 
                      punch_out_longitude = ?, 
                      punch_out_ip = ?, 
                      total_hours = ?, 
                      status = ?, 
                      notes = ? 
                  WHERE id = ?";
    
    $ip = $_SERVER['REMOTE_ADDR'];
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param(
        "sisdsdssi", 
        $currentTime, 
        $locationId, 
        $latitude, 
        $longitude, 
        $ip, 
        $totalHours, 
        $status, 
        $notes, 
        $logId
    );
    
    $updateResult = $updateStmt->execute();
    
    if ($updateResult) {
        // Update attendance summary record
        $today = date('Y-m-d');
        
        // Get all attendance logs for the day to calculate total hours
        $logsForDaySql = "SELECT SUM(total_hours) as day_total_hours FROM attendance_logs 
                         WHERE user_id = ? 
                         AND user_type = ? 
                         AND DATE(punch_in_time) = ? 
                         AND punch_out_time IS NOT NULL";
        $logsForDayStmt = $conn->prepare($logsForDaySql);
        $logsForDayStmt->bind_param("iss", $user_id, $user_type, $today);
        $logsForDayStmt->execute();
        $logsForDayResult = $logsForDayStmt->get_result();
        $logsForDay = $logsForDayResult->fetch_assoc();
        
        $totalDayHours = $logsForDay['day_total_hours'] ?? 0;
        
        // Update the summary record
        $summarySql = "UPDATE attendance_summary 
                      SET status = ?, 
                          work_hours = ?, 
                          is_early_exit = ? 
                      WHERE user_id = ? 
                      AND user_type = ? 
                      AND summary_date = ?";
        
        $summaryStmt = $conn->prepare($summarySql);
        $summaryStmt->bind_param(
            "sdisss", 
            $status, 
            $totalDayHours, 
            $isEarlyExit, 
            $user_id, 
            $user_type, 
            $today
        );
        
        $summaryStmt->execute();
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'Successfully punched out',
            'time' => $currentTime,
            'total_hours' => $totalHours,
            'attendance_status' => $status
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Failed to record punch out'
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Invalid action'
    ]);
} 