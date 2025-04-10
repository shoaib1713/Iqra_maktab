<?php
session_start();
require_once 'config.php';
require_once 'config/db.php';
require_once 'includes/time_utils.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: restrict_user.php?page=Salary Export&message=This page is restricted to administrators only.");
    exit();
}

// Get required parameters
$export_type = isset($_GET['type']) ? $_GET['type'] : '';
$period_id = isset($_GET['period_id']) ? intval($_GET['period_id']) : 0;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$date = isset($_GET['date']) ? $_GET['date'] : '';
$month = isset($_GET['month']) ? $_GET['month'] : '';

// Validate the export type
if (!in_array($export_type, ['period', 'daily', 'monthly', 'teacher'])) {
    die("Invalid export type");
}

// Set filename based on export type
$filename = 'salary_export_' . date('Y-m-d_H-i-s') . '.csv';

switch ($export_type) {
    case 'period':
        if ($period_id <= 0) {
            die("Invalid period ID");
        }
        
        // Get period details
        $periodSql = "SELECT * FROM salary_periods WHERE id = ?";
        $periodStmt = $conn->prepare($periodSql);
        $periodStmt->bind_param("i", $period_id);
        $periodStmt->execute();
        $periodResult = $periodStmt->get_result();
        $period = $periodResult->fetch_assoc();
        
        if (!$period) {
            die("Period not found");
        }
        
        $filename = 'salary_export_' . $period['period_name'] . '.csv';
        
        // Get all teacher salaries for this period
        $sql = "SELECT tsc.*, u.name, u.email, u.phone 
                FROM teacher_salary_calculations tsc
                JOIN users u ON tsc.user_id = u.id
                WHERE tsc.period_id = ?
                ORDER BY u.name";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $period_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Set headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Create CSV file
        $output = fopen('php://output', 'w');
        
        // Add headers
        fputcsv($output, [
            'Period: ' . $period['period_name'] . ' (' . date('d M Y', strtotime($period['start_date'])) . ' to ' . date('d M Y', strtotime($period['end_date'])) . ')'
        ]);
        fputcsv($output, [
            'Teacher ID', 
            'Name', 
            'Email',
            'Phone',
            'Base Salary', 
            'Deductions', 
            'Bonuses',
            'Final Salary', 
            'Working Hours', 
            'Required Hours',
            'Status',
            'Payment Date'
        ]);
        
        // Add data
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['user_id'],
                $row['name'],
                $row['email'],
                $row['phone'],
                $row['base_salary'],
                $row['deduction_amount'],
                $row['bonus_amount'],
                $row['final_salary'],
                $row['total_working_hours'],
                $row['expected_working_hours'],
                ucfirst($row['status']),
                $row['payment_date'] ? date('d M Y', strtotime($row['payment_date'])) : 'Not Paid'
            ]);
        }
        break;
        
    case 'daily':
        if (empty($date)) {
            die("Invalid date");
        }
        
        $filename = 'daily_salary_' . $date . '.csv';
        
        // Get all teacher salaries for this date
        $sql = "SELECT dsc.*, u.name, u.email, u.id as teacher_id
                FROM daily_salary_calculations dsc
                JOIN users u ON dsc.user_id = u.id
                WHERE dsc.calculation_date = ?
                ORDER BY u.name";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Set headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Create CSV file
        $output = fopen('php://output', 'w');
        
        // Add headers
        fputcsv($output, [
            'Daily Salary Report for ' . date('d M Y (l)', strtotime($date))
        ]);
        fputcsv($output, [
            'Teacher ID', 
            'Name', 
            'Email',
            'Working Minutes', 
            'Required Minutes',
            'Base Amount', 
            'Deduction', 
            'Final Amount',
            'Notes'
        ]);
        
        // Add data
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['teacher_id'],
                $row['name'],
                $row['email'],
                $row['working_minutes'] . ' (' . formatTime($row['working_minutes']) . ')',
                $row['required_minutes'] . ' (' . formatTime($row['required_minutes']) . ')',
                $row['base_amount'],
                $row['deduction_amount'],
                $row['final_amount'],
                $row['notes']
            ]);
        }
        break;
        
    case 'monthly':
        if (empty($month)) {
            die("Invalid month format (use YYYY-MM)");
        }
        
        $filename = 'monthly_salary_' . $month . '.csv';
        $monthStart = $month . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        
        // Get monthly summary for each teacher
        $sql = "SELECT 
                u.id as teacher_id, u.name, u.email,
                SUM(dsc.base_amount) as total_base,
                SUM(dsc.deduction_amount) as total_deduction,
                SUM(dsc.final_amount) as total_final,
                COUNT(dsc.id) as days_calculated,
                SUM(dsc.working_minutes) as total_working_minutes,
                SUM(dsc.required_minutes) as total_required_minutes
                FROM daily_salary_calculations dsc
                JOIN users u ON dsc.user_id = u.id
                WHERE dsc.calculation_date BETWEEN ? AND ?
                GROUP BY dsc.user_id
                ORDER BY u.name";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $monthStart, $monthEnd);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Set headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Create CSV file
        $output = fopen('php://output', 'w');
        
        // Add headers
        fputcsv($output, [
            'Monthly Salary Report for ' . date('F Y', strtotime($monthStart))
        ]);
        fputcsv($output, [
            'Teacher ID', 
            'Name', 
            'Email',
            'Days Calculated',
            'Total Working Hours', 
            'Required Hours',
            'Base Amount', 
            'Total Deductions', 
            'Final Amount'
        ]);
        
        // Add data
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['teacher_id'],
                $row['name'],
                $row['email'],
                $row['days_calculated'],
                formatTime($row['total_working_minutes']),
                formatTime($row['total_required_minutes']),
                $row['total_base'],
                $row['total_deduction'],
                $row['total_final']
            ]);
        }
        break;
        
    case 'teacher':
        if ($user_id <= 0) {
            die("Invalid teacher ID");
        }
        
        // Get teacher details
        $userSql = "SELECT * FROM users WHERE id = ?";
        $userStmt = $conn->prepare($userSql);
        $userStmt->bind_param("i", $user_id);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $user = $userResult->fetch_assoc();
        
        if (!$user) {
            die("Teacher not found");
        }
        
        $filename = 'teacher_salary_' . $user['name'] . '.csv';
        
        // Get all salary data for this teacher
        $sql = "SELECT tsc.*, sp.period_name, sp.start_date, sp.end_date
                FROM teacher_salary_calculations tsc
                JOIN salary_periods sp ON tsc.period_id = sp.id
                WHERE tsc.user_id = ?
                ORDER BY sp.end_date DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Set headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Create CSV file
        $output = fopen('php://output', 'w');
        
        // Add headers
        fputcsv($output, [
            'Salary History for ' . $user['name'] . ' (' . $user['email'] . ')'
        ]);
        fputcsv($output, [
            'Period', 
            'Start Date',
            'End Date',
            'Base Salary', 
            'Deductions', 
            'Bonuses',
            'Final Salary', 
            'Working Hours', 
            'Required Hours',
            'Status',
            'Payment Date'
        ]);
        
        // Add data
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['period_name'],
                date('d M Y', strtotime($row['start_date'])),
                date('d M Y', strtotime($row['end_date'])),
                $row['base_salary'],
                $row['deduction_amount'],
                $row['bonus_amount'],
                $row['final_salary'],
                $row['total_working_hours'],
                $row['expected_working_hours'],
                ucfirst($row['status']),
                $row['payment_date'] ? date('d M Y', strtotime($row['payment_date'])) : 'Not Paid'
            ]);
        }
        break;
}

// Close the CSV file
fclose($output);
exit(); 