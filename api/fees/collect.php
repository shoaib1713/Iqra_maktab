<?php

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

$token = getBearerToken();
if (!$token) {
    sendError('No token provided', 401);
}

if (!validateToken($token)) {
    sendError('Invalid token', 401);
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['studentId']) || !isset($data['amount']) || !isset($data['created_by']) || !isset($data['year']) || !isset($data['month'])){
    sendError('Student ID, amount, and payment receive by are required');
}

$studentId = (int)$data['studentId'];
$amount = (float)$data['amount'];
$created_by = (int)$data['created_by'];
$year = $data['year'];
$month = $data['month'];
$notes = isset($data['notes']) ? $data['notes'] : null;

// Validate student exists
$studentSql = "SELECT id FROM students WHERE id = ? AND is_deleted = 0";
$studentStmt = $conn->prepare($studentSql);
$studentStmt->bind_param("i", $studentId);
$studentStmt->execute();
$studentResult = $studentStmt->get_result();

if ($studentResult->num_rows === 0) {
    sendError('Student not found', 404);
}

// Insert fee record
$insertSql = "INSERT INTO fees (student_id, amount, created_at,created_by,Year,reason,month) 
              VALUES (?, ?, now(), ?, ?, ?,?)";

$insertStmt = $conn->prepare($insertSql);
$insertStmt->bind_param("idiisi", $studentId, $amount , $created_by, $year,$notes,$month);

if (!$insertStmt->execute()) {
    sendError('Failed to record fee payment', 500);
}

$feeId = $conn->insert_id;

// Get the created fee record
$getSql = "SELECT f.*, 'Cash' as payment_method 
           FROM fees f  
           WHERE f.id = ?";

$getStmt = $conn->prepare($getSql);
$getStmt->bind_param("i", $feeId);
$getStmt->execute();
$result = $getStmt->get_result();
$fee = $result->fetch_assoc();

$response = [
    'id' => $fee['id'],
    'studentId' => $fee['student_id'],
    'amount' => (float)$fee['amount'],
    'paymentDate' => $fee['created_at'],
    'paymentMethod' => 'Cash',
    'receiptNumber' => NULL,
    'notes' => $fee['reason']
];

sendResponse($response, 201); 