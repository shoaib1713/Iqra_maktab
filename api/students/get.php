<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

$token = getBearerToken();
if (!$token) {
    sendError('No token provided', 401);
}

if (!validateToken($token)) {
    sendError('Invalid token', 401);
}

if (!isset($_GET['id'])) {
    sendError('Student ID is required');
}

$studentId = (int)$_GET['id'];

// Get student details
$sql = "SELECT s.*, c.name as class_name 
        FROM students s 
        LEFT JOIN classes c ON s.class_id = c.id 
        WHERE s.id = ? AND s.is_active = 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    sendError('Student not found', 404);
}

$student = $result->fetch_assoc();

// Get student's fee history
$feeSql = "SELECT f.*, p.name as payment_method 
           FROM fees f 
           LEFT JOIN payment_methods p ON f.payment_method_id = p.id 
           WHERE f.student_id = ? 
           ORDER BY f.payment_date DESC";

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
        'paymentMethod' => $row['payment_method'],
        'receiptNumber' => $row['receipt_number'],
        'notes' => $row['notes']
    ];
}

// Get student's attendance history
$attendanceSql = "SELECT a.*, l.name as location 
                  FROM attendance a 
                  LEFT JOIN locations l ON a.location_id = l.id 
                  WHERE a.student_id = ? 
                  ORDER BY a.date DESC 
                  LIMIT 30";

$attendanceStmt = $conn->prepare($attendanceSql);
$attendanceStmt->bind_param("i", $studentId);
$attendanceStmt->execute();
$attendanceResult = $attendanceStmt->get_result();

$attendanceHistory = [];
while ($row = $attendanceResult->fetch_assoc()) {
    $attendanceHistory[] = [
        'id' => $row['id'],
        'date' => $row['date'],
        'status' => $row['status'],
        'location' => $row['location'],
        'notes' => $row['notes']
    ];
}

$response = [
    'student' => [
        'id' => $student['id'],
        'name' => $student['name'],
        'fatherName' => $student['father_name'],
        'classId' => $student['class_id'],
        'className' => $student['class_name'],
        'rollNumber' => $student['roll_number'],
        'phone' => $student['phone'],
        'address' => $student['address'],
        'isActive' => (bool)$student['is_active']
    ],
    'feeHistory' => $feeHistory,
    'attendanceHistory' => $attendanceHistory
];

sendResponse($response); 