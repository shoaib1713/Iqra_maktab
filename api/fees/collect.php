<?php
require_once '../config.php';

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

if (!isset($data['studentId']) || !isset($data['amount']) || !isset($data['paymentMethodId'])) {
    sendError('Student ID, amount, and payment method are required');
}

$studentId = (int)$data['studentId'];
$amount = (float)$data['amount'];
$paymentMethodId = (int)$data['paymentMethodId'];
$receiptNumber = isset($data['receiptNumber']) ? $data['receiptNumber'] : null;
$notes = isset($data['notes']) ? $data['notes'] : null;

// Validate student exists
$studentSql = "SELECT id FROM students WHERE id = ? AND is_active = 1";
$studentStmt = $conn->prepare($studentSql);
$studentStmt->bind_param("i", $studentId);
$studentStmt->execute();
$studentResult = $studentStmt->get_result();

if ($studentResult->num_rows === 0) {
    sendError('Student not found', 404);
}

// Validate payment method exists
$paymentSql = "SELECT id FROM payment_methods WHERE id = ?";
$paymentStmt = $conn->prepare($paymentSql);
$paymentStmt->bind_param("i", $paymentMethodId);
$paymentStmt->execute();
$paymentResult = $paymentStmt->get_result();

if ($paymentResult->num_rows === 0) {
    sendError('Invalid payment method', 400);
}

// Generate receipt number if not provided
if (!$receiptNumber) {
    $receiptNumber = 'REC-' . date('Ymd') . '-' . str_pad($studentId, 4, '0', STR_PAD_LEFT);
}

// Insert fee record
$insertSql = "INSERT INTO fees (student_id, amount, payment_date, payment_method_id, receipt_number, notes) 
              VALUES (?, ?, NOW(), ?, ?, ?)";

$insertStmt = $conn->prepare($insertSql);
$insertStmt->bind_param("idiss", $studentId, $amount, $paymentMethodId, $receiptNumber, $notes);

if (!$insertStmt->execute()) {
    sendError('Failed to record fee payment', 500);
}

$feeId = $conn->insert_id;

// Get the created fee record
$getSql = "SELECT f.*, p.name as payment_method 
           FROM fees f 
           LEFT JOIN payment_methods p ON f.payment_method_id = p.id 
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
    'paymentDate' => $fee['payment_date'],
    'paymentMethod' => $fee['payment_method'],
    'receiptNumber' => $fee['receipt_number'],
    'notes' => $fee['notes']
];

sendResponse($response, 201); 