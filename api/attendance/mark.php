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

if (!isset($data['studentId']) || !isset($data['status']) || !isset($data['date'])) {
    sendError('Student ID, status, and date are required');
}

$studentId = (int)$data['studentId'];
$status = $data['status'];
$date = $data['date'];
$locationId = isset($data['locationId']) ? (int)$data['locationId'] : null;
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

// Check if attendance already marked for this date
$checkSql = "SELECT id FROM attendance WHERE student_id = ? AND date = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("is", $studentId, $date);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows > 0) {
    sendError('Attendance already marked for this date', 400);
}

// Insert attendance record
$insertSql = "INSERT INTO attendance (student_id, date, status, location_id, notes) VALUES (?, ?, ?, ?, ?)";
$insertStmt = $conn->prepare($insertSql);
$insertStmt->bind_param("issss", $studentId, $date, $status, $locationId, $notes);

if (!$insertStmt->execute()) {
    sendError('Failed to mark attendance', 500);
}

$attendanceId = $conn->insert_id;

// Get the created attendance record
$getSql = "SELECT a.*, l.name as location 
           FROM attendance a 
           LEFT JOIN locations l ON a.location_id = l.id 
           WHERE a.id = ?";

$getStmt = $conn->prepare($getSql);
$getStmt->bind_param("i", $attendanceId);
$getStmt->execute();
$result = $getStmt->get_result();
$attendance = $result->fetch_assoc();

$response = [
    'id' => $attendance['id'],
    'studentId' => $attendance['student_id'],
    'date' => $attendance['date'],
    'status' => $attendance['status'],
    'location' => $attendance['location'],
    'notes' => $attendance['notes']
];

sendResponse($response, 201); 