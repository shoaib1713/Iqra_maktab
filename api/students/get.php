<?php

// Disable error output - very important to prevent PHP errors from breaking JSON
error_reporting(0);
ini_set('display_errors', 0);

// Set proper headers
header('Content-Type: application/json');
ob_clean(); // Clean any previous output buffer

// Include dependencies
try {
    require_once '../config.php';
    require_once __DIR__ . '/../../config/db.php';
} catch (Exception $e) {
    // If includes fail, return a properly formatted JSON error
    echo json_encode([
        'success' => false,
        'message' => 'Server configuration error',
        'data' => [
            'students' => [],
            'pagination' => [
                'total' => 0,
                'page' => 1,
                'limit' => 10,
                'totalPages' => 1
            ]
        ]
    ]);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

// Get bearer token - try both header and query param for flexibility with app
$token = getBearerToken();
if (!$token && isset($_GET['token'])) {
    $token = $_GET['token'];
}

// For app development, we can make token optional for testing
// but in production this should be required
$requireToken = true; // Set to false for development/testing

if ($requireToken && !$token) {
    sendError('No token provided', 401);
}

if ($requireToken && !validateToken($token)) {
    sendError('Invalid token', 401);
}

if (!isset($_GET['id'])) {
    sendError('Student ID is required');
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
    sendError('Student not found', 404);
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
        'paymentDate' => $row['created_at'],
        'paymentMethod' => null,
        'receiptNumber' => null,
        'notes' => $row['notes']
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

$response = [
    'success' => true,
    'message' => 'Student details retrieved successfully',
    'data' => [
        'id' => $student['id'],
        'name' => $student['name'],
        'father_name' => $student['father_name'] ?? '',
        'class_id' => $student['class_id'] ?? null,
        'class_name' => $student['class_name'] ?? '',
        'roll_number' => $student['roll_number'] ?? '',
        'phone' => $student['phone'] ?? '',
        'address' => $student['address'] ?? '',
        'is_active' => (bool)($student['is_deleted'] ? false : true),
        'annual_fees' => (float)($student['annual_fees'] ?? 0),
        'pending_fees' => (float)$pendingFees,
        'teacher_id' => $student['assigned_teacher'] ?? null,
        'teacher_name' => $student['teacher_name'] ?? '',
        'assigned_teacher' => $student['teacher_name'] ?? '',
        'photo' => $student['photo'] ?? '',
        'status' => $student['is_deleted'] ? 'inactive' : 'active',
        'created_at' => $student['created_at'] ?? '',
        'updated_at' => $student['updated_at'] ?? '',
        'deleted_at' => $student['deleted_at'] ?? null,
        'feeHistory' => $feeHistory
    ]
];

sendResponse($response); 