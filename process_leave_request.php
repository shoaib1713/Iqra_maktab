<?php
session_start();
require_once 'config.php';
require 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Get user info
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['role']; // Map role to user_type

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $leave_type_id = isset($_POST['leave_type_id']) ? intval($_POST['leave_type_id']) : 0;
    $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
    $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';
    $reason = isset($_POST['reason']) ? $_POST['reason'] : '';
    
    // Validate input
    $errors = [];
    
    if ($leave_type_id <= 0) {
        $errors[] = "Please select a valid leave type";
    }
    
    if (empty($start_date) || !strtotime($start_date)) {
        $errors[] = "Please enter a valid start date";
    }
    
    if (empty($end_date) || !strtotime($end_date)) {
        $errors[] = "Please enter a valid end date";
    }
    
    if (strtotime($end_date) < strtotime($start_date)) {
        $errors[] = "End date cannot be before start date";
    }
    
    if (empty($reason)) {
        $errors[] = "Please provide a reason for your leave request";
    }
    
    // If validation passes, process the request
    if (empty($errors)) {
        // Handle file upload if provided
        $attachment_path = "";
        
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
            $file_type = $_FILES['attachment']['type'];
            
            if (!in_array($file_type, $allowed_types)) {
                $errors[] = "Only PDF, JPG, and PNG files are allowed";
            } else {
                $upload_dir = "uploads/leave_attachments/";
                
                // Create directory if it doesn't exist
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_name = time() . '_' . $_FILES['attachment']['name'];
                $upload_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_path)) {
                    $attachment_path = $upload_path;
                } else {
                    $errors[] = "Failed to upload attachment";
                }
            }
        }
        
        if (empty($errors)) {
            // Insert leave request
            $insertSql = "INSERT INTO leave_requests 
                          (user_id, user_type, leave_type_id, start_date, end_date, reason, attachment) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($insertSql);
            $stmt->bind_param("isissss", $user_id, $user_type, $leave_type_id, $start_date, $end_date, $reason, $attachment_path);
            $result = $stmt->execute();
            
            if ($result) {
                // Set success message
                $_SESSION['success_message'] = "Your leave request has been submitted successfully";
                
                // Calculate the number of days in the leave request
                $start = new DateTime($start_date);
                $end = new DateTime($end_date);
                $end->modify('+1 day'); // Include end date
                
                $interval = new DateInterval('P1D');
                $date_range = new DatePeriod($start, $interval, $end);
                
                // Mark dates in the attendance summary as 'leave' (pending)
                foreach ($date_range as $date) {
                    $date_string = $date->format('Y-m-d');
                    $month = $date->format('m');
                    $year = $date->format('Y');
                    
                    // Check if it's a weekend or holiday
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
                    
                    // Check if there's an existing record for this date
                    $checkSql = "SELECT id FROM attendance_summary 
                                WHERE user_id = ? 
                                AND user_type = ? 
                                AND summary_date = ?";
                    
                    $checkStmt = $conn->prepare($checkSql);
                    $checkStmt->bind_param("iss", $user_id, $user_type, $date_string);
                    $checkStmt->execute();
                    $checkResult = $checkStmt->get_result();
                    
                    if ($checkResult->num_rows > 0) {
                        // Update existing record
                        $updateSql = "UPDATE attendance_summary 
                                    SET status = 'leave', 
                                        leave_type_id = ? 
                                    WHERE user_id = ? 
                                    AND user_type = ? 
                                    AND summary_date = ?";
                        
                        $updateStmt = $conn->prepare($updateSql);
                        $updateStmt->bind_param("iiss", $leave_type_id, $user_id, $user_type, $date_string);
                        $updateStmt->execute();
                    } else {
                        // Insert new record
                        $insertSummarySql = "INSERT INTO attendance_summary 
                                          (user_id, user_type, summary_date, month, year, status, leave_type_id) 
                                          VALUES (?, ?, ?, ?, ?, 'leave', ?)";
                        
                        $insertSummaryStmt = $conn->prepare($insertSummarySql);
                        $insertSummaryStmt->bind_param("issiii", $user_id, $user_type, $date_string, $month, $year, $leave_type_id);
                        $insertSummaryStmt->execute();
                    }
                }
                
                // Redirect back to attendance page
                header("Location: attendance.php");
                exit();
            } else {
                $errors[] = "Failed to submit leave request. Please try again.";
            }
        }
    }
    
    // If there are errors, store them in session and redirect back
    if (!empty($errors)) {
        $_SESSION['error_messages'] = $errors;
        header("Location: attendance.php");
        exit();
    }
} else {
    // If not a POST request, redirect to attendance page
    header("Location: attendance.php");
    exit();
} 