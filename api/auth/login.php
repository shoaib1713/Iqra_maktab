<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['username']) || !isset($data['password'])) {
    sendError('Username and password are required');
}

$username = $data['username'];
$password = $data['password'];

// Validate credentials
$sql = "SELECT id, email as username, password, role as user_type, name, email FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    sendError('Invalid credentials', 401);
}

$user = $result->fetch_assoc();

if (!password_verify($password, $user['password'])) {
    sendError('Invalid credentials', 401);
}

// Generate token
$token = bin2hex(random_bytes(32));
$tokenExpiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

// Update user token
$updateSql = "UPDATE users SET token = ?, token_expiry = ? WHERE id = ?";
$updateStmt = $conn->prepare($updateSql);
$updateStmt->bind_param("ssi", $token, $tokenExpiry, $user['id']);
$updateStmt->execute();

// Prepare response
$response = [
    'token' => $token,
    'userId' => $user['id'],
    'userType' => $user['user_type'],
    'name' => $user['name'],
    'email' => $user['email']
];

sendResponse($response); 