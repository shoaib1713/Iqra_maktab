<?php
// Debug endpoint that doesn't require token validation
require_once '../config.php';

// Get student ID from query parameter
if (!isset($_GET['id'])) {
    sendResponse([
        'success' => false,
        'message' => 'Student ID is required'
    ]);
}

$studentId = (int)$_GET['id'];

// Get student details
$sql = "SELECT s.*, s.class as class_name,
        u.name as teacher_name
        FROM students s 
        LEFT JOIN users u ON s.assigned_teacher = u.id
        WHERE s.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    sendResponse([
        'success' => false,
        'message' => 'Student not found'
    ]);
}

$student = $result->fetch_assoc();

// Get student's fee history
$feeSql = "SELECT f.* 
           FROM fees f 
           WHERE f.student_id = ? 
           ORDER BY f.created_at DESC";

$feeStmt = $conn->prepare($feeSql);
$feeStmt->bind_param("i", $studentId);
$feeStmt->execute();
$feeResult = $feeStmt->get_result();

$feeHistory = [];
while ($row = $feeResult->fetch_assoc()) {
    $feeHistory[] = [
        'id' => $row['id'],
        'amount' => (float)$row['amount'],
        'paymentDate' => $row['payment_date'],
        'paymentMethod' => $row['payment_method'] ?? '',
        'receiptNumber' => $row['receipt_number'] ?? '',
        'notes' => $row['notes'] ?? ''
    ];
}

// Calculate pending fees
$totalFees = $student['annual_fees'] ?? 0;
$paidFees = 0;

foreach ($feeHistory as $fee) {
    $paidFees += $fee['amount'];
}

$pendingFees = $totalFees - $paidFees;
if ($pendingFees < 0) $pendingFees = 0;

// Return a complete response for debugging
$response = [
    'success' => true,
    'message' => 'Debug mode: Student details retrieved successfully',
    'data' => [
        'id' => $student['id'],
        'name' => $student['name'],
        'father_name' => $student['father_name'] ?? '',
        'class_name' => $student['class_name'] ?? '',
        'phone' => $student['phone'] ?? '',
        'annual_fees' => (float)($student['annual_fees'] ?? 0),
        'pending_fees' => (float)$pendingFees,
        'teacher_id' => $student['assigned_teacher'] ?? null,
        'teacher_name' => $student['teacher_name'] ?? '',
        'assigned_teacher' => $student['teacher_name'] ?? '',
        'photo' => $student['photo'] ?? '',
        'status' => $student['is_deleted'] ? 'inactive' : 'active',
        'is_deleted' => (bool)($student['is_deleted'] ?? false),
        'created_at' => $student['created_at'] ?? '',
        'updated_at' => $student['updated_at'] ?? '',
        'feeHistory' => $feeHistory
    ]
];

sendResponse($response); 