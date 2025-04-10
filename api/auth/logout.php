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

// Clear token
$sql = "UPDATE users SET token = NULL, token_expiry = NULL WHERE token = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $token);
$stmt->execute();

sendResponse(['message' => 'Logged out successfully by shoaib']); 