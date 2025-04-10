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

// Check if notification_id and is_read parameters are provided
if (!isset($_POST['notification_id']) || !isset($_POST['is_read'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required parameters'
    ]);
    exit();
}

$notification_id = (int)$_POST['notification_id'];
$is_read = (int)$_POST['is_read'];

// Validate is_read (must be 0 or 1)
if ($is_read !== 0 && $is_read !== 1) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid read status value'
    ]);
    exit();
}

try {
    // Update notification read status
    // Make sure the notification belongs to the current user
    $stmt = $conn->prepare("
        UPDATE notifications 
        SET is_read = ?, updated_at = NOW() 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->bind_param("iii", $is_read, $notification_id, $user_id);
    $stmt->execute();
    
    // Check if any rows were affected
    if ($stmt->affected_rows > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Notification updated successfully'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Notification not found or not owned by you'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to update notification: ' . $e->getMessage()
    ]);
}
?> 