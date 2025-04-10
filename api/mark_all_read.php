<?php
session_start();
require_once '../config.php';
require_once '../config/db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'User not authenticated'
    ]);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Mark all user's notifications as read
    $stmt = $conn->prepare("
        UPDATE notifications 
        SET is_read = 1, updated_at = NOW() 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'All notifications marked as read',
        'count' => $stmt->affected_rows
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to mark notifications as read: ' . $e->getMessage()
    ]);
}
?> 