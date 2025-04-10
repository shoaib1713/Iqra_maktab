<?php
session_start();
require_once 'config.php';
require 'config/db.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: restrict_user.php?page=Salary&message=This action is restricted to administrators only.");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $salary_id = isset($_POST['salary_id']) ? intval($_POST['salary_id']) : 0;
    $payment_date = isset($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
    $reference_number = isset($_POST['reference_number']) ? trim($_POST['reference_number']) : '';
    $payment_notes = isset($_POST['payment_notes']) ? trim($_POST['payment_notes']) : '';
    
    // Validate input
    $errors = [];
    
    if ($salary_id <= 0) {
        $errors[] = "Invalid salary ID";
    }
    
    if (empty($payment_date) || !strtotime($payment_date)) {
        $errors[] = "Please enter a valid payment date";
    }
    
    if (empty($payment_method)) {
        $errors[] = "Payment method is required";
    }
    
    if (empty($errors)) {
        // Get the salary record to verify it exists and get teacher_id
        $checkSql = "SELECT teacher_id, period_id, final_salary, status FROM teacher_salary_calculations WHERE id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("i", $salary_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            $_SESSION['error_message'] = "Salary record not found";
            header("Location: salary_reports.php");
            exit();
        }
        
        $salaryData = $checkResult->fetch_assoc();
        
        // Check if already paid
        if ($salaryData['status'] === 'paid') {
            $_SESSION['error_message'] = "This salary has already been marked as paid";
            header("Location: salary_reports.php");
            exit();
        }
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update salary status to paid
            $updateSql = "UPDATE teacher_salary_calculations 
                         SET status = 'paid', 
                             payment_date = ?, 
                             payment_method = ?, 
                             reference_number = ?, 
                             payment_notes = ?, 
                             updated_at = NOW() 
                         WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("ssssi", $payment_date, $payment_method, $reference_number, $payment_notes, $salary_id);
            $updateStmt->execute();
            
            // Get period details for notification
            $periodSql = "SELECT period_name, start_date, end_date FROM salary_periods WHERE id = ?";
            $periodStmt = $conn->prepare($periodSql);
            $periodStmt->bind_param("i", $salaryData['period_id']);
            $periodStmt->execute();
            $periodResult = $periodStmt->get_result();
            $periodData = $periodResult->fetch_assoc();
            
            // Create notification for the teacher
            $notificationTitle = "Salary Payment Processed";
            $notificationMessage = "Your salary of ₹" . number_format($salaryData['final_salary'], 2) . " for the period " . 
                                $periodData['period_name'] . " has been processed. Payment method: " . 
                                ucwords(str_replace('_', ' ', $payment_method));
                                
            if (!empty($reference_number)) {
                $notificationMessage .= ". Reference: " . $reference_number;
            }
            
            $notificationSql = "INSERT INTO notifications (user_id, title, message, type, created_at) 
                              VALUES (?, ?, ?, 'salary_payment', NOW())";
            $notificationStmt = $conn->prepare($notificationSql);
            $notificationStmt->bind_param("iss", $salaryData['teacher_id'], $notificationTitle, $notificationMessage);
            $notificationStmt->execute();
            
            // Add to salary notifications table
            $salaryNotificationSql = "INSERT INTO salary_notifications 
                                    (teacher_id, period_id, salary_calculation_id, notification_type, 
                                     message, created_at) 
                                    VALUES (?, ?, ?, 'payment', ?, NOW())";
            $salaryNotificationStmt = $conn->prepare($salaryNotificationSql);
            $salaryNotificationStmt->bind_param("iiis", $salaryData['teacher_id'], $salaryData['period_id'], 
                                             $salary_id, $notificationMessage);
            $salaryNotificationStmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success_message'] = "Salary has been marked as paid successfully";
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $_SESSION['error_message'] = "Failed to process payment: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
    
    header("Location: salary_reports.php");
    exit();
} else {
    // Not a POST request, redirect to reports page
    header("Location: salary_reports.php");
    exit();
}
?> 