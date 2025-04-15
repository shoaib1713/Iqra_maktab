<?php
// Include dependencies
require_once '../config.php';
require_once __DIR__ . '/../../config/db.php';

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

// Get token - using the same flexible approach as get.php
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
$query = "SELECT s.*, u.name as teacher_name 
          FROM students s 
          LEFT JOIN users u ON s.assigned_teacher = u.id 
          WHERE s.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    sendError('Student not found', 404);
}

$student = $result->fetch_assoc();

// Get student status history
$historyQuery = "SELECT ssh.*, u.name as teacher_name, 
                  creator.name as created_by_name, 
                  updater.name as updated_by_name
                FROM student_status_history ssh
                LEFT JOIN users u ON ssh.assigned_teacher = u.id
                LEFT JOIN users creator ON ssh.created_by = creator.id
                LEFT JOIN users updater ON ssh.updated_by = updater.id
                WHERE ssh.student_id = ? 
                ORDER BY ssh.created_at DESC";
$historyStmt = $conn->prepare($historyQuery);
$historyStmt->bind_param("i", $studentId);
$historyStmt->execute();
$historyResult = $historyStmt->get_result();

$statusHistory = [];
while ($row = $historyResult->fetch_assoc()) {
    $month = date("F", mktime(0, 0, 0, $row['month'], 1));
    $statusHistory[] = [
        'id' => (int)$row['id'],
        'status' => $row['status'],
        'year' => $row['year'],
        'month' => $month,
        'teacherName' => $row['teacher_name'] ?? 'Not Assigned',
        'salanaFees' => (float)$row['salana_fees'],
        'createdBy' => $row['created_by_name'],
        'createdAt' => $row['created_at'],
        'updatedBy' => $row['updated_by_name'] ?? null,
        'updatedAt' => $row['updated_at'] ?? null,
        'isCurrentRecord' => $row['current_active_record'] == 0
    ];
}

// Get fee payment history
$paymentQuery = "SELECT f.*, u.name as created_by_name 
                FROM fees f 
                LEFT JOIN users u ON f.created_by = u.id 
                WHERE f.student_id = ? 
                ORDER BY f.created_at DESC";
$paymentStmt = $conn->prepare($paymentQuery);
$paymentStmt->bind_param("i", $studentId);
$paymentStmt->execute();
$paymentResult = $paymentStmt->get_result();

$paymentHistory = [];
while ($row = $paymentResult->fetch_assoc()) {
    $month = isset($row['month']) ? date("F", mktime(0, 0, 0, $row['month'], 1)) : '';
    $paymentHistory[] = [
        'id' => (int)$row['id'],
        'amount' => (float)$row['amount'],
        'month' => $month,
        'year' => $row['Year'] ?? date('Y'),
        'status' => $row['status'] ?? 'paid',
        'notes' => $row['notes'] ?? '',
        'createdBy' => $row['created_by_name'],
        'createdAt' => $row['created_at'],
        'receiptNumber' => $row['receipt_number'] ?? null
    ];
}

// Prepare the final response
$response = [
    'success' => true,
    'message' => 'Student history retrieved successfully',
    'data' => [
        'student' => [
            'id' => (int)$student['id'],
            'name' => $student['name'],
            'class' => $student['class'] ?? '',
            'annual_fees' => (float)($student['annual_fees'] ?? 0),
            'teacher_name' => $student['teacher_name'] ?? 'Not Assigned'
        ],
        'statusHistory' => $statusHistory,
        'paymentHistory' => $paymentHistory
    ]
];

// Send the response
sendResponse($response); 