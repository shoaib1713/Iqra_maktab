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
$response = [
    'status' => 'success',
    'data' => [],
    'unread_count' => 0
];

try {
    // First, count unread notifications for this user
    $countStmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
    $countStmt->bind_param("i", $user_id);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $countRow = $countResult->fetch_assoc();
    $response['unread_count'] = (int)$countRow['unread_count'];
    
    // Get user's notifications, ordered by date (most recent first)
    // Limit to 20 notifications to avoid performance issues
    $stmt = $conn->prepare("
        SELECT n.*, 
               a.title as announcement_title,
               a.content as announcement_content
        FROM notifications n
        LEFT JOIN announcements a ON n.type = 'announcement' AND n.reference_id = a.id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT 20
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // If this is an announcement notification and we have the announcement data
        if ($row['type'] === 'announcement' && !empty($row['announcement_title'])) {
            $row['title'] = $row['announcement_title'];
            $row['content'] = $row['announcement_content'];
        }
        
        // Remove unnecessary fields from the response
        unset($row['announcement_title']);
        unset($row['announcement_content']);
        
        $response['data'][] = $row;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch notifications: ' . $e->getMessage()
    ]);
}
?> 